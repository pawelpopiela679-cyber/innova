<?php
require_once __DIR__ . '/includes/bootstrap.php';

$error = null;

// --- Zgłoszenie chęci zapisu (odpowiednik enrollAction) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_action'] ?? '') === 'enroll') {
    csrf_check();
    $user = current_user();
    if (!$user) {
        redirect('logowanie.php?next=' . urlencode(url('kalendarz.php')));
    }
    $sessionId = (int) ($_POST['sessionId'] ?? 0);
    $childId = (int) ($_POST['childId'] ?? 0);

    $child = db()->prepare('SELECT * FROM children WHERE id = ?');
    $child->execute([$childId]);
    $child = $child->fetch();

    if (!$child || (int) $child['parent_id'] !== (int) $user['id']) {
        redirect_with('kalendarz.php', ['error' => 'To nie jest Twoje dziecko.']);
    }

    $cs = db()->prepare('SELECT cs.*, ct.name AS ct_name FROM class_sessions cs JOIN class_types ct ON ct.id = cs.class_type_id WHERE cs.id = ?');
    $cs->execute([$sessionId]);
    $cs = $cs->fetch();

    if (!$cs || $cs['status'] !== 'SCHEDULED') {
        redirect_with('kalendarz.php', ['error' => 'Te zajęcia nie są już dostępne.']);
    }

    $already = db()->prepare('SELECT * FROM enrollments WHERE session_id = ? AND child_id = ?');
    $already->execute([$sessionId, $childId]);
    $already = $already->fetch();

    if ($already && $already['status'] !== 'CANCELED') {
        redirect_with('panel-zapisy.php', ['info' => 'Dziecko ma już zgłoszenie na te zajęcia.']);
    }

    if ($already) {
        db()->prepare("UPDATE enrollments SET status='PENDING', canceled_at=NULL, confirmed_at=NULL WHERE id=?")->execute([$already['id']]);
        $enrollmentId = (int) $already['id'];
    } else {
        db()->prepare('INSERT INTO enrollments (session_id, child_id, parent_id, status) VALUES (?,?,?,?)')
            ->execute([$sessionId, $childId, $user['id'], 'PENDING']);
        $enrollmentId = db_last_id(db());
    }

    $parent = db()->prepare('SELECT * FROM users WHERE id = ?');
    $parent->execute([$user['id']]);
    $parent = $parent->fetch();

    $confirmedCount = (int) db()->query("SELECT COUNT(*) c FROM enrollments WHERE session_id = $sessionId AND status = 'CONFIRMED'")->fetch()['c'];

    send_enrollment_pending_email([
        'parentEmail' => $parent['email'], 'parentName' => $parent['name'],
        'childName' => $child['first_name'] . ' ' . $child['last_name'],
        'classTypeName' => $cs['ct_name'], 'sessionTitle' => $cs['title'],
        'startsAt' => $cs['starts_at'], 'endsAt' => $cs['ends_at'],
    ]);
    send_studio_new_signup_notification([
        'childName' => $child['first_name'] . ' ' . $child['last_name'], 'childBirthDate' => $child['birth_date'],
        'parentName' => $parent['name'], 'parentEmail' => $parent['email'], 'parentPhone' => $parent['phone'],
        'classTypeName' => $cs['ct_name'], 'sessionTitle' => $cs['title'],
        'startsAt' => $cs['starts_at'], 'endsAt' => $cs['ends_at'],
        'confirmedCount' => $confirmedCount, 'capacity' => $cs['capacity'],
    ]);

    redirect('panel-zapisy-potwierdzenie.php?id=' . $enrollmentId);
}

$error = $_GET['error'] ?? null;
$view = in_array($_GET['view'] ?? '', ['week', 'day'], true) ? $_GET['view'] : 'month';
$anchor = parse_date_param($_GET['date'] ?? null);
$classTypeId = !empty($_GET['classType']) ? (int) $_GET['classType'] : null;
[$from, $to] = range_for_view($view, $anchor);

$classTypes = db()->query('SELECT * FROM class_types ORDER BY id ASC')->fetchAll();
$sessions = get_sessions_with_availability($from, $to, $classTypeId);

$user = current_user();
$children = [];
if ($user) {
    $stmt = db()->prepare('SELECT * FROM children WHERE parent_id = ? ORDER BY first_name ASC');
    $stmt->execute([$user['id']]);
    $children = $stmt->fetchAll();
}

$extraParams = $classTypeId ? ['classType' => $classTypeId] : [];
$basePath = 'kalendarz.php';

$pageTitle = 'Kalendarz zajęć — INNOVA';
require __DIR__ . '/includes/layout_top.php';
?>
<div class="container" style="padding:40px 16px;">
  <h1 style="font-size:1.8rem;">Kalendarz zajęć</h1>
  <p class="text-muted mt-2">Wybierz dzień, żeby zobaczyć opis zajęć i zapisać dziecko.</p>

  <?php if ($error): ?><p class="alert alert-error"><?= e($error) ?></p><?php endif; ?>

  <form method="get" class="flex items-center gap-2 mt-6" style="font-size:0.9rem;">
    <input type="hidden" name="view" value="<?= e($view) ?>">
    <input type="hidden" name="date" value="<?= e(date_param($anchor)) ?>">
    <label for="classType" class="text-muted" style="margin:0;">Rodzaj zajęć:</label>
    <select id="classType" name="classType" style="width:auto;">
      <option value="">Wszystkie</option>
      <?php foreach ($classTypes as $ct): ?>
        <option value="<?= $ct['id'] ?>" <?= $classTypeId === (int) $ct['id'] ? 'selected' : '' ?>><?= e($ct['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-outline btn-sm">Filtruj</button>
  </form>

  <?php include __DIR__ . '/includes/partials/calendar-nav.php'; ?>

  <div class="mt-6">
    <?php if ($view === 'month'): ?>
      <?php include __DIR__ . '/includes/partials/calendar-month.php'; ?>
    <?php elseif ($view === 'week'): ?>
      <?php include __DIR__ . '/includes/partials/calendar-week.php'; ?>
    <?php else: ?>
      <?php $showEnrollForm = true; include __DIR__ . '/includes/partials/calendar-day.php'; ?>
    <?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
