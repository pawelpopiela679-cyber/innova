<?php
require_once __DIR__ . '/includes/bootstrap.php';

$error = null;
$next = $_GET['next'] ?? $_POST['next'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $email = trim(strtolower((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');
    $next = (string) ($_POST['next'] ?? '');

    if ($email === '' || $password === '') {
        $error = 'Podaj e-mail i hasło.';
    } else {
        $stmt = db()->prepare('SELECT * FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $u = $stmt->fetch();
        if (!$u || !verify_password($password, $u['password_hash'])) {
            $error = 'Nieprawidłowy e-mail lub hasło.';
        } else {
            login_user($u);
            if ($next !== '') {
                redirect($next);
            }
            redirect($u['role'] === 'ADMIN' || $u['role'] === 'INSTRUCTOR' ? 'admin.php' : 'panel.php');
        }
    }
}

$pageTitle = 'Zaloguj się — INNOVA';
require __DIR__ . '/includes/layout_top.php';
?>
<div class="container-sm" style="padding-top:48px; padding-bottom:48px;">
  <h1 style="font-size:1.6rem;">Zaloguj się</h1>
  <p class="text-muted">Zaloguj się, aby zapisać dziecko na zajęcia lub sprawdzić swoje zapisy.</p>

  <?php if ($error): ?><p class="alert alert-error"><?= e($error) ?></p><?php endif; ?>

  <form method="post" class="mt-6">
    <?= csrf_field() ?>
    <input type="hidden" name="next" value="<?= e($next) ?>">
    <div class="field">
      <label for="email">E-mail</label>
      <input id="email" name="email" type="email" required autocomplete="email">
    </div>
    <div class="field">
      <label for="password">Hasło</label>
      <input id="password" name="password" type="password" required autocomplete="current-password">
    </div>
    <button type="submit" class="btn btn-primary" style="width:100%;">Zaloguj się</button>
  </form>

  <p class="mt-6 text-muted">Nie masz jeszcze konta? <a href="<?= e(url('rejestracja.php')) ?>" style="text-decoration:underline;">Zarejestruj się</a></p>
</div>
<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
