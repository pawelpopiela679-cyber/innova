<?php
require_once __DIR__ . '/includes/bootstrap.php';
$user = require_login('panel.php');

$childrenCountStmt = db()->prepare('SELECT COUNT(*) c FROM children WHERE parent_id = ?');
$childrenCountStmt->execute([$user['id']]);
$childrenCount = (int) $childrenCountStmt->fetch()['c'];

// LEFT JOIN — zgłoszenie czekające w puli nie ma jeszcze grupy (pokazujemy
// je i tak, bez konkretnej daty); next_starts_at to najbliższe cotygodniowe
// wystąpienie przydzielonej grupy w kalendarzu.
$stmt = db()->prepare("SELECT e.*, c.first_name, c.last_name, ct.name AS ct_name, g.name AS group_name,
        (SELECT MIN(cs.starts_at) FROM class_sessions cs WHERE cs.group_id = e.group_id AND cs.starts_at >= ?) AS next_starts_at
    FROM enrollments e
    JOIN children c ON c.id = e.child_id
    LEFT JOIN class_groups g ON g.id = e.group_id
    LEFT JOIN class_types ct ON ct.id = COALESCE(e.class_type_id, g.class_type_id)
    WHERE e.parent_id = ? AND e.status IN ('PENDING','CONFIRMED','WAITLIST')
    ORDER BY (next_starts_at IS NULL) ASC, next_starts_at ASC, e.created_at DESC
    LIMIT 5");
$stmt->execute([date('Y-m-d H:i:s'), $user['id']]);
$upcoming = $stmt->fetchAll();

$firstName = explode(' ', $user['name'])[0];

$pageTitle = 'Panel rodzica — INNOVA';
$notebookTheme = true;
require __DIR__ . '/includes/layout_top.php';
?>
<div class="container-md" style="padding:40px 16px;">
  <?php include __DIR__ . '/includes/partials/panel-nav.php'; ?>

  <h1 style="font-size:1.6rem;">Witaj, <?= e($firstName) ?> 👋</h1>

  <div class="grid grid-2 mt-6">
    <a href="<?= e(url('panel-dzieci.php')) ?>" class="card" style="text-decoration:none; color:inherit;">
      <p class="text-muted">Dzieci na koncie</p>
      <p style="font-size:2rem; font-weight:800;"><?= $childrenCount ?></p>
      <p style="color:var(--primary);">Zarządzaj dziećmi →</p>
    </a>
    <a href="<?= e(url('zapisz.php')) ?>" class="card" style="text-decoration:none; color:inherit;">
      <p class="text-muted">Zapisz na nowe zajęcia</p>
      <p style="font-size:2rem;">📅</p>
      <p style="color:var(--primary);">Zapisz dziecko →</p>
    </a>
  </div>

  <h2 class="mt-8" style="font-size:1.1rem;">Najbliższe zajęcia</h2>
  <?php if (!$upcoming): ?>
    <p class="text-muted mt-2">Brak nadchodzących zapisów. <a href="<?= e(url('zapisz.php')) ?>" style="color:var(--primary); text-decoration:underline;">Zapisz dziecko na zajęcia</a>.</p>
  <?php else: ?>
    <div class="mt-4" style="display:flex; flex-direction:column; gap:8px;">
      <?php foreach ($upcoming as $e): ?>
        <div class="card flex items-center justify-between" style="justify-content:space-between; padding:16px;">
          <div>
            <p style="font-weight:700;"><?= e($e['ct_name']) ?><?= $e['group_name'] ? ' — ' . e($e['group_name']) : '' ?> — <?= e($e['first_name'] . ' ' . $e['last_name']) ?></p>
            <p class="text-muted"><?= $e['next_starts_at'] ? e(format_pl_date($e['next_starts_at'], true, true)) : 'Czeka na przydzielenie grupy' ?></p>
          </div>
          <?php if ($e['status'] === 'WAITLIST'): ?><span class="badge badge-waitlist">Lista rezerwowa</span><?php endif; ?>
          <?php if ($e['status'] === 'PENDING'): ?><span class="badge badge-pending">Oczekuje na potwierdzenie</span><?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
