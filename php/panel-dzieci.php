<?php
require_once __DIR__ . '/includes/bootstrap.php';
$user = require_login('panel-dzieci.php');

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['_action'] ?? '';

    if ($action === 'add') {
        $firstName = trim((string) ($_POST['firstName'] ?? ''));
        $lastName = trim((string) ($_POST['lastName'] ?? ''));
        $birthDate = trim((string) ($_POST['birthDate'] ?? ''));
        $notes = trim((string) ($_POST['notes'] ?? ''));

        if ($firstName === '') {
            $error = 'Podaj imię dziecka.';
        } elseif ($lastName === '') {
            $error = 'Podaj nazwisko dziecka.';
        } elseif ($birthDate === '' || strtotime($birthDate) === false) {
            $error = 'Podaj poprawną datę urodzenia.';
        } else {
            db()->prepare('INSERT INTO children (parent_id, first_name, last_name, birth_date, notes) VALUES (?,?,?,?,?)')
                ->execute([$user['id'], $firstName, $lastName, $birthDate, $notes ?: null]);
            redirect('panel-dzieci.php?added=1');
        }
    } elseif ($action === 'delete') {
        $childId = (int) ($_POST['childId'] ?? 0);
        $c = db()->prepare('SELECT * FROM children WHERE id = ?');
        $c->execute([$childId]);
        $c = $c->fetch();
        if ($c && (int) $c['parent_id'] === (int) $user['id']) {
            db()->prepare('DELETE FROM children WHERE id = ?')->execute([$childId]);
        }
        redirect('panel-dzieci.php?removed=1');
    }
}

$stmt = db()->prepare('SELECT * FROM children WHERE parent_id = ? ORDER BY first_name ASC');
$stmt->execute([$user['id']]);
$children = $stmt->fetchAll();

$pageTitle = 'Moje dzieci — INNOVA';
$notebookTheme = true;
require __DIR__ . '/includes/layout_top.php';
?>
<div class="container-md" style="padding:40px 16px;">
  <?php include __DIR__ . '/includes/partials/panel-nav.php'; ?>
  <h1 style="font-size:1.6rem;">Moje dzieci</h1>

  <?php if ($error): ?><p class="alert alert-error"><?= e($error) ?></p><?php endif; ?>
  <?php if (isset($_GET['added'])): ?><p class="alert alert-success">Dziecko zostało dodane.</p><?php endif; ?>
  <?php if (isset($_GET['removed'])): ?><p class="alert alert-success">Dziecko zostało usunięte.</p><?php endif; ?>

  <div class="mt-6" style="display:flex; flex-direction:column; gap:12px;">
    <?php if (!$children): ?>
      <p class="text-muted">Nie masz jeszcze dodanych dzieci — dodaj pierwsze poniżej.</p>
    <?php endif; ?>
    <?php foreach ($children as $c): ?>
      <div class="card flex items-center justify-between" style="justify-content:space-between;">
        <div>
          <p style="font-weight:700;"><?= e($c['first_name'] . ' ' . $c['last_name']) ?></p>
          <p class="text-muted">ur. <?= e(format_pl_date($c['birth_date'])) ?></p>
          <?php if ($c['notes']): ?><p class="mt-2"><?= e($c['notes']) ?></p><?php endif; ?>
        </div>
        <form method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="_action" value="delete">
          <input type="hidden" name="childId" value="<?= (int) $c['id'] ?>">
          <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Na pewno usunąć to dziecko?')">Usuń</button>
        </form>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="card mt-8">
    <h2 style="font-size:1.1rem;">Dodaj dziecko</h2>
    <form method="post" class="mt-4 grid grid-2">
      <?= csrf_field() ?>
      <input type="hidden" name="_action" value="add">
      <div class="field">
        <label for="firstName">Imię</label>
        <input id="firstName" name="firstName" required>
      </div>
      <div class="field">
        <label for="lastName">Nazwisko</label>
        <input id="lastName" name="lastName" required>
      </div>
      <div class="field">
        <label for="birthDate">Data urodzenia</label>
        <input id="birthDate" name="birthDate" type="date" required>
      </div>
      <div class="field">
        <label for="notes">Uwagi (opcjonalnie)</label>
        <input id="notes" name="notes" placeholder="np. alergie, potrzeby specjalne">
      </div>
      <div style="grid-column: 1 / -1;">
        <button type="submit" class="btn btn-primary">Dodaj dziecko</button>
      </div>
    </form>
  </div>
</div>
<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
