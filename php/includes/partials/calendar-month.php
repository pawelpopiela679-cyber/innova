<?php
/** Wymaga: $anchor, $sessions, $basePath, $extraParams. Opcjonalnie: $showFreeSpots. */
$showFreeSpots = $showFreeSpots ?? false;
$days = month_grid_days($anchor);

$byDate = [];
foreach ($sessions as $s) {
    if ($s['status'] !== 'SCHEDULED') continue;
    $d = substr($s['starts_at'], 0, 10);
    $byDate[$d][] = $s;
}
?>
<div class="cal-grid-month">
  <?php foreach (['Pon', 'Wt', 'Śr', 'Czw', 'Pt', 'Sob', 'Nd'] as $wd): ?>
    <div class="text-center text-muted" style="font-size:0.75rem; font-weight:700;"><?= $wd ?></div>
  <?php endforeach; ?>

  <?php foreach ($days as $day):
      $dateStr = date_param($day['date']);
      $daySessions = $byDate[$dateStr] ?? [];
      $freeSpots = array_sum(array_column($daySessions, 'spots_left'));
  ?>
    <div class="cal-cell <?= $day['inMonth'] ? '' : 'muted' ?>">
      <a href="<?= e(calendar_href($basePath, 'day', $day['date'], $extraParams)) ?>">
        <div class="cal-daynum"><?= (int) $day['date']->format('j') ?></div>
        <?php if ($daySessions): ?>
          <?php if ($showFreeSpots): ?>
            <div style="color: <?= $freeSpots > 0 ? '#1f7a4d' : '#b0413e' ?>; font-weight:700;"><?= $freeSpots ?> wolnych</div>
          <?php endif; ?>
          <?php foreach (array_slice($daySessions, 0, 3) as $s): ?>
            <div><span class="dot" style="background:<?= e($s['ct_color']) ?>;"></span><?= h_m($s['starts_at']) ?></div>
          <?php endforeach; ?>
          <?php if (count($daySessions) > 3): ?><div class="text-muted">+<?= count($daySessions) - 3 ?> więcej</div><?php endif; ?>
        <?php endif; ?>
      </a>
    </div>
  <?php endforeach; ?>
</div>
