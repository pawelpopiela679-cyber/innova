<?php
require_once __DIR__ . '/includes/bootstrap.php';
$user = require_login('panel-zapisy.php');

$id = (int) ($_GET['id'] ?? 0);
$stmt = db()->prepare("SELECT e.*, c.first_name, c.last_name, cs.starts_at, cs.ends_at, cs.title AS session_title,
        cs.instructor_name, cs.meeting_url, ct.name AS ct_name, ct.color AS ct_color
    FROM enrollments e
    JOIN children c ON c.id = e.child_id
    JOIN class_sessions cs ON cs.id = e.session_id
    JOIN class_types ct ON ct.id = cs.class_type_id
    WHERE e.id = ?");
$stmt->execute([$id]);
$e = $stmt->fetch();

if (!$e || (int) $e['parent_id'] !== (int) $user['id']) {
    http_response_code(404);
    $pageTitle = 'Nie znaleziono — INNOVA';
    require __DIR__ . '/includes/layout_top.php';
    echo '<div class="container-sm text-center" style="padding:64px 16px;"><h1>404</h1></div>';
    require __DIR__ . '/includes/layout_bottom.php';
    exit;
}

$pending = $e['status'] === 'PENDING';
$waitlisted = $e['status'] === 'WAITLIST';
$confirmed = $e['status'] === 'CONFIRMED';

$pageTitle = 'Potwierdzenie zgłoszenia — INNOVA';
require __DIR__ . '/includes/layout_top.php';
?>
<div class="container-sm text-center" style="padding:64px 16px;">
  <div style="width:64px; height:64px; margin:0 auto; border-radius:999px; background:#dff5e8; display:flex; align-items:center; justify-content:center; font-size:1.8rem;">
    <?= $pending ? '📩' : ($waitlisted ? '⏳' : '✅') ?>
  </div>
  <h1 class="mt-4" style="font-size:1.6rem;">
    <?= $pending ? 'Zgłoszenie przyjęte!' : ($waitlisted ? 'Dodano do listy rezerwowej' : 'Zapis potwierdzony!') ?>
  </h1>
  <p class="text-muted mt-2">
    <?php if ($pending): ?>
      Twoje zgłoszenie oczekuje na potwierdzenie przez pracownię — sprawdzimy dostępność i dobierzemy właściwą grupę wiekową. Wyślemy e-mail, gdy tylko to zrobimy.
    <?php elseif ($waitlisted): ?>
      Grupa jest obecnie pełna. Odezwiemy się, jeśli zwolni się miejsce.
    <?php else: ?>
      Wysłaliśmy potwierdzenie na Twój e-mail. Poinformowaliśmy również prowadzącego zajęcia.
    <?php endif; ?>
  </p>

  <div class="card mt-6 text-left">
    <div class="flex items-center gap-2">
      <span class="dot" style="background:<?= e($e['ct_color']) ?>;"></span>
      <span class="text-muted" style="font-weight:700; font-size:0.8rem; text-transform:uppercase;"><?= e($e['ct_name']) ?></span>
    </div>
    <h2 class="mt-2" style="font-size:1.1rem;"><?= e($e['session_title']) ?></h2>
    <p class="mt-2"><?= $pending ? 'Wybrany termin: ' : '' ?><?= e(format_pl_date($e['starts_at'], true, true)) ?>–<?= h_m($e['ends_at']) ?></p>
    <p class="text-muted mt-2">Dziecko: <?= e($e['first_name'] . ' ' . $e['last_name']) ?></p>
    <p class="text-muted">Prowadzący: <?= e($e['instructor_name']) ?></p>
    <?php if ($confirmed && $e['meeting_url']): ?>
      <p class="mt-4">Link do zajęć online: <a href="<?= e($e['meeting_url']) ?>" style="color:var(--primary); text-decoration:underline;"><?= e($e['meeting_url']) ?></a></p>
    <?php endif; ?>
  </div>

  <div class="mt-8 flex" style="justify-content:center; gap:12px;">
    <a href="<?= e(url('panel-zapisy.php')) ?>" class="btn btn-primary">Moje zapisy</a>
    <a href="<?= e(url('kalendarz.php')) ?>" class="btn btn-outline">Zapisz na kolejne zajęcia</a>
  </div>
</div>
<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
