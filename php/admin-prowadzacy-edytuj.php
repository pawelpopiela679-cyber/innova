<?php
require_once __DIR__ . '/includes/bootstrap.php';
$user = require_admin();

$targetId = (int) ($_GET['id'] ?? $_POST['userId'] ?? 0);
$target = db()->prepare('SELECT * FROM users WHERE id = ?');
$target->execute([$targetId]);
$target = $target->fetch();

if (!$target || $target['role'] !== 'INSTRUCTOR') {
    http_response_code(404);
    $pageTitle = 'Nie znaleziono — INNOVA';
    $notebookTheme = true;
require __DIR__ . '/includes/layout_top.php';
    echo '<div class="container-sm text-center" style="padding:64px 16px;"><h1>404</h1><p class="text-muted">Nie znaleziono takiego prowadzącego.</p></div>';
    require __DIR__ . '/includes/layout_bottom.php';
    exit;
}

$error = $_GET['error'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $name = trim((string) ($_POST['name'] ?? ''));
    $email = trim(strtolower((string) ($_POST['email'] ?? '')));
    $bio = trim((string) ($_POST['bio'] ?? ''));
    $newPassword = (string) ($_POST['newPassword'] ?? '');

    if (mb_strlen($name) < 2) {
        redirect_with('admin-prowadzacy-edytuj.php', ['id' => $targetId, 'error' => 'Podaj imię i nazwisko.']);
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        redirect_with('admin-prowadzacy-edytuj.php', ['id' => $targetId, 'error' => 'Podaj poprawny adres e-mail.']);
    }
    if ($newPassword !== '' && mb_strlen($newPassword) < 8) {
        redirect_with('admin-prowadzacy-edytuj.php', ['id' => $targetId, 'error' => 'Nowe hasło musi mieć co najmniej 8 znaków.']);
    }
    $taken = db()->prepare('SELECT id FROM users WHERE email = ? AND id != ?');
    $taken->execute([$email, $targetId]);
    if ($taken->fetch()) {
        redirect_with('admin-prowadzacy-edytuj.php', ['id' => $targetId, 'error' => 'Ten adres e-mail jest już zajęty przez inne konto.']);
    }

    $avatarUrl = $target['avatar_url'];
    if (!empty($_FILES['photo']['name'])) {
        try {
            $saved = save_uploaded_image($_FILES['photo'], 'instructors', (string) $targetId);
            if ($saved) {
                delete_uploaded_file($target['avatar_url']);
                $avatarUrl = $saved;
            }
        } catch (RuntimeException $e) {
            redirect_with('admin-prowadzacy-edytuj.php', ['id' => $targetId, 'error' => $e->getMessage()]);
        }
    } elseif (!empty($_POST['removePhoto'])) {
        delete_uploaded_file($target['avatar_url']);
        $avatarUrl = null;
    }

    $sql = 'UPDATE users SET name=?, email=?, bio=?, avatar_url=?';
    $params = [$name, $email, $bio ?: null, $avatarUrl];
    if ($newPassword !== '') {
        $sql .= ', password_hash=?';
        $params[] = hash_password($newPassword);
    }
    $sql .= ' WHERE id=?';
    $params[] = $targetId;
    db()->prepare($sql)->execute($params);

    redirect('admin-prowadzacy.php?updated=1');
}

$pageTitle = 'Edytuj prowadzącego — INNOVA';
$notebookTheme = true;
require __DIR__ . '/includes/layout_top.php';
?>
<div class="container-sm" style="padding:40px 16px;">
  <?php include __DIR__ . '/includes/partials/admin-nav.php'; ?>

  <h1 style="font-size:1.4rem;">Edytuj: <?= e($target['name']) ?></h1>

  <?php if ($error): ?><p class="alert alert-error"><?= e($error) ?></p><?php endif; ?>

  <form method="post" enctype="multipart/form-data" class="card mt-6">
    <?= csrf_field() ?>
    <input type="hidden" name="userId" value="<?= (int) $target['id'] ?>">
    <div class="flex items-center gap-3">
      <?php if ($target['avatar_url']): ?>
        <img src="<?= e(url($target['avatar_url'])) ?>" alt="" class="avatar" style="width:64px; height:64px;">
      <?php else: ?>
        <div class="avatar-placeholder" style="width:64px; height:64px; font-size:1.4rem;"><?= e(mb_substr($target['name'], 0, 1)) ?></div>
      <?php endif; ?>
      <div style="flex:1;">
        <label for="photo">Nowe zdjęcie (opcjonalnie)</label>
        <input id="photo" name="photo" type="file" accept="image/png,image/jpeg,image/webp,image/gif">
        <?php if ($target['avatar_url']): ?>
          <label class="checkbox-row mt-2"><input type="checkbox" name="removePhoto"> Usuń obecne zdjęcie</label>
        <?php endif; ?>
      </div>
    </div>

    <div class="field mt-4">
      <label for="name">Imię i nazwisko</label>
      <input id="name" name="name" required value="<?= e($target['name']) ?>">
    </div>
    <div class="field">
      <label for="email">E-mail</label>
      <input id="email" name="email" type="email" required value="<?= e($target['email']) ?>">
    </div>
    <div class="field">
      <label for="bio">Krótka notka (widoczna na „Poznaj nas”)</label>
      <textarea id="bio" name="bio" rows="3" maxlength="600"><?= e($target['bio'] ?? '') ?></textarea>
    </div>
    <div class="field">
      <label for="newPassword">Nowe hasło (zostaw puste, żeby nie zmieniać)</label>
      <input id="newPassword" name="newPassword" type="password" minlength="8" autocomplete="new-password">
    </div>

    <div class="flex gap-2 mt-4">
      <button type="submit" class="btn btn-primary">Zapisz zmiany</button>
      <a href="<?= e(url('admin-prowadzacy.php')) ?>" class="btn btn-outline">Anuluj</a>
    </div>
  </form>
</div>
<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
