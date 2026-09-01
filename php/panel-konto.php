<?php
/** Moje dane — realizacja praw RODO dla rodzica: eksport i usunięcie konta. */
require_once __DIR__ . '/includes/bootstrap.php';
$user = require_login('panel-konto.php');

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['export'] ?? '') === '1') {
    $data = rodo_export_user_data((int) $user['id']);
    rodo_send_export_download($data, 'innova-moje-dane');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['_action'] ?? '';
    if ($action === 'delete_account') {
        $password = (string) ($_POST['password'] ?? '');
        $fresh = db()->prepare('SELECT password_hash FROM users WHERE id = ?');
        $fresh->execute([$user['id']]);
        $fresh = $fresh->fetch();
        if (!$fresh || !verify_password($password, $fresh['password_hash'])) {
            $error = 'Nieprawidłowe hasło — konto NIE zostało usunięte.';
        } else {
            $userId = (int) $user['id'];
            logout_user();
            rodo_delete_user($userId);
            redirect('index.php?account_deleted=1');
        }
    }
}

$stmt = db()->prepare('SELECT COUNT(*) c FROM children WHERE parent_id = ?');
$stmt->execute([$user['id']]);
$childrenCount = (int) $stmt->fetch()['c'];

$stmt = db()->prepare('SELECT COUNT(*) c FROM enrollments WHERE parent_id = ?');
$stmt->execute([$user['id']]);
$enrollmentCount = (int) $stmt->fetch()['c'];

$pageTitle = 'Moje dane — INNOVA';
require __DIR__ . '/includes/layout_top.php';
?>
<div class="container-md" style="padding:40px 16px;">
  <?php include __DIR__ . '/includes/partials/panel-nav.php'; ?>
  <h1 style="font-size:1.6rem;">Moje dane</h1>
  <p class="text-muted">Zgodnie z RODO masz prawo pobrać kopię swoich danych i żądać ich usunięcia.</p>

  <?php if ($error): ?><p class="alert alert-error"><?= e($error) ?></p><?php endif; ?>

  <div class="card mt-6">
    <h2 style="font-size:1.1rem;">Co przechowujemy</h2>
    <p class="text-muted mt-2">Konto: <?= e($user['name']) ?> (<?= e($user['email']) ?>) ·
      <?= $childrenCount ?> <?= $childrenCount === 1 ? 'dziecko' : 'dzieci' ?> na koncie ·
      <?= $enrollmentCount ?> zgłoszeń na zajęcia (historia).</p>
    <a href="<?= e(url('panel-konto.php?export=1')) ?>" class="btn btn-primary mt-4">Pobierz moje dane (JSON)</a>
  </div>

  <div class="card mt-6" style="border-color:#e0a0a0;">
    <h2 style="font-size:1.1rem; color:#8a2f2f;">Usuń moje konto</h2>
    <p class="text-muted mt-2">Usuwa Twoje konto, dane wszystkich Twoich dzieci i całą historię zgłoszeń na zajęcia —
      <strong>nieodwracalnie</strong>. Pobierz dane powyżej, jeśli chcesz zachować kopię przed usunięciem.</p>
    <form method="post" class="mt-4" onsubmit="return confirm('To NIEODWRACALNIE usunie Twoje konto, dzieci i historię zapisów. Na pewno?');">
      <?= csrf_field() ?>
      <input type="hidden" name="_action" value="delete_account">
      <div class="field" style="max-width:320px;">
        <label for="password">Potwierdź hasłem</label>
        <input id="password" name="password" type="password" required autocomplete="current-password">
      </div>
      <button type="submit" class="btn btn-danger">Trwale usuń moje konto</button>
    </form>
  </div>
</div>
<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
