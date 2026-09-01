<?php
/**
 * Raporty: przychody wg miesiąca, zaległości, zapisy/frekwencja wg rodzaju
 * zajęć. Eksport CSV (?export=csv) i widok do wydruku/zapisu jako PDF
 * (przycisk "Drukuj" wywołuje wbudowaną w przeglądarkę funkcję drukowania —
 * bez żadnej biblioteki PDF, żeby działało bez Composera na hostingu
 * współdzielonym).
 */
require_once __DIR__ . '/includes/bootstrap.php';

$user = require_org_admin();
$org = require_org();

// --- Eksport CSV wszystkich zapisów organizacji ---
if (($_GET['export'] ?? '') === 'csv') {
    $stmt = db()->prepare("SELECT e.id, e.status, e.payment_status, e.amount_due_cents, e.paid_at, e.created_at,
            cs.title, cs.starts_at, ct.name AS ct_name, c.first_name, c.last_name, u.name AS parent_name, u.email AS parent_email
        FROM enrollments e
        JOIN class_sessions cs ON cs.id = e.session_id
        JOIN class_types ct ON ct.id = cs.class_type_id
        JOIN children c ON c.id = e.child_id
        JOIN users u ON u.id = e.parent_id
        WHERE e.org_id = ? ORDER BY cs.starts_at DESC");
    $stmt->execute([$org['id']]);
    $rows = $stmt->fetchAll();

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="innovago-zapisy-' . $org['slug'] . '-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputs($out, "\xEF\xBB\xBF"); // BOM, żeby Excel poprawnie odczytał polskie znaki
    fputcsv($out, ['ID', 'Status', 'Płatność', 'Kwota (zł)', 'Opłacono', 'Zajęcia', 'Rodzaj', 'Termin', 'Dziecko', 'Rodzic', 'E-mail rodzica', 'Zgłoszono'], ';');
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['id'], $r['status'], $r['payment_status'],
            $r['amount_due_cents'] !== null ? number_format($r['amount_due_cents'] / 100, 2, ',', '') : '',
            $r['paid_at'] ?? '', $r['title'], $r['ct_name'], $r['starts_at'],
            $r['first_name'] . ' ' . $r['last_name'], $r['parent_name'], $r['parent_email'], $r['created_at'],
        ], ';');
    }
    fclose($out);
    exit;
}

// --- Przychód wg miesiąca (ostatnie 6 miesięcy, licząc bieżący) ---
$sinceMonth = (new DateTime('first day of -5 months'))->format('Y-m-d 00:00:00');
$stmt = db()->prepare("SELECT amount_due_cents, paid_at FROM enrollments WHERE org_id = ? AND payment_status = 'PAID' AND paid_at >= ?");
$stmt->execute([$org['id'], $sinceMonth]);
$paidRows = $stmt->fetchAll();

$revenueByMonth = [];
for ($i = 5; $i >= 0; $i--) {
    $key = (new DateTime("first day of -$i months"))->format('Y-m');
    $revenueByMonth[$key] = 0;
}
foreach ($paidRows as $r) {
    $key = substr($r['paid_at'], 0, 7);
    if (isset($revenueByMonth[$key])) {
        $revenueByMonth[$key] += (int) $r['amount_due_cents'];
    }
}
$maxRevenue = max(1, max($revenueByMonth));

// --- Zaległości ---
$stmt = db()->prepare("SELECT e.amount_due_cents FROM enrollments e WHERE e.org_id = ? AND e.status = 'CONFIRMED' AND e.payment_status = 'UNPAID'");
$stmt->execute([$org['id']]);
$arrearsRows = $stmt->fetchAll();
$arrearsCount = count($arrearsRows);
$arrearsTotalCents = array_sum(array_column($arrearsRows, 'amount_due_cents'));

// --- Zapisy i frekwencja wg rodzaju zajęć ---
$stmt = db()->prepare("SELECT ct.name AS ct_name,
        COUNT(*) AS total,
        SUM(CASE WHEN e.status = 'CONFIRMED' THEN 1 ELSE 0 END) AS confirmed,
        SUM(CASE WHEN e.status = 'CANCELED' THEN 1 ELSE 0 END) AS canceled,
        SUM(CASE WHEN e.status = 'REJECTED' THEN 1 ELSE 0 END) AS rejected,
        SUM(CASE WHEN e.attendance_status = 'ABSENT' THEN 1 ELSE 0 END) AS absences
    FROM enrollments e
    JOIN class_sessions cs ON cs.id = e.session_id
    JOIN class_types ct ON ct.id = cs.class_type_id
    WHERE e.org_id = ?
    GROUP BY ct.id, ct.name
    ORDER BY total DESC");
$stmt->execute([$org['id']]);
$byClassType = $stmt->fetchAll();

$pageTitle = 'Raporty — ' . $org['name'];
require __DIR__ . '/includes/layout_top.php';
?>
<div class="container section">
  <div class="flex items-center gap-2" style="justify-content:space-between;flex-wrap:wrap;">
    <h1 class="section-title" style="margin-bottom:0;">Raporty</h1>
    <div class="flex gap-2">
      <a href="<?= e(url('raporty.php?export=csv')) ?>" class="btn btn-outline btn-sm">⬇ Eksport CSV (wszystkie zapisy)</a>
      <button type="button" class="btn btn-outline btn-sm" onclick="window.print()">🖨 Drukuj / zapisz jako PDF</button>
    </div>
  </div>

  <h2 class="mt-8">Przychód — ostatnie 6 miesięcy</h2>
  <div class="form-card mt-4 reveal">
    <?php foreach ($revenueByMonth as $month => $cents): ?>
      <div class="mt-2" style="font-size:.88rem;">
        <div class="flex" style="justify-content:space-between;">
          <span><?= e(PL_MONTHS_NOM[(int) substr($month, 5, 2)]) ?> <?= substr($month, 0, 4) ?></span>
          <strong><?= format_money($cents) ?></strong>
        </div>
        <div class="usage-bar"><div class="usage-bar-fill" style="width:<?= round($cents / $maxRevenue * 100) ?>%"></div></div>
      </div>
    <?php endforeach; ?>
    <div class="mt-4" style="border-top:1px solid var(--border);padding-top:12px;">
      <strong>Razem: <?= format_money((int) array_sum($revenueByMonth)) ?></strong>
    </div>
  </div>

  <h2 class="mt-8">Zaległości</h2>
  <div class="form-card mt-4 reveal">
    <p><strong><?= $arrearsCount ?></strong> nieopłaconych potwierdzonych zapisów, łącznie <strong><?= format_money((int) $arrearsTotalCents) ?></strong>.</p>
    <a href="<?= e(url('admin.php')) ?>" class="stat-link">Zobacz szczegóły na panelu →</a>
  </div>

  <h2 class="mt-8">Zapisy i frekwencja wg rodzaju zajęć</h2>
  <div class="table-wrap mt-4 reveal">
    <table class="data-table">
      <thead><tr><th>Rodzaj zajęć</th><th>Zgłoszeń łącznie</th><th>Potwierdzone</th><th>Anulowane</th><th>Odrzucone</th><th>Zgłoszone nieobecności</th></tr></thead>
      <tbody>
        <?php if (!$byClassType): ?><tr><td colspan="6" class="text-muted">Brak danych.</td></tr><?php endif; ?>
        <?php foreach ($byClassType as $r): ?>
          <tr>
            <td><?= e($r['ct_name']) ?></td>
            <td><?= (int) $r['total'] ?></td>
            <td><?= (int) $r['confirmed'] ?></td>
            <td><?= (int) $r['canceled'] ?></td>
            <td><?= (int) $r['rejected'] ?></td>
            <td><?= (int) $r['absences'] ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
