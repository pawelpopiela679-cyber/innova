<?php
/** Lista wszystkich organizacji (klientów SaaS-a) — panel super-admina, czyli Ciebie. */
require_once __DIR__ . '/includes/bootstrap.php';

$user = require_super_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $orgId = (int) ($_POST['org_id'] ?? 0);
    $status = (string) ($_POST['status'] ?? '');
    if (in_array($status, ['TRIAL', 'ACTIVE', 'SUSPENDED', 'CANCELED'], true)) {
        db()->prepare('UPDATE organizations SET status = ? WHERE id = ?')->execute([$status, $orgId]);
    }
    redirect('superadmin.php');
}

$orgs = db()->query("SELECT o.*, p.name AS plan_name,
        (SELECT COUNT(*) FROM users u WHERE u.org_id = o.id AND u.role IN ('ORG_ADMIN','INSTRUCTOR')) AS staff_count,
        (SELECT COUNT(DISTINCT child_id) FROM enrollments e WHERE e.org_id = o.id) AS students_count
    FROM organizations o LEFT JOIN subscription_plans p ON p.id = o.plan_id
    ORDER BY o.created_at DESC")->fetchAll();

$totalMrr = 0;
foreach ($orgs as $o) {
    if ($o['status'] === 'ACTIVE') {
        $planStmt = db()->prepare('SELECT price_monthly FROM subscription_plans WHERE id = ?');
        $planStmt->execute([$o['plan_id']]);
        $totalMrr += (int) ($planStmt->fetch()['price_monthly'] ?? 0);
    }
}

$pageTitle = 'Organizacje — Super-admin ZapisyPro';
require __DIR__ . '/includes/layout_top.php';
?>
<div class="container section">
  <h1 class="section-title">Organizacje</h1>

  <div class="stat-grid mt-6">
    <div class="stat-card reveal"><div class="stat-value" data-count="<?= count($orgs) ?>">0</div><div class="stat-label">Wszystkie organizacje</div></div>
    <div class="stat-card reveal" style="animation-delay:60ms;"><div class="stat-value" data-count="<?= count(array_filter($orgs, fn($o) => $o['status'] === 'ACTIVE')) ?>">0</div><div class="stat-label">Aktywne subskrypcje</div></div>
    <div class="stat-card reveal" style="animation-delay:120ms;"><div class="stat-value" data-count="<?= count(array_filter($orgs, fn($o) => $o['status'] === 'TRIAL')) ?>">0</div><div class="stat-label">W okresie próbnym</div></div>
    <div class="stat-card reveal" style="animation-delay:180ms;"><div class="stat-value"><?= number_format($totalMrr / 100, 0, ',', ' ') ?> zł</div><div class="stat-label">MRR (aktywne plany)</div></div>
  </div>

  <div class="table-wrap mt-8 reveal">
    <table class="data-table">
      <thead><tr><th>Organizacja</th><th>Plan</th><th>Status</th><th>Prowadzący</th><th>Dzieci</th><th>Zmień status</th></tr></thead>
      <tbody>
        <?php foreach ($orgs as $o): ?>
          <tr>
            <td><a href="<?= e(url('superadmin-organizacja.php?id=' . $o['id'])) ?>"><?= e($o['name']) ?></a></td>
            <td><?= e($o['plan_name'] ?? '—') ?></td>
            <td><span class="badge badge-<?= strtolower($o['status']) ?>"><?= e($o['status']) ?></span></td>
            <td><?= (int) $o['staff_count'] ?></td>
            <td><?= (int) $o['students_count'] ?></td>
            <td>
              <form method="post" class="flex items-center gap-2">
                <?= csrf_field() ?>
                <input type="hidden" name="org_id" value="<?= $o['id'] ?>">
                <select name="status" onchange="this.form.submit()">
                  <?php foreach (['TRIAL', 'ACTIVE', 'SUSPENDED', 'CANCELED'] as $s): ?>
                    <option value="<?= $s ?>" <?= $o['status'] === $s ? 'selected' : '' ?>><?= $s ?></option>
                  <?php endforeach; ?>
                </select>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
