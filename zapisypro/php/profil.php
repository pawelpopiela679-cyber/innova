<?php
require_once __DIR__ . '/includes/bootstrap.php';

$user = require_login();
$fresh = db()->prepare('SELECT * FROM users WHERE id = ?');
$fresh->execute([$user['id']]);
$fresh = $fresh->fetch();

$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $name = trim((string) ($_POST['name'] ?? ''));
    $email = trim(strtolower((string) ($_POST['email'] ?? '')));
    $phone = trim((string) ($_POST['phone'] ?? ''));
    $bio = trim((string) ($_POST['bio'] ?? ''));
    $currentPassword = (string) ($_POST['current_password'] ?? '');
    $newPassword = (string) ($_POST['new_password'] ?? '');

    if (mb_strlen($name) < 2) {
        $error = 'Podaj imię i nazwisko.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Podaj poprawny adres e-mail.';
    } else {
        $dup = db()->prepare('SELECT id FROM users WHERE email = ? AND id != ?');
        $dup->execute([$email, $user['id']]);
        if ($dup->fetch()) {
            $error = 'Ten adres e-mail jest już zajęty.';
        } elseif ($newPassword !== '' && !verify_password($currentPassword, $fresh['password_hash'])) {
            $error = 'Nieprawidłowe obecne hasło.';
        } elseif ($newPassword !== '' && mb_strlen($newPassword) < 8) {
            $error = 'Nowe hasło musi mieć co najmniej 8 znaków.';
        } else {
            $avatarUrl = $fresh['avatar_url'];
            if (!empty($_FILES['avatar']['name'])) {
                try {
                    $uploaded = save_uploaded_image($_FILES['avatar'], 'avatars', 'user-' . $user['id']);
                    if ($uploaded) {
                        delete_uploaded_file($avatarUrl);
                        $avatarUrl = $uploaded;
                    }
                } catch (RuntimeException $e) {
                    $error = $e->getMessage();
                }
            }

            if (!$error) {
                if ($newPassword !== '') {
                    db()->prepare('UPDATE users SET name=?, email=?, phone=?, bio=?, avatar_url=?, password_hash=? WHERE id=?')
                        ->execute([$name, $email, $phone ?: null, $bio ?: null, $avatarUrl, hash_password($newPassword), $user['id']]);
                } else {
                    db()->prepare('UPDATE users SET name=?, email=?, phone=?, bio=?, avatar_url=? WHERE id=?')
                        ->execute([$name, $email, $phone ?: null, $bio ?: null, $avatarUrl, $user['id']]);
                }
                $fresh['name'] = $name;
                $fresh['email'] = $email;
                $_SESSION['user']['name'] = $name;
                $_SESSION['user']['email'] = $email;
                $success = 'Zapisano zmiany.';
                $fresh = db()->prepare('SELECT * FROM users WHERE id = ?');
                $fresh->execute([$user['id']]);
                $fresh = $fresh->fetch();
            }
        }
    }
}

$pageTitle = 'Mój profil — ZapisyPro';
require __DIR__ . '/includes/layout_top.php';
?>
<div class="container-sm section">
  <h1 class="section-title">Mój profil</h1>

  <?php if ($error): ?><p class="alert alert-error"><?= e($error) ?></p><?php endif; ?>
  <?php if ($success): ?><p class="alert alert-success"><?= e($success) ?></p><?php endif; ?>

  <form method="post" enctype="multipart/form-data" class="mt-6 auth-card reveal">
    <?= csrf_field() ?>
    <?php if ($fresh['avatar_url']): ?>
      <img src="<?= e(url($fresh['avatar_url'])) ?>" alt="" style="width:72px;height:72px;border-radius:50%;object-fit:cover;margin-bottom:12px;">
    <?php endif; ?>
    <div class="field">
      <label for="avatar">Zdjęcie profilowe</label>
      <input id="avatar" name="avatar" type="file" accept="image/*">
    </div>
    <div class="field">
      <label for="name">Imię i nazwisko</label>
      <input id="name" name="name" required value="<?= e($fresh['name']) ?>">
    </div>
    <div class="field">
      <label for="email">E-mail</label>
      <input id="email" name="email" type="email" required value="<?= e($fresh['email']) ?>">
    </div>
    <div class="field">
      <label for="phone">Telefon</label>
      <input id="phone" name="phone" value="<?= e($fresh['phone'] ?? '') ?>">
    </div>
    <?php if (in_array($user['role'], ['ORG_ADMIN', 'INSTRUCTOR'], true)): ?>
    <div class="field">
      <label for="bio">Krótka notka (widoczna w organizacji)</label>
      <textarea id="bio" name="bio" rows="3"><?= e($fresh['bio'] ?? '') ?></textarea>
    </div>
    <?php endif; ?>
    <hr style="margin:20px 0;border-color:var(--border);">
    <p class="text-muted" style="font-size:.85rem;">Zmiana hasła (opcjonalnie):</p>
    <div class="field">
      <label for="current_password">Obecne hasło</label>
      <input id="current_password" name="current_password" type="password" autocomplete="current-password">
    </div>
    <div class="field">
      <label for="new_password">Nowe hasło</label>
      <input id="new_password" name="new_password" type="password" minlength="8" autocomplete="new-password">
    </div>
    <button type="submit" class="btn btn-primary" style="width:100%;">Zapisz zmiany</button>
  </form>
</div>
<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
