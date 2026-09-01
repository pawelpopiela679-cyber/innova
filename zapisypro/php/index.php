<?php
require_once __DIR__ . '/includes/bootstrap.php';

$plans = [];
try {
    $plans = db()->query('SELECT * FROM subscription_plans ORDER BY sort_order ASC')->fetchAll();
} catch (Throwable $e) {
    // baza jeszcze nie zainstalowana
}

$pageTitle = 'ZapisyPro — system zapisów i płatności dla szkółek i klubów';
require __DIR__ . '/includes/layout_top.php';
?>
<section class="hero">
  <div class="container hero-inner">
    <div class="reveal">
      <span class="eyebrow">Platforma SaaS dla szkółek i klubów</span>
      <h1>Zapisy, kalendarz i płatności<br>Twojej szkółki — <span class="grad-text">w jednym miejscu.</span></h1>
      <p class="lead">ZapisyPro to gotowy system dla właścicieli szkółek językowych, klubów sportowych i pracowni
        kreatywnych. Rodzice zapisują dzieci online, Ty widzisz grafik, płatności i frekwencję —
        bez arkuszy kalkulacyjnych i grup na WhatsAppie.</p>
      <div class="hero-cta">
        <a href="<?= e(url('rejestracja-organizacji.php')) ?>" class="btn btn-primary btn-lg">Załóż organizację — 14 dni za darmo</a>
        <a href="<?= e(url('cennik.php')) ?>" class="btn btn-outline btn-lg">Zobacz cennik</a>
      </div>
    </div>
    <div class="hero-visual reveal" style="animation-delay:120ms;">
      <div class="mock-card float">
        <div class="mock-row"><span class="mock-dot" style="background:#4338ca"></span>Robotyka — grupa A <b>8/10</b></div>
        <div class="mock-row"><span class="mock-dot" style="background:#f97362"></span>Angielski — 5-7 lat <b>6/10</b></div>
        <div class="mock-row"><span class="mock-dot" style="background:#818cf8"></span>Plastyka <b>10/10</b></div>
        <div class="mock-bar"><div class="mock-bar-fill"></div></div>
        <div class="mock-stat"><span>Należności opłacone</span><b>82%</b></div>
      </div>
    </div>
  </div>
</section>

<section class="container section">
  <h2 class="section-title reveal">Wszystko, czego potrzebuje właściciel szkółki</h2>
  <div class="feature-grid">
    <?php
    $features = [
        ['📅', 'Kalendarz i limity miejsc', 'Grafik zajęć z automatycznym pilnowaniem limitu w grupie i listą rezerwową.'],
        ['✅', 'Zgłoszenia z potwierdzeniem', 'Nic nie zapisuje się automatycznie — Ty decydujesz, do której grupy trafi dziecko.'],
        ['🔁', 'Odrabianie zajęć', 'Rodzic zgłasza nieobecność i sam wybiera inny termin na odrobienie — bez telefonów.'],
        ['💳', 'Status płatności', 'Widzisz od razu, kto zapłacił, a komu przypomnieć — ręcznie albo (wkrótce) online.'],
        ['🧑‍🏫', 'Godziny i wynagrodzenia', 'System sam liczy godziny każdego prowadzącego i szacowane wynagrodzenie.'],
        ['📣', 'Komunikacja masowa', 'Wiadomość e-mail (i SMS po podłączeniu bramki) do grupy, kilku osób albo wszystkich.'],
        ['🎨', 'Własny branding', 'Kolory dopasowane do Twojej marki — bez dotykania kodu.'],
        ['🏢', 'Wiele organizacji, jeden system', 'Ty jako właściciel platformy widzisz wszystkich klientów i ich plany subskrypcji.'],
    ];
    foreach ($features as $i => $f): ?>
      <div class="feature-card reveal" style="animation-delay: <?= $i * 60 ?>ms;">
        <div class="feature-icon"><?= $f[0] ?></div>
        <h3><?= e($f[1]) ?></h3>
        <p><?= e($f[2]) ?></p>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<?php if ($plans): ?>
<section class="container section">
  <h2 class="section-title reveal">Plany subskrypcji</h2>
  <div class="pricing-grid">
    <?php foreach ($plans as $i => $p): ?>
      <div class="pricing-card <?= $p['key_name'] === 'PRO' ? 'featured' : '' ?> reveal" style="animation-delay: <?= $i * 80 ?>ms;">
        <?php if ($p['key_name'] === 'PRO'): ?><div class="pricing-badge">Najpopularniejszy</div><?php endif; ?>
        <h3><?= e($p['name']) ?></h3>
        <div class="pricing-amount"><?= number_format($p['price_monthly'] / 100, 0, ',', ' ') ?> zł<span>/mies.</span></div>
        <ul class="pricing-features">
          <li>Do <?= $p['max_instructors'] >= 999 ? 'nieograniczonej liczby' : (int) $p['max_instructors'] ?> prowadzących</li>
          <li>Do <?= $p['max_students'] >= 999999 ? 'nieograniczonej liczby' : (int) $p['max_students'] ?> zapisanych dzieci</li>
          <li>Kalendarz, zapisy, płatności</li>
          <li>Odrabianie zajęć i komunikacja</li>
        </ul>
        <a href="<?= e(url('rejestracja-organizacji.php')) ?>" class="btn btn-primary" style="width:100%;">Wybierz i zacznij trial</a>
      </div>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<section class="cta-band reveal">
  <div class="container">
    <h2>Gotowy, żeby zamienić arkusze na system?</h2>
    <p>14 dni okresu próbnego, bez karty płatniczej.</p>
    <a href="<?= e(url('rejestracja-organizacji.php')) ?>" class="btn btn-primary btn-lg">Załóż organizację teraz</a>
  </div>
</section>
<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
