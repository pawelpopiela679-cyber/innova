<?php
require_once __DIR__ . '/includes/bootstrap.php';
$user = require_staff();

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$post = db()->prepare('SELECT * FROM news_posts WHERE id = ?');
$post->execute([$id]);
$post = $post->fetch();

if (!$post || ($user['role'] !== 'ADMIN' && (int) $post['author_id'] !== (int) $user['id'])) {
    http_response_code(404);
    $pageTitle = 'Nie znaleziono — INNOVA';
    $notebookTheme = true;
    require __DIR__ . '/includes/layout_top.php';
    echo '<div class="container-sm text-center" style="padding:64px 16px;"><h1>404</h1><p class="text-muted">Nie znaleziono wpisu albo nie masz do niego dostępu.</p></div>';
    require __DIR__ . '/includes/layout_bottom.php';
    exit;
}

$error = $_GET['error'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $title = trim((string) ($_POST['title'] ?? ''));
    $content = trim((string) ($_POST['content'] ?? ''));

    if ($title === '') {
        redirect_with('admin-aktualnosci-edytuj.php', ['id' => $id, 'error' => 'Podaj tytuł.']);
    }
    if ($content === '') {
        redirect_with('admin-aktualnosci-edytuj.php', ['id' => $id, 'error' => 'Podaj treść.']);
    }

    db()->prepare('UPDATE news_posts SET title=?, content=?, updated_at=CURRENT_TIMESTAMP WHERE id=?')
        ->execute([$title, $content, $id]);
    redirect('admin-aktualnosci.php?updated=1');
}

$pageTitle = 'Edytuj wpis — INNOVA';
$notebookTheme = true;
require __DIR__ . '/includes/layout_top.php';
?>
<div class="container-md" style="padding:40px 16px;">
  <?php include __DIR__ . '/includes/partials/admin-nav.php'; ?>

  <h1 style="font-size:1.4rem;">Edytuj: <?= e($post['title']) ?></h1>

  <?php if ($error): ?><p class="alert alert-error"><?= e($error) ?></p><?php endif; ?>

  <form method="post" class="card mt-6">
    <?= csrf_field() ?>
    <div class="field">
      <label for="title">Tytuł</label>
      <input id="title" name="title" required value="<?= e($post['title']) ?>">
    </div>
    <div class="field">
      <label for="content">Treść</label>
      <textarea id="content" name="content" required rows="10"><?= e($post['content']) ?></textarea>
    </div>
    <div class="flex gap-2 mt-4">
      <button type="submit" class="btn btn-primary">Zapisz zmiany</button>
      <a href="<?= e(url('admin-aktualnosci.php')) ?>" class="btn btn-outline">Anuluj</a>
    </div>
  </form>
</div>
<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
