<?php
/**
 * Wymaga zmiennych: $basePath (np. "kalendarz.php"), $view, $anchor (DateTime),
 * $extraParams (tablica dodatkowych parametrów GET do zachowania, np. classType).
 */
$prevAnchor = clone $anchor;
$nextAnchor = clone $anchor;
if ($view === 'day') {
    $prevAnchor->modify('-1 day');
    $nextAnchor->modify('+1 day');
} elseif ($view === 'week') {
    $prevAnchor->modify('-7 days');
    $nextAnchor->modify('+7 days');
} else {
    $prevAnchor->modify('-1 month');
    $nextAnchor->modify('+1 month');
}
$label = match ($view) {
    'day' => ucfirst(format_pl_date(date_param($anchor), true)),
    'week' => 'Tydzień od ' . format_pl_weekday_short(date_param(week_start($anchor))),
    default => PL_MONTHS_NOM[(int) $anchor->format('n')] . ' ' . $anchor->format('Y'),
};
?>
<div class="cal-nav">
  <a href="<?= e(calendar_href($basePath, $view, $prevAnchor, $extraParams)) ?>" class="btn btn-outline btn-sm">← Poprzedni</a>
  <a href="<?= e(calendar_href($basePath, $view, new DateTime('today'), $extraParams)) ?>" class="btn btn-outline btn-sm">Dziś</a>
  <a href="<?= e(calendar_href($basePath, $view, $nextAnchor, $extraParams)) ?>" class="btn btn-outline btn-sm">Następny →</a>
  <strong style="margin-left:8px;"><?= e($label) ?></strong>
  <span style="flex:1;"></span>
  <a href="<?= e(calendar_href($basePath, 'month', $anchor, $extraParams)) ?>" class="btn <?= $view === 'month' ? 'btn-primary' : 'btn-outline' ?> btn-sm">Miesiąc</a>
  <a href="<?= e(calendar_href($basePath, 'week', $anchor, $extraParams)) ?>" class="btn <?= $view === 'week' ? 'btn-primary' : 'btn-outline' ?> btn-sm">Tydzień</a>
  <a href="<?= e(calendar_href($basePath, 'day', $anchor, $extraParams)) ?>" class="btn <?= $view === 'day' ? 'btn-primary' : 'btn-outline' ?> btn-sm">Dzień</a>
</div>
