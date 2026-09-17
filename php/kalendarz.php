<?php
require_once __DIR__ . '/includes/bootstrap.php';

// Ta strona to teraz czysty podgląd cotygodniowego rytmu zajęć — bez
// wyboru konkretnego terminu. Realny zapis (wybór dziecka + rodzaju
// zajęć, bez ręcznego przebijania się przez kalendarz) jest w zapisz.php;
// zobacz też komentarz na górze tego pliku.

// --- Siatka "przykładowy tydzień" — bierzemy
// pierwszy pełny tydzień semestru jako reprezentatywny wzorzec zajęć.
$exampleAnchor = new DateTime(SEMESTER_START);
[$ewFrom, $ewTo] = range_for_view('week', $exampleAnchor);
$exampleSessions = get_sessions_with_availability($ewFrom, $ewTo, null);
$weekDayLabels = ['PON', 'WT', 'ŚR', 'CZW', 'PT', 'SOB', 'NIE'];
$exampleGrid = []; // ['H:i' => ['PON' => [sessionRow, ...], ...]]
foreach ($exampleSessions as $s) {
    if ($s['status'] !== 'SCHEDULED') {
        continue;
    }
    $dt = new DateTime($s['starts_at']);
    $hourLabel = $dt->format('H') . ':00';
    $dayLabel = $weekDayLabels[(int) $dt->format('N') - 1];
    $exampleGrid[$hourLabel][$dayLabel][] = $s;
}
ksort($exampleGrid);

$pageTitle = 'Grafik zajęć — INNOVA';
$notebookTheme = true;
$notebookBare = true;
$notebookActive = 'schedule';
require __DIR__ . '/includes/layout_top.php';
?>
<img class="nb-header-banner" src="<?= e(url('assets/img/headers/grafik.png')) ?>" alt="Grafik — sprawdź plan zajęć w INNOVA">

<div class="nb-grid-wrap">
  <table class="nb-week">
    <thead><tr><th></th><?php foreach (['PON', 'WT', 'ŚR', 'CZW', 'PT'] as $d): ?><th><?= $d ?></th><?php endforeach; ?></tr></thead>
    <tbody>
      <?php foreach ($exampleGrid as $hour => $days): ?>
        <tr>
          <td class="nb-time"><?= e($hour) ?></td>
          <?php foreach (['PON', 'WT', 'ŚR', 'CZW', 'PT'] as $d): ?>
            <td>
              <?php foreach ($days[$d] ?? [] as $s): [$bg, $ink] = nb_pastel($s['ct_key']);
                  $slotHref = $s['group_id'] ? signup_url((int) $s['group_id']) : signup_url();
              ?>
                <a href="<?= e($slotHref) ?>" class="nb-slot" style="background:<?= e($bg) ?>;" title="<?= e($s['ct_name']) ?> — kliknij, żeby zapisać dziecko na ten termin">
                  <span class="nb-dot" style="color:<?= e($ink) ?>;"><?= nb_icon_svg($s['ct_key'], '') ?></span>
                  <small style="color:<?= e($ink) ?>;"><?= h_m($s['starts_at']) ?></small>
                </a>
              <?php endforeach; ?>
            </td>
          <?php endforeach; ?>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
