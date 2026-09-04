<?php
require_once __DIR__ . '/includes/bootstrap.php';
$user = require_staff();

/**
 * Dawniej pełny kalendarz miesiąc/tydzień/dzień (includes/partials/calendar-*.php)
 * — usunięty na prośbę właścicielki (nie pasował stylem, a lista poniżej daje
 * to samo, prościej). Partiale zostają w repo nieużywane, jako backup, gdyby
 * kiedyś ten widok był jednak potrzebny z powrotem — patrz git log tego pliku.
 */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_action'] ?? '') === 'cancel_session') {
    csrf_check();
    $sessionId = (int) ($_POST['sessionId'] ?? 0);
    db()->prepare("UPDATE class_sessions SET status = 'CANCELED' WHERE id = ?")->execute([$sessionId]);
    redirect('admin.php?canceled=1');
}

$classTypeId = !empty($_GET['classType']) ? (int) $_GET['classType'] : null;
$classTypes = db()->query('SELECT * FROM class_types ORDER BY id ASC')->fetchAll();

// Okno 14 dni na raz (90 dni naraz dawało stronę o wysokości kilkudziesięciu
// tysięcy pikseli — dziesiątki zajęć dziennie, nieczytelne) — "Poprzednie/
// Następne" przesuwa się o dwa tygodnie, "Dziś" wraca do bieżącego okna.
$windowDays = 14;
$offset = (int) ($_GET['offset'] ?? 0);
$from = (new DateTime('today'))->modify(($offset * $windowDays) . ' days');
$to = (clone $from)->modify("+$windowDays days");
$sessions = get_sessions_with_availability($from, $to, $classTypeId);
$scheduled = array_values(array_filter($sessions, fn($s) => $s['status'] === 'SCHEDULED'));
$totalFree = array_sum(array_column($scheduled, 'spots_left'));
$totalCapacity = array_sum(array_column($scheduled, 'capacity'));

$byDay = [];
foreach ($scheduled as $s) {
    $byDay[substr($s['starts_at'], 0, 10)][] = $s;
}
ksort($byDay);

$pageTitle = 'Zaplanowane zajęcia — INNOVA';
$notebookTheme = true;
require __DIR__ . '/includes/layout_top.php';
?>
<div class="container" style="padding:40px 16px;">
  <?php include __DIR__ . '/includes/partials/admin-nav.php'; ?>

  <h1 style="font-size:1.6rem;">Zaplanowane zajęcia</h1>
  <p class="text-muted mt-2"><?= e(format_pl_date($from->format('Y-m-d'))) ?> – <?= e(format_pl_date((clone $to)->modify('-1 day')->format('Y-m-d'))) ?> — zajętość grup, edycja terminu i odwoływanie zajęć.</p>

  <?php if (isset($_GET['added'])): ?><p class="alert alert-success">Dodano <?= (int) $_GET['added'] ?> <?= (int) $_GET['added'] === 1 ? 'termin' : 'terminów' ?> do kalendarza.</p><?php endif; ?>
  <?php if (isset($_GET['canceled'])): ?><p class="alert alert-info">Zajęcia zostały odwołane.</p><?php endif; ?>
  <?php if (isset($_GET['updated'])): ?><p class="alert alert-success">Termin został zaktualizowany.</p><?php endif; ?>
  <?php if (isset($_GET['deleted'])): ?><p class="alert alert-info">Termin został usunięty.</p><?php endif; ?>

  <div class="flex flex-wrap gap-3 mt-4" style="font-size:0.9rem;">
    <span class="pill" style="background:#dff5e8; color:#1f7a4d;">Wolne miejsca w widoku: <?= $totalFree ?>/<?= $totalCapacity ?></span>
    <span class="pill" style="background:var(--surface); border:1px solid var(--border);">Zaplanowanych zajęć: <?= count($scheduled) ?></span>
  </div>

  <form method="get" class="flex items-center gap-2 mt-4" style="font-size:0.9rem;">
    <input type="hidden" name="offset" value="<?= $offset ?>">
    <label for="classType" class="text-muted" style="margin:0;">Rodzaj zajęć:</label>
    <select id="classType" name="classType" style="width:auto;">
      <option value="">Wszystkie</option>
      <?php foreach ($classTypes as $ct): ?>
        <option value="<?= $ct['id'] ?>" <?= $classTypeId === (int) $ct['id'] ? 'selected' : '' ?>><?= e($ct['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-outline btn-sm">Filtruj</button>
  </form>

  <div class="flex items-center gap-2 mt-4" style="font-size:0.9rem;">
    <a href="<?= e(url('admin.php?' . http_build_query(array_filter(['classType' => $classTypeId, 'offset' => $offset - 1])))) ?>" class="btn btn-outline btn-sm">← Poprzednie 14 dni</a>
    <a href="<?= e(url('admin.php?' . http_build_query(array_filter(['classType' => $classTypeId])))) ?>" class="btn btn-outline btn-sm">Dziś</a>
    <a href="<?= e(url('admin.php?' . http_build_query(array_filter(['classType' => $classTypeId, 'offset' => $offset + 1])))) ?>" class="btn btn-outline btn-sm">Następne 14 dni →</a>
  </div>

  <?php if (!$byDay): ?>
    <p class="text-muted mt-6">Brak zaplanowanych zajęć w tym okresie.</p>
  <?php endif; ?>

  <?php foreach ($byDay as $day => $daySessions): ?>
    <h2 style="font-size:1.05rem; margin-top:28px; margin-bottom:10px;"><?= e(format_pl_date($day, true)) ?></h2>
    <div style="display:flex; flex-direction:column; gap:10px;">
      <?php foreach ($daySessions as $s): ?>
        <div class="session-card">
          <div class="flex items-center gap-2" style="justify-content:space-between; flex-wrap:wrap;">
            <div>
              <span class="dot" style="background:<?= e($s['ct_color']) ?>;"></span>
              <strong><?= e($s['ct_name']) ?> — <?= e($s['title']) ?></strong>
              <p class="text-muted mt-2"><?= h_m($s['starts_at']) ?>–<?= h_m($s['ends_at']) ?> · prowadzi <?= e($s['instructor_name']) ?></p>
            </div>
            <div style="font-weight:700; color:<?= $s['is_full'] ? '#b0413e' : '#1f7a4d' ?>;">
              <?= $s['confirmed_count'] ?>/<?= (int) $s['capacity'] ?> zapisanych
            </div>
          </div>
          <div class="flex gap-2 mt-4">
            <a href="<?= e(url('admin-zajecia-edytuj.php?id=' . $s['id'])) ?>" class="btn btn-outline btn-sm">Edytuj</a>
            <form method="post" style="display:inline;">
              <?= csrf_field() ?>
              <input type="hidden" name="_action" value="cancel_session">
              <input type="hidden" name="sessionId" value="<?= (int) $s['id'] ?>">
              <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Na pewno odwołać te zajęcia?')">Odwołaj zajęcia</button>
            </form>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endforeach; ?>
</div>
<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
