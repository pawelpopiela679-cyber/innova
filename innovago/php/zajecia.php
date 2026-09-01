<?php
require_once __DIR__ . '/includes/bootstrap.php';

$user = require_staff();
$org = require_org();
$plan = org_plan($org);
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $formAction = $_POST['_action'] ?? '';

    if ($formAction === 'new_class_type') {
        $name = trim((string) ($_POST['name'] ?? ''));
        $key = strtoupper(preg_replace('/[^a-z0-9]+/i', '_', $name) ?? '');
        $description = trim((string) ($_POST['description'] ?? ''));
        $color = (string) ($_POST['color'] ?? '#7d7a4a');
        $ageMin = max(0, (int) ($_POST['age_min'] ?? 5));
        $ageMax = max($ageMin, (int) ($_POST['age_max'] ?? 12));
        if (mb_strlen($name) < 2) {
            $error = 'Podaj nazwę rodzaju zajęć.';
        } else {
            db()->prepare('INSERT INTO class_types (org_id, key_name, name, description, color, age_min, age_max) VALUES (?,?,?,?,?,?,?)')
                ->execute([$org['id'], $key . '_' . time(), $name, $description ?: $name, $color, $ageMin, $ageMax]);
            redirect('zajecia.php');
        }
    } elseif ($formAction === 'new_session') {
        $classTypeId = (int) ($_POST['classTypeId'] ?? 0);
        $title = trim((string) ($_POST['title'] ?? ''));
        $date = (string) ($_POST['date'] ?? '');
        $startTime = (string) ($_POST['startTime'] ?? '');
        $endTime = (string) ($_POST['endTime'] ?? '');
        $capacity = max(1, min(60, (int) ($_POST['capacity'] ?? 10)));
        $weeksCount = max(1, min(20, (int) ($_POST['weeksCount'] ?? 1)));
        $instructorName = trim((string) ($_POST['instructorName'] ?? ''));
        $meetingUrl = trim((string) ($_POST['meetingUrl'] ?? ''));
        if ($meetingUrl !== '' && !filter_var($meetingUrl, FILTER_VALIDATE_URL)) {
            $meetingUrl = ''; // niepoprawny URL — po prostu nie zapisujemy linku, zamiast blokować cały formularz
        }

        $ownsType = db()->prepare('SELECT id FROM class_types WHERE id = ? AND org_id = ?');
        $ownsType->execute([$classTypeId, $org['id']]);

        if (!$ownsType->fetch() || $title === '' || $date === '' || !preg_match('/^\d{2}:\d{2}$/', $startTime) || !preg_match('/^\d{2}:\d{2}$/', $endTime)) {
            $error = 'Uzupełnij wszystkie wymagane pola.';
        } elseif ($startTime >= $endTime) {
            $error = 'Godzina zakończenia musi być po godzinie rozpoczęcia.';
        } else {
            $instructorId = in_array($user['role'], ['ORG_ADMIN', 'INSTRUCTOR'], true) ? $user['id'] : null;
            $anchor = new DateTime($date);
            [$sh, $sm] = explode(':', $startTime);
            [$eh, $em] = explode(':', $endTime);
            $added = 0;
            for ($w = 0; $w < $weeksCount; $w++) {
                $starts = (clone $anchor)->modify("+$w weeks")->setTime((int) $sh, (int) $sm);
                $ends = (clone $anchor)->modify("+$w weeks")->setTime((int) $eh, (int) $em);
                db()->prepare('INSERT INTO class_sessions (org_id, class_type_id, title, starts_at, ends_at, capacity, meeting_url, instructor_id, instructor_name) VALUES (?,?,?,?,?,?,?,?,?)')
                    ->execute([$org['id'], $classTypeId, $title, $starts->format('Y-m-d H:i:s'), $ends->format('Y-m-d H:i:s'), $capacity, $meetingUrl ?: null, $instructorId, $instructorName ?: $user['name']]);
                $added++;
            }
            redirect('zajecia.php?added=' . $added);
        }
    } elseif ($formAction === 'cancel_session') {
        $sessionId = (int) ($_POST['sessionId'] ?? 0);
        db()->prepare("UPDATE class_sessions SET status = 'CANCELED' WHERE id = ? AND org_id = ?")->execute([$sessionId, $org['id']]);
        redirect('zajecia.php');
    } elseif ($formAction === 'set_meeting_url') {
        $sessionId = (int) ($_POST['sessionId'] ?? 0);
        $meetingUrl = trim((string) ($_POST['meetingUrl'] ?? ''));
        if ($meetingUrl !== '' && !filter_var($meetingUrl, FILTER_VALIDATE_URL)) {
            $error = 'Nieprawidłowy adres URL linku do zajęć.';
        } else {
            db()->prepare('UPDATE class_sessions SET meeting_url = ? WHERE id = ? AND org_id = ?')
                ->execute([$meetingUrl ?: null, $sessionId, $org['id']]);
            redirect('zajecia.php');
        }
    }
}

$classTypes = db()->prepare('SELECT * FROM class_types WHERE org_id = ? ORDER BY name ASC');
$classTypes->execute([$org['id']]);
$classTypes = $classTypes->fetchAll();

$upcoming = db()->prepare("SELECT cs.*, ct.name AS ct_name, ct.color AS ct_color,
    (SELECT COUNT(*) FROM enrollments e WHERE e.session_id = cs.id AND e.status = 'CONFIRMED') AS confirmed_count
    FROM class_sessions cs JOIN class_types ct ON ct.id = cs.class_type_id
    WHERE cs.org_id = ? AND cs.status = 'SCHEDULED' AND cs.starts_at >= ?
    ORDER BY cs.starts_at ASC LIMIT 30");
$upcoming->execute([$org['id'], date('Y-m-d H:i:s')]);
$upcoming = $upcoming->fetchAll();

$instructorCount = org_instructor_count((int) $org['id']);
$atInstructorLimit = $plan && $plan['max_instructors'] < 999 && $instructorCount >= (int) $plan['max_instructors'];

$pageTitle = 'Zajęcia — ' . $org['name'];
require __DIR__ . '/includes/layout_top.php';
?>
<div class="container section">
  <h1 class="section-title">Zajęcia</h1>
  <?php if ($error): ?><p class="alert alert-error"><?= e($error) ?></p><?php endif; ?>
  <?php if (!empty($_GET['added'])): ?><p class="alert alert-success">Dodano <?= (int) $_GET['added'] ?> termin(y/ów) do kalendarza.</p><?php endif; ?>

  <div class="two-col mt-6">
    <div>
      <h2>Rodzaje zajęć</h2>
      <?php if (!$classTypes): ?><p class="text-muted">Jeszcze żadnych — dodaj pierwszy poniżej.</p><?php endif; ?>
      <ul class="type-list">
        <?php foreach ($classTypes as $ct): ?>
          <li><span class="dot" style="background:<?= e($ct['color']) ?>;"></span><?= e($ct['name']) ?> <span class="text-muted">(<?= (int) $ct['age_min'] ?>–<?= (int) $ct['age_max'] ?> lat)</span></li>
        <?php endforeach; ?>
      </ul>

      <details class="mt-4"><summary class="btn btn-outline btn-sm" style="display:inline-block;cursor:pointer;">+ Nowy rodzaj zajęć</summary>
        <form method="post" class="mt-4 form-card reveal">
          <?= csrf_field() ?>
          <input type="hidden" name="_action" value="new_class_type">
          <div class="field"><label>Nazwa</label><input name="name" required placeholder="np. Szachy"></div>
          <div class="field"><label>Opis</label><textarea name="description" rows="2"></textarea></div>
          <div class="field"><label>Kolor</label><input type="color" name="color" value="#7d7a4a"></div>
          <div class="grid grid-2">
            <div class="field"><label>Wiek od</label><input type="number" name="age_min" value="5" min="0"></div>
            <div class="field"><label>Wiek do</label><input type="number" name="age_max" value="12" min="0"></div>
          </div>
          <button type="submit" class="btn btn-primary">Dodaj rodzaj zajęć</button>
        </form>
      </details>
    </div>

    <div>
      <h2>Nowy termin</h2>
      <?php if ($atInstructorLimit): ?>
        <p class="alert alert-error">Osiągnięto limit prowadzących dla planu <?= e($plan['name']) ?>. <a href="<?= e(url('abonament.php')) ?>">Zmień plan →</a></p>
      <?php endif; ?>
      <?php if ($classTypes): ?>
      <form method="post" class="mt-2 form-card reveal">
        <?= csrf_field() ?>
        <input type="hidden" name="_action" value="new_session">
        <div class="field"><label>Rodzaj zajęć</label>
          <select name="classTypeId" required><?php foreach ($classTypes as $ct): ?><option value="<?= $ct['id'] ?>"><?= e($ct['name']) ?></option><?php endforeach; ?></select>
        </div>
        <div class="field"><label>Nazwa terminu/grupy</label><input name="title" required placeholder="np. Grupa A"></div>
        <div class="grid grid-2">
          <div class="field"><label>Data pierwszych zajęć</label><input type="date" name="date" required></div>
          <div class="field"><label>Limit miejsc</label><input type="number" name="capacity" value="10" min="1" max="60" required></div>
          <div class="field"><label>Godzina od</label><input type="time" name="startTime" required></div>
          <div class="field"><label>Godzina do</label><input type="time" name="endTime" required></div>
        </div>
        <div class="field"><label>Powtórz co tydzień, przez ile tygodni?</label><input type="number" name="weeksCount" value="8" min="1" max="20" style="max-width:8rem;"></div>
        <div class="field"><label>Prowadzący</label><input name="instructorName" value="<?= e($user['name']) ?>" required></div>
        <div class="field">
          <label for="meetingUrl">Link do zajęć online (Google Meet / Zoom, opcjonalnie)</label>
          <div class="flex gap-2">
            <input id="meetingUrl" name="meetingUrl" placeholder="https://meet.google.com/..." style="flex:1;">
            <a href="https://meet.new" target="_blank" rel="noopener" class="btn btn-outline btn-sm" style="white-space:nowrap;">Utwórz nowe Meet ↗</a>
          </div>
          <p class="field-hint">„Utwórz nowe Meet” otworzy od razu gotowy pokój Google Meet w nowej karcie — skopiuj z niego adres i wklej tutaj. Ten sam link zostanie ustawiony na wszystkich wygenerowanych terminach cyklu.</p>
        </div>
        <button type="submit" class="btn btn-primary">Dodaj do kalendarza</button>
      </form>
      <?php endif; ?>
    </div>
  </div>

  <h2 class="mt-8">Najbliższe terminy</h2>
  <div class="table-wrap mt-4 reveal">
    <table class="data-table">
      <thead><tr><th>Zajęcia</th><th>Termin</th><th>Zapełnienie</th><th>Link online</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($upcoming as $s): ?>
          <tr>
            <td><span class="dot" style="background:<?= e($s['ct_color']) ?>;"></span><?= e($s['ct_name']) ?> — <?= e($s['title']) ?></td>
            <td>
              <?= e(format_pl_date($s['starts_at'], true, true)) ?>
              <a href="<?= e(google_calendar_link($s['ct_name'] . ' — ' . $s['title'], $s['starts_at'], $s['ends_at'], '', $s['meeting_url'] ?? '')) ?>" target="_blank" rel="noopener" class="text-muted" style="font-size:.78rem;display:block;">+ Kalendarz Google</a>
            </td>
            <td><?= (int) $s['confirmed_count'] ?>/<?= (int) $s['capacity'] ?></td>
            <td>
              <?php if ($s['meeting_url']): ?>
                <a href="<?= e($s['meeting_url']) ?>" target="_blank" rel="noopener">🔗 dołącz</a>
              <?php endif; ?>
              <details style="display:inline-block;">
                <summary class="text-muted" style="cursor:pointer;font-size:.78rem;display:inline;"><?= $s['meeting_url'] ? 'zmień' : '+ dodaj link' ?></summary>
                <form method="post" class="flex gap-2 mt-2">
                  <?= csrf_field() ?>
                  <input type="hidden" name="_action" value="set_meeting_url">
                  <input type="hidden" name="sessionId" value="<?= $s['id'] ?>">
                  <input name="meetingUrl" value="<?= e($s['meeting_url'] ?? '') ?>" placeholder="https://..." style="width:180px;">
                  <button class="btn btn-outline btn-sm">Zapisz</button>
                </form>
              </details>
            </td>
            <td><form method="post" onsubmit="return confirm('Odwołać ten termin?');"><?= csrf_field() ?><input type="hidden" name="_action" value="cancel_session"><input type="hidden" name="sessionId" value="<?= $s['id'] ?>"><button class="btn btn-outline btn-sm">Odwołaj</button></form></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
