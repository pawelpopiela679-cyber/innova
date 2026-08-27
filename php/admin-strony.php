<?php
require_once __DIR__ . '/includes/bootstrap.php';
$user = require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['_action'] ?? '';

    if ($action === 'create') {
        $title = trim((string) ($_POST['title'] ?? ''));
        $slug = strtolower(trim((string) ($_POST['slug'] ?? '')));
        $content = trim((string) ($_POST['content'] ?? ''));
        $showInNav = !empty($_POST['showInNav']) ? 1 : 0;

        if ($title === '') {
            redirect_with('admin-strony.php', ['error' => 'Podaj tytuł strony.']);
        }
        if (!preg_match('/^[a-z0-9-]+$/', $slug)) {
            redirect_with('admin-strony.php', ['error' => 'Adres może zawierać tylko małe litery, cyfry i myślniki.']);
        }
        if ($content === '') {
            redirect_with('admin-strony.php', ['error' => 'Podaj treść strony.']);
        }
        $exists = db()->prepare('SELECT id FROM pages WHERE slug = ?');
        $exists->execute([$slug]);
        if ($exists->fetch()) {
            redirect_with('admin-strony.php', ['error' => 'Strona z tym adresem już istnieje.']);
        }
        $maxSort = (int) db()->query('SELECT COALESCE(MAX(sort_order),0) m FROM pages')->fetch()['m'];
        db()->prepare('INSERT INTO pages (slug, title, content, show_in_nav, sort_order) VALUES (?,?,?,?,?)')
            ->execute([$slug, $title, $content, $showInNav, $maxSort + 1]);
        redirect('admin-strony.php?added=1');
    }

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        db()->prepare('DELETE FROM pages WHERE id = ?')->execute([$id]);
        redirect('admin-strony.php?deleted=1');
    }
}

$pages = db()->query('SELECT * FROM pages ORDER BY sort_order ASC')->fetchAll();

$pageTitle = 'Strony — INNOVA';
require __DIR__ . '/includes/layout_top.php';
?>
<div class="container-md" style="padding:40px 16px;">
  <?php include __DIR__ . '/includes/partials/admin-nav.php'; ?>

  <h1 style="font-size:1.6rem;">Strony</h1>
  <p class="text-muted mt-2">
    Dodawaj własne podstrony (np. „Regulamin”, „FAQ”) bez potrzeby edycji kodu. Strona
    <a href="<?= e(url('poznaj-nas.php')) ?>" style="text-decoration:underline;">Poznaj nas</a>
    jest wbudowana na stałe i aktualizuje się automatycznie z listy prowadzących.
  </p>

  <?php if (isset($_GET['error'])): ?><p class="alert alert-error"><?= e($_GET['error']) ?></p><?php endif; ?>
  <?php if (isset($_GET['added'])): ?><p class="alert alert-success">Strona została dodana.</p><?php endif; ?>
  <?php if (isset($_GET['updated'])): ?><p class="alert alert-success">Strona została zaktualizowana.</p><?php endif; ?>
  <?php if (isset($_GET['deleted'])): ?><p class="alert alert-info">Strona została usunięta.</p><?php endif; ?>

  <div class="mt-6" style="display:flex; flex-direction:column; gap:12px;">
    <?php if (!$pages): ?><p class="text-muted">Nie masz jeszcze żadnych dodatkowych stron.</p><?php endif; ?>
    <?php foreach ($pages as $p): ?>
      <div class="card flex items-center justify-between" style="justify-content:space-between; flex-wrap:wrap; gap:12px;">
        <div>
          <p style="font-weight:700;"><?= e($p['title']) ?>
            <?php if ($p['show_in_nav']): ?><span class="badge" style="background:color-mix(in srgb, var(--sage) 20%, var(--background)); color:var(--sage);">w menu</span><?php endif; ?>
          </p>
          <p class="text-muted">/strona.php?slug=<?= e($p['slug']) ?></p>
        </div>
        <div class="flex gap-2">
          <a href="<?= e(url('strona.php?slug=' . $p['slug'])) ?>" target="_blank" class="btn btn-outline btn-sm">Podgląd</a>
          <a href="<?= e(url('admin-strony-edytuj.php?id=' . $p['id'])) ?>" class="btn btn-outline btn-sm">Edytuj</a>
          <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="_action" value="delete">
            <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
            <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Na pewno usunąć tę stronę?')">Usuń</button>
          </form>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="card mt-8">
    <h2 style="font-size:1.1rem;">Dodaj nową stronę</h2>
    <form method="post" class="mt-4">
      <?= csrf_field() ?>
      <input type="hidden" name="_action" value="create">
      <div class="field">
        <label for="title">Tytuł</label>
        <input id="title" name="title" required placeholder="np. Regulamin zajęć">
      </div>
      <div class="field">
        <label for="slug">Adres strony (bez spacji i polskich znaków)</label>
        <input id="slug" name="slug" required pattern="[a-z0-9-]+" placeholder="np. regulamin">
        <p class="field-hint">Strona będzie dostępna pod adresem: strona.php?slug=adres</p>
      </div>
      <div class="field">
        <label for="content">Treść</label>
        <textarea id="content" name="content" required rows="8" placeholder="Pisz akapitami — pusta linia zaczyna nowy akapit."></textarea>
      </div>
      <label class="checkbox-row"><input type="checkbox" name="showInNav" checked> Pokaż w menu nawigacji</label>
      <button type="submit" class="btn btn-sm mt-4" style="background:var(--sage); color:#fff;">Dodaj stronę</button>
    </form>
  </div>
</div>
<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
