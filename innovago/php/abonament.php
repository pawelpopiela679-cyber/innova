<?php
/**
 * Widok abonamentu organizacji: plan, status, wykorzystanie limitów, zmiana
 * planu. Bez integracji z bramką płatności (patrz README_PHP.md) zmiana
 * planu jest tu od razu stosowana — w wersji z prawdziwymi płatnościami
 * przed zastosowaniem stałaby między nimi strona płatności/checkout.
 */
require_once __DIR__ . '/includes/bootstrap.php';

$user = require_org_admin();
$org = require_org();
$plans = db()->query('SELECT * FROM subscription_plans ORDER BY sort_order ASC')->fetchAll();
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $planId = (int) ($_POST['plan_id'] ?? 0);
    if (in_array($planId, array_column($plans, 'id'), true)) {
        db()->prepare('UPDATE organizations SET plan_id = ? WHERE id = ?')->execute([$planId, $org['id']]);
        $success = 'Plan zaktualizowany.';
        $orgStmt = db()->prepare('SELECT * FROM organizations WHERE id = ?');
        $orgStmt->execute([$org['id']]);
        $org = $orgStmt->fetch();
    }
}

$plan = org_plan($org);
$instructorCount = org_instructor_count((int) $org['id']);
$studentCount = org_student_count((int) $org['id']);

$pageTitle = 'Abonament — ' . $org['name'];
require __DIR__ . '/includes/layout_top.php';
?>
<div class="container section">
  <h1 class="section-title">Abonament</h1>

  <?php if ($success): ?><p class="alert alert-success"><?= e($success) ?></p><?php endif; ?>

  <div class="form-card mt-6 reveal">
    <p><strong>Status:</strong>
      <?= match ($org['status']) { 'TRIAL' => 'Okres próbny do ' . format_pl_date($org['trial_ends_at']), 'ACTIVE' => 'Aktywna subskrypcja', 'SUSPENDED' => 'Zawieszona', default => 'Anulowana' } ?>
    </p>
    <p><strong>Obecny plan:</strong> <?= e($plan['name'] ?? '—') ?></p>
    <div class="usage-bars mt-4">
      <div>
        <div class="flex" style="justify-content:space-between;"><span>Prowadzący</span><span><?= $instructorCount ?> / <?= $plan && $plan['max_instructors'] < 999 ? (int) $plan['max_instructors'] : '∞' ?></span></div>
        <div class="usage-bar"><div class="usage-bar-fill" style="width:<?= $plan && $plan['max_instructors'] < 999 ? min(100, round($instructorCount / max(1, $plan['max_instructors']) * 100)) : 8 ?>%"></div></div>
      </div>
      <div class="mt-4">
        <div class="flex" style="justify-content:space-between;"><span>Zapisane dzieci</span><span><?= $studentCount ?> / <?= $plan && $plan['max_students'] < 999999 ? (int) $plan['max_students'] : '∞' ?></span></div>
        <div class="usage-bar"><div class="usage-bar-fill" style="width:<?= $plan && $plan['max_students'] < 999999 ? min(100, round($studentCount / max(1, $plan['max_students']) * 100)) : 8 ?>%"></div></div>
      </div>
    </div>
  </div>

  <h2 class="mt-8">Zmień plan</h2>
  <p class="text-muted">Bez podpiętej bramki płatności zmiana jest natychmiastowa — w wersji produkcyjnej z płatnościami
    online (Przelewy24/PayU) trafiłbyś tu najpierw na stronę płatności.</p>
  <form method="post" class="mt-4">
    <?= csrf_field() ?>
    <div class="plan-radio-group">
      <?php foreach ($plans as $p): ?>
        <label class="plan-radio">
          <input type="radio" name="plan_id" value="<?= $p['id'] ?>" <?= (int) $org['plan_id'] === (int) $p['id'] ? 'checked' : '' ?>>
          <span><strong><?= e($p['name']) ?></strong> — <?= number_format($p['price_monthly'] / 100, 0, ',', ' ') ?> zł/mies. · do <?= $p['max_instructors'] >= 999 ? '∞' : (int) $p['max_instructors'] ?> prowadzących, do <?= $p['max_students'] >= 999999 ? '∞' : (int) $p['max_students'] ?> dzieci</span>
        </label>
      <?php endforeach; ?>
    </div>
    <button type="submit" class="btn btn-primary mt-4">Zapisz plan</button>
  </form>
</div>
<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
