<?php
require_once __DIR__ . '/includes/bootstrap.php';
$user = require_staff();

function fetch_enrollment_full(int $id): ?array
{
    $stmt = db()->prepare("SELECT e.*, c.first_name, c.last_name, c.birth_date,
            u.name AS parent_name, u.email AS parent_email, u.phone AS parent_phone,
            cs.title AS session_title, cs.starts_at, cs.ends_at, cs.capacity, cs.instructor_name, cs.meeting_url,
            cs.class_type_id, ct.name AS ct_name
        FROM enrollments e
        JOIN children c ON c.id = e.child_id
        JOIN users u ON u.id = e.parent_id
        JOIN class_sessions cs ON cs.id = e.session_id
        JOIN class_types ct ON ct.id = cs.class_type_id
        WHERE e.id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['_action'] ?? '';
    $enrollmentId = (int) ($_POST['enrollmentId'] ?? 0);
    $e = fetch_enrollment_full($enrollmentId);

    if (!$e) {
        redirect_with('admin-zapisy.php', ['error' => 'Nie znaleziono zgłoszenia.']);
    }

    if ($action === 'confirm') {
        $targetSessionId = (int) ($_POST['sessionId'] ?? $e['session_id']);
        $target = db()->prepare('SELECT * FROM class_sessions WHERE id = ?');
        $target->execute([$targetSessionId]);
        $target = $target->fetch();
        if (!$target || (int) $target['class_type_id'] !== (int) $e['class_type_id']) {
            redirect_with('admin-zapisy.php', ['error' => 'Wybrana grupa nie należy do tego samego rodzaju zajęć.']);
        }

        // Limit miejsc obowiązuje też przy ręcznym potwierdzaniu przez admina —
        // bez tego sprawdzenia kolejne kliknięcia "Potwierdź" mogłyby po cichu
        // przepełnić grupę ponad capacity (np. 11/10). Zgłoszenie, które jest
        // już CONFIRMED w tej samej grupie (zmiana np. tylko innych pól), się
        // nie liczy podwójnie do własnego limitu.
        $countStmt = db()->prepare(
            "SELECT COUNT(*) c FROM enrollments WHERE session_id = ? AND status = 'CONFIRMED' AND id != ?"
        );
        $countStmt->execute([$targetSessionId, $enrollmentId]);
        $confirmedCount = (int) $countStmt->fetch()['c'];
        if ($confirmedCount >= (int) $target['capacity']) {
            redirect_with('admin-zapisy.php', ['error' => 'Wybrana grupa jest już pełna (' . $confirmedCount . '/' . (int) $target['capacity'] . ') — przypisz na listę rezerwową albo wybierz inną grupę.']);
        }

        db()->prepare("UPDATE enrollments SET status='CONFIRMED', session_id=?, confirmed_at=CURRENT_TIMESTAMP WHERE id=?")
            ->execute([$targetSessionId, $enrollmentId]);

        send_enrollment_confirmation_email([
            'parentEmail' => $e['parent_email'], 'parentName' => $e['parent_name'],
            'childName' => $e['first_name'] . ' ' . $e['last_name'],
            'classTypeName' => $e['ct_name'], 'sessionTitle' => $target['title'],
            'startsAt' => $target['starts_at'], 'endsAt' => $target['ends_at'],
            'instructorName' => $target['instructor_name'], 'meetingUrl' => $target['meeting_url'],
            'waitlisted' => false,
        ]);
        redirect('admin-zapisy.php?confirmed=1');
    }

    if ($action === 'waitlist') {
        db()->prepare("UPDATE enrollments SET status='WAITLIST', confirmed_at=NULL WHERE id=?")->execute([$enrollmentId]);
        send_enrollment_confirmation_email([
            'parentEmail' => $e['parent_email'], 'parentName' => $e['parent_name'],
            'childName' => $e['first_name'] . ' ' . $e['last_name'],
            'classTypeName' => $e['ct_name'], 'sessionTitle' => $e['session_title'],
            'startsAt' => $e['starts_at'], 'endsAt' => $e['ends_at'],
            'instructorName' => $e['instructor_name'], 'meetingUrl' => $e['meeting_url'],
            'waitlisted' => true,
        ]);
        redirect('admin-zapisy.php?waitlisted=1');
    }

    if ($action === 'decline') {
        $wasConfirmed = $e['status'] === 'CONFIRMED';
        db()->prepare("UPDATE enrollments SET status='CANCELED', canceled_at=CURRENT_TIMESTAMP WHERE id=?")->execute([$enrollmentId]);
        if ($wasConfirmed) {
            promote_next_waitlisted((int) $e['session_id']);
        }
        send_enrollment_declined_email([
            'parentEmail' => $e['parent_email'], 'parentName' => $e['parent_name'],
            'childName' => $e['first_name'] . ' ' . $e['last_name'],
            'classTypeName' => $e['ct_name'], 'sessionTitle' => $e['session_title'],
        ]);
        redirect('admin-zapisy.php?declined=1');
    }
}

$filters = ['PENDING', 'CONFIRMED', 'WAITLIST', 'CANCELED', 'ALL'];
$filterLabels = [
    'PENDING' => 'Oczekujące na potwierdzenie', 'CONFIRMED' => 'Potwierdzone',
    'WAITLIST' => 'Lista rezerwowa', 'CANCELED' => 'Anulowane', 'ALL' => 'Wszystkie',
];
$filter = in_array($_GET['status'] ?? '', $filters, true) ? $_GET['status'] : 'PENDING';

$sql = "SELECT e.*, c.first_name, c.last_name, c.birth_date,
            u.name AS parent_name, u.email AS parent_email, u.phone AS parent_phone,
            cs.title AS session_title, cs.starts_at, cs.ends_at, cs.capacity, cs.class_type_id,
            ct.name AS ct_name, ct.color AS ct_color,
            (SELECT COUNT(*) FROM enrollments e2 WHERE e2.session_id = cs.id AND e2.status = 'CONFIRMED') AS confirmed_in_current
        FROM enrollments e
        JOIN children c ON c.id = e.child_id
        JOIN users u ON u.id = e.parent_id
        JOIN class_sessions cs ON cs.id = e.session_id
        JOIN class_types ct ON ct.id = cs.class_type_id";
$params = [];
if ($filter !== 'ALL') {
    $sql .= ' WHERE e.status = ?';
    $params[] = $filter;
}
$sql .= ' ORDER BY e.created_at ASC';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$enrollments = $stmt->fetchAll();

// Grupy (terminy) tego samego rodzaju zajęć, do rozwijanej listy "przypisz inną grupę".
$allSessions = db()->query("SELECT * FROM class_sessions WHERE status = 'SCHEDULED' ORDER BY starts_at ASC")->fetchAll();
$sessionsByClassType = [];
foreach ($allSessions as $s) {
    $sessionsByClassType[$s['class_type_id']][] = $s;
}

$statusLabel = ['PENDING' => 'Oczekuje', 'CONFIRMED' => 'Potwierdzony', 'WAITLIST' => 'Lista rezerwowa', 'CANCELED' => 'Anulowany'];
$statusBadge = ['PENDING' => 'badge-pending', 'CONFIRMED' => 'badge-confirmed', 'WAITLIST' => 'badge-waitlist', 'CANCELED' => 'badge-canceled'];

$pageTitle = 'Zgłoszenia i zapisy — INNOVA';
require __DIR__ . '/includes/layout_top.php';
?>
<div class="container" style="padding:40px 16px;">
  <?php include __DIR__ . '/includes/partials/admin-nav.php'; ?>

  <h1 style="font-size:1.6rem;">Zgłoszenia i zapisy</h1>
  <p class="text-muted mt-2">Sprawdź wiek dziecka, potwierdź zgłoszenie do wybranej grupy (lub przypisz inną, jeśli wiek pasuje lepiej gdzie indziej) — grupy max. 10 dzieci.</p>

  <?php if (isset($_GET['error'])): ?><p class="alert alert-error"><?= e($_GET['error']) ?></p><?php endif; ?>
  <?php if (isset($_GET['confirmed'])): ?><p class="alert alert-success">Zgłoszenie potwierdzone, rodzic dostał e-mail.</p><?php endif; ?>
  <?php if (isset($_GET['waitlisted'])): ?><p class="alert alert-warning">Przeniesiono na listę rezerwową.</p><?php endif; ?>
  <?php if (isset($_GET['declined'])): ?><p class="alert alert-info">Zgłoszenie odrzucone/anulowane.</p><?php endif; ?>

  <div class="flex flex-wrap gap-2 mt-6">
    <?php foreach ($filters as $f): ?>
      <a href="<?= e(url('admin-zapisy.php?status=' . $f)) ?>" class="btn btn-sm <?= $f === $filter ? 'btn-primary' : 'btn-outline' ?>"><?= $filterLabels[$f] ?></a>
    <?php endforeach; ?>
  </div>

  <div class="mt-6" style="display:flex; flex-direction:column; gap:16px;">
    <?php if (!$enrollments): ?>
      <p class="card text-center text-muted" style="border-style:dashed;">Brak zgłoszeń w tej kategorii.</p>
    <?php endif; ?>

    <?php foreach ($enrollments as $e):
        $age = calculate_age($e['birth_date']);
        $groupOptions = $sessionsByClassType[$e['class_type_id']] ?? [];
    ?>
      <div class="card">
        <div class="flex items-center gap-2">
          <span class="dot" style="background:<?= e($e['ct_color']) ?>;"></span>
          <span class="text-muted" style="font-size:0.75rem; font-weight:700; text-transform:uppercase;"><?= e($e['ct_name']) ?></span>
          <span class="badge <?= $statusBadge[$e['status']] ?>"><?= $statusLabel[$e['status']] ?></span>
        </div>
        <p class="mt-2" style="font-weight:700;"><?= e($e['first_name'] . ' ' . $e['last_name']) ?> <span class="text-muted" style="font-weight:400;">— <?= $age ?> lat</span></p>
        <p class="text-muted">Rodzic: <?= e($e['parent_name']) ?> · <?= e($e['parent_email']) ?><?= $e['parent_phone'] ? ' · ' . e($e['parent_phone']) : '' ?></p>
        <p class="mt-2">Zgłoszona grupa: <strong><?= e($e['session_title']) ?></strong> — <?= e(format_pl_date($e['starts_at'], true, true)) ?> · zajętość <?= (int) $e['confirmed_in_current'] ?>/<?= (int) $e['capacity'] ?></p>

        <?php if ($e['status'] !== 'CANCELED'): ?>
          <div class="flex flex-wrap items-center gap-2 mt-4" style="border-top:1px solid var(--border); padding-top:16px;">
            <form method="post" class="flex flex-wrap items-center gap-2">
              <?= csrf_field() ?>
              <input type="hidden" name="_action" value="confirm">
              <input type="hidden" name="enrollmentId" value="<?= (int) $e['id'] ?>">
              <select name="sessionId" style="width:auto;">
                <?php foreach ($groupOptions as $s): ?>
                  <option value="<?= (int) $s['id'] ?>" <?= (int) $s['id'] === (int) $e['session_id'] ? 'selected' : '' ?>>
                    <?= e($s['title']) ?> — <?= (new DateTime($s['starts_at']))->format('d.m H:i') ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <button type="submit" class="btn btn-sm" style="background:var(--sage); color:#fff;">Potwierdź do tej grupy</button>
            </form>
            <form method="post">
              <?= csrf_field() ?>
              <input type="hidden" name="_action" value="waitlist">
              <input type="hidden" name="enrollmentId" value="<?= (int) $e['id'] ?>">
              <button type="submit" class="btn btn-outline btn-sm">Lista rezerwowa</button>
            </form>
            <form method="post">
              <?= csrf_field() ?>
              <input type="hidden" name="_action" value="decline">
              <input type="hidden" name="enrollmentId" value="<?= (int) $e['id'] ?>">
              <button type="submit" class="btn btn-danger btn-sm">Odrzuć / anuluj</button>
            </form>
          </div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
