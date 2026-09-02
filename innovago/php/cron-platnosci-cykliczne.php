<?php
/**
 * ============================================================================
 *  CRON — KROK 2: automatyczne pobieranie płatności cyklicznych
 * ============================================================================
 * Ten skrypt NIE jest uruchamiany przez przeglądarkę użytkownika — trzeba go
 * samodzielnie dodać do harmonogramu zadań (cron) na hostingu, żeby
 * uruchamiał się cyklicznie (zalecane: raz dziennie). Instrukcja dla
 * home.pl jest w DEPLOY_HOMEPL.md, sekcja "Płatności cykliczne (cron)".
 *
 * Co robi, krok po kroku:
 *  1. Znajduje zapisy (enrollments), które są POTWIERDZONE, NIEOPŁACONE,
 *     mają ustaloną kwotę, zajęcia zaczynają się w ciągu najbliższych
 *     TPAY_CRON_CHARGE_DAYS_AHEAD dni, i nie wyczerpały limitu prób
 *     (TPAY_CRON_MAX_ATTEMPTS) — patrz config.php.
 *  2. Dla każdego takiego zapisu szuka aktywnego, zapisanego tokenu karty
 *     rodzica z włączonym automatycznym pobieraniem (payment_tokens,
 *     auto_charge_enabled=1, revoked_at IS NULL) — patrz panel-platnosci.php,
 *     gdzie rodzic zarządza tym ustawieniem.
 *  3. Jeśli token jest, próbuje obciążyć go przez tpay_charge_token()
 *     (includes/tpay.php).
 *
 * ⚠ WAŻNE — DOKŁADNIE TAK SAMO JAK W KROKU 1: to, że tpay_charge_token()
 * zwróci "success" (patrz jego docblock), NIE oznacza jeszcze, że
 * payment_status ma zostać ustawiony na PAID. To wciąż robi WYŁĄCZNIE
 * webhook-tpay.php po zweryfikowaniu podpisanego powiadomienia od Tpay —
 * ten cron tylko INICJUJE obciążenie i zapisuje wynik próby (licznik prób /
 * ostatni błąd), zupełnie jak platnosc.php dla płatności ręcznej.
 *
 * Bezpieczeństwo uruchamiania:
 *  - Z linii poleceń (php cron-platnosci-cykliczne.php) — zawsze dozwolone,
 *    to jedyny sposób, w jaki ten skrypt POWINIEN być uruchamiany.
 *  - Przez adres URL — TYLKO jeśli w config.local.php ustawiono niepuste
 *    CRON_SECRET i podano je jako ?secret=... — inaczej 403. To zabezpieczenie
 *    przed sytuacją, w której ktokolwiek z internetu odgadnie adres tego
 *    pliku i wywoła prawdziwe obciążenia kart bez Twojej wiedzy.
 */
require_once __DIR__ . '/includes/bootstrap.php';

$isCli = PHP_SAPI === 'cli';
if (!$isCli) {
    $secret = $_GET['secret'] ?? '';
    if (CRON_SECRET === '' || !hash_equals(CRON_SECRET, (string) $secret)) {
        http_response_code(403);
        exit("Brak dostępu. Ten skrypt uruchamiaj z linii poleceń, albo ustaw CRON_SECRET w config.local.php i podaj je w ?secret=...\n");
    }
}

set_time_limit(0); // pobieranie wielu płatności po kolei może chwilę potrwać — nie chcemy przerwać w połowie

/** Krótki log na wyjście (konsola cron / przeglądarka, gdy testujesz przez URL). */
function cron_log(string $line): void
{
    echo '[' . date('Y-m-d H:i:s') . '] ' . $line . "\n";
}

cron_log('Start — TPAY_SIMULATE=' . (TPAY_SIMULATE ? 'true (test)' : 'false (prawdziwe płatności)'));

if (!TPAY_ENABLED) {
    cron_log('Płatności online wyłączone globalnie (brak kluczy Tpay) — kończę.');
    exit;
}

$chargeBefore = (new DateTime('+' . TPAY_CRON_CHARGE_DAYS_AHEAD . ' days'))->format('Y-m-d H:i:s');
$now = date('Y-m-d H:i:s');

// Zapytanie celowo NIE filtruje po org_id — cron działa dla WSZYSTKICH
// organizacji na tej instalacji na raz (jeden proces cron obsługuje całą
// platformę SaaS, nie osobno na klienta). Odosobnienie danych i tak jest
// zachowane, bo każdy wiersz niesie własne org_id, używane przy każdej
// operacji zapisu (patrz tpay_mark_enrollment_paid, panel-platnosci.php).
$stmt = db()->prepare("SELECT e.*, ct.name AS ct_name, cs.title AS session_title, cs.starts_at,
        u.name AS parent_name, u.email AS parent_email, o.name AS org_name, c.first_name AS child_first_name
    FROM enrollments e
    JOIN class_sessions cs ON cs.id = e.session_id
    JOIN class_types ct ON ct.id = cs.class_type_id
    JOIN users u ON u.id = e.parent_id
    JOIN organizations o ON o.id = e.org_id
    JOIN children c ON c.id = e.child_id
    WHERE e.status = 'CONFIRMED'
      AND e.payment_status = 'UNPAID'
      AND e.amount_due_cents > 0
      AND e.tpay_charge_attempts < ?
      AND cs.starts_at <= ?
    ORDER BY cs.starts_at ASC");
$stmt->execute([TPAY_CRON_MAX_ATTEMPTS, $chargeBefore]);
$candidates = $stmt->fetchAll();

cron_log('Znaleziono ' . count($candidates) . ' nieopłaconych zapisów kwalifikujących się do próby automatycznego pobrania (zajęcia do ' . $chargeBefore . ').');

$charged = 0;
$skippedNoToken = 0;
$failed = 0;

foreach ($candidates as $enrollment) {
    // Najnowszy aktywny token z włączoną zgodą na automatyczne pobieranie —
    // patrz panel-platnosci.php, gdzie rodzic tym zarządza.
    $tokenStmt = db()->prepare("SELECT * FROM payment_tokens
        WHERE parent_id = ? AND org_id = ? AND revoked_at IS NULL AND auto_charge_enabled = 1
        ORDER BY created_at DESC LIMIT 1");
    $tokenStmt->execute([$enrollment['parent_id'], $enrollment['org_id']]);
    $token = $tokenStmt->fetch();

    if (!$token) {
        // Brak zgody / zapisanej karty — nic nie robimy, rodzic opłaci
        // ręcznie (platnosc.php) albo organizacja ręcznie oznaczy jako
        // opłacone (zapisy.php). To NIE jest błąd, więc nie liczymy tego
        // jako nieudaną próbę (tpay_charge_attempts zostaje bez zmian).
        $skippedNoToken++;
        continue;
    }

    $description = $enrollment['ct_name'] . ' — ' . $enrollment['session_title'];
    $result = tpay_charge_token(
        $token['tpay_card_token'],
        (int) $enrollment['amount_due_cents'],
        $description,
        (string) $enrollment['id']
    );

    if ($result['success']) {
        // Obciążenie PRZYJĘTE do przetworzenia — NIE oznaczamy jako
        // opłacone tutaj (patrz duży komentarz na górze pliku). Zapisujemy
        // tylko numer transakcji i liczbę prób; ostateczne payment_status
        // ustawi webhook, gdy przyjdzie potwierdzenie.
        db()->prepare('UPDATE enrollments SET tpay_transaction_id = ?, tpay_charge_attempts = tpay_charge_attempts + 1, tpay_last_error = NULL WHERE id = ?')
            ->execute([$result['transactionId'], $enrollment['id']]);
        cron_log("OK  #{$enrollment['id']} {$description} — obciążenie zainicjowane (transactionId={$result['transactionId']}), czeka na potwierdzenie webhookiem.");
        $charged++;
    } else {
        $newAttempts = (int) $enrollment['tpay_charge_attempts'] + 1;
        db()->prepare('UPDATE enrollments SET tpay_charge_attempts = ?, tpay_last_error = ? WHERE id = ?')
            ->execute([$newAttempts, $result['error'], $enrollment['id']]);
        cron_log("BŁĄD #{$enrollment['id']} {$description} — {$result['error']} (próba $newAttempts/" . TPAY_CRON_MAX_ATTEMPTS . ')');
        $failed++;

        if ($newAttempts >= TPAY_CRON_MAX_ATTEMPTS) {
            // Limit prób wyczerpany — wyłączamy automatyczne pobieranie tym
            // tokenem (żeby nie próbować bez końca kartą, która np.
            // wygasła), token zostaje zapisany do wglądu/historii.
            db()->prepare('UPDATE payment_tokens SET auto_charge_enabled = 0 WHERE id = ?')->execute([$token['id']]);
            cron_log("     -> wyłączono automatyczne pobieranie tokenem #{$token['id']} (limit prób wyczerpany).");
        }

        try {
            send_payment_charge_failed_email([
                'parentEmail' => $enrollment['parent_email'],
                'parentName' => $enrollment['parent_name'],
                'childName' => $enrollment['child_first_name'],
                'classTypeName' => $enrollment['ct_name'],
                'sessionTitle' => $enrollment['session_title'],
                'amountCents' => (int) $enrollment['amount_due_cents'],
                'reason' => $result['error'],
                'orgName' => $enrollment['org_name'],
            ]);
        } catch (Throwable $mailError) {
            // Brak SMTP / błąd wysyłki nie powinien przerwać reszty crona —
            // logujemy i idziemy dalej (mailer.php i tak zapisuje nieudane
            // próby do storage/mail.log).
            error_log('[InnovaGo/Cron] Nie udało się wysłać e-maila o nieudanej płatności: ' . $mailError->getMessage());
        }
    }
}

cron_log("Koniec. Zainicjowano: $charged, pominięto (brak zgody/karty): $skippedNoToken, nieudane: $failed.");
