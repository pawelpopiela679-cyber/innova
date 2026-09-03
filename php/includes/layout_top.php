<?php
/**
 * Wspólny nagłówek każdej strony: <head>, kolory motywu, nawigacja.
 * Każda strona ustawia $pageTitle przed dołączeniem tego pliku.
 * Zamyka go includes/layout_bottom.php (stopka + </body></html>).
 */

$theme = get_theme();
$user = current_user();
$isStaff = $user && in_array($user['role'], ['ADMIN', 'INSTRUCTOR'], true);

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
<link href="https://fonts.googleapis.com/css2?family=Quicksand:wght@600;700&family=Caveat:wght@600;700&family=Nunito:wght@400;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(url('assets/style.css')) ?>">
<style><?= theme_css_vars($theme) ?></style>
</head>
<body>
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
<main>
