<?php
require_once __DIR__ . '/includes/bootstrap.php';
$user = require_staff();

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $classTypeId = (int) ($_POST['classTypeId'] ?? 0);
    $title = trim((string) ($_POST['title'] ?? ''));
    $date = (string) ($_POST['date'] ?? '');
    $startTime = (string) ($_POST['startTime'] ?? '');
    $endTime = (string) ($_POST['endTime'] ?? '');
    $capacity = max(1, min(10, (int) ($_POST['capacity'] ?? 10)));
    $weeksCount = max(1, min(20, (int) ($_POST['weeksCount'] ?? 1)));
    $instructorName = trim((string) ($_POST['instructorName'] ?? ''));
    $meetingUrl = trim((string) ($_POST['meetingUrl'] ?? ''));
    $description = trim((string) ($_POST['description'] ?? ''));

    if (!$classTypeId || $title === '' || $date === '' || !preg_match('/^\d{2}:\d{2}$/', $startTime) || !preg_match('/^\d{2}:\d{2}$/', $endTime)) {
        $error = 'Uzupełnij wszystkie wymagane pola.';
    } elseif ($startTime >= $endTime) {
        $error = 'Godzina zakończenia musi być po godzinie rozpoczęcia.';
    } elseif ($instructorName === '') {
        $error = 'Podaj prowadzącego.';
    } else {
        $instructorId = ($user['role'] === 'INSTRUCTOR' || $user['role'] === 'ADMIN') ? $user['id'] : null;
        $anchor = new DateTime($date);

        // Grupa — trwały byt (nazwa, dzień/godzina, prowadzący, limit) —
        // niezależny od pojedynczych "wystąpień" w kalendarzu (class_sessions
        // niżej). Dzieci zapisują się DO GRUPY (patrz admin-grupy.php), nie
        // do konkretnego tygodnia.
        $groupId = db()->prepare(
            'INSERT INTO class_groups (class_type_id, name, instructor_id, instructor_name, day_of_week, start_time, end_time, capacity, meeting_url) VALUES (?,?,?,?,?,?,?,?,?)'
        );
        $groupId->execute([
            $classTypeId, $title, $instructorId, $instructorName,
            (int) $anchor->format('N'), $startTime, $endTime, $capacity, $meetingUrl ?: null,
        ]);
        $groupId = db_last_id(db());

        $added = 0;
        for ($w = 0; $w < $weeksCount; $w++) {
            $starts = clone $anchor;
            $starts->modify("+$w weeks");
            [$sh, $sm] = explode(':', $startTime);
            [$eh, $em] = explode(':', $endTime);
            $starts->setTime((int) $sh, (int) $sm);
            $ends = clone $anchor;
            $ends->modify("+$w weeks");
            $ends->setTime((int) $eh, (int) $em);

            db()->prepare('INSERT INTO class_sessions (class_type_id, group_id, title, description, starts_at, ends_at, capacity, meeting_url, instructor_id, instructor_name) VALUES (?,?,?,?,?,?,?,?,?,?)')
                ->execute([
                    $classTypeId, $groupId, $title, $description ?: null,
                    $starts->format('Y-m-d H:i:s'), $ends->format('Y-m-d H:i:s'),
                    $capacity, $meetingUrl ?: null, $instructorId, $instructorName,
                ]);
            $added++;
        }
        redirect('admin.php?added=' . $added);
    }
}

$classTypes = db()->query('SELECT * FROM class_types ORDER BY id ASC')->fetchAll();

// Podstawione z kliknięcia komórki w admin-grafik.php (dzień + godzina) —
// żeby nie trzeba było ręcznie wpisywać tego, co już wybrano na siatce.
$prefillDate = (string) ($_GET['date'] ?? '');
$prefillStart = (string) ($_GET['startTime'] ?? '');
$prefillEnd = (string) ($_GET['endTime'] ?? '');

$pageTitle = 'Nowe zajęcia — INNOVA';
$notebookTheme = true;
require __DIR__ . '/includes/layout_top.php';
?>
<div class="container-md" style="padding:40px 16px;">
  <?php include __DIR__ . '/includes/partials/admin-nav.php'; ?>

  <h1 style="font-size:1.6rem;">Nowa grupa</h1>
  <p class="text-muted mt-2">
    Tworzy nową, stałą grupę (dzień/godzina/prowadzący/limit miejsc) i od razu dopina jej
    cotygodniowe terminy w kalendarzu. Dzieci przypisujesz do utworzonej grupy w
    <a href="<?= e(url('admin-grupy.php')) ?>" style="color:var(--primary); text-decoration:underline;">panelu grup</a>.
  </p>

  <?php if ($error): ?><p class="alert alert-error"><?= e($error) ?></p><?php endif; ?>

  <form method="post" class="mt-6 grid grid-2">
    <?= csrf_field() ?>
    <div class="field" style="grid-column:1/-1;">
      <label for="classTypeId">Rodzaj zajęć</label>
      <select id="classTypeId" name="classTypeId" required>
        <?php foreach ($classTypes as $ct): ?><option value="<?= $ct['id'] ?>"><?= e($ct['name']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="field" style="grid-column:1/-1;">
      <label for="title">Nazwa grupy</label>
      <input id="title" name="title" required placeholder="np. Robotyka „1” — grupa online">
    </div>
    <div class="field">
      <label for="date">Data pierwszych zajęć</label>
      <input id="date" name="date" type="date" required value="<?= e($prefillDate) ?>">
    </div>
    <div class="field">
      <label for="capacity">Liczba miejsc (maks. 10)</label>
      <input id="capacity" name="capacity" type="number" min="1" max="10" value="10" required>
    </div>
    <div class="field">
      <label for="startTime">Godzina rozpoczęcia</label>
      <input id="startTime" name="startTime" type="time" required value="<?= e($prefillStart) ?>">
    </div>
    <div class="field">
      <label for="endTime">Godzina zakończenia</label>
      <input id="endTime" name="endTime" type="time" required value="<?= e($prefillEnd) ?>">
    </div>
    <div class="field" style="grid-column:1/-1;">
      <label for="weeksCount">Powtórz co tydzień, przez ile tygodni?</label>
      <input id="weeksCount" name="weeksCount" type="number" min="1" max="20" value="10" required style="max-width:10rem;">
      <p class="field-hint">Wpisz 1, jeśli to jednorazowe zajęcia (np. odrabianie zaległości), bez powtórzeń.</p>
    </div>
    <div class="field" style="grid-column:1/-1;">
      <label for="instructorName">Prowadzący</label>
      <input id="instructorName" name="instructorName" required value="<?= e($user['name']) ?>">
    </div>
    <div class="field" style="grid-column:1/-1;">
      <label for="meetingUrl">Link do zajęć online (opcjonalnie)</label>
      <input id="meetingUrl" name="meetingUrl" placeholder="https://meet...">
    </div>
    <div class="field" style="grid-column:1/-1;">
      <label for="description">Opis zajęć tego dnia (opcjonalnie, nadpisuje ogólny opis)</label>
      <textarea id="description" name="description" rows="3"></textarea>
    </div>
    <div style="grid-column:1/-1;">
      <button type="submit" class="btn btn-primary">Dodaj do kalendarza</button>
    </div>
  </form>
</div>
<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
