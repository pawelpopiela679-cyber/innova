<?php
require_once __DIR__ . '/includes/bootstrap.php';

$user = require_org_admin();
$org = require_org();
$plan = org_plan($org);
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $formAction = $_POST['_action'] ?? '';

    if ($formAction === 'add') {
        $name = trim((string) ($_POST['name'] ?? ''));
        $email = trim(strtolower((string) ($_POST['email'] ?? '')));
        $password = (string) ($_POST['password'] ?? '');
        $wage = (string) ($_POST['wage'] ?? '');
        $instructorCount = org_instructor_count((int) $org['id']);

        if ($plan && $plan['max_instructors'] < 999 && $instructorCount >= (int) $plan['max_instructors']) {
            $error = 'Osiągnięto limit prowadzących dla planu ' . $plan['name'] . '. Zmień plan w Abonamencie.';
        } elseif (mb_strlen($name) < 2 || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($password) < 8) {
            $error = 'Uzupełnij poprawnie imię, e-mail i hasło (min. 8 znaków).';
        } else {
            $exists = db()->prepare('SELECT id FROM users WHERE email = ?');
            $exists->execute([$email]);
            if ($exists->fetch()) {
                $error = 'Ten e-mail jest już zajęty.';
            } else {
                $wageCents = $wage !== '' ? (int) round(((float) str_replace(',', '.', $wage)) * 100) : null;
                db()->prepare('INSERT INTO users (org_id, name, email, password_hash, role, wage_hourly_cents) VALUES (?,?,?,?,?,?)')
                    ->execute([$org['id'], $name, $email, hash_password($password), 'INSTRUCTOR', $wageCents]);
                redirect('prowadzacy.php');
            }
        }
    } elseif ($formAction === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        db()->prepare("DELETE FROM users WHERE id = ? AND org_id = ? AND role = 'INSTRUCTOR'")->execute([$id, $org['id']]);
        redirect('prowadzacy.php');
    }
}

$stmt = db()->prepare("SELECT * FROM users WHERE org_id = ? AND role IN ('ORG_ADMIN','INSTRUCTOR') ORDER BY role ASC, name ASC");
$stmt->execute([$org['id']]);
$staff = $stmt->fetchAll();

$pageTitle = 'Prowadzący — ' . $org['name'];
require __DIR__ . '/includes/layout_top.php';
?>
<div class="container section">
  <h1 class="section-title">Prowadzący</h1>
  <?php if ($error): ?><p class="alert alert-error"><?= e($error) ?></p><?php endif; ?>

  <div class="table-wrap mt-6 reveal">
    <table class="data-table">
      <thead><tr><th>Imię i nazwisko</th><th>E-mail</th><th>Rola</th><th>Stawka/h</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($staff as $s): ?>
          <tr>
            <td><?= e($s['name']) ?></td>
            <td><?= e($s['email']) ?></td>
            <td><?= $s['role'] === 'ORG_ADMIN' ? 'Właściciel' : 'Prowadzący' ?></td>
            <td><?= $s['wage_hourly_cents'] ? format_money((int) $s['wage_hourly_cents']) : '—' ?></td>
            <td><?php if ($s['role'] === 'INSTRUCTOR'): ?>
              <form method="post" onsubmit="return confirm('Usunąć to konto prowadzącego?');"><?= csrf_field() ?><input type="hidden" name="_action" value="delete"><input type="hidden" name="id" value="<?= $s['id'] ?>"><button class="btn btn-outline btn-sm">Usuń</button></form>
            <?php endif; ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <h2 class="mt-8">Dodaj prowadzącego</h2>
  <?php if ($plan): ?><p class="text-muted">Plan <?= e($plan['name']) ?>: <?= org_instructor_count((int) $org['id']) ?> / <?= $plan['max_instructors'] >= 999 ? '∞' : (int) $plan['max_instructors'] ?> prowadzących.</p><?php endif; ?>
  <form method="post" class="mt-4 form-card reveal">
    <?= csrf_field() ?>
    <input type="hidden" name="_action" value="add">
    <div class="grid grid-2">
      <div class="field"><label>Imię i nazwisko</label><input name="name" required></div>
      <div class="field"><label>E-mail (login)</label><input name="email" type="email" required></div>
      <div class="field"><label>Hasło startowe</label><input name="password" type="password" minlength="8" required></div>
      <div class="field"><label>Stawka za godzinę (zł, opcjonalnie)</label><input name="wage" placeholder="np. 60"></div>
    </div>
    <button type="submit" class="btn btn-primary">Dodaj prowadzącego</button>
  </form>
</div>
<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
