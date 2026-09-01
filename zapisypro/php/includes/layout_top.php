<?php
/**
 * Wspólny nagłówek każdej strony: <head>, kolory motywu, nawigacja
 * dopasowana do roli (publiczna / super-admin / organizacja / rodzic).
 * Każda strona ustawia $pageTitle przed dołączeniem tego pliku.
 * Zamyka go includes/layout_bottom.php (stopka + </body></html>).
 */

$user = current_user();
// Nazwa celowo NIE "$org": pliki wołające ten layout mają często swoją
// własną zmienną $org (np. organizację oglądaną przez super-admina) —
// gdyby nazywała się tak samo, require tego pliku (ten sam scope!) by ją
// nadpisał od tego miejsca w dół.
$__navOrg = $user ? current_org() : null;
$theme = get_theme($__navOrg['id'] ?? null);
$isSuperAdmin = $user && $user['role'] === 'SUPER_ADMIN';
$isOrgAdmin = $user && $user['role'] === 'ORG_ADMIN';
$isStaff = $user && in_array($user['role'], ['ORG_ADMIN', 'INSTRUCTOR'], true);
$isParent = $user && $user['role'] === 'PARENT';
?><!doctype html>
<html lang="pl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle ?? 'ZapisyPro — system zapisów dla wielu szkółek i klubów') ?></title>
<meta name="description" content="ZapisyPro — platforma SaaS do zarządzania zapisami, kalendarzem zajęć i płatnościami dla szkółek i klubów zajęć dla dzieci. Wiele organizacji, jeden system, model subskrypcyjny.">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(url('assets/style.css')) ?>">
<style><?= theme_css_vars($theme) ?></style>
</head>
<body>
<header class="navbar">
  <div class="navbar-inner">
    <a href="<?= e(url($user ? ($isSuperAdmin ? 'superadmin.php' : ($isStaff ? 'admin.php' : 'panel-rodzic.php')) : 'index.php')) ?>" class="navbar-brand"><?= render_logo('sm') ?></a>

    <button class="navbar-toggle" type="button" aria-label="Menu" onclick="document.querySelector('.navbar-links').classList.toggle('open')">☰</button>

    <nav class="navbar-links">
      <?php if (!$user): ?>
        <a href="<?= e(url('index.php')) ?>">Platforma</a>
        <a href="<?= e(url('cennik.php')) ?>">Cennik</a>
      <?php elseif ($isSuperAdmin): ?>
        <a href="<?= e(url('superadmin.php')) ?>">Organizacje</a>
        <a href="<?= e(url('superadmin-plany.php')) ?>">Plany</a>
      <?php elseif ($isStaff): ?>
        <a href="<?= e(url('admin.php')) ?>">Panel</a>
        <a href="<?= e(url('kalendarz.php')) ?>">Kalendarz</a>
        <a href="<?= e(url('zapisy.php')) ?>">Zgłoszenia</a>
        <a href="<?= e(url('zajecia.php')) ?>">Zajęcia</a>
        <?php if ($isOrgAdmin): ?>
          <a href="<?= e(url('raporty.php')) ?>">Raporty</a>
          <details class="nav-more">
            <summary>Więcej ▾</summary>
            <div class="nav-more-panel">
              <a href="<?= e(url('umowy.php')) ?>">Umowy</a>
              <a href="<?= e(url('rodo.php')) ?>">RODO</a>
              <a href="<?= e(url('prowadzacy.php')) ?>">Prowadzący</a>
              <a href="<?= e(url('komunikacja.php')) ?>">Komunikacja</a>
              <a href="<?= e(url('godziny.php')) ?>">Godziny i wynagrodzenia</a>
              <a href="<?= e(url('wyglad.php')) ?>">Wygląd</a>
              <a href="<?= e(url('abonament.php')) ?>">Abonament</a>
            </div>
          </details>
        <?php endif; ?>
      <?php elseif ($isParent): ?>
        <a href="<?= e(url('panel-rodzic.php')) ?>">Panel</a>
        <a href="<?= e(url('panel-dzieci.php')) ?>">Moje dzieci</a>
        <a href="<?= e(url('kalendarz.php')) ?>">Kalendarz zajęć</a>
        <a href="<?= e(url('panel-zapisy.php')) ?>">Moje zapisy</a>
        <a href="<?= e(url('panel-umowy.php')) ?>">Moje umowy</a>
      <?php endif; ?>
    </nav>

    <div class="navbar-right">
      <?php if ($user): ?>
        <?php if (!$isSuperAdmin): ?><a href="<?= e(url('profil.php')) ?>" class="text-muted"><?= e($user['name']) ?></a><?php else: ?><span class="text-muted"><?= e($user['name']) ?></span><?php endif; ?>
        <form method="post" action="<?= e(url('wyloguj.php')) ?>" class="inline">
          <?= csrf_field() ?>
          <button type="submit" class="btn btn-outline btn-sm">Wyloguj</button>
        </form>
      <?php else: ?>
        <a href="<?= e(url('logowanie.php')) ?>" class="btn btn-outline btn-sm">Zaloguj się</a>
        <a href="<?= e(url('rejestracja-organizacji.php')) ?>" class="btn btn-primary btn-sm">Załóż organizację</a>
      <?php endif; ?>
    </div>
  </div>
</header>
<main>
