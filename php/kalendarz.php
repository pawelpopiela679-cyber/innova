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
$notebookActive = 'schedule';
require __DIR__ . '/includes/layout_top.php';
?>
<div class="nb-hero" style="grid-template-columns:1fr 1.4fr; margin-top:0;">
  <div class="nb-photo-block" style="max-width:260px;">
    <img src="<?= e(url('assets/img/banners/grafik.png')) ?>" alt="Grafik — sprawdź aktualne terminy zajęć i zaplanuj swoją przygodę z INNOVA!" style="width:100%; height:auto; filter:drop-shadow(0 8px 16px rgba(74,67,38,.18));">
  </div>
  <div>
    <div class="nb-step-title">3. Sprawdź grafik <span class="nb-box"></span></div>
    <div class="nb-step-sub">Poniżej przykładowy, cotygodniowy rytm zajęć.</div>
    <a href="<?= e(signup_url()) ?>" class="nb-pill-dark">Zapisz się ↓</a>
  </div>
</div>

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
                  $slotHref = $s['group_id'] ? signup_url((int) $s['group_id']) : url('zajecia.php#cennik-' . $s['ct_key']);
              ?>
                <a href="<?= e($slotHref) ?>" class="nb-slot" title="<?= e($s['ct_name']) ?> — kliknij, żeby zapisać dziecko na ten termin">
                  <span class="nb-dot" style="background:<?= e($bg) ?>;"><?= nb_icon_svg($s['ct_key'], '') ?></span>
                  <small><?= h_m($s['starts_at']) ?></small>
                </a>
              <?php endforeach; ?>
            </td>
          <?php endforeach; ?>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<div class="nb-two-col" style="margin-top:34px;">
  <div>
    <div class="nb-step-title">4. Zapisz się <span class="nb-box"></span></div>
    <div class="nb-step-sub">To proste!</div>
    <div class="nb-process-item">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="12" rx="1.5"/><path d="M2 20h20"/></svg>
      <div>Wybierz dziecko i rodzaj zajęć w formularzu zapisu <b>(2 minuty)</b></div>
    </div>
    <div class="nb-process-item">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 6l9 7 9-7"/></svg>
      <div>Pracownia dobiera grupę i potwierdza zapis <b>e-mailem</b></div>
    </div>
    <div class="nb-process-item">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M8 14s1.5 2 4 2 4-2 4-2M9 9h.01M15 9h.01"/></svg>
      <div>Dołącz do INNOVA i odkryj swoje pasje!</div>
    </div>
  </div>
  <div>
    <img src="<?= e(url('assets/img/banners/zapisz-sie.png')) ?>" alt="Zapisz się już dziś!" style="width:130px; height:auto; display:block; margin:0 auto 16px; transform:rotate(-3deg);">
    <div class="nb-callout">
      <div style="display:flex; gap:10px; align-items:center; margin-bottom:6px;">
        <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" style="color:var(--nb-gold);"><path d="M12 2l2.9 6.3 6.9.7-5.2 4.6 1.6 6.8L12 16.9 5.8 20.4l1.6-6.8L2.2 9l6.9-.7z"/></svg>
        <b>Dzień otwarty — bezpłatne zajęcia pokazowe</b>
      </div>
      <p style="margin:0 0 12px; font-size:.85rem;"><?= e(format_pl_date(OPEN_DAY_DATE)) ?>, 10:00–13:00 — poznaj pracownię, prowadzących i ofertę zajęć przed startem zapisów na semestr.</p>
      <a href="<?= e(signup_url()) ?>" class="nb-btn solid uppercase" style="width:100%; justify-content:center; box-sizing:border-box;">Zapisz się teraz</a>
    </div>
  </div>
</div>
<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
