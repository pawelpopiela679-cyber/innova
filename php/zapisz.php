<?php
require_once __DIR__ . '/includes/bootstrap.php';

/**
 * Uproszczone zgłoszenie chęci zapisu — zastępuje dawny wybór konkretnego
 * terminu w kalendarzu. Rodzic wybiera tylko dziecko + rodzaj zajęć;
 * konkretny termin (session_id, wymagany przez schemat) dobierany jest
 * automatycznie spośród najbliższych zaplanowanych zajęć tego rodzaju —
 * i tak, jak już mówi podpis pod formularzem, to pracownia dobiera
 * właściwą grupę wiekową i potwierdza mailem, więc wybrany tu termin nie
 * jest wiążący.
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

    if (!$child || (int) $child['parent_id'] !== (int) $user['id']) {
        $error = 'Wybierz dziecko z listy.';
    } else {
        $session = db()->prepare(
            "SELECT cs.*, ct.name AS ct_name FROM class_sessions cs JOIN class_types ct ON ct.id = cs.class_type_id
             WHERE cs.class_type_id = ? AND cs.status = 'SCHEDULED' AND cs.starts_at >= ?
             ORDER BY cs.starts_at ASC LIMIT 1"
        );
        $session->execute([$classTypeId, date('Y-m-d H:i:s')]);
        $session = $session->fetch();

        if (!$session) {
            $error = 'Chwilowo brak zaplanowanych terminów dla tych zajęć — napisz do nas, a znajdziemy termin.';
        } else {
            $sessionId = (int) $session['id'];
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

            $countStmt = db()->prepare("SELECT COUNT(*) c FROM enrollments WHERE session_id = ? AND status = 'CONFIRMED'");
            $countStmt->execute([$sessionId]);
            $confirmedCount = (int) $countStmt->fetch()['c'];

            send_enrollment_pending_email([
                'parentEmail' => $user['email'], 'parentName' => $user['name'],
                'childName' => $child['first_name'] . ' ' . $child['last_name'],
                'classTypeName' => $session['ct_name'], 'sessionTitle' => $session['title'],
                'startsAt' => $session['starts_at'], 'endsAt' => $session['ends_at'],
            ]);
            send_studio_new_signup_notification([
                'childName' => $child['first_name'] . ' ' . $child['last_name'], 'childBirthDate' => $child['birth_date'],
                'parentName' => $user['name'], 'parentEmail' => $user['email'], 'parentPhone' => $user['phone'] ?? '',
                'classTypeName' => $session['ct_name'], 'sessionTitle' => $session['title'],
                'startsAt' => $session['starts_at'], 'endsAt' => $session['ends_at'],
                'confirmedCount' => $confirmedCount, 'capacity' => $session['capacity'],
            ]);

            redirect('panel-zapisy-potwierdzenie.php?id=' . $enrollmentId);
        }
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
