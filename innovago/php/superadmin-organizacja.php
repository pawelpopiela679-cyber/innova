<?php
require_once __DIR__ . '/includes/bootstrap.php';

$user = require_super_admin();
$orgId = (int) ($_GET['id'] ?? 0);
$org = db()->prepare('SELECT * FROM organizations WHERE id = ?');
$org->execute([$orgId]);
$org = $org->fetch();
if (!$org) {
    redirect('superadmin.php');
}

$plans = db()->query('SELECT * FROM subscription_plans ORDER BY sort_order ASC')->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $planId = (int) ($_POST['plan_id'] ?? 0);
    $status = (string) ($_POST['status'] ?? $org['status']);
    if (in_array($planId, array_column($plans, 'id'), true) && in_array($status, ['TRIAL', 'ACTIVE', 'SUSPENDED', 'CANCELED'], true)) {
        db()->prepare('UPDATE organizations SET plan_id = ?, status = ? WHERE id = ?')->execute([$planId, $status, $orgId]);
        redirect('superadmin-organizacja.php?id=' . $orgId);
    }
}

$staff = db()->prepare("SELECT * FROM users WHERE org_id = ? AND role IN ('ORG_ADMIN','INSTRUCTOR') ORDER BY role ASC");
$staff->execute([$orgId]);
$staff = $staff->fetchAll();

$instructorCount = org_instructor_count($orgId);
$studentCount = org_student_count($orgId);

$pageTitle = $org['name'] . ' — Super-admin InnovaGo';
require __DIR__ . '/includes/layout_top.php';
?>
<div class="container-sm section">
  <a href="<?= e(url('superadmin.php')) ?>" class="text-muted">← Wszystkie organizacje</a>
  <h1 class="section-title mt-2"><?= e($org['name']) ?></h1>
  <p class="text-muted">Link do rejestracji dla rodziców: <code><?= e(APP_URL . url('rejestracja.php?org=' . $org['slug'])) ?></code></p>
  <p class="text-muted"><?= $instructorCount ?> prowadzących · <?= $studentCount ?> zapisanych dzieci</p>

  <form method="post" class="mt-6 form-card reveal">
    <?= csrf_field() ?>
    <div class="field">
      <label>Plan</label>
      <select name="plan_id">
        <?php foreach ($plans as $p): ?><option value="<?= $p['id'] ?>" <?= (int) $org['plan_id'] === (int) $p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?> — <?= number_format($p['price_monthly'] / 100, 0, ',', ' ') ?> zł/mies.</option><?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label>Status</label>
      <select name="status">
        <?php foreach (['TRIAL' => 'Okres próbny', 'ACTIVE' => 'Aktywna', 'SUSPENDED' => 'Zawieszona', 'CANCELED' => 'Anulowana'] as $k => $l): ?>
          <option value="<?= $k ?>" <?= $org['status'] === $k ? 'selected' : '' ?>><?= $l ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <button type="submit" class="btn btn-primary">Zapisz</button>
  </form>

  <h2 class="mt-8">Konta administracyjne</h2>
  <ul class="type-list mt-2">
    <?php foreach ($staff as $s): ?>
      <li><?= e($s['name']) ?> — <?= e($s['email']) ?> <span class="text-muted">(<?= $s['role'] === 'ORG_ADMIN' ? 'właściciel' : 'prowadzący' ?>)</span></li>
    <?php endforeach; ?>
  </ul>
</div>
<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
