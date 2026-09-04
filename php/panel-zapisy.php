<?php
require_once __DIR__ . '/includes/bootstrap.php';
$user = require_login('panel-zapisy.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_action'] ?? '') === 'cancel') {
    csrf_check();
    $enrollmentId = (int) ($_POST['enrollmentId'] ?? 0);
    $e = db()->prepare('SELECT * FROM enrollments WHERE id = ?');
    $e->execute([$enrollmentId]);
    $e = $e->fetch();
    if ($e && (int) $e['parent_id'] === (int) $user['id'] && $e['status'] !== 'CANCELED') {
        $wasConfirmed = $e['status'] === 'CONFIRMED';
        db()->prepare("UPDATE enrollments SET status='CANCELED', canceled_at=CURRENT_TIMESTAMP WHERE id=?")->execute([$enrollmentId]);
        if ($wasConfirmed && $e['group_id']) {
            promote_next_waitlisted((int) $e['group_id']);
        }
    }
    redirect('panel-zapisy.php?canceled=1');
}

// LEFT JOIN — zgłoszenie czekające w puli (jeszcze bez grupy) nie ma ani
// group_id, ani (dziś) session_id; stare zgłoszenia sprzed grup mają tylko
// session_id. class_type_id jest dziś zawsze ustawiony.
$stmt = db()->prepare("SELECT e.*, c.first_name, c.last_name, ct.name AS ct_name, ct.color AS ct_color,
        g.name AS group_name, g.day_of_week AS group_day, g.start_time AS group_start, g.end_time AS group_end,
        cs.starts_at AS legacy_starts_at, cs.title AS legacy_title
    FROM enrollments e
    JOIN children c ON c.id = e.child_id
    LEFT JOIN class_groups g ON g.id = e.group_id
    LEFT JOIN class_sessions cs ON cs.id = e.session_id
    LEFT JOIN class_types ct ON ct.id = COALESCE(e.class_type_id, g.class_type_id, cs.class_type_id)
    WHERE e.parent_id = ?
    ORDER BY e.created_at DESC");
$stmt->execute([$user['id']]);
$enrollments = $stmt->fetchAll();

$statusLabel = ['PENDING' => 'Oczekuje na potwierdzenie', 'CONFIRMED' => 'Potwierdzony', 'WAITLIST' => 'Lista rezerwowa', 'CANCELED' => 'Anulowany'];
$statusBadge = ['PENDING' => 'badge-pending', 'CONFIRMED' => 'badge-confirmed', 'WAITLIST' => 'badge-waitlist', 'CANCELED' => 'badge-canceled'];

$pageTitle = 'Moje zapisy — INNOVA';
$notebookTheme = true;
require __DIR__ . '/includes/layout_top.php';
?>
<div class="container-md" style="padding:40px 16px;">
  <?php include __DIR__ . '/includes/partials/panel-nav.php'; ?>
  <h1 style="font-size:1.6rem;">Moje zapisy</h1>

  <?php if (isset($_GET['info'])): ?><p class="alert alert-warning"><?= e($_GET['info']) ?></p><?php endif; ?>
  <?php if (isset($_GET['canceled'])): ?><p class="alert alert-success">Zapis został anulowany.</p><?php endif; ?>

  <?php if (!$enrollments): ?>
    <p class="text-muted mt-4">Brak zapisów. <a href="<?= e(url('zapisz.php')) ?>" style="color:var(--primary); text-decoration:underline;">Zapisz dziecko na zajęcia</a>.</p>
  <?php else: ?>
    <div class="mt-6" style="display:flex; flex-direction:column; gap:12px;">
      <?php foreach ($enrollments as $e):
          $groupTitle = $e['group_name'] ?? $e['legacy_title'];
          $schedule = $e['group_name']
              ? format_group_schedule((int) $e['group_day'], $e['group_start'], $e['group_end'])
              : ($e['legacy_starts_at'] ? format_pl_date($e['legacy_starts_at'], false, true) : null);
          $isPast = $e['legacy_starts_at'] && strtotime($e['legacy_starts_at']) < time();
      ?>
        <div class="card flex items-center justify-between" style="justify-content:space-between; flex-wrap:wrap; gap:12px;">
          <div>
            <div class="flex items-center gap-2">
              <span class="dot" style="background:<?= e($e['ct_color']) ?>;"></span>
              <strong><?= e($e['ct_name']) ?><?= $groupTitle ? ' — ' . e($groupTitle) : '' ?></strong>
              <span class="badge <?= $statusBadge[$e['status']] ?>"><?= $statusLabel[$e['status']] ?></span>
            </div>
            <p class="text-muted mt-2">
              <?= e($e['first_name'] . ' ' . $e['last_name']) ?>
              <?= $schedule ? ' · ' . e($schedule) : ' · czeka na przydzielenie grupy' ?>
            </p>
          </div>
          <?php if ($e['status'] !== 'CANCELED' && !$isPast): ?>
            <form method="post">
              <?= csrf_field() ?>
              <input type="hidden" name="_action" value="cancel">
              <input type="hidden" name="enrollmentId" value="<?= (int) $e['id'] ?>">
              <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Na pewno anulować zapis?')">Anuluj zapis</button>
            </form>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
