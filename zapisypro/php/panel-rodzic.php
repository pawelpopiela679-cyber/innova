<?php
require_once __DIR__ . '/includes/bootstrap.php';

$user = require_role(['PARENT'], 'panel-rodzic.php');
$org = require_org();

$stmt = db()->prepare('SELECT COUNT(*) c FROM children WHERE parent_id = ?');
$stmt->execute([$user['id']]);
$childrenCount = (int) $stmt->fetch()['c'];

$stmt = db()->prepare("SELECT e.*, cs.title, cs.starts_at, ct.name AS ct_name, c.first_name
    FROM enrollments e JOIN class_sessions cs ON cs.id = e.session_id JOIN class_types ct ON ct.id = cs.class_type_id
    JOIN children c ON c.id = e.child_id
    WHERE e.parent_id = ? AND e.status IN ('CONFIRMED','WAITLIST') AND cs.starts_at >= ?
    ORDER BY cs.starts_at ASC LIMIT 5");
$stmt->execute([$user['id'], date('Y-m-d H:i:s')]);
$upcoming = $stmt->fetchAll();

$stmt = db()->prepare("SELECT COUNT(*) c FROM enrollments WHERE parent_id = ? AND payment_status = 'UNPAID' AND status = 'CONFIRMED'");
$stmt->execute([$user['id']]);
$unpaidCount = (int) $stmt->fetch()['c'];

$pageTitle = 'Panel rodzica — ' . $org['name'];
require __DIR__ . '/includes/layout_top.php';
?>
<div class="container section">
  <h1 class="section-title">Witaj, <?= e($user['name']) ?>!</h1>
  <p class="text-muted"><?= e($org['name']) ?></p>

  <div class="stat-grid mt-6">
    <div class="stat-card reveal">
      <div class="stat-value" data-count="<?= $childrenCount ?>">0</div>
      <div class="stat-label">Dzieci na koncie</div>
      <a href="<?= e(url('panel-dzieci.php')) ?>" class="stat-link">Zarządzaj →</a>
    </div>
    <div class="stat-card reveal" style="animation-delay:60ms;">
      <div class="stat-value" data-count="<?= count($upcoming) ?>">0</div>
      <div class="stat-label">Nadchodzące zajęcia</div>
      <a href="<?= e(url('panel-zapisy.php')) ?>" class="stat-link">Zobacz →</a>
    </div>
    <div class="stat-card reveal" style="animation-delay:120ms;">
      <div class="stat-value" data-count="<?= $unpaidCount ?>">0</div>
      <div class="stat-label">Do opłacenia</div>
      <a href="<?= e(url('panel-zapisy.php')) ?>" class="stat-link">Zobacz →</a>
    </div>
  </div>

  <h2 class="mt-8">Najbliższe zajęcia</h2>
  <?php if (!$upcoming): ?>
    <p class="text-muted mt-2">Brak nadchodzących zajęć. <a href="<?= e(url('kalendarz.php')) ?>">Zapisz dziecko →</a></p>
  <?php endif; ?>
  <div class="agenda-list mt-4">
    <?php foreach ($upcoming as $u): ?>
      <div class="agenda-card reveal">
        <div class="agenda-main">
          <div class="agenda-title"><?= e($u['ct_name']) ?> — <?= e($u['title']) ?></div>
          <div class="text-muted"><?= e($u['first_name']) ?> · <?= e(format_pl_date($u['starts_at'], true, true)) ?></div>
        </div>
        <span class="badge badge-<?= strtolower($u['status']) ?>"><?= $u['status'] === 'WAITLIST' ? 'Lista rezerwowa' : 'Potwierdzone' ?></span>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
