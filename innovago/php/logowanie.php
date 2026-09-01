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
    } elseif ($locked = login_rate_limit_check($email)) {
        $error = $locked;
    } else {
        $stmt = db()->prepare('SELECT * FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $u = $stmt->fetch();
        if (!$u || !verify_password($password, $u['password_hash'])) {
            login_rate_limit_record_failure($email);
            $error = 'Nieprawidłowy e-mail lub hasło.';
        } else {
            login_rate_limit_record_success($email);
            login_user($u);
            if ($next !== '') {
                redirect($next);
            }
            redirect(match ($u['role']) {
                'SUPER_ADMIN' => 'superadmin.php',
                'ORG_ADMIN', 'INSTRUCTOR' => 'admin.php',
                default => 'panel-rodzic.php',
            });
        }
    }
}

$pageTitle = 'Zaloguj się — InnovaGo';
require __DIR__ . '/includes/layout_top.php';
?>
<div class="container-sm section">
  <h1 class="section-title">Zaloguj się</h1>

  <?php if ($error): ?><p class="alert alert-error"><?= e($error) ?></p><?php endif; ?>

  <form method="post" class="mt-6 auth-card reveal">
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

  <p class="mt-6 text-muted">Prowadzisz szkółkę i nie masz jeszcze konta? <a href="<?= e(url('rejestracja-organizacji.php')) ?>">Załóż organizację</a></p>
</div>
<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
