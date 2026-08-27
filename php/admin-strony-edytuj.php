<?php
require_once __DIR__ . '/includes/bootstrap.php';
$user = require_admin();

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$page = db()->prepare('SELECT * FROM pages WHERE id = ?');
$page->execute([$id]);
$page = $page->fetch();

if (!$page) {
    http_response_code(404);
    $pageTitle = 'Nie znaleziono — INNOVA';
    require __DIR__ . '/includes/layout_top.php';
    echo '<div class="container-sm text-center" style="padding:64px 16px;"><h1>404</h1></div>';
    require __DIR__ . '/includes/layout_bottom.php';
    exit;
}

$error = $_GET['error'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $title = trim((string) ($_POST['title'] ?? ''));
    $slug = strtolower(trim((string) ($_POST['slug'] ?? '')));
    $content = trim((string) ($_POST['content'] ?? ''));
    $showInNav = !empty($_POST['showInNav']) ? 1 : 0;

    if ($title === '') {
        redirect_with('admin-strony-edytuj.php', ['id' => $id, 'error' => 'Podaj tytuł strony.']);
    }
    if (!preg_match('/^[a-z0-9-]+$/', $slug)) {
        redirect_with('admin-strony-edytuj.php', ['id' => $id, 'error' => 'Adres może zawierać tylko małe litery, cyfry i myślniki.']);
    }
    if ($content === '') {
        redirect_with('admin-strony-edytuj.php', ['id' => $id, 'error' => 'Podaj treść strony.']);
    }
    $taken = db()->prepare('SELECT id FROM pages WHERE slug = ? AND id != ?');
    $taken->execute([$slug, $id]);
    if ($taken->fetch()) {
        redirect_with('admin-strony-edytuj.php', ['id' => $id, 'error' => 'Strona z tym adresem już istnieje.']);
    }

    db()->prepare('UPDATE pages SET title=?, slug=?, content=?, show_in_nav=?, updated_at=CURRENT_TIMESTAMP WHERE id=?')
        ->execute([$title, $slug, $content, $showInNav, $id]);
    redirect('admin-strony.php?updated=1');
}

$pageTitle = 'Edytuj stronę — INNOVA';
require __DIR__ . '/includes/layout_top.php';
?>
<div class="container-md" style="padding:40px 16px;">
  <?php include __DIR__ . '/includes/partials/admin-nav.php'; ?>

  <h1 style="font-size:1.4rem;">Edytuj: <?= e($page['title']) ?></h1>

  <?php if ($error): ?><p class="alert alert-error"><?= e($error) ?></p><?php endif; ?>

  <form method="post" class="card mt-6">
    <?= csrf_field() ?>
    <div class="field">
      <label for="title">Tytuł</label>
      <input id="title" name="title" required value="<?= e($page['title']) ?>">
    </div>
    <div class="field">
      <label for="slug">Adres strony</label>
      <input id="slug" name="slug" required pattern="[a-z0-9-]+" value="<?= e($page['slug']) ?>">
    </div>
    <div class="field">
      <label for="content">Treść</label>
      <textarea id="content" name="content" required rows="10"><?= e($page['content']) ?></textarea>
    </div>
    <label class="checkbox-row"><input type="checkbox" name="showInNav" <?= $page['show_in_nav'] ? 'checked' : '' ?>> Pokaż w menu nawigacji</label>

    <div class="flex gap-2 mt-4">
      <button type="submit" class="btn btn-primary">Zapisz zmiany</button>
      <a href="<?= e(url('admin-strony.php')) ?>" class="btn btn-outline">Anuluj</a>
    </div>
  </form>
</div>
<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
