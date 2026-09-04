<?php
require_once __DIR__ . '/includes/bootstrap.php';
$user = require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['_action'] ?? '';

    if ($action === 'create') {
        $name = trim((string) ($_POST['name'] ?? ''));
        $email = trim(strtolower((string) ($_POST['email'] ?? '')));
        $password = (string) ($_POST['password'] ?? '');
        $bio = trim((string) ($_POST['bio'] ?? ''));
        $canManageGroups = !empty($_POST['canManageGroups']) ? 1 : 0;

        if (mb_strlen($name) < 2) {
            redirect_with('admin-prowadzacy.php', ['error' => 'Podaj imię i nazwisko.']);
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            redirect_with('admin-prowadzacy.php', ['error' => 'Podaj poprawny adres e-mail.']);
        }
        if (mb_strlen($password) < 8) {
            redirect_with('admin-prowadzacy.php', ['error' => 'Hasło musi mieć co najmniej 8 znaków.']);
        }
        $exists = db()->prepare('SELECT id FROM users WHERE email = ?');
        $exists->execute([$email]);
        if ($exists->fetch()) {
            redirect_with('admin-prowadzacy.php', ['error' => 'Konto z tym adresem e-mail już istnieje.']);
        }

        db()->prepare('INSERT INTO users (name, email, bio, password_hash, role, can_manage_groups) VALUES (?,?,?,?,?,?)')
            ->execute([$name, $email, $bio ?: null, hash_password($password), 'INSTRUCTOR', $canManageGroups]);
        $newId = db_last_id(db());

        if (!empty($_FILES['photo']['name'])) {
            try {
                $saved = save_uploaded_image($_FILES['photo'], 'instructors', (string) $newId);
                if ($saved) {
                    db()->prepare('UPDATE users SET avatar_url = ? WHERE id = ?')->execute([$saved, $newId]);
                }
            } catch (RuntimeException $e) {
                redirect_with('admin-prowadzacy.php', ['added' => 1, 'error' => $e->getMessage()]);
            }
        }
        redirect('admin-prowadzacy.php?added=1');
    }

    if ($action === 'delete') {
        $targetId = (int) ($_POST['userId'] ?? 0);
        if ($targetId === (int) $user['id']) {
            redirect_with('admin-prowadzacy.php', ['error' => 'Nie możesz usunąć własnego konta.']);
        }
        $target = db()->prepare('SELECT * FROM users WHERE id = ?');
        $target->execute([$targetId]);
        $target = $target->fetch();
        if (!$target || $target['role'] !== 'INSTRUCTOR') {
            redirect_with('admin-prowadzacy.php', ['error' => 'Nie znaleziono prowadzącego.']);
        }
        db()->prepare('UPDATE class_sessions SET instructor_id = NULL WHERE instructor_id = ?')->execute([$targetId]);
        delete_uploaded_file($target['avatar_url']);
        db()->prepare('DELETE FROM users WHERE id = ?')->execute([$targetId]);
        redirect('admin-prowadzacy.php?deleted=1');
    }
}

$staff = db()->query("SELECT * FROM users WHERE role IN ('ADMIN','INSTRUCTOR') ORDER BY role ASC, name ASC")->fetchAll();
$roleLabel = ['ADMIN' => 'Właściciel (master admin)', 'INSTRUCTOR' => 'Prowadzący'];

$pageTitle = 'Prowadzący i konta — INNOVA';
$notebookTheme = true;
require __DIR__ . '/includes/layout_top.php';
?>
<div class="container-md" style="padding:40px 16px;">
  <?php include __DIR__ . '/includes/partials/admin-nav.php'; ?>

  <h1 style="font-size:1.6rem;">Prowadzący i konta</h1>
  <p class="text-muted mt-2">
    Jako właściciel pracowni (master admin) możesz zakładać, edytować i usuwać konta prowadzących —
    po zalogowaniu każdy prowadzący sam dodaje swój grafik w <strong>+ Nowe zajęcia</strong>.
  </p>

  <?php if (isset($_GET['error'])): ?><p class="alert alert-error"><?= e($_GET['error']) ?></p><?php endif; ?>
  <?php if (isset($_GET['added'])): ?><p class="alert alert-success">Konto prowadzącego zostało utworzone — przekaż mu e-mail i hasło.</p><?php endif; ?>
  <?php if (isset($_GET['updated'])): ?><p class="alert alert-success">Dane prowadzącego zostały zaktualizowane.</p><?php endif; ?>
  <?php if (isset($_GET['deleted'])): ?><p class="alert alert-info">Konto prowadzącego zostało usunięte. Zaplanowane zajęcia zostają w kalendarzu.</p><?php endif; ?>

  <div class="mt-6" style="display:flex; flex-direction:column; gap:12px;">
    <?php foreach ($staff as $u): ?>
      <div class="card flex items-center justify-between" style="justify-content:space-between; flex-wrap:wrap; gap:12px;">
        <div class="flex items-center gap-3">
          <?php if ($u['avatar_url']): ?>
            <img src="<?= e(url($u['avatar_url'])) ?>" alt="" class="avatar">
          <?php else: ?>
            <div class="avatar-placeholder"><?= e(mb_substr($u['name'], 0, 1)) ?></div>
          <?php endif; ?>
          <div>
            <p style="font-weight:700;"><?= e($u['name']) ?> <span class="badge" style="background:color-mix(in srgb, var(--sage) 20%, var(--background)); color:var(--sage);"><?= $roleLabel[$u['role']] ?? $u['role'] ?></span>
              <?php if ($u['role'] === 'INSTRUCTOR' && $u['can_manage_groups']): ?><span class="badge" style="background:color-mix(in srgb, var(--coral) 22%, var(--background)); color:var(--coral);">Dostęp do grup</span><?php endif; ?>
            </p>
            <p class="text-muted"><?= e($u['email']) ?></p>
          </div>
        </div>
        <div class="flex items-center gap-3">
          <p class="text-muted" style="font-size:0.78rem;">Konto od <?= e(format_pl_date($u['created_at'])) ?></p>
          <?php if ($u['role'] === 'INSTRUCTOR'): ?>
            <a href="<?= e(url('admin-prowadzacy-edytuj.php?id=' . $u['id'])) ?>" class="btn btn-outline btn-sm">Edytuj</a>
            <form method="post">
              <?= csrf_field() ?>
              <input type="hidden" name="_action" value="delete">
              <input type="hidden" name="userId" value="<?= (int) $u['id'] ?>">
              <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Na pewno usunąć to konto?')">Usuń</button>
            </form>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="card mt-8">
    <h2 style="font-size:1.1rem;">Dodaj konto prowadzącego</h2>
    <p class="text-muted mt-2">Ustaw hasło startowe i przekaż je prowadzącemu — może je potem zmienić w edycji konta.</p>
    <form method="post" enctype="multipart/form-data" class="mt-4 grid grid-2">
      <?= csrf_field() ?>
      <input type="hidden" name="_action" value="create">
      <div class="field">
        <label for="name">Imię i nazwisko</label>
        <input id="name" name="name" required>
      </div>
      <div class="field">
        <label for="email">E-mail (login)</label>
        <input id="email" name="email" type="email" required>
      </div>
      <div class="field">
        <label for="password">Hasło startowe</label>
        <input id="password" name="password" type="text" required minlength="8" placeholder="co najmniej 8 znaków">
      </div>
      <div class="field">
        <label for="photo">Zdjęcie (opcjonalnie)</label>
        <input id="photo" name="photo" type="file" accept="image/png,image/jpeg,image/webp,image/gif">
      </div>
      <div class="field" style="grid-column:1/-1;">
        <label for="bio">Krótka notka na stronę „Poznaj nas” (opcjonalnie)</label>
        <textarea id="bio" name="bio" rows="2" maxlength="600"></textarea>
      </div>
      <div class="field" style="grid-column:1/-1;">
        <label class="checkbox-row"><input type="checkbox" name="canManageGroups"> Rozszerzone uprawnienia — panel zarządzania grupami</label>
      </div>
      <div style="grid-column:1/-1;">
        <button type="submit" class="btn btn-sm" style="background:var(--sage); color:#fff;">Utwórz konto prowadzącego</button>
      </div>
    </form>
  </div>
</div>
<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
