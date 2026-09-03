<?php
require_once __DIR__ . '/includes/bootstrap.php';

$pageTitle = 'Kontakt — INNOVA';
$notebookTheme = true;
$notebookActive = 'contact';
require __DIR__ . '/includes/layout_top.php';
?>
<h1 class="nb-section-title" style="font-size:1.8rem;">Skontaktuj się z nami</h1>
<p class="text-center text-muted mt-2">Masz pytania o zajęcia, grafik albo zapisy? Chętnie pomożemy.</p>

<div class="nb-cards mt-8" style="grid-template-columns:repeat(3,1fr);">
  <div class="nb-card" style="background:#cfe6f7; cursor:default;">
    <svg class="nb-icon" viewBox="0 0 24 24" fill="none" stroke="#2f6ea3" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20.9 17.4l-3-1.3a1.4 1.4 0 00-1.5.3l-1.3 1.3a12.4 12.4 0 01-5.8-5.8l1.3-1.3c.4-.4.5-1 .3-1.5l-1.3-3a1.4 1.4 0 00-1.4-.9H5.6C4.7 5.2 4 6 4.1 6.9c.5 4.6 2.6 8.9 6 12.3 3.4 3.4 7.7 5.5 12.3 6 .9.1 1.7-.6 1.7-1.5v-3.7c0-.6-.4-1.2-1-1.4z"/></svg>
    <h3 style="color:#2f6ea3;">Telefon</h3>
    <p><a href="tel:+48790250363" style="color:inherit; text-decoration:underline;">790 250 363</a></p>
  </div>
  <div class="nb-card" style="background:#dcebd6; cursor:default;">
    <svg class="nb-icon" viewBox="0 0 24 24" fill="none" stroke="#4d7a3f" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 6l9 7 9-7"/></svg>
    <h3 style="color:#4d7a3f;">E-mail</h3>
    <p><a href="mailto:kontakt@innova-pracownia.pl" style="color:inherit; text-decoration:underline;">kontakt@innova-pracownia.pl</a></p>
  </div>
  <div class="nb-card" style="background:#faedc4; cursor:default;">
    <svg class="nb-icon" viewBox="0 0 24 24" fill="none" stroke="#b8872a" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21s7-6.5 7-11.5a7 7 0 10-14 0C5 14.5 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/></svg>
    <h3 style="color:#b8872a;">Adres</h3>
    <p>ul. Kolejowa, Czechowice-Dziedzice</p>
  </div>
</div>

<div class="nb-section" style="text-align:center;">
  <p class="text-muted">Znajdziesz nas też tutaj:</p>
  <div class="nb-social" style="margin-top:12px;">
    <a href="https://facebook.com/innova.pracownia" style="background:#3b5998;">f</a>
    <a href="https://instagram.com/innova_pracownia" style="background:linear-gradient(45deg,#f58529,#dd2a7b,#8134af);">ig</a>
  </div>
</div>
<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
