<?php
require_once __DIR__ . '/includes/bootstrap.php';

$plans = db()->query('SELECT * FROM subscription_plans ORDER BY sort_order ASC')->fetchAll();

$pageTitle = 'Cennik — ZapisyPro';
require __DIR__ . '/includes/layout_top.php';
?>
<div class="container section">
  <h1 class="section-title reveal">Cennik</h1>
  <p class="text-muted reveal" style="max-width:560px;">Płacisz jeden abonament miesięczny za całą organizację —
    bez limitu zapisów w ramach planu i bez prowizji od zapisanych dzieci.</p>

  <div class="pricing-grid mt-8">
    <?php foreach ($plans as $i => $p): ?>
      <div class="pricing-card <?= $p['key_name'] === 'PRO' ? 'featured' : '' ?> reveal" style="animation-delay: <?= $i * 80 ?>ms;">
        <?php if ($p['key_name'] === 'PRO'): ?><div class="pricing-badge">Najpopularniejszy</div><?php endif; ?>
        <h3><?= e($p['name']) ?></h3>
        <div class="pricing-amount"><?= number_format($p['price_monthly'] / 100, 0, ',', ' ') ?> zł<span>/mies.</span></div>
        <ul class="pricing-features">
          <li>Do <?= $p['max_instructors'] >= 999 ? 'nieograniczonej liczby' : (int) $p['max_instructors'] ?> prowadzących</li>
          <li>Do <?= $p['max_students'] >= 999999 ? 'nieograniczonej liczby' : (int) $p['max_students'] ?> zapisanych dzieci</li>
          <li>Kalendarz i lista rezerwowa</li>
          <li>Status płatności i przypomnienia</li>
          <li>Odrabianie zajęć</li>
          <li>Komunikacja masowa</li>
          <li>Własny branding</li>
        </ul>
        <a href="<?= e(url('rejestracja-organizacji.php')) ?>" class="btn btn-primary" style="width:100%;">Zacznij 14-dniowy trial</a>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="mt-8 reveal" style="max-width:640px;">
    <h3>Płatności online (Przelewy24 / PayU) i integracja SMS</h3>
    <p class="text-muted">W wersji 1 status płatności rodzice/administrator oznaczają ręcznie (np. po przelewie), a SMS-y
      wymagają podpięcia własnego konta u dostawcy bramki (np. SMSAPI.pl) — kod jest już gotowy w
      <code>includes/sms.php</code>, brakuje tylko klucza API. Pełna automatyczna płatność online to naturalny kolejny krok.</p>
  </div>
</div>
<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
