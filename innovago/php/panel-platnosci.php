<?php
/**
 * ============================================================================
 *  MOJE PŁATNOŚCI — KROK 2 (zarządzanie zapisanymi kartami / płatnościami cyklicznymi)
 * ============================================================================
 * Rodzic widzi tu karty, które zapisał wcześniej przy płatności online
 * (checkbox "zapamiętaj kartę" w panel-zapisy.php -> platnosc.php ->
 * token przychodzi w webhooku -> tpay_mark_enrollment_paid() zapisuje go
 * do tabeli payment_tokens — patrz includes/tpay.php).
 *
 * Z tego miejsca rodzic może:
 *  - włączyć/wyłączyć automatyczne pobieranie danym tokenem (auto_charge_enabled)
 *    — kartę można zostawić "tylko do ręcznych płatności" bez zgody na cron,
 *  - trwale usunąć (odwołać) zapisaną kartę.
 *
 * Samo AUTOMATYCZNE OBCIĄŻANIE nią kolejnych zajęć wykonuje osobny skrypt
 * cron-platnosci-cykliczne.php, uruchamiany cyklicznie (np. raz dziennie)
 * przez harmonogram zadań (cron) na hostingu — ten plik tylko zarządza
 * zgodą i samymi tokenami, nie robi żadnych obciążeń.
 */
require_once __DIR__ . '/includes/bootstrap.php';

$user = require_role(['PARENT'], 'panel-platnosci.php');
$org = require_org();
$error = null;
$info = $_GET['info'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $formAction = $_POST['_action'] ?? '';

    if ($formAction === 'toggle_auto') {
        $id = (int) ($_POST['id'] ?? 0);
        // UPDATE ... WHERE parent_id/org_id: granica bezpieczeństwa — rodzic
        // może zarządzać WYŁĄCZNIE własnymi tokenami, nigdy cudzymi.
        db()->prepare('UPDATE payment_tokens SET auto_charge_enabled = 1 - auto_charge_enabled WHERE id = ? AND parent_id = ? AND org_id = ?')
            ->execute([$id, $user['id'], $org['id']]);
        redirect_with('panel-platnosci.php', ['info' => 'Zaktualizowano ustawienie automatycznych płatności.']);
    } elseif ($formAction === 'revoke') {
        $id = (int) ($_POST['id'] ?? 0);
        db()->prepare('UPDATE payment_tokens SET revoked_at = CURRENT_TIMESTAMP WHERE id = ? AND parent_id = ? AND org_id = ?')
            ->execute([$id, $user['id'], $org['id']]);
        redirect_with('panel-platnosci.php', ['info' => 'Karta została usunięta z zapisanych metod płatności.']);
    } elseif ($formAction === 'add_test_token' && TPAY_SIMULATE) {
        // --- WYŁĄCZNIE do testów lokalnych (patrz warunek TPAY_SIMULATE
        // powyżej — ta gałąź jest fizycznie nieosiągalna na produkcji,
        // dokładnie tak samo jak reszta trybu symulacji w includes/tpay.php).
        // Pozwala ręcznie dodać token testowy bez przechodzenia całej
        // ścieżki płatności — przydatne do przetestowania
        // cron-platnosci-cykliczne.php, w tym ścieżki BŁĘDU: token
        // zaczynający się od "FAIL-" jest przez tpay_charge_token()
        // rozpoznawany jako celowo odrzucany (patrz includes/tpay.php). ---
        $shouldFail = !empty($_POST['simulateFailure']);
        $token = ($shouldFail ? 'FAIL-' : 'SIM-TOKEN-') . bin2hex(random_bytes(4));
        $label = trim((string) ($_POST['label'] ?? '')) ?: ('Karta testowa ' . ($shouldFail ? '(zawsze odrzucana)' : '(zawsze akceptowana)'));
        db()->prepare('INSERT INTO payment_tokens (org_id, parent_id, tpay_card_token, card_label, created_at, auto_charge_enabled) VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP, 1)')
            ->execute([$org['id'], $user['id'], $token, $label]);
        redirect_with('panel-platnosci.php', ['info' => 'Dodano token testowy: ' . $token]);
    }
}

$stmt = db()->prepare('SELECT * FROM payment_tokens WHERE parent_id = ? AND org_id = ? AND revoked_at IS NULL ORDER BY created_at DESC');
$stmt->execute([$user['id'], $org['id']]);
$tokens = $stmt->fetchAll();

$pageTitle = 'Moje płatności — ' . $org['name'];
require __DIR__ . '/includes/layout_top.php';
?>
<div class="container section">
  <h1 class="section-title">Moje płatności</h1>
  <p class="text-muted">Zapisane karty używane do płatności online i (opcjonalnie) automatycznego pobierania za kolejne zajęcia.</p>
  <?php if ($error): ?><p class="alert alert-error"><?= e($error) ?></p><?php endif; ?>
  <?php if ($info): ?><p class="alert alert-success"><?= e($info) ?></p><?php endif; ?>

  <?php if (!TPAY_ENABLED): ?>
    <p class="text-muted mt-6">Płatności online nie są obecnie włączone w tej organizacji.</p>
  <?php elseif (!$tokens): ?>
    <p class="text-muted mt-6">Nie masz jeszcze żadnych zapisanych kart. Zaznacz „zapamiętaj kartę” przy najbliższej
      płatności online w <a href="<?= e(url('panel-zapisy.php')) ?>">Moich zapisach</a>, żeby kolejne płatności mogły
      być pobierane automatycznie.</p>
  <?php else: ?>
    <div class="enroll-list mt-6">
      <?php foreach ($tokens as $t): ?>
        <div class="enroll-card reveal">
          <div class="enroll-main">
            <span class="enroll-title">💳 <?= e($t['card_label']) ?></span>
            <div class="text-muted">Dodano <?= e(format_pl_date($t['created_at'])) ?></div>
          </div>
          <div class="enroll-badges">
            <span class="badge badge-<?= $t['auto_charge_enabled'] ? 'confirmed' : 'pending' ?>">
              <?= $t['auto_charge_enabled'] ? 'Automatyczne pobieranie: WŁ.' : 'Automatyczne pobieranie: WYŁ.' ?>
            </span>
          </div>
          <div class="enroll-actions">
            <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="_action" value="toggle_auto"><input type="hidden" name="id" value="<?= $t['id'] ?>">
              <button class="btn btn-outline btn-sm"><?= $t['auto_charge_enabled'] ? 'Wyłącz automatyczne pobieranie' : 'Włącz automatyczne pobieranie' ?></button>
            </form>
            <form method="post" class="inline" onsubmit="return confirm('Usunąć tę zapisaną kartę?');"><?= csrf_field() ?><input type="hidden" name="_action" value="revoke"><input type="hidden" name="id" value="<?= $t['id'] ?>">
              <button class="btn btn-outline btn-sm">Usuń kartę</button>
            </form>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if (TPAY_SIMULATE): ?>
    <!-- Widoczne WYŁĄCZNIE w trybie symulacji (patrz warunek TPAY_SIMULATE
         wyżej) — sposób na przetestowanie cron-platnosci-cykliczne.php,
         w tym ścieżki nieudanej płatności, bez przechodzenia całej ścieżki
         płatności ręcznej. -->
    <div class="card mt-8" style="padding:1.5rem;">
      <p class="text-muted" style="letter-spacing:.06em;text-transform:uppercase;font-size:.75rem;">🧪 Tylko tryb symulacji</p>
      <h2 style="margin-top:.25rem;">Dodaj token testowy</h2>
      <form method="post" class="flex items-center gap-2 mt-4" style="flex-wrap:wrap;">
        <?= csrf_field() ?>
        <input type="hidden" name="_action" value="add_test_token">
        <input type="text" name="label" placeholder="Etykieta (opcjonalnie)" style="max-width:220px;">
        <label class="text-muted" style="display:flex;align-items:center;gap:4px;">
          <input type="checkbox" name="simulateFailure" value="1"> ten token ma być zawsze ODRZUCANY (test błędu)
        </label>
        <button type="submit" class="btn btn-primary btn-sm">Dodaj token testowy</button>
      </form>
    </div>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
