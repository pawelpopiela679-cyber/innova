<?php
require_once __DIR__ . '/includes/bootstrap.php';
$user = require_staff();

/**
 * Aktualności — z prośby prowadzących: mogą sami wrzucać newsy/nowinki z
 * życia pracowni, widoczne publicznie na aktualnosci.php. W odróżnieniu od
 * "Stron" (admin-strony.php, tylko właścicielka) — to dostępne dla KAŻDEGO
 * prowadzącego. Edytować/usuwać wpis może jego autor albo właścicielka
 * (moderacja) — nie każdy każdego.
 */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['_action'] ?? '';

    if ($action === 'create') {
        $title = trim((string) ($_POST['title'] ?? ''));
        $content = trim((string) ($_POST['content'] ?? ''));

        if ($title === '') {
            redirect_with('admin-aktualnosci.php', ['error' => 'Podaj tytuł.']);
        }
        if ($content === '') {
            redirect_with('admin-aktualnosci.php', ['error' => 'Podaj treść.']);
        }
        db()->prepare('INSERT INTO news_posts (title, content, author_id, author_name) VALUES (?,?,?,?)')
            ->execute([$title, $content, $user['id'], $user['name']]);
        redirect('admin-aktualnosci.php?added=1');
    }

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $post = db()->prepare('SELECT * FROM news_posts WHERE id = ?');
        $post->execute([$id]);
        $post = $post->fetch();
        if ($post && ($user['role'] === 'ADMIN' || (int) $post['author_id'] === (int) $user['id'])) {
            db()->prepare('DELETE FROM news_posts WHERE id = ?')->execute([$id]);
            redirect('admin-aktualnosci.php?deleted=1');
        }
        redirect_with('admin-aktualnosci.php', ['error' => 'Możesz usuwać tylko własne wpisy.']);
    }
}

$posts = db()->query('SELECT * FROM news_posts ORDER BY created_at DESC')->fetchAll();

$pageTitle = 'Aktualności — INNOVA';
$notebookTheme = true;
require __DIR__ . '/includes/layout_top.php';
?>
<div class="container-md" style="padding:40px 16px;">
  <?php include __DIR__ . '/includes/partials/admin-nav.php'; ?>

  <h1 style="font-size:1.6rem;">Aktualności</h1>
  <p class="text-muted mt-2">
    Newsy i nowinki z życia pracowni — widoczne dla wszystkich na
    <a href="<?= e(url('aktualnosci.php')) ?>" target="_blank" style="text-decoration:underline;">publicznej stronie Aktualności</a>.
    Może je dodawać każdy prowadzący; edytować/usuwać można tylko własne wpisy (właścicielka — wszystkie).
  </p>

  <?php if (isset($_GET['error'])): ?><p class="alert alert-error"><?= e($_GET['error']) ?></p><?php endif; ?>
  <?php if (isset($_GET['added'])): ?><p class="alert alert-success">Wpis został dodany.</p><?php endif; ?>
  <?php if (isset($_GET['updated'])): ?><p class="alert alert-success">Wpis został zaktualizowany.</p><?php endif; ?>
  <?php if (isset($_GET['deleted'])): ?><p class="alert alert-info">Wpis został usunięty.</p><?php endif; ?>

  <div class="card mt-6">
    <h2 style="font-size:1.1rem;">Nowy wpis</h2>
    <form method="post" class="mt-4">
      <?= csrf_field() ?>
      <input type="hidden" name="_action" value="create">
      <div class="field">
        <label for="title">Tytuł</label>
        <input id="title" name="title" required placeholder="np. Wyniki konkursu plastycznego!">
      </div>
      <div class="field">
        <label for="content">Treść</label>
        <textarea id="content" name="content" required rows="6" placeholder="Pisz akapitami — pusta linia zaczyna nowy akapit."></textarea>
      </div>
      <button type="submit" class="btn btn-sm mt-2" style="background:var(--sage); color:#fff;">Opublikuj</button>
    </form>
  </div>

  <div class="mt-8" style="display:flex; flex-direction:column; gap:12px;">
    <?php if (!$posts): ?><p class="text-muted">Brak wpisów — dodaj pierwszy powyżej.</p><?php endif; ?>
    <?php foreach ($posts as $p): ?>
      <div class="card">
        <div class="flex items-center justify-between" style="justify-content:space-between; flex-wrap:wrap; gap:8px;">
          <div>
            <p style="font-weight:700;"><?= e($p['title']) ?></p>
            <p class="text-muted" style="font-size:0.8rem;"><?= e($p['author_name']) ?> · <?= e(format_pl_date($p['created_at'])) ?></p>
          </div>
          <?php if ($user['role'] === 'ADMIN' || (int) $p['author_id'] === (int) $user['id']): ?>
            <div class="flex gap-2">
              <a href="<?= e(url('admin-aktualnosci-edytuj.php?id=' . $p['id'])) ?>" class="btn btn-outline btn-sm">Edytuj</a>
              <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="_action" value="delete">
                <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Na pewno usunąć ten wpis?')">Usuń</button>
              </form>
            </div>
          <?php endif; ?>
        </div>
        <p class="mt-3" style="white-space:pre-line; font-size:0.9rem;"><?= e(mb_strimwidth($p['content'], 0, 240, '…')) ?></p>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
