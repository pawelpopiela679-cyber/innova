<?php
/** Masowa komunikacja — e-mail (i SMS, po podłączeniu bramki) do wybranej grupy albo wszystkich rodziców. */
require_once __DIR__ . '/includes/bootstrap.php';

$user = require_org_admin();
$org = require_org();
$sent = null;
$error = null;

$classTypes = db()->prepare('SELECT * FROM class_types WHERE org_id = ? ORDER BY name ASC');
$classTypes->execute([$org['id']]);
$classTypes = $classTypes->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $target = (string) ($_POST['target'] ?? 'all');
    $classTypeId = (int) ($_POST['classTypeId'] ?? 0);
    $subject = trim((string) ($_POST['subject'] ?? ''));
    $message = trim((string) ($_POST['message'] ?? ''));
    $channels = $_POST['channels'] ?? ['email'];

    if ($subject === '' || $message === '') {
        $error = 'Podaj temat i treść wiadomości.';
    } else {
        if ($target === 'group' && $classTypeId) {
            $stmt = db()->prepare("SELECT DISTINCT u.id, u.name, u.email, u.phone FROM users u
                JOIN enrollments e ON e.parent_id = u.id
                JOIN class_sessions cs ON cs.id = e.session_id
                WHERE cs.org_id = ? AND cs.class_type_id = ? AND e.status IN ('CONFIRMED','WAITLIST','PENDING')");
            $stmt->execute([$org['id'], $classTypeId]);
        } else {
            $stmt = db()->prepare("SELECT id, name, email, phone FROM users WHERE org_id = ? AND role = 'PARENT'");
            $stmt->execute([$org['id']]);
        }
        $recipients = $stmt->fetchAll();

        $html = '<div style="font-family:sans-serif;max-width:480px;"><p>' . nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8')) . '</p>'
            . '<p style="color:#999;font-size:12px;">' . htmlspecialchars($org['name'], ENT_QUOTES, 'UTF-8') . '</p></div>';

        $count = 0;
        foreach ($recipients as $r) {
            if (in_array('email', $channels, true) && $r['email']) {
                send_mail($r['email'], $subject, $html, $message);
            }
            if (in_array('sms', $channels, true) && $r['phone']) {
                send_sms($r['phone'], $subject . ': ' . $message);
            }
            $count++;
        }
        $sent = "Wysłano do $count odbiorców.";
    }
}

$pageTitle = 'Komunikacja — ' . $org['name'];
require __DIR__ . '/includes/layout_top.php';
?>
<div class="container-sm section">
  <h1 class="section-title">Komunikacja</h1>
  <p class="text-muted">Wyślij wiadomość do wszystkich rodziców albo tylko do zapisanych na wybrane zajęcia.</p>

  <?php if ($error): ?><p class="alert alert-error"><?= e($error) ?></p><?php endif; ?>
  <?php if ($sent): ?><p class="alert alert-success"><?= e($sent) ?></p><?php endif; ?>

  <form method="post" class="mt-6 form-card reveal">
    <?= csrf_field() ?>
    <div class="field">
      <label>Odbiorcy</label>
      <select name="target" id="target" onchange="document.getElementById('groupField').style.display = this.value === 'group' ? 'block' : 'none';">
        <option value="all">Wszyscy rodzice</option>
        <option value="group">Zapisani na wybrane zajęcia</option>
      </select>
    </div>
    <div class="field" id="groupField" style="display:none;">
      <label>Rodzaj zajęć</label>
      <select name="classTypeId"><?php foreach ($classTypes as $ct): ?><option value="<?= $ct['id'] ?>"><?= e($ct['name']) ?></option><?php endforeach; ?></select>
    </div>
    <div class="field">
      <label>Kanał</label>
      <label class="checkbox-inline"><input type="checkbox" name="channels[]" value="email" checked> E-mail</label>
      <label class="checkbox-inline"><input type="checkbox" name="channels[]" value="sms"> SMS <span class="text-muted">(wymaga bramki — patrz cennik.php)</span></label>
    </div>
    <div class="field"><label>Temat</label><input name="subject" required></div>
    <div class="field"><label>Treść</label><textarea name="message" rows="5" required></textarea></div>
    <button type="submit" class="btn btn-primary">Wyślij</button>
  </form>
</div>
<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
