<?php
require_once __DIR__ . '/includes/bootstrap.php';
$user = require_staff();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_action'] ?? '') === 'cancel_session') {
    csrf_check();
    $sessionId = (int) ($_POST['sessionId'] ?? 0);
    db()->prepare("UPDATE class_sessions SET status = 'CANCELED' WHERE id = ?")->execute([$sessionId]);
    redirect('admin.php?canceled=1');
}

$view = in_array($_GET['view'] ?? '', ['week', 'day'], true) ? $_GET['view'] : 'month';
$anchor = parse_date_param($_GET['date'] ?? null);
$classTypeId = !empty($_GET['classType']) ? (int) $_GET['classType'] : null;
[$from, $to] = range_for_view($view, $anchor);

$classTypes = db()->query('SELECT * FROM class_types ORDER BY id ASC')->fetchAll();
$sessions = get_sessions_with_availability($from, $to, $classTypeId);
$scheduled = array_values(array_filter($sessions, fn($s) => $s['status'] === 'SCHEDULED'));
$totalFree = array_sum(array_column($scheduled, 'spots_left'));
$totalCapacity = array_sum(array_column($scheduled, 'capacity'));

$basePath = 'admin.php';
$extraParams = $classTypeId ? ['classType' => $classTypeId] : [];

$pageTitle = 'Dostępność terminów — INNOVA';
require __DIR__ . '/includes/layout_top.php';
?>
<div class="container" style="padding:40px 16px;">
  <?php include __DIR__ . '/includes/partials/admin-nav.php'; ?>

  <h1 style="font-size:1.6rem;">Dostępność terminów</h1>
  <p class="text-muted mt-2">Podgląd wolnych miejsc na dany dzień, tydzień i miesiąc — dla wszystkich prowadzących.</p>

  <?php if (isset($_GET['added'])): ?><p class="alert alert-success">Dodano <?= (int) $_GET['added'] ?> <?= (int) $_GET['added'] === 1 ? 'termin' : 'terminów' ?> do kalendarza.</p><?php endif; ?>
  <?php if (isset($_GET['canceled'])): ?><p class="alert alert-info">Zajęcia zostały odwołane.</p><?php endif; ?>

  <div class="flex flex-wrap gap-3 mt-4" style="font-size:0.9rem;">
    <span class="pill" style="background:#dff5e8; color:#1f7a4d;">Wolne miejsca w widoku: <?= $totalFree ?>/<?= $totalCapacity ?></span>
    <span class="pill" style="background:var(--surface); border:1px solid var(--border);">Zaplanowanych zajęć: <?= count($scheduled) ?></span>
  </div>

  <form method="get" class="flex items-center gap-2 mt-4" style="font-size:0.9rem;">
    <input type="hidden" name="view" value="<?= e($view) ?>">
    <input type="hidden" name="date" value="<?= e(date_param($anchor)) ?>">
    <label for="classType" class="text-muted" style="margin:0;">Rodzaj zajęć:</label>
    <select id="classType" name="classType" style="width:auto;">
      <option value="">Wszystkie</option>
      <?php foreach ($classTypes as $ct): ?>
        <option value="<?= $ct['id'] ?>" <?= $classTypeId === (int) $ct['id'] ? 'selected' : '' ?>><?= e($ct['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-outline btn-sm">Filtruj</button>
  </form>

  <?php include __DIR__ . '/includes/partials/calendar-nav.php'; ?>

  <div class="mt-6">
    <?php if ($view === 'month'): ?>
      <?php $showFreeSpots = true; include __DIR__ . '/includes/partials/calendar-month.php'; ?>
    <?php elseif ($view === 'week'): ?>
      <?php $showAdminMeta = true; include __DIR__ . '/includes/partials/calendar-week.php'; ?>
    <?php else: ?>
      <?php $showCancelForm = true; include __DIR__ . '/includes/partials/calendar-day.php'; ?>
    <?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
