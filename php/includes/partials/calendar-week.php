<?php
/** Wymaga: $anchor, $sessions, $basePath, $extraParams. Opcjonalnie: $showAdminMeta. */
$showAdminMeta = $showAdminMeta ?? false;
$days = build_week_days($anchor);
$scheduled = array_values(array_filter($sessions, fn($s) => $s['status'] === 'SCHEDULED'));
?>
<div class="cal-week-grid">
  <?php foreach ($days as $day):
      $dateStr = date_param($day);
      $daySessions = array_values(array_filter($scheduled, fn($s) => substr($s['starts_at'], 0, 10) === $dateStr));
      usort($daySessions, fn($a, $b) => strcmp($a['starts_at'], $b['starts_at']));
      $freeSpots = array_sum(array_column($daySessions, 'spots_left'));
  ?>
    <a href="<?= e(calendar_href($basePath, 'day', $day, $extraParams)) ?>" class="cal-day-card" style="display:block; text-decoration:none; color:inherit;">
      <p style="font-weight:700; text-transform:capitalize; margin:0;"><?= e(format_pl_weekday_short($dateStr)) ?></p>
      <?php if ($showAdminMeta): ?>
        <p style="font-size:0.8rem; font-weight:700; color:<?= ($freeSpots === 0 && $daySessions) ? '#b0413e' : '#1f7a4d' ?>; margin:4px 0;">
          <?= $daySessions ? "$freeSpots wolnych miejsc" : 'brak zajęć' ?>
        </p>
      <?php endif; ?>
      <div class="mt-2" style="display:flex; flex-direction:column; gap:4px;">
        <?php if (!$daySessions): ?><p class="text-muted" style="font-size:0.78rem;">Brak zajęć</p><?php endif; ?>
        <?php foreach ($daySessions as $s): ?>
          <div style="font-size:0.78rem;">
            <span class="dot" style="background:<?= e($s['ct_color']) ?>;"></span><?= h_m($s['starts_at']) ?>
            <?= $showAdminMeta ? e($s['ct_name']) . " ({$s['confirmed_count']}/{$s['capacity']})" : e($s['title']) ?>
            <?php if (!$showAdminMeta): ?>
              <div style="color: <?= $s['is_full'] ? '#b0413e' : '#1f7a4d' ?>;"><?= $s['is_full'] ? 'brak miejsc' : $s['spots_left'] . ' wolnych' ?></div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    </a>
  <?php endforeach; ?>
</div>
