<?php
require_once __DIR__ . '/includes/bootstrap.php';
$user = require_login('panel-zapisy.php');

$id = (int) ($_GET['id'] ?? 0);
// LEFT JOIN class_groups/class_sessions — świeże zgłoszenie (po rodzaju
// zajęć) nie ma jeszcze ani grupy, ani sesji, dopóki pracownia go nie
// przydzieli w admin-grupy.php; stare zgłoszenia sprzed grup mają tylko
// session_id. class_type_id jest dziś zawsze ustawiony (backfill w seed.php).
$stmt = db()->prepare("SELECT e.*, c.first_name, c.last_name,
        ct.name AS ct_name, ct.color AS ct_color,
        g.name AS group_name, g.day_of_week AS group_day, g.start_time AS group_start, g.end_time AS group_end,
        g.instructor_name AS group_instructor, g.meeting_url AS group_meeting_url,
        cs.starts_at AS legacy_starts_at, cs.ends_at AS legacy_ends_at, cs.title AS legacy_title,
        cs.instructor_name AS legacy_instructor, cs.meeting_url AS legacy_meeting_url
    FROM enrollments e
    JOIN children c ON c.id = e.child_id
    LEFT JOIN class_groups g ON g.id = e.group_id
    LEFT JOIN class_sessions cs ON cs.id = e.session_id
    LEFT JOIN class_types ct ON ct.id = COALESCE(e.class_type_id, g.class_type_id, cs.class_type_id)
    WHERE e.id = ?");
$stmt->execute([$id]);
$e = $stmt->fetch();

if (!$e || (int) $e['parent_id'] !== (int) $user['id']) {
    http_response_code(404);
    $pageTitle = 'Nie znaleziono — INNOVA';
    $notebookTheme = true;
    require __DIR__ . '/includes/layout_top.php';
    echo '<div class="container-sm text-center" style="padding:64px 16px;"><h1>404</h1></div>';
    require __DIR__ . '/includes/layout_bottom.php';
    exit;
}

$pending = $e['status'] === 'PENDING';
$waitlisted = $e['status'] === 'WAITLIST';
$confirmed = $e['status'] === 'CONFIRMED';

$hasGroup = !empty($e['group_id']);
$hasLegacySession = !empty($e['session_id']) && !$hasGroup;
$title = $hasGroup ? $e['group_name'] : ($hasLegacySession ? $e['legacy_title'] : null);
$instructor = $hasGroup ? $e['group_instructor'] : ($hasLegacySession ? $e['legacy_instructor'] : null);
$meetingUrl = $hasGroup ? $e['group_meeting_url'] : ($hasLegacySession ? $e['legacy_meeting_url'] : null);
$schedule = $hasGroup
    ? format_group_schedule((int) $e['group_day'], $e['group_start'], $e['group_end'])
    : ($hasLegacySession ? format_pl_date($e['legacy_starts_at'], true, true) . '–' . h_m($e['legacy_ends_at']) : null);

$pageTitle = 'Potwierdzenie zgłoszenia — INNOVA';
$notebookTheme = true;
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
      Twoje zgłoszenie czeka w puli — sprawdzimy dostępność i dobierzemy właściwą grupę wiekową. Wyślemy e-mail, gdy tylko to zrobimy.
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
    <p class="text-muted mt-2">Dziecko: <?= e($e['first_name'] . ' ' . $e['last_name']) ?></p>
    <?php if ($title): ?>
      <h2 class="mt-2" style="font-size:1.1rem;"><?= e($title) ?></h2>
      <p class="mt-2"><?= e($schedule) ?></p>
      <p class="text-muted">Prowadzący: <?= e($instructor) ?></p>
      <?php if ($confirmed && $meetingUrl): ?>
        <p class="mt-4">Link do zajęć online: <a href="<?= e($meetingUrl) ?>" style="color:var(--primary); text-decoration:underline;"><?= e($meetingUrl) ?></a></p>
      <?php endif; ?>
    <?php else: ?>
      <p class="mt-2 text-muted">Grupa i termin zajęć — wkrótce, po przydzieleniu przez pracownię.</p>
    <?php endif; ?>
  </div>

  <div class="mt-8 flex" style="justify-content:center; gap:12px;">
    <a href="<?= e(url('panel-zapisy.php')) ?>" class="btn btn-primary">Moje zapisy</a>
    <a href="<?= e(url('zapisz.php')) ?>" class="btn btn-outline">Zapisz na kolejne zajęcia</a>
  </div>
</div>
<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
