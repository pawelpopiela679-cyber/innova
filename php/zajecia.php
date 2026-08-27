<?php
require_once __DIR__ . '/includes/bootstrap.php';

$classTypes = db()->query('SELECT * FROM class_types ORDER BY id ASC')->fetchAll();
$tiersStmt = db()->prepare('SELECT * FROM pricing_tiers WHERE class_type_id = ? ORDER BY sort_order ASC');

$pageTitle = 'Oferta i cennik — INNOVA';
require __DIR__ . '/includes/layout_top.php';
?>
<div class="container-md" style="padding:48px 16px;">
  <h1 class="text-center" style="font-size:1.8rem;">Oferta <span style="color:var(--coral);">i cennik</span></h1>
  <p class="text-center text-muted mt-4">
    Zajęcia odbywają się 1x w tygodniu. Pełny terminarz i wolne miejsca znajdziesz w
    <a href="<?= e(url('kalendarz.php')) ?>" style="color:var(--coral); text-decoration:underline;">kalendarzu</a>.
  </p>

  <div class="mt-8" style="display:flex; flex-direction:column; gap:24px;">
    <?php foreach ($classTypes as $i => $ct):
        $tiersStmt->execute([$ct['id']]);
        $tiers = $tiersStmt->fetchAll();
        $hasLabels = array_filter($tiers, fn($t) => $t['label'] !== '');
    ?>
      <section id="<?= e($ct['key_name']) ?>" class="card card-top-accent" style="border-top-color:<?= e($ct['color']) ?>;">
        <span class="card-badge" style="background:<?= e($ct['color']) ?>;"><?= $i + 1 ?></span>
        <div class="flex items-center gap-3">
          <span class="icon-box" style="background:<?= e($ct['color']) ?>22; font-size:1.4rem;"><?= class_icon($ct['key_name']) ?></span>
          <h2 style="font-size:1.2rem;"><?= e($ct['name']) ?></h2>
        </div>
        <p class="mt-2"><?= e($ct['description']) ?></p>

        <?php if ($tiers): ?>
        <div class="mt-4" style="overflow-x:auto;">
          <table>
            <thead><tr>
              <?php if ($hasLabels): ?><th>Wariant</th><?php endif; ?>
              <th>Wiek</th><th>Czas</th><th>Cena</th>
            </tr></thead>
            <tbody>
              <?php foreach ($tiers as $t): ?>
                <tr>
                  <?php if ($hasLabels): ?><td><?= e($t['label'] ?: $ct['name']) ?></td><?php endif; ?>
                  <td class="text-muted"><?= e($t['age_label']) ?></td>
                  <td class="text-muted"><?= (int) $t['duration_min'] ?> min</td>
                  <td style="font-weight:700; color:var(--sage);">
                    <?= (int) $t['price_monthly'] ?> zł / mies.
                    <?php if ($t['one_time_fee'] !== null): ?>
                      <span class="text-muted" style="font-weight:400;"> (+ <?= (int) $t['one_time_fee'] ?> zł pakiet startowy, jednorazowo)</span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>

        <p class="text-muted mt-4">Wiek uczestników: <?= (int) $ct['age_min'] ?>–<?= (int) $ct['age_max'] ?> lat</p>
        <a href="<?= e(url('kalendarz.php?classType=' . $ct['id'])) ?>" class="btn btn-primary mt-4">Zobacz terminy</a>
      </section>
    <?php endforeach; ?>
  </div>

  <p class="text-center text-muted mt-8"><strong style="color:var(--foreground);">Zniżki:</strong> rodzeństwo −15% · Karta Dużej Rodziny −10%</p>
</div>
<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
