<?php
/**
 * Wspólny nagłówek każdej strony: <head>, kolory motywu, nawigacja.
 * Każda strona ustawia $pageTitle przed dołączeniem tego pliku.
 * Zamyka go includes/layout_bottom.php (stopka + </body></html>).
 */

$theme = get_theme();
$user = current_user();
$isStaff = $user && in_array($user['role'], ['ADMIN', 'INSTRUCTOR'], true);
// Strony publiczne mogą ustawić $notebookTheme = true i $notebookActive =
// '<klucz zakładki>' PRZED dołączeniem tego pliku, żeby dostać styl
// "zeszytu szkolnego" (spirala, zakładki po prawej) zamiast zwykłego navbara.
// Panel admina/prowadzącego i logowanie zostają bez zmian.
$notebookTheme = $notebookTheme ?? false;
$notebookActive = $notebookActive ?? '';

$customPages = [];
try {
    $customPages = db()->query('SELECT slug, title FROM pages WHERE show_in_nav = 1 ORDER BY sort_order ASC')->fetchAll();
} catch (Throwable $e) {
    // Tabela jeszcze nie istnieje (przed install.php) — nic nie pokazujemy.
}
?><!doctype html>
<html lang="pl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle ?? 'INNOVA — Pracownia kreatywno-edukacyjna') ?></title>
<meta name="description" content="Miejsce rozwoju dla Twojego dziecka. Zajęcia z angielskiego, scenicznych, robotyki, kreatywne, matematyki i eksperymentatorium w Czechowicach-Dziedzicach i online.">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@600;700;800&family=Quicksand:wght@600;700&family=Caveat:wght@600;700&family=Nunito:wght@400;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(url('assets/style.css')) ?>">
<?php if ($notebookTheme): ?><link rel="stylesheet" href="<?= e(url('assets/notebook.css')) ?>"><?php endif; ?>
<style><?= theme_css_vars($theme) ?></style>
</head>
<body<?= $notebookTheme ? ' class="notebook-body"' : '' ?>>
<?php if ($notebookTheme): ?>
<div class="notebook-wrap">
  <div class="notebook">
    <div class="spiral"></div>
    <?= nb_render_tabs($notebookActive) ?>
    <div style="display:flex; justify-content:flex-end; gap:12px; align-items:center; font-size:.8rem; margin-bottom:8px; flex-wrap:wrap; padding-right:150px;">
      <?php if ($user): ?>
        <?php if ($isStaff): ?>
          <a href="<?= e(url('admin.php')) ?>" style="color:var(--nb-green,#3f7d45); font-weight:700; text-decoration:none;">Panel prowadzącego</a>
        <?php else: ?>
          <a href="<?= e(url('panel.php')) ?>" style="color:var(--nb-green,#3f7d45); font-weight:700; text-decoration:none;">Panel rodzica</a>
        <?php endif; ?>
        <span style="color:var(--nb-muted,#8a7f5c);"><?= e($user['name']) ?></span>
        <form method="post" action="<?= e(url('wyloguj.php')) ?>" style="display:inline;">
          <?= csrf_field() ?>
          <button type="submit" style="background:none; border:none; color:var(--nb-muted,#8a7f5c); font-weight:700; text-decoration:underline; cursor:pointer; font-size:inherit;">Wyloguj</button>
        </form>
      <?php else: ?>
        <a href="<?= e(url('logowanie.php')) ?>" style="color:var(--nb-muted,#8a7f5c); font-weight:700; text-decoration:none;">Zaloguj się</a>
      <?php endif; ?>
    </div>
    <div class="nb-topbar">
      <a href="<?= e(url('index.php')) ?>" style="text-decoration:none;"><?= render_logo('md', true) ?></a>
      <?= nb_render_toplinks($notebookActive) ?>
    </div>
<?php else: ?>
<header class="navbar">
  <div class="navbar-inner">
    <a href="<?= e(url('index.php')) ?>"><?= render_logo('sm') ?></a>
    <nav class="navbar-links">
      <a href="<?= e(url('zajecia.php')) ?>">Zajęcia i cennik</a>
      <a href="<?= e(url('kalendarz.php')) ?>">Kalendarz</a>
      <a href="<?= e(url('poznaj-nas.php')) ?>">Poznaj nas</a>
      <?php foreach ($customPages as $p): ?>
        <a href="<?= e(url('strona.php?slug=' . urlencode($p['slug']))) ?>"><?= e($p['title']) ?></a>
      <?php endforeach; ?>
      <?php if ($isStaff): ?>
        <a href="<?= e(url('admin.php')) ?>">Panel prowadzącego</a>
      <?php endif; ?>
    </nav>
    <div class="navbar-right">
      <?php if ($user): ?>
        <?php if (!$isStaff): ?>
          <a href="<?= e(url('panel.php')) ?>">Panel rodzica</a>
        <?php endif; ?>
        <span class="text-muted"><?= e($user['name']) ?></span>
        <form method="post" action="<?= e(url('wyloguj.php')) ?>" class="inline">
          <?= csrf_field() ?>
          <button type="submit" class="btn btn-outline btn-sm">Wyloguj</button>
        </form>
      <?php else: ?>
        <a href="<?= e(url('logowanie.php')) ?>" class="btn btn-outline btn-sm">Zaloguj się</a>
        <a href="<?= e(url('rejestracja.php')) ?>" class="btn btn-primary btn-sm">Załóż konto</a>
      <?php endif; ?>
    </div>
  </div>
</header>
<?php endif; ?>
<main>
