<?php
require_once __DIR__ . '/includes/bootstrap.php';

$classTypes = db()->query("SELECT * FROM class_types WHERE key_name != 'OPEN_DAY' ORDER BY id ASC")->fetchAll();

$pageTitle = 'INNOVA — Pracownia kreatywno-edukacyjna';
$notebookTheme = true;
$notebookActive = 'home';
require __DIR__ . '/includes/layout_top.php';
?>
<svg class="nb-doodle" style="left:-40px; top:6px; width:30px; height:30px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M13 2L4 14h6l-1 8 9-12h-6l1-8z"/></svg>
<svg class="nb-doodle" style="left:180px; top:-4px; width:28px; height:28px; color:var(--nb-gold);" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M12 2l2.9 6.3 6.9.7-5.2 4.6 1.6 6.8L12 16.9 5.8 20.4l1.6-6.8L2.2 9l6.9-.7z"/></svg>
<svg class="nb-doodle" style="left:-52px; top:400px; width:42px; height:20px;" viewBox="0 0 42 20" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 4c8 10 16 12 24 4M20 4l6 4-2 7" stroke-linecap="round" stroke-linejoin="round"/></svg>

<div class="nb-hero">
  <div>
    <h1>Witamy w <span class="hl">INNOVA<svg viewBox="0 0 100 10" preserveAspectRatio="none"><path d="M2 6 C 20 10, 80 2, 98 7" stroke="currentColor" stroke-width="3" fill="none" stroke-linecap="round"/></svg></span>!</h1>
    <div class="nb-tagline">Rozwijamy pasje. Odkrywamy talenty.<br>Tworzymy przyszłość!</div>
    <p class="lead">Zgłoś dziecko na zajęcia w kilka minut: sprawdź kalendarz, wybierz termin,
      a my dobierzemy odpowiednią grupę i potwierdzimy zapis e-mailem. Każde dziecko jest
      <u>wyjątkowe</u> — pomagamy mu rozkwitać.</p>
    <div class="nb-cta-row">
      <a href="<?= e(url('zajecia.php')) ?>" class="nb-btn">Poznaj ofertę →</a>
      <a href="<?= e(url('rejestracja.php')) ?>" class="nb-btn coral">Zapisz dziecko →</a>
    </div>
    <div class="flex flex-wrap gap-2 mt-6" style="font-size:.82rem;">
      <span class="nb-callout" style="display:inline-block; padding:6px 14px; margin:0;">⭐ Dzień otwarty: 12.09.2026</span>
      <span class="nb-callout" style="display:inline-block; padding:6px 14px; margin:0;">📅 Start zajęć: 15.09.2026</span>
    </div>
  </div>
  <div class="nb-photo-block">
    <div class="nb-photo-frame">
      <div class="nb-photo-placeholder">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="8.5" cy="10.5" r="1.5"/><path d="M3 16l4.5-4.5a1 1 0 011.4 0L14 16.5M14 15l2-2a1 1 0 011.4 0L21 16.5"/></svg>
        <span>miejsce na zdjęcie z zajęć</span>
      </div>
    </div>
    <div class="nb-photo-caption">wgraj prawdziwe zdjęcie w panelu „Wygląd”</div>
    <div class="nb-sticky">U nas nauka<br>zamienia się<br>w przygodę! 💚</div>
  </div>
</div>

<div class="nb-section">
  <h2 class="nb-section-title">✨ Nasze zajęcia</h2>
  <div class="nb-cards">
    <?php foreach ($classTypes as $ct): [$bg, $ink] = nb_pastel($ct['key_name']); ?>
      <a href="<?= e(url('zajecia.php#' . $ct['key_name'])) ?>" class="nb-card" style="background:<?= e($bg) ?>;">
        <div class="nb-tape"></div>
        <?= nb_icon_svg($ct['key_name']) ?>
        <h3 style="color:<?= e($ink) ?>;"><?= e($ct['name']) ?></h3>
        <p><?= e($ct['description']) ?></p>
        <span class="nb-more" style="color:<?= e($ink) ?>;">Dowiedz się więcej →</span>
      </a>
    <?php endforeach; ?>
  </div>
  <p class="text-center text-muted mt-4">Zajęcia odbywają się 1x w tygodniu. <a href="<?= e(url('zajecia.php')) ?>" style="color:var(--nb-coral); text-decoration:underline;">Zobacz pełny cennik →</a></p>
</div>

<div class="nb-why">
  <div class="nb-why-head">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 21s-7-4.35-9.5-9C.8 8.1 2.7 4 6.7 4 9 4 10.8 5.3 12 7c1.2-1.7 3-3 5.3-3 4 0 5.9 4.1 4.2 8-2.5 4.65-9.5 9-9.5 9z"/></svg>
    Dlaczego INNOVA?
  </div>
  <div class="nb-why-grid">
    <div class="nb-why-item">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="8" cy="8" r="3.2"/><circle cx="17" cy="9" r="2.6"/><path d="M2.5 20c0-3.6 2.5-6.4 5.5-6.4s5.5 2.8 5.5 6.4M14.3 14.2c2.6.2 4.7 2.6 4.7 5.8"/></svg>
      <div><h4>Kameralne grupy</h4><p>Mała liczba dzieci w grupie (maks. 10) — więcej uwagi dla każdego.</p></div>
    </div>
    <div class="nb-why-item">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 21s-7-4.35-9.5-9C.8 8.1 2.7 4 6.7 4 9 4 10.8 5.3 12 7c1.2-1.7 3-3 5.3-3 4 0 5.9 4.1 4.2 8-2.5 4.65-9.5 9-9.5 9z"/></svg>
      <div><h4>Przyjazna atmosfera</h4><p>Bezpieczna, ciepła przestrzeń, w której dzieci chętnie wracają.</p></div>
    </div>
    <div class="nb-why-item">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2l2.9 6.3 6.9.7-5.2 4.6 1.6 6.8L12 16.9 5.8 20.4l1.6-6.8L2.2 9l6.9-.7z"/></svg>
      <div><h4>Nauka przez działanie</h4><p>Wiedza i umiejętności zdobywane w praktyce, nie z podręcznika.</p></div>
    </div>
    <div class="nb-why-item">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M13 2s5 2 5 9c0 3-1.5 5-1.5 5H7.5S6 14 6 11c0-7 5-9 5-9z"/><path d="M9 16l-2.5 4M15 16l2.5 4M12 6.5a2 2 0 100 4 2 2 0 000-4z"/></svg>
      <div><h4>Materiały w cenie</h4><p>Podstawowe materiały na zajęcia — bez dodatkowych zakupów.</p></div>
    </div>
  </div>
</div>

<div class="nb-section" style="border-top:2px dashed var(--nb-rule); padding-top:26px;">
  <h2 class="nb-section-title" style="font-size:1.4rem;">Jak to działa?</h2>
  <div class="grid" style="display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); gap:20px;">
    <?php
    $steps = [
        ['Załóż konto rodzica', 'Szybka rejestracja e-mailem.'],
        ['Dodaj dziecko', 'Imię, nazwisko i data urodzenia — wiek pomaga dobrać grupę.'],
        ['Zgłoś chęć zapisu', 'Sprawdź grafik i wybierz termin, który Wam pasuje.'],
        ['Potwierdzamy grupę', 'Sprawdzamy dostępność i dobieramy grupę odpowiednią do wieku.'],
        ['Gotowe!', 'Dostajesz e-mail z potwierdzeniem i przypisaną grupą.'],
    ];
    foreach ($steps as $i => [$title, $text]): ?>
      <div>
        <div style="width:32px;height:32px;border-radius:999px;background:var(--nb-green);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.85rem;margin-bottom:8px;font-family:'Baloo 2','Nunito',sans-serif;"><?= $i + 1 ?></div>
        <h3 style="font-size:1rem; margin:0 0 4px;"><?= e($title) ?></h3>
        <p class="text-muted" style="margin:0;"><?= e($text) ?></p>
      </div>
    <?php endforeach; ?>
  </div>
  <p class="text-center text-muted mt-6"><strong style="color:var(--nb-ink);">Zniżki:</strong> rodzeństwo −15% · Karta Dużej Rodziny −10%</p>
</div>

<div class="nb-foot">
  <svg class="nb-doodle" style="right:20px; bottom:10px; left:auto; width:70px; height:70px; color:#5f9fd6; opacity:.5;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4"><path d="M12 2l2.9 6.3 6.9.7-5.2 4.6 1.6 6.8L12 16.9 5.8 20.4l1.6-6.8L2.2 9l6.9-.7z"/></svg>
  <div class="nb-join">→ Dołącz do społeczności INNOVA!</div>
  <p>Razem tworzymy miejsce, gdzie dzieci uczą się z radością. 🙂</p>
  <div class="nb-social">
    <a href="https://facebook.com/innova.pracownia" style="background:#3b5998;">f</a>
    <a href="https://instagram.com/innova_pracownia" style="background:linear-gradient(45deg,#f58529,#dd2a7b,#8134af);">ig</a>
  </div>
</div>
<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
