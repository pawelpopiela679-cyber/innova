<?php
require_once __DIR__ . '/includes/bootstrap.php';

$user = require_staff();
$org = require_org();
$plan = org_plan($org);
$welcome = !empty($_GET['welcome']);

$stmt = db()->prepare("SELECT COUNT(*) c FROM enrollments WHERE org_id = ? AND status = 'PENDING'");
$stmt->execute([$org['id']]);
$pendingCount = (int) $stmt->fetch()['c'];

$stmt = db()->prepare("SELECT COUNT(*) c FROM enrollments WHERE org_id = ? AND status = 'CONFIRMED' AND payment_status = 'UNPAID'");
$stmt->execute([$org['id']]);
$unpaidCount = (int) $stmt->fetch()['c'];

$stmt = db()->prepare("SELECT COUNT(DISTINCT child_id) c FROM enrollments WHERE org_id = ? AND status = 'CONFIRMED'");
$stmt->execute([$org['id']]);
$activeChildren = (int) $stmt->fetch()['c'];

$weekStart = (new DateTime('monday this week'))->format('Y-m-d H:i:s');
$weekEnd = (new DateTime('monday next week'))->format('Y-m-d H:i:s');
$stmt = db()->prepare('SELECT COUNT(*) c FROM class_sessions WHERE org_id = ? AND starts_at >= ? AND starts_at < ?');
$stmt->execute([$org['id'], $weekStart, $weekEnd]);
$sessionsThisWeek = (int) $stmt->fetch()['c'];

$instructorCount = org_instructor_count((int) $org['id']);

$stmt = db()->prepare("SELECT e.*, cs.title, cs.starts_at, ct.name AS ct_name, c.first_name, c.last_name, u.name AS parent_name
    FROM enrollments e
    JOIN class_sessions cs ON cs.id = e.session_id
    JOIN class_types ct ON ct.id = cs.class_type_id
    JOIN children c ON c.id = e.child_id
    JOIN users u ON u.id = e.parent_id
    WHERE e.org_id = ? AND e.status = 'PENDING'
    ORDER BY e.created_at ASC LIMIT 6");
$stmt->execute([$org['id']]);
$recentPending = $stmt->fetchAll();

$pageTitle = 'Panel — ' . $org['name'];
require __DIR__ . '/includes/layout_top.php';
?>
<div class="container section">
  <?php if ($welcome): ?>
    <p class="alert alert-success reveal">Witaj w ZapisyPro! Organizacja „<?= e($org['name']) ?>” jest gotowa —
      dodaj pierwsze zajęcia w zakładce „Zajęcia”, a link do rejestracji dla rodziców to:
      <code><?= e(APP_URL . url('rejestracja.php?org=' . $org['slug'])) ?></code></p>
  <?php endif; ?>

  <?php if ($org['status'] === 'TRIAL'): ?>
    <div class="banner reveal">
      🎁 Okres próbny planu <strong><?= e($plan['name'] ?? '—') ?></strong> — aktywny do
      <strong><?= e(format_pl_date($org['trial_ends_at'])) ?></strong>.
      <a href="<?= e(url('abonament.php')) ?>">Zobacz abonament →</a>
    </div>
  <?php elseif (!org_is_active($org)): ?>
    <div class="banner warn reveal">⚠️ Subskrypcja nieaktywna — skontaktuj się z operatorem ZapisyPro, żeby wznowić dostęp.</div>
  <?php endif; ?>

  <h1 class="section-title">Panel — <?= e($org['name']) ?></h1>

  <div class="stat-grid mt-6">
    <div class="stat-card reveal">
      <div class="stat-value" data-count="<?= $pendingCount ?>">0</div>
      <div class="stat-label">Zgłoszenia do potwierdzenia</div>
      <a href="<?= e(url('zapisy.php')) ?>" class="stat-link">Zobacz →</a>
    </div>
    <div class="stat-card reveal" style="animation-delay:60ms;">
      <div class="stat-value" data-count="<?= $unpaidCount ?>">0</div>
      <div class="stat-label">Nieopłacone zapisy</div>
      <a href="<?= e(url('zapisy.php?filter=unpaid')) ?>" class="stat-link">Zobacz →</a>
    </div>
    <div class="stat-card reveal" style="animation-delay:120ms;">
      <div class="stat-value" data-count="<?= $sessionsThisWeek ?>">0</div>
      <div class="stat-label">Zajęcia w tym tygodniu</div>
      <a href="<?= e(url('kalendarz.php')) ?>" class="stat-link">Kalendarz →</a>
    </div>
    <div class="stat-card reveal" style="animation-delay:180ms;">
      <div class="stat-value" data-count="<?= $activeChildren ?>">0</div>
      <div class="stat-label">Zapisanych dzieci<?= $plan ? ' / limit ' . ($plan['max_students'] >= 999999 ? '∞' : (int) $plan['max_students']) : '' ?></div>
      <a href="<?= e(url('abonament.php')) ?>" class="stat-link">Abonament →</a>
    </div>
  </div>

  <h2 class="mt-8">Najnowsze zgłoszenia</h2>
  <?php if (!$recentPending): ?>
    <p class="text-muted mt-2">Brak oczekujących zgłoszeń — na bieżąco! 🎉</p>
  <?php else: ?>
    <div class="table-wrap mt-4 reveal">
      <table class="data-table">
        <thead><tr><th>Dziecko</th><th>Zajęcia</th><th>Termin</th><th>Rodzic</th></tr></thead>
        <tbody>
          <?php foreach ($recentPending as $p): ?>
            <tr>
              <td><?= e($p['first_name'] . ' ' . $p['last_name']) ?></td>
              <td><?= e($p['ct_name']) ?> — <?= e($p['title']) ?></td>
              <td><?= e(format_pl_date($p['starts_at'], false, true)) ?></td>
              <td><?= e($p['parent_name']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <a href="<?= e(url('zapisy.php')) ?>" class="btn btn-primary mt-4">Przejdź do wszystkich zgłoszeń</a>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
