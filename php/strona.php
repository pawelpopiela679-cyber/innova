<?php
require_once __DIR__ . '/includes/bootstrap.php';

$slug = (string) ($_GET['slug'] ?? '');
$stmt = db()->prepare('SELECT * FROM pages WHERE slug = ?');
$stmt->execute([$slug]);
$page = $stmt->fetch();

if (!$page) {
    http_response_code(404);
    $pageTitle = 'Nie znaleziono strony — INNOVA';
    $notebookTheme = true;
require __DIR__ . '/includes/layout_top.php';
    echo '<div class="container-sm text-center" style="padding:64px 16px;"><h1>404</h1><p class="text-muted">Nie znaleziono takiej strony.</p></div>';
    require __DIR__ . '/includes/layout_bottom.php';
    exit;
}

$paragraphs = preg_split('/\n\s*\n/', trim($page['content']));

$pageTitle = e($page['title']) . ' — INNOVA';
$notebookTheme = true;
require __DIR__ . '/includes/layout_top.php';
?>
<div class="container-sm" style="padding:48px 16px;">
  <h1 class="text-center" style="font-size:1.8rem;"><?= e($page['title']) ?></h1>
  <div class="mt-8" style="display:flex; flex-direction:column; gap:16px;">
    <?php foreach ($paragraphs as $p): ?>
      <p style="white-space:pre-line;"><?= e(trim($p)) ?></p>
    <?php endforeach; ?>
  </div>
</div>
<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
