<?php
require_once __DIR__ . '/includes/bootstrap.php';

$posts = db()->query('SELECT * FROM news_posts ORDER BY created_at DESC')->fetchAll();

$pageTitle = 'Aktualności — INNOVA';
$notebookTheme = true;
require __DIR__ . '/includes/layout_top.php';
?>
<div class="container-sm" style="padding:48px 16px;">
  <h1 class="text-center" style="font-size:1.8rem;">Aktualności</h1>
  <p class="text-muted text-center mt-2">Nowinki i newsy z życia pracowni.</p>

  <?php if (!$posts): ?>
    <p class="text-muted text-center mt-8">Na razie brak wpisów — zajrzyj tu wkrótce!</p>
  <?php endif; ?>

  <div class="mt-8" style="display:flex; flex-direction:column; gap:24px;">
    <?php foreach ($posts as $p): ?>
      <article class="card">
        <h2 style="font-size:1.2rem;"><?= e($p['title']) ?></h2>
        <p class="text-muted" style="font-size:0.82rem;"><?= e($p['author_name']) ?> · <?= e(format_pl_date($p['created_at'])) ?></p>
        <div class="mt-3" style="display:flex; flex-direction:column; gap:12px;">
          <?php foreach (preg_split('/\n\s*\n/', trim($p['content'])) as $para): ?>
            <p style="white-space:pre-line;"><?= e(trim($para)) ?></p>
          <?php endforeach; ?>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
</div>
<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
