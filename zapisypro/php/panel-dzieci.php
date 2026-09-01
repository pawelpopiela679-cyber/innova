<?php
require_once __DIR__ . '/includes/bootstrap.php';

$user = require_role(['PARENT'], 'panel-dzieci.php');
$org = require_org();
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $formAction = $_POST['_action'] ?? '';
    if ($formAction === 'add') {
        $firstName = trim((string) ($_POST['first_name'] ?? ''));
        $lastName = trim((string) ($_POST['last_name'] ?? ''));
        $birthDate = (string) ($_POST['birth_date'] ?? '');
        $notes = trim((string) ($_POST['notes'] ?? ''));
        if ($firstName === '' || $lastName === '' || !$birthDate) {
            $error = 'Uzupełnij imię, nazwisko i datę urodzenia.';
        } else {
            db()->prepare('INSERT INTO children (org_id, parent_id, first_name, last_name, birth_date, notes) VALUES (?,?,?,?,?,?)')
                ->execute([$org['id'], $user['id'], $firstName, $lastName, $birthDate, $notes ?: null]);
            redirect('panel-dzieci.php');
        }
    } elseif ($formAction === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        db()->prepare('DELETE FROM children WHERE id = ? AND parent_id = ?')->execute([$id, $user['id']]);
        redirect('panel-dzieci.php');
    }
}

$stmt = db()->prepare('SELECT * FROM children WHERE parent_id = ? ORDER BY first_name ASC');
$stmt->execute([$user['id']]);
$children = $stmt->fetchAll();

$pageTitle = 'Moje dzieci — ' . $org['name'];
require __DIR__ . '/includes/layout_top.php';
?>
<div class="container-sm section">
  <h1 class="section-title">Moje dzieci</h1>
  <?php if ($error): ?><p class="alert alert-error"><?= e($error) ?></p><?php endif; ?>

  <div class="mt-6">
    <?php if (!$children): ?><p class="text-muted">Jeszcze żadnego dziecka — dodaj poniżej.</p><?php endif; ?>
    <?php foreach ($children as $c): ?>
      <div class="child-card reveal">
        <div>
          <strong><?= e($c['first_name'] . ' ' . $c['last_name']) ?></strong>
          <span class="text-muted"> — <?= calculate_age($c['birth_date']) ?> lat (ur. <?= e(format_pl_date($c['birth_date'])) ?>)</span>
          <?php if ($c['notes']): ?><div class="text-muted" style="font-size:.85rem;"><?= e($c['notes']) ?></div><?php endif; ?>
        </div>
        <form method="post" onsubmit="return confirm('Usunąć dziecko z konta?');"><?= csrf_field() ?><input type="hidden" name="_action" value="delete"><input type="hidden" name="id" value="<?= $c['id'] ?>"><button class="btn btn-outline btn-sm">Usuń</button></form>
      </div>
    <?php endforeach; ?>
  </div>

  <h2 class="mt-8">Dodaj dziecko</h2>
  <form method="post" class="mt-4 form-card reveal">
    <?= csrf_field() ?>
    <input type="hidden" name="_action" value="add">
    <div class="grid grid-2">
      <div class="field"><label>Imię</label><input name="first_name" required></div>
      <div class="field"><label>Nazwisko</label><input name="last_name" required></div>
    </div>
    <div class="field"><label>Data urodzenia</label><input type="date" name="birth_date" required></div>
    <div class="field"><label>Uwagi (opcjonalnie)</label><textarea name="notes" rows="2"></textarea></div>
    <button type="submit" class="btn btn-primary">Dodaj dziecko</button>
  </form>
</div>
<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
