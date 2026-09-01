<?php
require_once __DIR__ . '/includes/bootstrap.php';

$user = require_super_admin();
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id = (int) ($_POST['id'] ?? 0);
    $name = trim((string) ($_POST['name'] ?? ''));
    $price = (int) round(((float) str_replace(',', '.', (string) ($_POST['price'] ?? '0'))) * 100);
    $maxInstructors = (int) ($_POST['max_instructors'] ?? 1);
    $maxStudents = (int) ($_POST['max_students'] ?? 20);
    db()->prepare('UPDATE subscription_plans SET name=?, price_monthly=?, max_instructors=?, max_students=? WHERE id=?')
        ->execute([$name, $price, $maxInstructors, $maxStudents, $id]);
    $success = 'Zapisano zmiany planu „' . $name . '”.';
}

$plans = db()->query('SELECT * FROM subscription_plans ORDER BY sort_order ASC')->fetchAll();
$orgCounts = db()->query('SELECT plan_id, COUNT(*) c FROM organizations GROUP BY plan_id')->fetchAll();
$orgCountsByPlan = array_column($orgCounts, 'c', 'plan_id');

$pageTitle = 'Plany subskrypcji — Super-admin InnovaGo';
require __DIR__ . '/includes/layout_top.php';
?>
<div class="container section">
  <h1 class="section-title">Plany subskrypcji</h1>
  <?php if ($success): ?><p class="alert alert-success"><?= e($success) ?></p><?php endif; ?>

  <div class="pricing-grid mt-6">
    <?php foreach ($plans as $p): ?>
      <form method="post" class="form-card reveal">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= $p['id'] ?>">
        <div class="field"><label>Nazwa</label><input name="name" value="<?= e($p['name']) ?>" required></div>
        <div class="field"><label>Cena / mies. (zł)</label><input name="price" value="<?= number_format($p['price_monthly'] / 100, 2, '.', '') ?>"></div>
        <div class="field"><label>Limit prowadzących (999 = bez limitu)</label><input type="number" name="max_instructors" value="<?= (int) $p['max_instructors'] ?>"></div>
        <div class="field"><label>Limit dzieci (999999 = bez limitu)</label><input type="number" name="max_students" value="<?= (int) $p['max_students'] ?>"></div>
        <p class="text-muted"><?= (int) ($orgCountsByPlan[$p['id']] ?? 0) ?> organizacji na tym planie.</p>
        <button type="submit" class="btn btn-primary" style="width:100%;">Zapisz</button>
      </form>
    <?php endforeach; ?>
  </div>
</div>
<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
