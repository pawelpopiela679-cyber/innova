<?php
require_once __DIR__ . '/includes/bootstrap.php';
$user = require_staff();

/**
 * Grafik zajęć jednego dziecka (tydzień/miesiąc/kwartał/pół roku/rok) — do
 * wydruku (Ctrl+P w przeglądarce). Widoczne tylko dla prowadzących/admina
 * (require_staff() wyżej) — link doprowadza tu z admin-grupy.php, przy
 * każdym dziecku przypisanym do grupy.
 */

$childId = (int) ($_GET['child_id'] ?? 0);
$child = db()->prepare('SELECT * FROM children WHERE id = ?');
$child->execute([$childId]);
$child = $child->fetch();

if (!$child) {
    http_response_code(404);
    $pageTitle = 'Nie znaleziono — INNOVA';
    $notebookTheme = true;
    require __DIR__ . '/includes/layout_top.php';
    echo '<div class="container-sm text-center" style="padding:64px 16px;"><h1>404</h1><p class="text-muted">Nie znaleziono takiego dziecka.</p></div>';
    require __DIR__ . '/includes/layout_bottom.php';
    exit;
}

$ranges = ['week' => ['Tydzień', 1], 'month' => ['Miesiąc', 4], 'quarter' => ['Kwartał', 13], 'half' => ['Pół roku', 26], 'year' => ['Rok', 52]];
$range = array_key_exists($_GET['range'] ?? '', $ranges) ? $_GET['range'] : 'month';
[$rangeLabel, $weeksCount] = $ranges[$range];

// Grupy, do których dziecko jest aktywnie zapisane (CONFIRMED — lista
// rezerwowa nie ma jeszcze stałego miejsca w grafiku).
$groups = db()->prepare("SELECT g.*, ct.name AS ct_name
    FROM enrollments e JOIN class_groups g ON g.id = e.group_id JOIN class_types ct ON ct.id = g.class_type_id
    WHERE e.child_id = ? AND e.status = 'CONFIRMED'
    ORDER BY g.day_of_week ASC, g.start_time ASC");
$groups->execute([$childId]);
$groups = $groups->fetchAll();

// Konkretne daty w wybranym zakresie, per grupa — od najbliższego wystąpienia
// jej dnia tygodnia, co tydzień, $weeksCount razy.
$occurrencesByDate = [];
$today = new DateTime('today');
foreach ($groups as $g) {
    $next = clone $today;
    $todayIso = (int) $today->format('N');
    $targetIso = (int) $g['day_of_week'];
    $diff = ($targetIso - $todayIso + 7) % 7;
    $next->modify("+$diff days");
    for ($w = 0; $w < $weeksCount; $w++) {
        $date = (clone $next)->modify("+$w weeks");
        $key = $date->format('Y-m-d');
        $occurrencesByDate[$key][] = $g;
    }
}
ksort($occurrencesByDate);

$pageTitle = 'Grafik — ' . $child['first_name'] . ' ' . $child['last_name'] . ' — INNOVA';
$notebookTheme = true;
require __DIR__ . '/includes/layout_top.php';
?>
<div class="container-md nb-print-area" style="padding:40px 16px;">
  <div class="flex items-center justify-between no-print" style="justify-content:space-between; flex-wrap:wrap; gap:12px;">
    <a href="<?= e(url('admin-grupy.php')) ?>" style="color:var(--primary); text-decoration:underline; font-size:0.85rem;">← Wróć do panelu grup</a>
    <button onclick="window.print()" class="btn btn-primary btn-sm">🖨️ Drukuj</button>
  </div>

  <h1 class="mt-4" style="font-size:1.6rem;">Grafik zajęć — <?= e($child['first_name'] . ' ' . $child['last_name']) ?></h1>
  <p class="text-muted mt-2">ur. <?= e(format_pl_date($child['birth_date'])) ?></p>

  <div class="flex flex-wrap gap-2 mt-4 no-print">
    <?php foreach ($ranges as $key => $rangeDef): ?>
      <a href="<?= e(url('harmonogram-dziecka.php?child_id=' . $childId . '&range=' . $key)) ?>" class="btn btn-sm <?= $key === $range ? 'btn-primary' : 'btn-outline' ?>"><?= e($rangeDef[0]) ?></a>
    <?php endforeach; ?>
  </div>

  <?php if (!$groups): ?>
    <p class="text-muted mt-6">To dziecko nie jest jeszcze potwierdzone w żadnej grupie.</p>
  <?php else: ?>
    <h2 class="mt-8" style="font-size:1.1rem;">Cotygodniowy rytm</h2>
    <table class="mt-3">
      <thead><tr><th>Dzień</th><th>Godzina</th><th>Zajęcia</th><th>Prowadzący</th></tr></thead>
      <tbody>
        <?php foreach ($groups as $g): ?>
          <tr>
            <td><?= e(weekday_name_iso((int) $g['day_of_week'])) ?></td>
            <td><?= e($g['start_time']) ?>–<?= e($g['end_time']) ?></td>
            <td><?= e($g['ct_name']) ?> — <?= e($g['name']) ?></td>
            <td><?= e($g['instructor_name']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <h2 class="mt-8" style="font-size:1.1rem;"><?= e($rangeLabel) ?> — konkretne terminy</h2>
    <div class="mt-3" style="display:flex; flex-direction:column; gap:6px;">
      <?php foreach ($occurrencesByDate as $date => $dateGroups): ?>
        <div class="flex items-center gap-3" style="border-bottom:1px solid var(--border); padding-bottom:6px;">
          <strong style="min-width:200px;"><?= e(format_pl_date($date, true)) ?></strong>
          <span class="text-muted">
            <?php foreach ($dateGroups as $i => $g): ?>
              <?= $i > 0 ? ' · ' : '' ?><?= e($g['ct_name']) ?> (<?= e($g['start_time']) ?>–<?= e($g['end_time']) ?>)
            <?php endforeach; ?>
          </span>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
<style media="print">
  .notebook .spiral, .notebook .nb-tabs, .notebook .nb-topbar, .no-print, footer.site-footer { display: none !important; }
  .notebook { box-shadow: none !important; background: none !important; padding: 0 !important; min-height: 0 !important; }
</style>
<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
