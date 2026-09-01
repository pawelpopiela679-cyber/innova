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

$stmt = db()->prepare("SELECT e.*, cs.title, cs.starts_at, ct.name AS ct_name, c.first_name
    FROM enrollments e JOIN class_sessions cs ON cs.id = e.session_id JOIN class_types ct ON ct.id = cs.class_type_id
    JOIN children c ON c.id = e.child_id
    WHERE e.parent_id = ? AND e.status = 'CONFIRMED' AND e.payment_status = 'UNPAID'
    ORDER BY cs.starts_at ASC");
$stmt->execute([$user['id']]);
$arrears = $stmt->fetchAll();
$unpaidCount = count($arrears);
$arrearsTotalCents = array_sum(array_column($arrears, 'amount_due_cents'));

$todayStart = (new DateTime('today'))->format('Y-m-d H:i:s');
$todayEnd = (new DateTime('tomorrow'))->format('Y-m-d H:i:s');
$stmt = db()->prepare("SELECT e.*, cs.title, cs.starts_at, cs.ends_at, ct.name AS ct_name, ct.color AS ct_color, c.first_name
    FROM enrollments e JOIN class_sessions cs ON cs.id = e.session_id JOIN class_types ct ON ct.id = cs.class_type_id
    JOIN children c ON c.id = e.child_id
    WHERE e.parent_id = ? AND e.status IN ('CONFIRMED','WAITLIST') AND cs.starts_at >= ? AND cs.starts_at < ?
    ORDER BY cs.starts_at ASC");
$stmt->execute([$user['id'], $todayStart, $todayEnd]);
$todaySessions = $stmt->fetchAll();

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

  <div class="two-col mt-8">
    <div>
      <h2>Zajęcia dziś <span class="text-muted" style="font-weight:400;"><?= e(format_pl_date($todayStart, true)) ?></span></h2>
      <?php if (!$todaySessions): ?>
        <p class="text-muted mt-2">Brak zajęć dziś.</p>
      <?php else: ?>
        <div class="agenda-list mt-4">
          <?php foreach ($todaySessions as $s): ?>
            <div class="agenda-card reveal">
              <div class="agenda-dot" style="background:<?= e($s['ct_color']) ?>;"></div>
              <div class="agenda-main">
                <div class="agenda-title"><?= e($s['ct_name']) ?> — <?= e($s['title']) ?></div>
                <div class="text-muted"><?= e($s['first_name']) ?> · <?= h_m($s['starts_at']) ?>–<?= h_m($s['ends_at']) ?></div>
              </div>
              <span class="badge badge-<?= strtolower($s['status']) ?>"><?= $s['status'] === 'WAITLIST' ? 'Lista rezerwowa' : 'Potwierdzone' ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <div>
      <h2>Zaległości<?php if ($arrears): ?> <span class="text-muted" style="font-weight:400;">łącznie <?= format_money((int) $arrearsTotalCents) ?></span><?php endif; ?></h2>
      <?php if (!$arrears): ?>
        <p class="text-muted mt-2">Brak zaległości — wszystko opłacone! 🎉</p>
      <?php else: ?>
        <div class="enroll-list mt-4">
          <?php foreach ($arrears as $a): ?>
            <div class="enroll-card reveal">
              <div class="enroll-main">
                <div class="enroll-title"><?= e($a['ct_name']) ?> — <?= e($a['title']) ?></div>
                <div class="text-muted"><?= e($a['first_name']) ?> · <?= e(format_pl_date($a['starts_at'])) ?></div>
              </div>
              <div class="enroll-badges"><span class="badge badge-pending"><?= $a['amount_due_cents'] ? format_money((int) $a['amount_due_cents']) : 'Do opłacenia' ?></span></div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
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
