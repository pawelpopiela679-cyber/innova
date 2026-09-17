<?php
require_once __DIR__ . '/includes/bootstrap.php';

$posts = db()->query('SELECT * FROM news_posts ORDER BY created_at DESC')->fetchAll();
$featured = $posts ? array_shift($posts) : null;

$pageTitle = 'Aktualności — INNOVA';
$notebookTheme = true;
$notebookBare = true;
$notebookActive = 'news';
require __DIR__ . '/includes/layout_top.php';
?>
<img class="nb-header-banner" src="<?= e(url('assets/img/headers/aktualnosci.png')) ?>" alt="Aktualności — bądź na bieżąco z życiem INNOVA">

<?php if (!$featured): ?>
  <p class="text-muted text-center mt-8">Na razie brak wpisów — zajrzyj tu wkrótce!</p>
<?php else: ?>
  <article class="nb-card" style="background:#fff; cursor:default; margin-bottom:30px;">
    <p class="text-muted" style="font-size:0.82rem; margin:0 0 6px;"><?= e(format_pl_date($featured['created_at'])) ?></p>
    <h2 style="font-size:1.6rem; margin:0 0 10px;"><?= e($featured['title']) ?></h2>
    <div style="display:flex; flex-direction:column; gap:12px;">
      <?php foreach (preg_split('/\n\s*\n/', trim($featured['content'])) as $para): ?>
        <p style="white-space:pre-line;"><?= e(trim($para)) ?></p>
      <?php endforeach; ?>
    </div>
    <p class="text-muted mt-3" style="font-size:0.8rem;">— <?= e($featured['author_name']) ?></p>
  </article>

  <?php if ($posts): ?>
    <div class="nb-cards" style="grid-template-columns: repeat(3, 1fr);">
      <?php foreach ($posts as $p): ?>
        <article class="nb-card" style="background:#fff; cursor:default;">
          <div class="nb-tape"></div>
          <p class="text-muted" style="font-size:0.78rem; margin:0 0 4px;"><?= e(format_pl_date($p['created_at'])) ?></p>
          <h3 style="font-size:1.15rem;"><?= e($p['title']) ?></h3>
          <p style="font-size:.86rem;"><?= e(mb_strimwidth(trim(preg_replace('/\s+/', ' ', $p['content'])), 0, 160, '…')) ?></p>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
<?php endif; ?>
<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
