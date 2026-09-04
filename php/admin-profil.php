<?php
require_once __DIR__ . '/includes/bootstrap.php';
$user = require_staff();

$error = $_GET['error'] ?? null;
$me = db()->prepare('SELECT * FROM users WHERE id = ?');
$me->execute([$user['id']]);
$me = $me->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $name = trim((string) ($_POST['name'] ?? ''));
    $email = trim(strtolower((string) ($_POST['email'] ?? '')));
    $bio = trim((string) ($_POST['bio'] ?? ''));
    $currentPassword = (string) ($_POST['currentPassword'] ?? '');
    $newPassword = (string) ($_POST['newPassword'] ?? '');

    if (mb_strlen($name) < 2) {
        redirect_with('admin-profil.php', ['error' => 'Podaj imię i nazwisko.']);
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        redirect_with('admin-profil.php', ['error' => 'Podaj poprawny adres e-mail.']);
    }
    if ($newPassword !== '' && mb_strlen($newPassword) < 8) {
        redirect_with('admin-profil.php', ['error' => 'Nowe hasło musi mieć co najmniej 8 znaków.']);
    }
    if (!verify_password($currentPassword, $me['password_hash'])) {
        redirect_with('admin-profil.php', ['error' => 'Obecne hasło jest nieprawidłowe.']);
    }
    $taken = db()->prepare('SELECT id FROM users WHERE email = ? AND id != ?');
    $taken->execute([$email, $user['id']]);
    if ($taken->fetch()) {
        redirect_with('admin-profil.php', ['error' => 'Ten adres e-mail jest już zajęty przez inne konto.']);
    }

    $avatarUrl = $me['avatar_url'];
    if (!empty($_FILES['photo']['name'])) {
        try {
            $saved = save_uploaded_image($_FILES['photo'], 'instructors', (string) $user['id']);
            if ($saved) {
                delete_uploaded_file($me['avatar_url']);
                $avatarUrl = $saved;
            }
        } catch (RuntimeException $e) {
            redirect_with('admin-profil.php', ['error' => $e->getMessage()]);
        }
    } elseif (!empty($_POST['removePhoto'])) {
        delete_uploaded_file($me['avatar_url']);
        $avatarUrl = null;
    }

    $sql = 'UPDATE users SET name=?, email=?, bio=?, avatar_url=?';
    $params = [$name, $email, $bio ?: null, $avatarUrl];
    if ($newPassword !== '') {
        $sql .= ', password_hash=?';
        $params[] = hash_password($newPassword);
    }
    $sql .= ' WHERE id=?';
    $params[] = $user['id'];
    db()->prepare($sql)->execute($params);

    // Odśwież sesję, żeby nawigacja od razu pokazała nową nazwę.
    login_user(['id' => $user['id'], 'name' => $name, 'email' => $email, 'role' => $user['role']]);

    redirect('admin-profil.php?saved=1');
}

$pageTitle = 'Mój profil — INNOVA';
$notebookTheme = true;
require __DIR__ . '/includes/layout_top.php';
?>
<div class="container-sm" style="padding:40px 16px;">
  <?php include __DIR__ . '/includes/partials/admin-nav.php'; ?>

  <h1 style="font-size:1.4rem;">Mój profil</h1>
  <p class="text-muted mt-2">
    <?= $me['role'] === 'ADMIN'
        ? 'To jest Twoje konto master admina (właściciela pracowni). Tutaj zmienisz swoją nazwę wyświetlaną, e-mail, zdjęcie i hasło.'
        : 'Tutaj zmienisz swoje dane widoczne na stronie „Poznaj nas”, e-mail i hasło.' ?>
  </p>

  <?php if ($error): ?><p class="alert alert-error"><?= e($error) ?></p><?php endif; ?>
  <?php if (isset($_GET['saved'])): ?><p class="alert alert-success">Zapisano zmiany.</p><?php endif; ?>

  <form method="post" enctype="multipart/form-data" class="card mt-6">
    <?= csrf_field() ?>
    <div class="flex items-center gap-3">
      <?php if ($me['avatar_url']): ?>
        <img src="<?= e(url($me['avatar_url'])) ?>" alt="" class="avatar" style="width:64px; height:64px;">
      <?php else: ?>
        <div class="avatar-placeholder" style="width:64px; height:64px; font-size:1.4rem;"><?= e(mb_substr($me['name'], 0, 1)) ?></div>
      <?php endif; ?>
      <div style="flex:1;">
        <label for="photo">Zdjęcie profilowe</label>
        <input id="photo" name="photo" type="file" accept="image/png,image/jpeg,image/webp,image/gif">
        <?php if ($me['avatar_url']): ?>
          <label class="checkbox-row mt-2"><input type="checkbox" name="removePhoto"> Usuń obecne zdjęcie</label>
        <?php endif; ?>
      </div>
    </div>

    <div class="field mt-4">
      <label for="name">Imię i nazwisko / nazwa wyświetlana</label>
      <input id="name" name="name" required value="<?= e($me['name']) ?>">
    </div>
    <div class="field">
      <label for="email">E-mail (login)</label>
      <input id="email" name="email" type="email" required value="<?= e($me['email']) ?>">
    </div>
    <div class="field">
      <label for="bio">Krótka notka (widoczna na „Poznaj nas”)</label>
      <textarea id="bio" name="bio" rows="3" maxlength="600"><?= e($me['bio'] ?? '') ?></textarea>
    </div>

    <hr style="border-color:var(--border); margin:16px 0;">

    <div class="field">
      <label for="newPassword">Nowe hasło (zostaw puste, żeby nie zmieniać)</label>
      <input id="newPassword" name="newPassword" type="password" minlength="8" autocomplete="new-password">
    </div>
    <div class="field">
      <label for="currentPassword">Obecne hasło (wymagane, żeby potwierdzić zmiany)</label>
      <input id="currentPassword" name="currentPassword" type="password" required autocomplete="current-password">
    </div>

    <button type="submit" class="btn btn-primary">Zapisz zmiany</button>
  </form>
</div>
<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
