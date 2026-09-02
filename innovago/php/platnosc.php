<?php
/**
 * ============================================================================
 *  PŁATNOŚĆ ONLINE — KROK 1 (start płatności jednorazowej) + KROK 2 (opcjonalna
 *  tokenizacja karty przy tej płatności — patrz $requestTokenization niżej)
 * ============================================================================
 * Ta strona nie ma własnego formularza — to punkt wejścia, do którego
 * prowadzi przycisk "Zapłać online" (panel-zapisy.php / panel-rodzic.php).
 * Zadanie tego pliku:
 *   1. sprawdzić, że zapis (enrollments) naprawdę należy do zalogowanego
 *      rodzica i faktycznie czeka na opłacenie,
 *   2. poprosić Tpay o utworzenie transakcji (tpay_create_transaction),
 *   3. przekierować przeglądarkę rodzica na stronę płatności Tpay
 *      (albo — w TPAY_SIMULATE — na naszą lokalną stronę-atrapę
 *      platnosc-symulacja.php).
 *
 * WAŻNE: ten plik NIGDY nie ustawia payment_status na PAID. To robi
 * wyłącznie webhook-tpay.php, po zweryfikowaniu podpisu Tpay — patrz
 * obszerny komentarz przy tpay_verify_webhook_signature() w
 * includes/tpay.php, jeśli chcesz zrozumieć dlaczego to rozróżnienie jest
 * tak istotne z punktu widzenia bezpieczeństwa (żeby nikt nie mógł sobie
 * "wpisać w przeglądarce" adresu strony sukcesu i oznaczyć zapisu jako
 * opłaconego za darmo).
 */
require_once __DIR__ . '/includes/bootstrap.php';

$user = require_role(['PARENT'], 'platnosc.php');
$org = require_org();

// Wejście wyłącznie przez formularz POST z CSRF — to żądanie tworzy
// prawdziwą (albo symulowaną) transakcję płatniczą, więc traktujemy je jak
// każdą inną akcję zmieniającą stan (patrz inne _action w panel-zapisy.php).
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('panel-zapisy.php');
}
csrf_check();

$enrollmentId = (int) ($_POST['id'] ?? 0);

// Pobieramy zapis RAZEM z danymi zajęć (potrzebne do opisu płatności) i
// filtrujemy od razu po parent_id + org_id — to jest granica bezpieczeństwa:
// rodzic może zapłacić WYŁĄCZNIE za własny zapis, we własnej organizacji.
$stmt = db()->prepare("SELECT e.*, ct.name AS ct_name, cs.title AS session_title
    FROM enrollments e
    JOIN class_sessions cs ON cs.id = e.session_id
    JOIN class_types ct ON ct.id = cs.class_type_id
    WHERE e.id = ? AND e.parent_id = ? AND e.org_id = ?");
$stmt->execute([$enrollmentId, $user['id'], $org['id']]);
$enrollment = $stmt->fetch();

if (!$enrollment) {
    redirect_with('panel-zapisy.php', ['error' => 'Nie znaleziono zapisu.']);
}
if ($enrollment['status'] !== 'CONFIRMED') {
    redirect_with('panel-zapisy.php', ['error' => 'Płatność online jest dostępna tylko dla potwierdzonych zapisów.']);
}
if ($enrollment['payment_status'] === 'PAID') {
    redirect_with('panel-zapisy.php', ['info' => 'Ten zapis jest już opłacony.']);
}
if ((int) $enrollment['amount_due_cents'] <= 0) {
    // Organizacja nie ustawiła jeszcze kwoty za te zajęcia — nie ma czego
    // pobrać. Zamiast mylącej płatności "0,00 zł" prosimy o kontakt.
    redirect_with('panel-zapisy.php', ['error' => 'Kwota do zapłaty nie została jeszcze ustalona przez organizację — skontaktuj się z nią bezpośrednio.']);
}
if (!TPAY_ENABLED) {
    redirect_with('panel-zapisy.php', ['error' => 'Płatności online nie są obecnie włączone w tej organizacji.']);
}

// KROK 2: rodzic mógł zaznaczyć w panel-zapisy.php checkbox "zapamiętaj tę
// kartę do przyszłych płatności" — jeśli tak, prosimy Tpay o tokenizację
// PRZY OKAZJI tej samej płatności (nie ma osobnego "zapisz kartę bez
// płacenia" — token powstaje jako efekt uboczny prawdziwej transakcji).
// Zapisany token trafi do bazy automatycznie w tpay_mark_enrollment_paid(),
// wywoływanym z webhooka po potwierdzeniu płatności — nie tutaj.
$requestTokenization = !empty($_POST['saveCard']);

try {
    $result = tpay_create_transaction([
        'amountCents' => (int) $enrollment['amount_due_cents'],
        'description' => $enrollment['ct_name'] . ' — ' . $enrollment['session_title'],
        'payerEmail' => $user['email'],
        'payerName' => $user['name'],
        // Pełne, absolutne adresy — successUrl/errorUrl otwiera przeglądarka
        // rodzica, ale notificationUrl wywołuje bezpośrednio serwer Tpay
        // (nie przeglądarka), więc MUSI być pełnym adresem z domeną
        // (patrz absolute_url() w includes/helpers.php).
        'successUrl' => absolute_url('platnosc-powrot.php?status=success&id=' . $enrollmentId),
        'errorUrl' => absolute_url('platnosc-powrot.php?status=error&id=' . $enrollmentId),
        'notificationUrl' => absolute_url('webhook-tpay.php'),
        'externalId' => (string) $enrollmentId,
        'requestCardTokenization' => $requestTokenization,
    ]);
} catch (RuntimeException $e) {
    // Błąd po stronie Tpay (np. sandbox nieosiągalny, złe klucy) — logujemy
    // szczegóły dla admina, a rodzicowi pokazujemy krótki, zrozumiały komunikat.
    error_log('[InnovaGo/Tpay] Błąd tworzenia transakcji dla enrollments.id=' . $enrollmentId . ': ' . $e->getMessage());
    redirect_with('panel-zapisy.php', ['error' => 'Nie udało się rozpocząć płatności online. Spróbuj ponownie za chwilę albo skontaktuj się z organizacją.']);
}

// Zapamiętujemy numer transakcji od razu (przed przekierowaniem) — dzięki
// temu nawet jeśli webhook przyjdzie zanim rodzic wróci do naszej appki,
// mamy już powiązanie enrollments.id <-> tpay_transaction_id w bazie do
// wglądu/diagnostyki (samo dopasowanie webhooka i tak działa niezależnie od
// tego pola — patrz tpay_extract_enrollment_id w includes/tpay.php).
db()->prepare('UPDATE enrollments SET tpay_transaction_id = ?, tpay_charge_attempts = tpay_charge_attempts + 1, tpay_last_error = NULL WHERE id = ?')
    ->execute([$result['transactionId'], $enrollmentId]);

// Przekierowanie na stronę płatności — UWAGA: celowo NIE używamy tu
// redirect() z includes/helpers.php, bo ten helper sam doklejałby prefiks
// ścieżki (base path) do adresu. $result['paymentUrl'] jest już albo pełnym
// adresem https://... (prawdziwy Tpay), albo gotową ścieżką z prefiksem
// (tryb symulacji, patrz includes/tpay.php) — w obu przypadkach gotowy do
// bezpośredniego użycia w nagłówku Location.
header('Location: ' . $result['paymentUrl']);
exit;
