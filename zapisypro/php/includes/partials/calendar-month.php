<?php
/** Wymaga: $anchor, $sessions, $basePath, $extraParams, $selectedDate. */
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
    <div class="cal-weekday"><?= $wd ?></div>
  <?php endforeach; ?>

  <?php foreach ($days as $i => $day):
      $dateStr = date_param($day['date']);
      // Nazwa celowo NIE "$daySessions": ten plik jest wołany przez require w
      // tym samym scope co kalendarz.php, który ma WŁASNĄ zmienną
      // $daySessions (lista zajęć wybranego dnia pod spodem kalendarza) — ta
      // sama nazwa tutaj by ją nadpisała po każdym przebiegu tej pętli
      // (dokładnie ten sam błąd co kolizja $org w layout_top.php).
      $cellSessions = $byDate[$dateStr] ?? [];
      $freeSpots = array_sum(array_column($cellSessions, 'spots_left'));
      $isSelected = $dateStr === $selectedDate;
      $isToday = $dateStr === date('Y-m-d');
  ?>
    <a href="<?= e(calendar_href($basePath, 'month', $anchor, array_merge($extraParams, ['selected' => $dateStr]))) ?>"
       class="cal-cell <?= $day['inMonth'] ? '' : 'muted' ?> <?= $isSelected ? 'selected' : '' ?> <?= $isToday ? 'today' : '' ?>"
       style="animation-delay: <?= $i * 8 ?>ms;">
        <div class="cal-daynum"><?= (int) $day['date']->format('j') ?></div>
        <?php if ($cellSessions): ?>
          <div class="cal-free <?= $freeSpots > 0 ? 'ok' : 'full' ?>"><?= $freeSpots ?> wolnych</div>
          <?php foreach (array_slice($cellSessions, 0, 2) as $s): ?>
            <div class="cal-dot-row"><span class="dot" style="background:<?= e($s['ct_color']) ?>;"></span><?= h_m($s['starts_at']) ?></div>
          <?php endforeach; ?>
          <?php if (count($cellSessions) > 2): ?><div class="text-muted" style="font-size:.7rem;">+<?= count($cellSessions) - 2 ?> więcej</div><?php endif; ?>
        <?php endif; ?>
    </a>
  <?php endforeach; ?>
</div>
