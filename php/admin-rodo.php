<?php
/**
 * Narzędzie RODO dla właściciela pracowni: wyszukanie konta rodzica po
 * e-mailu, podgląd/eksport jego danych, trwałe usunięcie na żądanie —
 * dla sytuacji, gdy prośba przychodzi telefonicznie/mailowo, a nie przez
 * samoobsługowy panel rodzica (panel-konto.php).
 */
require_once __DIR__ . '/includes/bootstrap.php';
$user = require_role(['ADMIN'], 'admin-rodo.php');

$error = null;
$success = null;
$found = null;

if ($_SERVER['REQUEST_METHOD'] === 'GET' && !empty($_GET['export'])) {
    $targetId = (int) $_GET['export'];
    $target = db()->prepare("SELECT id FROM users WHERE id = ? AND role = 'PARENT'");
    $target->execute([$targetId]);
    if ($target->fetch()) {
        rodo_send_export_download(rodo_export_user_data($targetId), 'innova-dane-rodzica-' . $targetId);
    }
    redirect('admin-rodo.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['_action'] ?? '';
    if ($action === 'delete') {
        $targetId = (int) ($_POST['id'] ?? 0);
        $target = db()->prepare("SELECT * FROM users WHERE id = ? AND role = 'PARENT'");
        $target->execute([$targetId]);
        $target = $target->fetch();
        if (!$target) {
            $error = 'Nie znaleziono konta rodzica o tym ID.';
        } else {
            rodo_delete_user($targetId);
            redirect_with('admin-rodo.php', ['deleted' => $target['name'] . ' (' . $target['email'] . ')']);
        }
    }
}

if (!empty($_GET['deleted'])) {
    $success = 'Konto „' . $_GET['deleted'] . '” i powiązane z nim dane zostały trwale usunięte.';
}

$searchEmail = trim((string) ($_GET['q'] ?? ''));
if ($searchEmail !== '') {
    $stmt = db()->prepare("SELECT u.*, (SELECT COUNT(*) FROM children WHERE parent_id = u.id) AS children_count,
            (SELECT COUNT(*) FROM enrollments WHERE parent_id = u.id) AS enrollments_count
        FROM users u WHERE u.role = 'PARENT' AND u.email LIKE ?");
    $stmt->execute(['%' . $searchEmail . '%']);
    $found = $stmt->fetchAll();
}

$pageTitle = 'RODO — INNOVA';
require __DIR__ . '/includes/layout_top.php';
?>
<div class="container-md" style="padding:40px 16px;">
  <?php include __DIR__ . '/includes/partials/admin-nav.php'; ?>
  <h1 style="font-size:1.6rem;">RODO — dane rodziców</h1>
  <p class="text-muted">Wyszukaj konto rodzica po adresie e-mail, żeby pobrać jego dane albo je trwale usunąć na żądanie.</p>

  <?php if ($error): ?><p class="alert alert-error"><?= e($error) ?></p><?php endif; ?>
  <?php if ($success): ?><p class="alert alert-success"><?= e($success) ?></p><?php endif; ?>

  <form method="get" class="mt-6 flex items-center gap-2">
    <input name="q" type="email" placeholder="adres e-mail rodzica" value="<?= e($searchEmail) ?>" style="max-width:320px;">
    <button type="submit" class="btn btn-primary">Szukaj</button>
  </form>

  <?php if ($found !== null): ?>
    <?php if (!$found): ?>
      <p class="text-muted mt-6">Brak konta rodzica pasującego do „<?= e($searchEmail) ?>”.</p>
    <?php endif; ?>
    <div class="mt-6" style="display:flex; flex-direction:column; gap:12px;">
      <?php foreach ($found as $p): ?>
        <div class="card">
          <p style="font-weight:700;"><?= e($p['name']) ?> — <?= e($p['email']) ?></p>
          <p class="text-muted"><?= (int) $p['children_count'] ?> <?= (int) $p['children_count'] === 1 ? 'dziecko' : 'dzieci' ?> ·
            <?= (int) $p['enrollments_count'] ?> zgłoszeń na zajęcia · konto od <?= e(format_pl_date($p['created_at'])) ?></p>
          <div class="flex gap-2 mt-4">
            <a href="<?= e(url('admin-rodo.php?export=' . $p['id'])) ?>" class="btn btn-outline btn-sm">Pobierz dane (JSON)</a>
            <form method="post" onsubmit="return confirm('To NIEODWRACALNIE usunie konto tego rodzica, dane jego dzieci i całą historię zapisów. Na pewno?');">
              <?= csrf_field() ?>
              <input type="hidden" name="_action" value="delete">
              <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
              <button type="submit" class="btn btn-danger btn-sm">Usuń trwale</button>
            </form>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
