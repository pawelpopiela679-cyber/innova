<?php
/**
 * ============================================================================
 *  PŁATNOŚĆ ONLINE — STRONA SYMULACJI (tylko gdy TPAY_SIMULATE = true)
 * ============================================================================
 * W prawdziwej integracji ta strona NIE ISTNIEJE w naszej appce — rodzic
 * trafiałby na hostowaną przez Tpay stronę płatności (formularz karty /
 * wybór BLIK / przelewu), a Tpay sam, po stronie swojego serwera, wysłałby
 * do nas webhook i przekierował przeglądarkę z powrotem.
 *
 * Tutaj, w trybie symulacji, UDAJEMY oba te kroki ręcznie, żeby dało się
 * przetestować CAŁY przepływ płatności bez zakładania konta Tpay:
 *   - ten plik pokazuje kwotę/opis i przycisk "Zapłać" (symulacja sukcesu)
 *     oraz "Odrzuć płatność" (symulacja porażki),
 *   - po kliknięciu wywołujemy DOKŁADNIE tę samą funkcję przetwarzającą
 *     powiadomienie, której używa prawdziwy webhook-tpay.php —
 *     tpay_process_webhook_payload() w includes/tpay.php — z nagłówkiem
 *     "symulacja", który tpay_verify_webhook_signature() rozpoznaje
 *     WYŁĄCZNIE gdy TPAY_SIMULATE=true (ta gałąź jest fizycznie
 *     nieosiągalna, gdy TPAY_SIMULATE=false, czyli na produkcji),
 *   - dopiero PO przetworzeniu przekierowujemy na platnosc-powrot.php,
 *     tak samo jak zrobiłby to prawdziwy Tpay po zakończonej płatności.
 * Dzięki temu test w symulacji przechodzi przez DOKŁADNIE ten sam kod
 * przetwarzania webhooka, który będzie użyty na produkcji (różni się tylko
 * to, jak podpis jest weryfikowany, i to, że wywołujemy funkcję bezpośrednio
 * zamiast przez prawdziwe żądanie HTTP od Tpay — samo wywołanie funkcji, nie
 * "zapytanie HTTP do samego siebie", żeby test działał identycznie
 * niezależnie od konfiguracji serwera, na którym akurat testujesz).
 */
require_once __DIR__ . '/includes/bootstrap.php';

if (!TPAY_SIMULATE) {
    // Zabezpieczenie na wszelki wypadek: gdyby ten plik został przypadkiem
    // wdrożony na produkcję z TPAY_SIMULATE=false, nie pozwalamy go użyć —
    // nie chcemy, żeby ktokolwiek mógł "kliknąć zapłacono" bez prawdziwej
    // płatności.
    http_response_code(404);
    exit('Nie znaleziono.');
}

$user = require_role(['PARENT'], 'platnosc-symulacja.php');
$org = require_org();

$transactionId = (string) ($_GET['transactionId'] ?? $_POST['transactionId'] ?? '');
$externalId = (string) ($_GET['externalId'] ?? $_POST['externalId'] ?? '');
$amountCents = (int) ($_GET['amountCents'] ?? $_POST['amountCents'] ?? 0);
$tokenize = ($_GET['tokenize'] ?? $_POST['tokenize'] ?? '0') === '1'; // KROK 2 — patrz niżej

$enrollmentId = tpay_extract_enrollment_id('innovago-enrollment-' . $externalId) ?? (int) $externalId;

// Sprawdzamy, że zapis naprawdę istnieje i należy do zalogowanego rodzica —
// w symulacji TO PRZEGLĄDARKA RODZICA "jest" Tpay, więc musimy pilnować tej
// granicy sami (prawdziwy Tpay nie zna naszej sesji logowania w ogóle).
$stmt = db()->prepare('SELECT e.*, ct.name AS ct_name, cs.title AS session_title
    FROM enrollments e JOIN class_sessions cs ON cs.id = e.session_id JOIN class_types ct ON ct.id = cs.class_type_id
    WHERE e.id = ? AND e.parent_id = ? AND e.org_id = ?');
$stmt->execute([$enrollmentId, $user['id'], $org['id']]);
$enrollment = $stmt->fetch();

if (!$enrollment || $enrollment['tpay_transaction_id'] !== $transactionId) {
    http_response_code(400);
    exit('Nieprawidłowy albo nieaktualny link płatności symulowanej.');
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['_action'])) {
    csrf_check();

    if ($_POST['_action'] === 'simulate_success') {
        // --- symulowany token karty (KROK 2) -----------------------------
        // Gdy rodzic (w symulacji) zaznaczył "zapamiętaj kartę", generujemy
        // fałszywy, ale realistyczny token — dokładnie to samo pole, którego
        // prawdziwy Tpay użyłby w prawdziwym powiadomieniu webhook.
        $simulatedCardToken = $tokenize ? ('SIM-TOKEN-' . bin2hex(random_bytes(6))) : null;

        // Budujemy treść powiadomienia w formacie x-www-form-urlencoded —
        // taki sam kształt, jakiego (wg dokumentacji) używa prawdziwe Tpay
        // (patrz ⚠ ZWERYFIKUJ w webhook-tpay.php przy parsowaniu $rawBody).
        $rawBody = http_build_query([
            'tr_id' => $transactionId,
            'tr_status' => 'TRUE', // "TRUE" = płatność zaakceptowana (konwencja klasycznego API Tpay)
            'tr_amount' => number_format($amountCents / 100, 2, '.', ''),
            'tr_crc' => 'innovago-enrollment-' . $enrollmentId, // patrz tpay_extract_enrollment_id()
            'tr_desc' => $enrollment['ct_name'] . ' — ' . $enrollment['session_title'],
            'test_mode' => '1',
        ] + ($simulatedCardToken !== null ? ['card_token' => $simulatedCardToken] : []));

        // Bezpośrednie wywołanie tej samej funkcji, której używa prawdziwy
        // webhook-tpay.php — patrz komentarz na górze pliku, dlaczego NIE
        // robimy tu prawdziwego zapytania HTTP "do samego siebie".
        $result = tpay_process_webhook_payload($rawBody, 'symulacja');

        if (!$result['verified'] || !$result['matched']) {
            // W praktyce nie powinno się zdarzyć (sami budujemy poprawne
            // dane powyżej) — zostawione jako zabezpieczenie, gdyby ktoś
            // np. zmienił format $rawBody bez dopasowania po drugiej stronie.
            $error = 'Nie udało się przetworzyć symulowanego powiadomienia (verified=' . ($result['verified'] ? '1' : '0') . ', matched=' . ($result['matched'] ? '1' : '0') . ').';
        } else {
            redirect('platnosc-powrot.php?status=success&id=' . $enrollmentId);
        }
    } elseif ($_POST['_action'] === 'simulate_fail') {
        // Symulacja odrzuconej płatności — NIE wywołujemy webhooka wcale
        // (dokładnie tak zachowałby się prawdziwy Tpay przy nieudanej
        // płatności: brak webhooka "sukcesu", zapis pozostaje UNPAID).
        redirect('platnosc-powrot.php?status=error&id=' . $enrollmentId);
    }
}

$pageTitle = 'Symulacja płatności — Tpay';
require __DIR__ . '/includes/layout_top.php';
?>
<div class="container section" style="max-width:520px;">
  <div class="card reveal" style="text-align:center;padding:2.5rem 2rem;">
    <p class="text-muted" style="letter-spacing:.06em;text-transform:uppercase;font-size:.75rem;">🧪 Tryb symulacji — bez prawdziwej płatności</p>
    <h1 class="section-title" style="margin-top:.5rem;">Symulowana płatność Tpay</h1>
    <p class="text-muted mt-2">To jest lokalna atrapa strony płatności Tpay — służy WYŁĄCZNIE do testowania. Wyłącz <code>TPAY_SIMULATE</code> w <code>config.local.php</code>, gdy będziesz gotowy przyjmować prawdziwe płatności.</p>

    <div class="mt-6" style="background:var(--surface-2,#f4f4f8);border-radius:12px;padding:1.25rem;">
      <div style="font-size:2rem;font-weight:700;"><?= e(format_money($amountCents)) ?></div>
      <div class="text-muted"><?= e($enrollment['ct_name']) ?> — <?= e($enrollment['session_title']) ?></div>
      <div class="text-muted" style="font-size:.78rem;margin-top:.5rem;">Nr transakcji: <?= e($transactionId) ?></div>
    </div>

    <?php if ($error): ?><p class="alert alert-error mt-4"><?= e($error) ?></p><?php endif; ?>

    <form method="post" class="mt-6">
      <?= csrf_field() ?>
      <input type="hidden" name="transactionId" value="<?= e($transactionId) ?>">
      <input type="hidden" name="externalId" value="<?= e($externalId) ?>">
      <input type="hidden" name="amountCents" value="<?= $amountCents ?>">
      <?php if ($tokenize): ?>
        <p class="text-muted" style="font-size:.85rem;">✓ Ta symulacja doda też zapisaną kartę testową (KROK 2).</p>
      <?php endif; ?>
      <input type="hidden" name="tokenize" value="<?= $tokenize ? '1' : '0' ?>">
      <div class="flex gap-2 mt-4" style="justify-content:center;">
        <button type="submit" name="_action" value="simulate_success" class="btn btn-primary">✅ Symuluj udaną płatność</button>
        <button type="submit" name="_action" value="simulate_fail" class="btn btn-outline">❌ Symuluj odrzucenie</button>
      </div>
    </form>
  </div>
</div>
<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
