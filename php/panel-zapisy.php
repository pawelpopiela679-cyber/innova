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
        if ($wasConfirmed) {
            promote_next_waitlisted((int) $e['session_id']);
        }
    }
    redirect('panel-zapisy.php?canceled=1');
}

$stmt = db()->prepare("SELECT e.*, c.first_name, c.last_name, cs.starts_at, cs.title AS session_title, ct.name AS ct_name, ct.color AS ct_color
    FROM enrollments e
    JOIN children c ON c.id = e.child_id
    JOIN class_sessions cs ON cs.id = e.session_id
    JOIN class_types ct ON ct.id = cs.class_type_id
    WHERE e.parent_id = ? ORDER BY cs.starts_at DESC");
$stmt->execute([$user['id']]);
$enrollments = $stmt->fetchAll();

$statusLabel = ['PENDING' => 'Oczekuje na potwierdzenie', 'CONFIRMED' => 'Potwierdzony', 'WAITLIST' => 'Lista rezerwowa', 'CANCELED' => 'Anulowany'];
$statusBadge = ['PENDING' => 'badge-pending', 'CONFIRMED' => 'badge-confirmed', 'WAITLIST' => 'badge-waitlist', 'CANCELED' => 'badge-canceled'];

$pageTitle = 'Moje zapisy — INNOVA';
require __DIR__ . '/includes/layout_top.php';
?>
<div class="container-md" style="padding:40px 16px;">
  <?php include __DIR__ . '/includes/partials/panel-nav.php'; ?>
  <h1 style="font-size:1.6rem;">Moje zapisy</h1>

  <?php if (isset($_GET['info'])): ?><p class="alert alert-warning"><?= e($_GET['info']) ?></p><?php endif; ?>
  <?php if (isset($_GET['canceled'])): ?><p class="alert alert-success">Zapis został anulowany.</p><?php endif; ?>

  <?php if (!$enrollments): ?>
    <p class="text-muted mt-4">Brak zapisów. <a href="<?= e(url('kalendarz.php')) ?>" style="color:var(--primary); text-decoration:underline;">Przeglądaj kalendarz zajęć</a>.</p>
  <?php else: ?>
    <div class="mt-6" style="display:flex; flex-direction:column; gap:12px;">
      <?php foreach ($enrollments as $e):
          $isPast = strtotime($e['starts_at']) < time();
      ?>
        <div class="card flex items-center justify-between" style="justify-content:space-between; flex-wrap:wrap; gap:12px;">
          <div>
            <div class="flex items-center gap-2">
              <span class="dot" style="background:<?= e($e['ct_color']) ?>;"></span>
              <strong><?= e($e['ct_name']) ?> — <?= e($e['session_title']) ?></strong>
              <span class="badge <?= $statusBadge[$e['status']] ?>"><?= $statusLabel[$e['status']] ?></span>
            </div>
            <p class="text-muted mt-2"><?= e($e['first_name'] . ' ' . $e['last_name']) ?> · <?= e(format_pl_date($e['starts_at'], false, true)) ?></p>
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
