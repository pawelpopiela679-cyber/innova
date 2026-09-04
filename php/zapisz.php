<?php
require_once __DIR__ . '/includes/bootstrap.php';

/**
 * Uproszczone zgłoszenie chęci zapisu — zastępuje dawny wybór konkretnego
 * terminu w kalendarzu. Rodzic wybiera tylko dziecko + rodzaj zajęć;
 * zgłoszenie trafia do puli oczekujących (status PENDING, bez group_id) —
 * to pracownia w panelu grup (admin-grupy.php) przydziela dziecko do
 * konkretnej grupy (dnia/godziny/prowadzącego) i potwierdza e-mailem.
 */
$user = require_login('zapisz.php');

$stmt = db()->prepare('SELECT * FROM children WHERE parent_id = ? ORDER BY first_name ASC');
$stmt->execute([$user['id']]);
$children = $stmt->fetchAll();

$classTypes = db()->query("SELECT * FROM class_types WHERE key_name != 'OPEN_DAY' ORDER BY id ASC")->fetchAll();

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $childId = (int) ($_POST['childId'] ?? 0);
    $classTypeId = (int) ($_POST['classTypeId'] ?? 0);

    $child = db()->prepare('SELECT * FROM children WHERE id = ?');
    $child->execute([$childId]);
    $child = $child->fetch();

    $classType = db()->prepare('SELECT * FROM class_types WHERE id = ?');
    $classType->execute([$classTypeId]);
    $classType = $classType->fetch();

    if (!$child || (int) $child['parent_id'] !== (int) $user['id']) {
        $error = 'Wybierz dziecko z listy.';
    } elseif (!$classType) {
        $error = 'Wybierz rodzaj zajęć z listy.';
    } else {
        $already = db()->prepare("SELECT * FROM enrollments WHERE child_id = ? AND class_type_id = ? AND status != 'CANCELED'");
        $already->execute([$childId, $classTypeId]);
        $already = $already->fetch();

        if ($already) {
            redirect_with('panel-zapisy.php', ['info' => 'Dziecko ma już zgłoszenie na te zajęcia.']);
        }

        db()->prepare('INSERT INTO enrollments (child_id, parent_id, class_type_id, status) VALUES (?,?,?,?)')
            ->execute([$childId, $user['id'], $classTypeId, 'PENDING']);
        $enrollmentId = db_last_id(db());

        send_signup_request_email([
            'parentEmail' => $user['email'], 'parentName' => $user['name'],
            'childName' => $child['first_name'] . ' ' . $child['last_name'],
            'classTypeName' => $classType['name'],
        ]);
        send_studio_new_request_notification([
            'childName' => $child['first_name'] . ' ' . $child['last_name'], 'childBirthDate' => $child['birth_date'],
            'parentName' => $user['name'], 'parentEmail' => $user['email'], 'parentPhone' => $user['phone'] ?? '',
            'classTypeName' => $classType['name'],
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
    <p class="nb-form-sub">Wybierz dziecko i rodzaj zajęć — grupę dopasowaną do wieku i dokładny termin potwierdzimy e-mailem.</p>

    <?php if ($error): ?><p class="nb-alert-error"><?= e($error) ?></p><?php endif; ?>

    <?php if (!$children): ?>
      <p class="text-center" style="margin:18px 0;">Nie masz jeszcze dodanych dzieci.</p>
      <a href="<?= e(url('panel-dzieci.php')) ?>" class="nb-btn solid" style="width:100%; justify-content:center; box-sizing:border-box;">Dodaj dziecko →</a>
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
          <label for="classTypeId">Rodzaj zajęć</label>
          <select id="classTypeId" name="classTypeId" required>
            <?php foreach ($classTypes as $ct): ?>
              <option value="<?= (int) $ct['id'] ?>"><?= e($ct['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <button type="submit" class="nb-btn solid" style="width:100%; justify-content:center; box-sizing:border-box;">Zgłoś chęć zapisu</button>
      </form>
      <p class="nb-form-foot">Zgłoszenie wymaga potwierdzenia przez pracownię — dobierzemy grupę odpowiednią do wieku dziecka i odpowiemy e-mailem.</p>
    <?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
