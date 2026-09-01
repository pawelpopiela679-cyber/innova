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

// --- Przychód w tym miesiącu (i miesiąc wcześniej, do porównania) ---
$monthStart = (new DateTime('first day of this month'))->format('Y-m-d 00:00:00');
$monthEnd = (new DateTime('first day of next month'))->format('Y-m-d 00:00:00');
$stmt = db()->prepare("SELECT COALESCE(SUM(amount_due_cents), 0) c FROM enrollments WHERE org_id = ? AND payment_status = 'PAID' AND paid_at >= ? AND paid_at < ?");
$stmt->execute([$org['id'], $monthStart, $monthEnd]);
$revenueThisMonthCents = (int) $stmt->fetch()['c'];

$prevMonthStart = (new DateTime('first day of last month'))->format('Y-m-d 00:00:00');
$stmt = db()->prepare("SELECT COALESCE(SUM(amount_due_cents), 0) c FROM enrollments WHERE org_id = ? AND payment_status = 'PAID' AND paid_at >= ? AND paid_at < ?");
$stmt->execute([$org['id'], $prevMonthStart, $monthStart]);
$revenueLastMonthCents = (int) $stmt->fetch()['c'];

// --- Zajęcia dziś ---
$todayStart = (new DateTime('today'))->format('Y-m-d H:i:s');
$todayEnd = (new DateTime('tomorrow'))->format('Y-m-d H:i:s');
$stmt = db()->prepare("SELECT cs.*, ct.name AS ct_name, ct.color AS ct_color,
        (SELECT COUNT(*) FROM enrollments e WHERE e.session_id = cs.id AND e.status = 'CONFIRMED') AS confirmed_count
    FROM class_sessions cs
    JOIN class_types ct ON ct.id = cs.class_type_id
    WHERE cs.org_id = ? AND cs.status = 'SCHEDULED' AND cs.starts_at >= ? AND cs.starts_at < ?
    ORDER BY cs.starts_at ASC");
$stmt->execute([$org['id'], $todayStart, $todayEnd]);
$todaySessions = $stmt->fetchAll();

// --- Zaległości: potwierdzone, nieopłacone zapisy ---
$stmt = db()->prepare("SELECT e.*, cs.title, cs.starts_at, ct.name AS ct_name, c.first_name, c.last_name,
        u.name AS parent_name, u.email AS parent_email, u.phone AS parent_phone
    FROM enrollments e
    JOIN class_sessions cs ON cs.id = e.session_id
    JOIN class_types ct ON ct.id = cs.class_type_id
    JOIN children c ON c.id = e.child_id
    JOIN users u ON u.id = e.parent_id
    WHERE e.org_id = ? AND e.status = 'CONFIRMED' AND e.payment_status = 'UNPAID'
    ORDER BY cs.starts_at ASC");
$stmt->execute([$org['id']]);
$arrears = $stmt->fetchAll();
$arrearsTotalCents = array_sum(array_column($arrears, 'amount_due_cents'));

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
    <p class="alert alert-success reveal">Witaj w InnovaGo! Organizacja „<?= e($org['name']) ?>” jest gotowa —
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
    <div class="banner warn reveal">⚠️ Subskrypcja nieaktywna — skontaktuj się z operatorem InnovaGo, żeby wznowić dostęp.</div>
  <?php endif; ?>

  <h1 class="section-title">Panel — <?= e($org['name']) ?></h1>

  <div class="stat-grid mt-6">
    <div class="stat-card reveal">
      <div class="stat-value"><?= number_format($revenueThisMonthCents / 100, 0, ',', ' ') ?> zł</div>
      <div class="stat-label">
        Przychód w tym miesiącu
        <?php if ($revenueLastMonthCents > 0):
          $deltaPct = round((($revenueThisMonthCents - $revenueLastMonthCents) / $revenueLastMonthCents) * 100);
        ?>
          <span class="<?= $deltaPct >= 0 ? 'trend-up' : 'trend-down' ?>"><?= $deltaPct >= 0 ? '▲' : '▼' ?> <?= abs($deltaPct) ?>% vs poprz. mies.</span>
        <?php endif; ?>
      </div>
      <a href="<?= e(url('raporty.php')) ?>" class="stat-link">Raporty →</a>
    </div>
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
                <div class="text-muted"><?= h_m($s['starts_at']) ?>–<?= h_m($s['ends_at']) ?> · <?= e($s['instructor_name']) ?></div>
                <?php if ($s['meeting_url']): ?><div style="font-size:.78rem;"><a href="<?= e($s['meeting_url']) ?>" target="_blank" rel="noopener">🔗 dołącz online</a></div><?php endif; ?>
              </div>
              <div class="agenda-status <?= (int) $s['confirmed_count'] >= (int) $s['capacity'] ? 'full' : 'ok' ?>"><?= (int) $s['confirmed_count'] ?>/<?= (int) $s['capacity'] ?></div>
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
                <div class="enroll-title"><?= e($a['first_name'] . ' ' . $a['last_name']) ?> <span class="text-muted">— <?= e($a['ct_name']) ?></span></div>
                <div class="text-muted" style="font-size:.8rem;"><?= e($a['parent_name']) ?><?= $a['parent_phone'] ? ' · ' . e($a['parent_phone']) : '' ?> · zajęcia <?= e(format_pl_date($a['starts_at'])) ?></div>
              </div>
              <div class="enroll-badges"><span class="badge badge-pending"><?= $a['amount_due_cents'] ? format_money((int) $a['amount_due_cents']) : 'Do opłacenia' ?></span></div>
              <div class="enroll-actions">
                <form method="post" action="<?= e(url('zapisy.php')) ?>"><?= csrf_field() ?><input type="hidden" name="_action" value="mark_paid"><input type="hidden" name="id" value="<?= $a['id'] ?>"><button class="btn btn-primary btn-sm">Oznacz jako opłacone</button></form>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
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
