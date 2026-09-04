<?php
require_once __DIR__ . '/includes/bootstrap.php';
$user = require_staff();

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$stmt = db()->prepare('SELECT cs.*, ct.name AS ct_name FROM class_sessions cs JOIN class_types ct ON ct.id = cs.class_type_id WHERE cs.id = ?');
$stmt->execute([$id]);
$session = $stmt->fetch();

if (!$session) {
    $pageTitle = 'Nie znaleziono — INNOVA';
    $notebookTheme = true;
    require __DIR__ . '/includes/layout_top.php';
    echo '<div class="container-md" style="padding:40px 16px;"><p>Nie znaleziono tych zajęć.</p></div>';
    require __DIR__ . '/includes/layout_bottom.php';
    exit;
}

$countStmt = db()->prepare("SELECT COUNT(*) c FROM enrollments WHERE session_id = ? AND status != 'CANCELED'");
$countStmt->execute([$id]);
$enrolledCount = (int) $countStmt->fetch()['c'];

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['_action'] ?? '';

    if ($action === 'delete') {
        if ($enrolledCount > 0) {
            $error = 'Nie można usunąć — są do nich zgłoszenia. Użyj „Odwołaj zajęcia" zamiast usuwania.';
        } else {
            db()->prepare('DELETE FROM class_sessions WHERE id = ?')->execute([$id]);
            redirect('admin.php?deleted=1');
        }
    } else {
        $date = (string) ($_POST['date'] ?? '');
        $startTime = (string) ($_POST['startTime'] ?? '');
        $endTime = (string) ($_POST['endTime'] ?? '');
        $capacity = max(1, min(10, (int) ($_POST['capacity'] ?? 10)));
        $instructorName = trim((string) ($_POST['instructorName'] ?? ''));
        $meetingUrl = trim((string) ($_POST['meetingUrl'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));

        if ($date === '' || !preg_match('/^\d{2}:\d{2}$/', $startTime) || !preg_match('/^\d{2}:\d{2}$/', $endTime)) {
            $error = 'Uzupełnij datę i godziny.';
        } elseif ($startTime >= $endTime) {
            $error = 'Godzina zakończenia musi być po godzinie rozpoczęcia.';
        } elseif ($instructorName === '') {
            $error = 'Podaj prowadzącego.';
        } elseif ($capacity < $enrolledCount) {
            $error = "Liczba miejsc nie może być mniejsza niż liczba obecnych zgłoszeń ($enrolledCount).";
        } else {
            $starts = new DateTime($date);
            [$sh, $sm] = explode(':', $startTime);
            $starts->setTime((int) $sh, (int) $sm);
            $ends = new DateTime($date);
            [$eh, $em] = explode(':', $endTime);
            $ends->setTime((int) $eh, (int) $em);

            db()->prepare('UPDATE class_sessions SET starts_at=?, ends_at=?, capacity=?, instructor_name=?, meeting_url=?, description=? WHERE id=?')
                ->execute([
                    $starts->format('Y-m-d H:i:s'), $ends->format('Y-m-d H:i:s'),
                    $capacity, $instructorName, $meetingUrl ?: null, $description ?: null, $id,
                ]);
            redirect('admin.php?updated=1');
        }
    }
}

$startsDt = new DateTime($session['starts_at']);
$pageTitle = 'Edytuj zajęcia — INNOVA';
$notebookTheme = true;
require __DIR__ . '/includes/layout_top.php';
?>
<div class="container-md" style="padding:40px 16px;">
  <?php include __DIR__ . '/includes/partials/admin-nav.php'; ?>

  <h1 style="font-size:1.6rem;">Edytuj: <?= e($session['ct_name']) ?> — <?= e($session['title']) ?></h1>
  <p class="text-muted mt-2">Zmienia tylko ten jeden termin — nie rusza innych tygodni tej samej grupy.</p>
  <?php if ($enrolledCount > 0): ?>
    <p class="text-muted mt-2">Zgłoszonych na ten termin: <?= $enrolledCount ?>/<?= (int) $session['capacity'] ?>.</p>
  <?php endif; ?>

  <?php if ($error): ?><p class="alert alert-error"><?= e($error) ?></p><?php endif; ?>

  <form method="post" class="mt-6 grid grid-2">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int) $session['id'] ?>">
    <div class="field">
      <label for="date">Data</label>
      <input id="date" name="date" type="date" required value="<?= e($startsDt->format('Y-m-d')) ?>">
    </div>
    <div class="field">
      <label for="capacity">Liczba miejsc (maks. 10)</label>
      <input id="capacity" name="capacity" type="number" min="<?= max(1, $enrolledCount) ?>" max="10" required value="<?= (int) $session['capacity'] ?>">
    </div>
    <div class="field">
      <label for="startTime">Godzina rozpoczęcia</label>
      <input id="startTime" name="startTime" type="time" required value="<?= e($startsDt->format('H:i')) ?>">
    </div>
    <div class="field">
      <label for="endTime">Godzina zakończenia</label>
      <input id="endTime" name="endTime" type="time" required value="<?= e((new DateTime($session['ends_at']))->format('H:i')) ?>">
    </div>
    <div class="field" style="grid-column:1/-1;">
      <label for="instructorName">Prowadzący</label>
      <input id="instructorName" name="instructorName" required value="<?= e($session['instructor_name']) ?>">
    </div>
    <div class="field" style="grid-column:1/-1;">
      <label for="meetingUrl">Link do zajęć online (opcjonalnie)</label>
      <input id="meetingUrl" name="meetingUrl" placeholder="https://meet..." value="<?= e($session['meeting_url'] ?? '') ?>">
    </div>
    <div class="field" style="grid-column:1/-1;">
      <label for="description">Opis zajęć tego dnia (opcjonalnie, nadpisuje ogólny opis)</label>
      <textarea id="description" name="description" rows="3"><?= e($session['description'] ?? '') ?></textarea>
    </div>
    <div style="grid-column:1/-1; display:flex; gap:10px;">
      <button type="submit" class="btn btn-primary">Zapisz zmiany</button>
      <a href="<?= e(url('admin.php')) ?>" class="btn btn-outline">Anuluj</a>
    </div>
  </form>

  <form method="post" class="mt-8">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int) $session['id'] ?>">
    <input type="hidden" name="_action" value="delete">
    <?php if ($enrolledCount > 0): ?>
      <p class="field-hint">Nie można usunąć — są zgłoszenia na ten termin. Użyj „Odwołaj zajęcia” na liście zajęć.</p>
    <?php else: ?>
      <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Na pewno usunąć ten termin? Tego nie da się cofnąć.')">Usuń ten termin</button>
    <?php endif; ?>
  </form>
</div>
<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
