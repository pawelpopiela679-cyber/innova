<?php
require_once __DIR__ . '/includes/bootstrap.php';
$user = require_staff();

/**
 * Klikalny grafik tygodniowy — klikasz w pustą komórkę (dzień + godzina) i
 * od razu trafiasz do formularza "Nowa grupa" z podstawioną datą i godziną,
 * zamiast ręcznie wypełniać wszystko od zera. Zajęte komórki pokazują
 * istniejące zajęcia (link do edycji).
 */

$weekAnchor = parse_date_param($_GET['week'] ?? null);
$days = build_week_days($weekAnchor);
$from = (clone $days[0])->setTime(0, 0);
$to = (clone $from)->modify('+7 days');

$sessions = array_values(array_filter(
    get_sessions_with_availability($from, $to, null),
    fn($s) => $s['status'] === 'SCHEDULED'
));

// Siatka godzin: stałe okno 8:00–20:00, plus godziny spoza okna, jeśli akurat
// są tam jakieś zajęcia (żeby nic nie "zniknęło" z widoku).
$hours = range(8, 20);
foreach ($sessions as $s) {
    $h = (int) substr($s['starts_at'], 11, 2);
    if (!in_array($h, $hours, true)) {
        $hours[] = $h;
    }
}
sort($hours);

// cellMap[dzień 0..6][godzina] = [sesja, sesja, ...]
$cellMap = [];
foreach ($sessions as $s) {
    $dt = new DateTime($s['starts_at']);
    $dayIndex = (int) $dt->format('N') - 1;
    $h = (int) $dt->format('H');
    $cellMap[$dayIndex][$h][] = $s;
}

$dayLabels = ['Poniedziałek', 'Wtorek', 'Środa', 'Czwartek', 'Piątek', 'Sobota', 'Niedziela'];

$pageTitle = 'Grafik — INNOVA';
$notebookTheme = true;
require __DIR__ . '/includes/layout_top.php';
?>
<div class="container" style="padding:40px 16px;">
  <?php include __DIR__ . '/includes/partials/admin-nav.php'; ?>

  <h1 style="font-size:1.6rem;">Grafik tygodniowy</h1>
  <p class="text-muted mt-2">
    Kliknij pustą komórkę, żeby dodać zajęcia w tym dniu i o tej godzinie. Kliknij w istniejące zajęcia,
    żeby je edytować albo odwołać.
  </p>

  <div class="flex items-center gap-2 mt-4" style="font-size:0.9rem;">
    <a href="<?= e(url('admin-grafik.php?week=' . date_param((clone $days[0])->modify('-7 days')))) ?>" class="btn btn-outline btn-sm">← Poprzedni tydzień</a>
    <a href="<?= e(url('admin-grafik.php')) ?>" class="btn btn-outline btn-sm">Bieżący tydzień</a>
    <a href="<?= e(url('admin-grafik.php?week=' . date_param((clone $days[0])->modify('+7 days')))) ?>" class="btn btn-outline btn-sm">Następny tydzień →</a>
  </div>
  <p class="text-muted mt-2" style="font-size:0.85rem;">
    <?= e(format_pl_date($days[0]->format('Y-m-d'))) ?> – <?= e(format_pl_date($days[6]->format('Y-m-d'))) ?>
  </p>

  <div class="nb-grid-wrap mt-4">
    <table class="nb-week nb-grafik-week">
      <thead>
        <tr>
          <th style="width:70px;"></th>
          <?php foreach ($days as $d): ?>
            <th>
              <?= e($dayLabels[(int) $d->format('N') - 1]) ?><br>
              <span class="text-muted" style="font-weight:400;"><?= e($d->format('d.m')) ?></span>
            </th>
          <?php endforeach; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($hours as $h): ?>
          <tr>
            <td class="nb-time"><?= sprintf('%02d:00', $h) ?></td>
            <?php foreach ($days as $dayIndex => $d):
                $cellSessions = $cellMap[$dayIndex][$h] ?? [];
                $cellDate = $d->format('Y-m-d');
                $addUrl = url('admin-zajecia-nowe.php?date=' . $cellDate . '&startTime=' . sprintf('%02d:00', $h) . '&endTime=' . sprintf('%02d:00', $h + 1));
            ?>
              <td class="nb-grafik-cell">
                <?php foreach ($cellSessions as $s): [$bg, $ink] = nb_pastel($s['ct_key']); ?>
                  <a href="<?= e(url('admin-zajecia-edytuj.php?id=' . $s['id'])) ?>" class="nb-grafik-chip" title="<?= e($s['title']) ?>" style="background:<?= e($bg) ?>; color:<?= e($ink) ?>;">
                    <span class="nb-grafik-chip-icon"><?= nb_icon_svg($s['ct_key'], '') ?></span>
                    <span>
                      <strong><?= e($s['ct_name']) ?></strong>
                      <?php if ($s['title'] !== $s['ct_name']): ?><br><?= e($s['title']) ?><?php endif; ?>
                      <br><?= h_m($s['starts_at']) ?>–<?= h_m($s['ends_at']) ?>
                    </span>
                  </a>
                <?php endforeach; ?>
                <?php if (!$cellSessions): ?>
                  <a href="<?= e($addUrl) ?>" class="nb-grafik-empty" title="Dodaj zajęcia — <?= e($dayLabels[$dayIndex]) ?> <?= sprintf('%02d:00', $h) ?>"></a>
                <?php endif; ?>
              </td>
            <?php endforeach; ?>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<style>
  table.nb-grafik-week { min-width:900px; }
  /* Puste komórki: całe pole klikalne (bez widocznego przycisku) — najedź,
     żeby zobaczyć delikatne podświetlenie i "+", kliknij gdziekolwiek w
     komórce, żeby dodać zajęcia o tej godzinie/dniu. */
  table.nb-grafik-week td.nb-grafik-cell { padding:0; vertical-align:top; min-width:120px; height:52px; }
  .nb-grafik-empty { display:block; width:100%; height:100%; min-height:52px; text-decoration:none; position:relative; }
  .nb-grafik-empty:hover { background:color-mix(in srgb, var(--nb-green,#3f7d45) 8%, transparent); }
  .nb-grafik-empty:hover::after {
    content:"+"; position:absolute; inset:0; display:flex; align-items:center; justify-content:center;
    color:var(--nb-green,#3f7d45); font-size:1.3rem; font-weight:700;
  }
  .nb-grafik-chip {
    display:flex; gap:6px; align-items:flex-start; border-radius:8px; padding:4px 6px;
    margin:4px; font-size:0.76rem; text-decoration:none;
  }
  .nb-grafik-chip:hover { filter:brightness(0.95); }
  .nb-grafik-chip-icon { flex:none; width:16px; height:16px; margin-top:1px; }
  .nb-grafik-chip-icon svg { width:16px; height:16px; display:block; }
</style>
<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
