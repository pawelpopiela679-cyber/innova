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
$classTypesForPalette = db()->query('SELECT * FROM class_types ORDER BY id ASC')->fetchAll();

$pageTitle = 'Grafik — INNOVA';
$notebookTheme = true;
require __DIR__ . '/includes/layout_top.php';
?>
<div class="container" style="padding:40px 16px;">
  <?php include __DIR__ . '/includes/partials/admin-nav.php'; ?>

  <h1 style="font-size:1.6rem;">Grafik tygodniowy</h1>
  <p class="text-muted mt-2">
    Przeciągnij ikonkę rodzaju zajęć na wybrany dzień i godzinę, żeby dodać nowe zajęcia (albo po prostu
    kliknij pustą komórkę). Kliknij w istniejące zajęcia, żeby je edytować albo odwołać.
  </p>

  <div class="nb-grafik-palette mt-4">
    <?php foreach ($classTypesForPalette as $ct): [$bg, $ink] = nb_pastel($ct['key_name']); ?>
      <div class="nb-grafik-palette-item" draggable="true" data-class-type-id="<?= (int) $ct['id'] ?>"
           style="background:<?= e($bg) ?>; color:<?= e($ink) ?>;" title="Przeciągnij na grafik, żeby dodać">
        <?= nb_icon_svg($ct['key_name'], 'nb-grafik-palette-icon') ?>
        <span><?= e($ct['name']) ?></span>
      </div>
    <?php endforeach; ?>
  </div>

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
                $cellStart = sprintf('%02d:00', $h);
                $cellEnd = sprintf('%02d:00', $h + 1);
                $addUrlBase = 'admin-zajecia-nowe.php?date=' . $cellDate . '&startTime=' . $cellStart . '&endTime=' . $cellEnd;
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
                  <a href="<?= e(url($addUrlBase)) ?>" class="nb-grafik-empty" data-add-url-base="<?= e(url($addUrlBase)) ?>"
                     title="Dodaj zajęcia — <?= e($dayLabels[$dayIndex]) ?> <?= $cellStart ?>"></a>
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

  /* Paleta rodzajów zajęć — przeciągnij ikonkę na pustą komórkę grafiku. */
  .nb-grafik-palette { display:flex; flex-wrap:wrap; gap:8px; }
  .nb-grafik-palette-item {
    display:flex; align-items:center; gap:6px; padding:6px 12px; border-radius:999px;
    font-size:0.82rem; font-weight:600; cursor:grab; user-select:none;
    border:1px solid rgba(0,0,0,0.08);
  }
  .nb-grafik-palette-item:active { cursor:grabbing; }
  .nb-grafik-palette-item.is-dragging { opacity:0.4; }
  .nb-grafik-palette-icon { width:18px; height:18px; flex:none; }
  /* Podświetlenie komórki, nad którą aktualnie przeciągana jest ikonka. */
  .nb-grafik-empty.is-drop-target {
    background:color-mix(in srgb, var(--nb-green,#3f7d45) 18%, transparent) !important;
    outline:2px dashed var(--nb-green,#3f7d45); outline-offset:-2px;
  }
</style>
<script>
(function () {
  var palette = document.querySelectorAll('.nb-grafik-palette-item');
  var dropzones = document.querySelectorAll('.nb-grafik-empty');

  palette.forEach(function (item) {
    item.addEventListener('dragstart', function (e) {
      e.dataTransfer.setData('text/plain', item.getAttribute('data-class-type-id'));
      e.dataTransfer.effectAllowed = 'copy';
      item.classList.add('is-dragging');
    });
    item.addEventListener('dragend', function () {
      item.classList.remove('is-dragging');
    });
  });

  dropzones.forEach(function (zone) {
    zone.addEventListener('dragover', function (e) {
      e.preventDefault();
      e.dataTransfer.dropEffect = 'copy';
      zone.classList.add('is-drop-target');
    });
    zone.addEventListener('dragleave', function () {
      zone.classList.remove('is-drop-target');
    });
    zone.addEventListener('drop', function (e) {
      e.preventDefault();
      zone.classList.remove('is-drop-target');
      var classTypeId = e.dataTransfer.getData('text/plain');
      if (!classTypeId) {
        return;
      }
      var base = zone.getAttribute('data-add-url-base');
      var sep = base.indexOf('?') === -1 ? '?' : '&';
      window.location.href = base + sep + 'classTypeId=' + encodeURIComponent(classTypeId);
    });
  });
})();
</script>
<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
