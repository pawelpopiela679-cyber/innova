<?php
require_once __DIR__ . '/includes/bootstrap.php';

/**
 * Zgłoszenie chęci zapisu — rodzic wybiera dziecko I KONKRETNY termin
 * (grupę: dzień + godzinę + prowadzącego), a nie tylko ogólny rodzaj zajęć —
 * to pracownia odgórnie przydzielała termin, czego nie chcieliśmy.
 * Zgłoszenie trafia od razu z wybraną grupą do panelu grup (admin-grupy.php)
 * ze statusem PENDING — pracownia sprawdza dostępność miejsc i potwierdza
 * e-mailem (albo, jeśli grupa jest pełna, zapisuje na listę rezerwową).
 */
$user = require_login('zapisz.php');

$stmt = db()->prepare('SELECT * FROM children WHERE parent_id = ? ORDER BY first_name ASC');
$stmt->execute([$user['id']]);
$children = $stmt->fetchAll();

$groups = get_groups_with_availability();
$groupsByType = [];
foreach ($groups as $g) {
    $groupsByType[$g['class_type_id']]['name'] = $g['ct_name'];
    $groupsByType[$g['class_type_id']]['groups'][] = $g;
}

$preselectedGroup = (int) ($_GET['group'] ?? $_POST['groupId'] ?? 0);
$error = null;

$noteValue = trim((string) ($_POST['note'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $childId = (int) ($_POST['childId'] ?? 0);
    $groupId = (int) ($_POST['groupId'] ?? 0);
    $note = mb_substr($noteValue, 0, 1000);

    $child = db()->prepare('SELECT * FROM children WHERE id = ?');
    $child->execute([$childId]);
    $child = $child->fetch();

    $group = db()->prepare('SELECT g.*, ct.id AS class_type_id, ct.name AS ct_name, u.email AS instructor_email
        FROM class_groups g JOIN class_types ct ON ct.id = g.class_type_id
        LEFT JOIN users u ON u.id = g.instructor_id
        WHERE g.id = ? AND g.active = 1');
    $group->execute([$groupId]);
    $group = $group->fetch();

    if (!$child || (int) $child['parent_id'] !== (int) $user['id']) {
        $error = 'Wybierz dziecko z listy.';
    } elseif (!$group) {
        $error = 'Wybierz zajęcia z listy.';
    } else {
        $already = db()->prepare("SELECT * FROM enrollments WHERE child_id = ? AND class_type_id = ? AND status != 'CANCELED'");
        $already->execute([$childId, $group['class_type_id']]);
        $already = $already->fetch();

        if ($already) {
            redirect_with('panel-zapisy.php', ['info' => 'Dziecko ma już zgłoszenie na te zajęcia.']);
        }

        db()->prepare('INSERT INTO enrollments (child_id, parent_id, class_type_id, group_id, status, note) VALUES (?,?,?,?,?,?)')
            ->execute([$childId, $user['id'], $group['class_type_id'], $groupId, 'PENDING', $note !== '' ? $note : null]);
        $enrollmentId = db_last_id(db());

        $countStmt = db()->prepare("SELECT COUNT(*) c FROM enrollments WHERE group_id = ? AND status = 'CONFIRMED'");
        $countStmt->execute([$groupId]);
        $confirmedCount = (int) $countStmt->fetch()['c'];
        $when = format_group_schedule((int) $group['day_of_week'], $group['start_time'], $group['end_time']);

        send_signup_request_email([
            'parentEmail' => $user['email'], 'parentName' => $user['name'],
            'childName' => $child['first_name'] . ' ' . $child['last_name'],
            'classTypeName' => $group['ct_name'], 'groupName' => $group['name'], 'when' => $when,
            'instructorName' => $group['instructor_name'],
        ]);
        send_studio_new_request_notification([
            'childName' => $child['first_name'] . ' ' . $child['last_name'], 'childBirthDate' => $child['birth_date'],
            'parentName' => $user['name'], 'parentEmail' => $user['email'], 'parentPhone' => $user['phone'] ?? '',
            'classTypeName' => $group['ct_name'], 'groupName' => $group['name'], 'when' => $when,
            'confirmedCount' => $confirmedCount, 'capacity' => (int) $group['capacity'], 'note' => $note,
        ]);

        redirect('panel-zapisy-potwierdzenie.php?id=' . $enrollmentId);
    }
}

$pageTitle = 'Zapisz dziecko — INNOVA';
$notebookTheme = true;
$notebookActive = 'signup';
require __DIR__ . '/includes/layout_top.php';
?>
<div class="nb-form-wrap">
  <div class="nb-form-card nb-form">
    <div class="nb-tape"></div>
    <div class="nb-form-title">Zapisz dziecko na zajęcia</div>
    <p class="nb-form-sub">Wybierz dziecko i konkretny termin, który Wam pasuje — resztę (potwierdzenie miejsca) załatwimy e-mailem.</p>

    <?php if ($error): ?><p class="nb-alert-error"><?= e($error) ?></p><?php endif; ?>

    <?php if (!$children): ?>
      <p class="text-center" style="margin:18px 0;">Nie masz jeszcze dodanych dzieci.</p>
      <a href="<?= e(url('panel-dzieci.php')) ?>" class="nb-btn solid" style="width:100%; justify-content:center; box-sizing:border-box;">Dodaj dziecko →</a>
    <?php elseif (!$groups): ?>
      <p class="text-center" style="margin:18px 0;">Aktualnie nie ma jeszcze otwartych grup zajęć — sprawdź wkrótce albo skontaktuj się z nami.</p>
    <?php else: ?>
      <form method="post">
        <?= csrf_field() ?>
        <div class="nb-field">
          <label for="childId">Dziecko</label>
          <select id="childId" name="childId" required>
            <?php foreach ($children as $c): ?>
              <option value="<?= (int) $c['id'] ?>"><?= e($c['first_name'] . ' ' . $c['last_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="nb-field">
          <label for="groupId">Zajęcia — dzień i godzina</label>
          <select id="groupId" name="groupId" required>
            <option value="">Wybierz termin…</option>
            <?php foreach ($groupsByType as $ctId => $bucket): ?>
              <optgroup label="<?= e($bucket['name']) ?>">
                <?php foreach ($bucket['groups'] as $g): ?>
                  <option value="<?= (int) $g['id'] ?>" <?= $preselectedGroup === (int) $g['id'] ? 'selected' : '' ?>>
                    <?= e(ucfirst(weekday_name_plural_iso((int) $g['day_of_week']))) ?> <?= e($g['start_time']) ?>–<?= e($g['end_time']) ?>
                    — <?= e($g['instructor_name']) ?>
                    <?= $g['is_full'] ? ' (brak miejsc — lista rezerwowa)' : ' (wolne miejsca: ' . (int) $g['spots_left'] . '/' . (int) $g['capacity'] . ')' ?>
                  </option>
                <?php endforeach; ?>
              </optgroup>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="nb-field">
          <label for="note">Wiadomość do prowadzących (opcjonalnie)</label>
          <textarea id="note" name="note" rows="3" maxlength="1000" placeholder="Np. specjalne potrzeby dziecka, alergie, prośba o konkretną grupę…"><?= e($noteValue) ?></textarea>
        </div>
        <button type="submit" class="nb-btn solid" style="width:100%; justify-content:center; box-sizing:border-box;">Zgłoś chęć zapisu</button>
      </form>
      <p class="nb-form-foot">Zgłoszenie wymaga potwierdzenia przez pracownię — jeśli wybrany termin jest pełny, zaproponujemy inny albo zapiszemy dziecko na listę rezerwową.</p>
    <?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
