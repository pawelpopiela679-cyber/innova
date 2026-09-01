<?php
/** Godziny i szacowane wynagrodzenia prowadzących w wybranym zakresie dat. */
require_once __DIR__ . '/includes/bootstrap.php';

$user = require_org_admin();
$org = require_org();

$from = $_GET['from'] ?? (new DateTime('first day of this month'))->format('Y-m-d');
$to = $_GET['to'] ?? (new DateTime('last day of this month'))->format('Y-m-d');

$stmt = db()->prepare("SELECT u.id, u.name, u.wage_hourly_cents,
        cs.starts_at, cs.ends_at
    FROM class_sessions cs
    JOIN users u ON u.id = cs.instructor_id
    WHERE cs.org_id = ? AND cs.status != 'CANCELED' AND cs.starts_at >= ? AND cs.starts_at <= ?
    ORDER BY u.name ASC, cs.starts_at ASC");
$stmt->execute([$org['id'], $from . ' 00:00:00', $to . ' 23:59:59']);
$rows = $stmt->fetchAll();

$byInstructor = [];
foreach ($rows as $r) {
    $minutes = (strtotime($r['ends_at']) - strtotime($r['starts_at'])) / 60;
    if (!isset($byInstructor[$r['id']])) {
        $byInstructor[$r['id']] = ['name' => $r['name'], 'wage' => (int) ($r['wage_hourly_cents'] ?? 0), 'minutes' => 0, 'sessions' => 0];
    }
    $byInstructor[$r['id']]['minutes'] += $minutes;
    $byInstructor[$r['id']]['sessions']++;
}

$pageTitle = 'Godziny i wynagrodzenia — ' . $org['name'];
require __DIR__ . '/includes/layout_top.php';
?>
<div class="container section">
  <h1 class="section-title">Godziny i wynagrodzenia</h1>
  <p class="text-muted">Suma godzin przeprowadzonych zajęć w wybranym okresie i szacowane wynagrodzenie na podstawie stawki
    ustawionej w „Prowadzący”. To orientacyjne wyliczenie — nie zastępuje księgowości.</p>

  <form method="get" class="flex items-center gap-2 mt-6">
    <label>Od <input type="date" name="from" value="<?= e($from) ?>"></label>
    <label>Do <input type="date" name="to" value="<?= e($to) ?>"></label>
    <button class="btn btn-outline btn-sm">Przelicz</button>
  </form>

  <div class="table-wrap mt-6 reveal">
    <table class="data-table">
      <thead><tr><th>Prowadzący</th><th>Liczba zajęć</th><th>Godziny</th><th>Stawka/h</th><th>Szacowane wynagrodzenie</th></tr></thead>
      <tbody>
        <?php if (!$byInstructor): ?><tr><td colspan="5" class="text-muted">Brak przeprowadzonych zajęć w tym okresie.</td></tr><?php endif; ?>
        <?php foreach ($byInstructor as $i): $hours = $i['minutes'] / 60; ?>
          <tr>
            <td><?= e($i['name']) ?></td>
            <td><?= $i['sessions'] ?></td>
            <td><?= number_format($hours, 1, ',', ' ') ?> h</td>
            <td><?= $i['wage'] ? format_money($i['wage']) : '— (ustaw w „Prowadzący")' ?></td>
            <td><strong><?= $i['wage'] ? format_money((int) round($hours * $i['wage'])) : '—' ?></strong></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
