<?php
require_once __DIR__ . '/includes/bootstrap.php';

$pageTitle = 'Kontakt — INNOVA';
require __DIR__ . '/includes/layout_top.php';

$kontaktPhone = get_content('footer.phone_number', '790 250 363');
$kontaktAddress = get_content('footer.address_text', 'ul. Kolejowa, Czechowice-Dziedzice');
$fbHandle = get_content('footer.facebook_handle', 'innova.pracownia');
$igHandle = get_content('footer.instagram_handle', 'innova_pracownia');
?>
<div class="nb-graphic-page" style="background-image:url('<?= e(url('assets/img/banners/kontakt.png')) ?>');">
  <div class="nb-graphic-overlay" style="left:58%; top:28.5%; width:34%;">
    <a href="mailto:kontakt@innova-pracownia.pl">kontakt@innova-<br>pracownia.pl</a>
  </div>
  <div class="nb-graphic-overlay" style="left:58%; top:36%; width:34%;">
    <a href="tel:+48<?= e(preg_replace('/[^0-9]/', '', $kontaktPhone)) ?>"><?= e($kontaktPhone) ?></a>
  </div>
  <div class="nb-graphic-overlay" style="left:58%; top:42%; width:34%;">
    <?= e($kontaktAddress) ?>
  </div>
  <div class="nb-graphic-overlay nb-graphic-social" style="left:24%; top:53.5%; width:45%;">
    <a href="https://facebook.com/<?= e($fbHandle) ?>" style="background:#3b5998;">f</a>
    <a href="https://instagram.com/<?= e($igHandle) ?>" style="background:linear-gradient(45deg,#f58529,#dd2a7b,#8134af);">ig</a>
  </div>
</div>
<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
