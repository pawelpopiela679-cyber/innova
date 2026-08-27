<?php
require_once __DIR__ . '/includes/bootstrap.php';

$error = null;
$next = $_GET['next'] ?? $_POST['next'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $name = trim((string) ($_POST['name'] ?? ''));
    $email = trim(strtolower((string) ($_POST['email'] ?? '')));
    $phone = trim((string) ($_POST['phone'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $next = (string) ($_POST['next'] ?? '');

    if (mb_strlen($name) < 2) {
        $error = 'Podaj imię i nazwisko.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Podaj poprawny adres e-mail.';
    } elseif (mb_strlen($password) < 8) {
        $error = 'Hasło musi mieć co najmniej 8 znaków.';
    } else {
        $exists = db()->prepare('SELECT id FROM users WHERE email = ?');
        $exists->execute([$email]);
        if ($exists->fetch()) {
            $error = 'Konto z tym adresem e-mail już istnieje.';
        } else {
            db()->prepare('INSERT INTO users (name, email, phone, password_hash, role) VALUES (?,?,?,?,?)')
                ->execute([$name, $email, $phone ?: null, hash_password($password), 'PARENT']);
            $newId = db_last_id(db());
            login_user(['id' => $newId, 'name' => $name, 'email' => $email, 'role' => 'PARENT']);
            redirect($next !== '' ? $next : 'panel.php');
        }
    }
}

$pageTitle = 'Załóż konto — INNOVA';
require __DIR__ . '/includes/layout_top.php';
?>
<div class="container-sm" style="padding-top:48px; padding-bottom:48px;">
  <h1 style="font-size:1.6rem;">Załóż konto rodzica</h1>
  <p class="text-muted">Konto pozwala dodać dzieci i zapisywać je na zajęcia.</p>

  <?php if ($error): ?><p class="alert alert-error"><?= e($error) ?></p><?php endif; ?>

  <form method="post" class="mt-6">
    <?= csrf_field() ?>
    <input type="hidden" name="next" value="<?= e($next) ?>">
    <div class="field">
      <label for="name">Imię i nazwisko</label>
      <input id="name" name="name" required value="<?= e($_POST['name'] ?? '') ?>">
    </div>
    <div class="field">
      <label for="email">E-mail</label>
      <input id="email" name="email" type="email" required value="<?= e($_POST['email'] ?? '') ?>">
    </div>
    <div class="field">
      <label for="phone">Telefon (opcjonalnie)</label>
      <input id="phone" name="phone" value="<?= e($_POST['phone'] ?? '') ?>">
    </div>
    <div class="field">
      <label for="password">Hasło</label>
      <input id="password" name="password" type="password" required minlength="8" autocomplete="new-password">
      <p class="field-hint">Co najmniej 8 znaków.</p>
    </div>
    <button type="submit" class="btn btn-primary" style="width:100%;">Załóż konto</button>
  </form>

  <p class="mt-6 text-muted">Masz już konto? <a href="<?= e(url('logowanie.php')) ?>" style="text-decoration:underline;">Zaloguj się</a></p>
</div>
<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
