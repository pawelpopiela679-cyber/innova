<?php
require_once __DIR__ . '/includes/bootstrap.php';

$classTypes = db()->query('SELECT * FROM class_types ORDER BY id ASC')->fetchAll();

$pageTitle = 'INNOVA — Pracownia kreatywno-edukacyjna';
require __DIR__ . '/includes/layout_top.php';
?>
<section class="container" style="padding:56px 16px; text-align:center;">
  <?= render_logo('lg', true) ?>
  <p class="mt-4" style="font-family:var(--font-script); font-size:2.4rem; color:var(--sage); margin:0;">Miejsce rozwoju</p>
  <h1 style="font-size:1.6rem; color:var(--coral); margin-top:4px;">DLA TWOJEGO DZIECKA</h1>
  <p class="text-muted mt-4" style="max-width:560px; margin-left:auto; margin-right:auto; font-size:1.1rem;">
    Zgłoś dziecko na zajęcia w kilka minut: sprawdź kalendarz, wybierz termin,
    a my dobierzemy odpowiednią grupę i potwierdzimy zapis e-mailem.
  </p>
  <div class="flex flex-wrap gap-3 mt-6" style="justify-content:center;">
    <a href="<?= e(url('kalendarz.php')) ?>" class="btn btn-primary">Zobacz kalendarz zajęć</a>
    <a href="<?= e(url('rejestracja.php')) ?>" class="btn btn-outline">Załóż konto rodzica</a>
  </div>
  <div class="flex flex-wrap gap-3 mt-6" style="justify-content:center;">
    <span class="pill banner-coral" style="color:var(--coral);">⭐ Dzień otwarty: 12.09.2026</span>
    <span class="pill banner-mustard" style="color:var(--sage);">📅 Start zajęć: 14.09.2026</span>
  </div>
</section>

<section class="container" style="padding-bottom:48px;">
  <h2 class="text-center mt-6" style="font-size:1.4rem;">Nasza oferta</h2>
  <div class="grid grid-3 mt-6">
    <?php foreach ($classTypes as $i => $ct): ?>
      <a href="<?= e(url('zajecia.php#' . $ct['key_name'])) ?>" class="card card-top-accent" style="border-top-color:<?= e($ct['color']) ?>; text-decoration:none; color:inherit; display:block;">
        <span class="card-badge" style="background:<?= e($ct['color']) ?>;"><?= $i + 1 ?></span>
        <div class="flex items-center gap-3">
          <span class="icon-box" style="background:<?= e($ct['color']) ?>22;"><?= class_icon($ct['key_name']) ?></span>
          <h3><?= e($ct['name']) ?></h3>
        </div>
        <p class="text-muted mt-2"><?= e($ct['description']) ?></p>
        <p class="text-muted mt-2" style="font-size:0.8rem; font-weight:600;">Wiek: <?= (int) $ct['age_min'] ?>–<?= (int) $ct['age_max'] ?> lat</p>
      </a>
    <?php endforeach; ?>
  </div>
  <p class="text-center text-muted mt-4">Zajęcia odbywają się 1x w tygodniu. <a href="<?= e(url('zajecia.php')) ?>" style="color:var(--coral); text-decoration:underline;">Zobacz pełny cennik →</a></p>
</section>

<section class="container" style="padding-bottom:48px;">
  <div class="card">
    <h2 class="text-center" style="font-size:1.2rem;">Dlaczego warto?</h2>
    <div class="grid grid-3 mt-6">
      <div class="text-center">
        <div class="icon-box" style="margin:0 auto; background:var(--sage)22; font-size:1.6rem; width:56px; height:56px; border-radius:999px;">🧸</div>
        <h3 class="mt-2">Kameralne grupy</h3>
        <p class="text-muted">Mała liczba dzieci w grupie — więcej uwagi dla każdego.</p>
      </div>
      <div class="text-center">
        <div class="icon-box" style="margin:0 auto; background:var(--coral)22; font-size:1.6rem; width:56px; height:56px; border-radius:999px;">🤗</div>
        <h3 class="mt-2">Przyjazna atmosfera</h3>
        <p class="text-muted">Bezpieczna, ciepła przestrzeń, w której dzieci chętnie wracają.</p>
      </div>
      <div class="text-center">
        <div class="icon-box" style="margin:0 auto; background:var(--mustard)22; font-size:1.6rem; width:56px; height:56px; border-radius:999px;">🎯</div>
        <h3 class="mt-2">Nauka przez działanie</h3>
        <p class="text-muted">Wiedza i umiejętności zdobywane w praktyce, nie z podręcznika.</p>
      </div>
    </div>
    <p class="text-center mt-6" style="max-width:420px; margin-left:auto; margin-right:auto;"><span class="pill banner-mustard">💛 Materiały podstawowe w cenie zajęć</span></p>
  </div>
</section>

<section class="container" style="padding-bottom:48px;">
  <div class="card text-center">
    <h2 style="font-size:1.2rem;">Jak to działa?</h2>
    <div class="grid mt-6" style="grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); text-align:left;">
      <?php
      $steps = [
          ['Załóż konto rodzica', 'Szybka rejestracja e-mailem.'],
          ['Dodaj dziecko', 'Imię, nazwisko i data urodzenia — wiek pomaga dobrać grupę.'],
          ['Zgłoś chęć zapisu', 'Sprawdź kalendarz i wybierz termin, który Wam pasuje.'],
          ['Potwierdzamy grupę', 'Sprawdzamy dostępność i dobieramy grupę odpowiednią do wieku.'],
          ['Gotowe!', 'Dostajesz e-mail z potwierdzeniem i przypisaną grupą.'],
      ];
      foreach ($steps as $i => [$title, $text]): ?>
        <div>
          <div style="width:32px;height:32px;border-radius:999px;background:var(--sage);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:0.85rem;margin-bottom:8px;"><?= $i + 1 ?></div>
          <h3 style="font-size:1rem;"><?= e($title) ?></h3>
          <p class="text-muted"><?= e($text) ?></p>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="container text-center text-muted" style="padding-bottom:56px;">
  <p><strong style="color:var(--foreground);">Zniżki:</strong> rodzeństwo −15% · Karta Dużej Rodziny −10%</p>
</section>
<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
