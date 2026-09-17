<?php
require_once __DIR__ . '/includes/bootstrap.php';

$pageTitle = 'INNOVA — Pracownia kreatywno-edukacyjna';
$notebookTheme = true;
$notebookBare = true;
$notebookActive = 'home';
require __DIR__ . '/includes/layout_top.php';
?>
<div class="nb-hero" style="grid-template-columns: .95fr 1.05fr;">
  <div class="nb-photo-block">
    <img src="<?= e(url('assets/img/hero-cover.png')) ?>" alt="Zeszyt INNOVA — Odkrywaj. Twórz. Rośnij." class="nb-photo-real">
  </div>
  <div>
    <h1><?= e(get_content('home.hero_title', 'Miejsce, w którym')) ?> <span class="hl"><?= e(get_content('home.hero_title_hl', 'pomysły rosną')) ?><svg viewBox="0 0 100 10" preserveAspectRatio="none"><path d="M2 6 C 20 10, 80 2, 98 7" stroke="currentColor" stroke-width="3" fill="none" stroke-linecap="round"/></svg></span>!</h1>
    <p class="lead"><?= nl2br(e(get_content('home.lead', 'Kreatywno-edukacyjne zajęcia dla dzieci i młodzieży, które inspirują, rozwijają pasje i dają nowe możliwości.'))) ?></p>
    <div class="nb-cta-row">
      <a href="<?= e(url('poznaj-nas.php')) ?>" class="nb-btn solid"><?= e(get_content('home.cta_offer_label', 'Poznaj nas bliżej →')) ?></a>
      <a href="<?= e(url('zajecia.php')) ?>" class="nb-btn"><?= e(get_content('home.cta_signup_label', 'Zobacz zajęcia →')) ?></a>
    </div>
  </div>
</div>
<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
