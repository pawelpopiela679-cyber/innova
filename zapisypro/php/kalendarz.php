<?php
require_once __DIR__ . '/includes/bootstrap.php';

$org = require_org();
$user = current_user();
$isParent = $user['role'] === 'PARENT';

$error = $_GET['error'] ?? null;
$info = $_GET['info'] ?? null;

// --- Zapis dziecka na zajęcia (tylko rodzic) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_action'] ?? '') === 'enroll') {
    csrf_check();
    if (!$isParent) {
        redirect('kalendarz.php');
    }
    $sessionId = (int) ($_POST['sessionId'] ?? 0);
    $childId = (int) ($_POST['childId'] ?? 0);

    $child = db()->prepare('SELECT * FROM children WHERE id = ? AND org_id = ?');
    $child->execute([$childId, $org['id']]);
    $child = $child->fetch();

    if (!$child || (int) $child['parent_id'] !== (int) $user['id']) {
        redirect_with('kalendarz.php', ['error' => 'To nie jest Twoje dziecko.']);
    }

    $cs = db()->prepare('SELECT cs.*, ct.name AS ct_name FROM class_sessions cs JOIN class_types ct ON ct.id = cs.class_type_id WHERE cs.id = ? AND cs.org_id = ?');
    $cs->execute([$sessionId, $org['id']]);
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
    } else {
        db()->prepare('INSERT INTO enrollments (org_id, session_id, child_id, parent_id, status) VALUES (?,?,?,?,?)')
            ->execute([$org['id'], $sessionId, $childId, $user['id'], 'PENDING']);
    }

    $parent = db()->prepare('SELECT * FROM users WHERE id = ?');
    $parent->execute([$user['id']]);
    $parent = $parent->fetch();
    $countStmt = db()->prepare("SELECT COUNT(*) c FROM enrollments WHERE session_id = ? AND status = 'CONFIRMED'");
    $countStmt->execute([$sessionId]);
    $confirmedCount = (int) $countStmt->fetch()['c'];

    send_enrollment_pending_email([
        'parentEmail' => $parent['email'], 'parentName' => $parent['name'], 'orgName' => $org['name'],
        'childName' => $child['first_name'] . ' ' . $child['last_name'],
        'classTypeName' => $cs['ct_name'], 'sessionTitle' => $cs['title'],
        'startsAt' => $cs['starts_at'], 'endsAt' => $cs['ends_at'],
    ]);
    send_org_new_signup_notification([
        'orgNotifyEmail' => $org['notify_email'],
        'childName' => $child['first_name'] . ' ' . $child['last_name'], 'childBirthDate' => $child['birth_date'],
        'parentName' => $parent['name'], 'parentEmail' => $parent['email'], 'parentPhone' => $parent['phone'],
        'classTypeName' => $cs['ct_name'], 'sessionTitle' => $cs['title'],
        'startsAt' => $cs['starts_at'], 'endsAt' => $cs['ends_at'],
        'confirmedCount' => $confirmedCount, 'capacity' => $cs['capacity'],
    ]);

    redirect_with('kalendarz.php', ['info' => 'Zgłoszenie wysłane — czeka na potwierdzenie.', 'selected' => substr($cs['starts_at'], 0, 10)]);
}

$anchor = parse_date_param($_GET['date'] ?? null);
$classTypeId = !empty($_GET['classType']) ? (int) $_GET['classType'] : null;
$selectedDate = $_GET['selected'] ?? date_param(new DateTime('today'));
[$from, $to] = range_for_view('month', $anchor);

$classTypes = db()->prepare('SELECT * FROM class_types WHERE org_id = ? ORDER BY name ASC');
$classTypes->execute([$org['id']]);
$classTypes = $classTypes->fetchAll();

$sessions = get_sessions_with_availability($org['id'], $from, $to, $classTypeId);

$daySessions = array_values(array_filter($sessions, fn($s) => substr($s['starts_at'], 0, 10) === $selectedDate && $s['status'] === 'SCHEDULED'));

$children = [];
if ($isParent) {
    $stmt = db()->prepare('SELECT * FROM children WHERE parent_id = ? ORDER BY first_name ASC');
    $stmt->execute([$user['id']]);
    $children = $stmt->fetchAll();
}

$extraParams = $classTypeId ? ['classType' => $classTypeId] : [];
$basePath = 'kalendarz.php';

$pageTitle = 'Kalendarz zajęć — ' . $org['name'];
require __DIR__ . '/includes/layout_top.php';
?>
<div class="container section">
  <h1 class="section-title">Kalendarz zajęć</h1>
  <p class="text-muted mt-2"><?= e($org['name']) ?><?= $isParent ? ' — wybierz dzień, żeby zapisać dziecko.' : '' ?></p>

  <?php if ($error): ?><p class="alert alert-error"><?= e($error) ?></p><?php endif; ?>
  <?php if ($info): ?><p class="alert alert-success"><?= e($info) ?></p><?php endif; ?>

  <form method="get" class="flex items-center gap-2 mt-6" style="font-size:0.9rem;">
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

  <div class="cal-nav mt-6">
    <a href="<?= e(calendar_href($basePath, 'month', (clone $anchor)->modify('-1 month'), $extraParams)) ?>" class="btn btn-outline btn-sm">← Poprzedni</a>
    <div class="cal-nav-title"><?= e(PL_MONTHS_NOM[(int) $anchor->format('n')]) ?> <?= $anchor->format('Y') ?></div>
    <a href="<?= e(calendar_href($basePath, 'month', (clone $anchor)->modify('+1 month'), $extraParams)) ?>" class="btn btn-outline btn-sm">Następny →</a>
  </div>

  <div class="mt-6">
    <?php require __DIR__ . '/includes/partials/calendar-month.php'; ?>
  </div>

  <div class="day-agenda mt-8 reveal">
    <h2><?= e(format_pl_date($selectedDate, true)) ?></h2>
    <?php if (!$daySessions): ?>
      <p class="text-muted">Brak zajęć tego dnia.</p>
    <?php endif; ?>
    <?php foreach ($daySessions as $s): ?>
      <div class="agenda-card">
        <div class="agenda-dot" style="background:<?= e($s['ct_color']) ?>;"></div>
        <div class="agenda-main">
          <div class="agenda-title"><?= e($s['ct_name']) ?> — <?= e($s['title']) ?></div>
          <div class="text-muted"><?= h_m($s['starts_at']) ?>–<?= h_m($s['ends_at']) ?> · <?= e($s['instructor_name']) ?></div>
        </div>
        <div class="agenda-status <?= $s['is_full'] ? 'full' : 'ok' ?>"><?= $s['is_full'] ? 'Brak miejsc' : $s['spots_left'] . ' wolnych' ?></div>
        <?php if ($isParent): ?>
          <?php if ($children): ?>
            <form method="post" class="agenda-enroll">
              <?= csrf_field() ?>
              <input type="hidden" name="_action" value="enroll">
              <input type="hidden" name="sessionId" value="<?= $s['id'] ?>">
              <select name="childId" required>
                <?php foreach ($children as $c): ?>
                  <option value="<?= $c['id'] ?>"><?= e($c['first_name'] . ' ' . $c['last_name']) ?></option>
                <?php endforeach; ?>
              </select>
              <button type="submit" class="btn btn-primary btn-sm"><?= $s['is_full'] ? 'Zapisz (lista rezerwowa)' : 'Zapisz' ?></button>
            </form>
          <?php else: ?>
            <a href="<?= e(url('panel-dzieci.php')) ?>" class="btn btn-outline btn-sm">Dodaj dziecko, żeby zapisać</a>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
