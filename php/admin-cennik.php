<?php
require_once __DIR__ . '/includes/bootstrap.php';
$user = require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['_action'] ?? '';

    if ($action === 'update_tier') {
        $id = (int) ($_POST['id'] ?? 0);
        $label = trim((string) ($_POST['label'] ?? ''));
        $ageLabel = trim((string) ($_POST['age_label'] ?? ''));
        $duration = (int) ($_POST['duration_min'] ?? 0);
        $price = (int) ($_POST['price_monthly'] ?? 0);
        $feeRaw = trim((string) ($_POST['one_time_fee'] ?? ''));
        $fee = $feeRaw === '' ? null : (int) $feeRaw;
        $sortOrder = (int) ($_POST['sort_order'] ?? 0);

        if ($ageLabel === '' || $duration <= 0 || $price < 0) {
            redirect_with('admin-cennik.php', ['error' => 'Uzupełnij poprawnie wiek, czas trwania i cenę.']);
        }

        db()->prepare('UPDATE pricing_tiers SET label=?, age_label=?, duration_min=?, price_monthly=?, one_time_fee=?, sort_order=? WHERE id=?')
            ->execute([$label, $ageLabel, $duration, $price, $fee, $sortOrder, $id]);
        redirect('admin-cennik.php?updated=1');
    }

    if ($action === 'delete_tier') {
        $id = (int) ($_POST['id'] ?? 0);
        db()->prepare('DELETE FROM pricing_tiers WHERE id = ?')->execute([$id]);
        redirect('admin-cennik.php?deleted=1');
    }

    if ($action === 'create_tier') {
        $classTypeId = (int) ($_POST['class_type_id'] ?? 0);
        $label = trim((string) ($_POST['label'] ?? ''));
        $ageLabel = trim((string) ($_POST['age_label'] ?? ''));
        $duration = (int) ($_POST['duration_min'] ?? 0);
        $price = (int) ($_POST['price_monthly'] ?? 0);
        $feeRaw = trim((string) ($_POST['one_time_fee'] ?? ''));
        $fee = $feeRaw === '' ? null : (int) $feeRaw;

        if (!$classTypeId || $ageLabel === '' || $duration <= 0 || $price < 0) {
            redirect_with('admin-cennik.php', ['error' => 'Uzupełnij poprawnie wiek, czas trwania i cenę nowej pozycji.']);
        }

        $stmt = db()->prepare('SELECT COALESCE(MAX(sort_order), -1) m FROM pricing_tiers WHERE class_type_id = ?');
        $stmt->execute([$classTypeId]);
        $maxSort = (int) $stmt->fetch()['m'];

        db()->prepare('INSERT INTO pricing_tiers (class_type_id, label, age_label, duration_min, price_monthly, one_time_fee, sort_order) VALUES (?,?,?,?,?,?,?)')
            ->execute([$classTypeId, $label, $ageLabel, $duration, $price, $fee, $maxSort + 1]);
        redirect('admin-cennik.php?added=1');
    }
}

$classTypes = db()->query('SELECT * FROM class_types ORDER BY id ASC')->fetchAll();
$tiersStmt = db()->prepare('SELECT * FROM pricing_tiers WHERE class_type_id = ? ORDER BY sort_order ASC');

$pageTitle = 'Cennik — INNOVA';
require __DIR__ . '/includes/layout_top.php';
?>
<div class="container" style="padding:40px 16px;">
  <?php include __DIR__ . '/includes/partials/admin-nav.php'; ?>

  <h1 style="font-size:1.6rem;">Cennik</h1>
  <p class="text-muted mt-2">
    Edytuj ceny, warianty wiekowe i czas trwania zajęć — zmiany widać od razu na stronie
    <a href="<?= e(url('zajecia.php')) ?>" style="text-decoration:underline;">Zajęcia i cennik</a>.
    Rodzaje zajęć (nazwy, opisy, kolory) edytujesz bezpośrednio w bazie danych — tu zmieniasz tylko pozycje cennika.
  </p>

  <?php if (isset($_GET['error'])): ?><p class="alert alert-error"><?= e($_GET['error']) ?></p><?php endif; ?>
  <?php if (isset($_GET['updated'])): ?><p class="alert alert-success">Zapisano zmiany w cenniku.</p><?php endif; ?>
  <?php if (isset($_GET['added'])): ?><p class="alert alert-success">Dodano nową pozycję cennika.</p><?php endif; ?>
  <?php if (isset($_GET['deleted'])): ?><p class="alert alert-info">Usunięto pozycję cennika.</p><?php endif; ?>

  <div class="mt-6" style="display:flex; flex-direction:column; gap:24px;">
    <?php foreach ($classTypes as $ct):
        if ($ct['key_name'] === 'OPEN_DAY') continue; // Dzień otwarty nie ma cennika
        $tiersStmt->execute([$ct['id']]);
        $tiers = $tiersStmt->fetchAll();
    ?>
      <div class="card">
        <div class="flex items-center gap-2">
          <span class="dot" style="background:<?= e($ct['color']) ?>;"></span>
          <h2 style="font-size:1.1rem;"><?= e($ct['name']) ?></h2>
        </div>

        <?php if ($tiers): ?>
          <div class="mt-4" style="overflow-x:auto;">
            <table>
              <thead>
                <tr>
                  <th>Wariant</th><th>Wiek</th><th>Czas (min)</th><th>Cena/mies. (zł)</th>
                  <th>Opłata jednorazowa (zł)</th><th>Kolejność</th><th></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($tiers as $t):
                    $saveFormId = 'save-tier-' . $t['id'];
                    $deleteFormId = 'delete-tier-' . $t['id'];
                ?>
                  <!-- Formularze deklarowane POZA <tr>/<td> (zagnieżdżanie <form> w wierszu
                       tabeli jest nieprawidłowym HTML-em i część przeglądarek go "gubi",
                       przez co przycisk Zapisz/Usuń nic by nie robił) — pola w wierszu
                       łączymy z formularzem atrybutem form="...". -->
                  <form id="<?= $saveFormId ?>" method="post"></form>
                  <form id="<?= $deleteFormId ?>" method="post" onsubmit="return confirm('Na pewno usunąć tę pozycję cennika?')"></form>
                  <tr>
                    <input type="hidden" form="<?= $saveFormId ?>" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" form="<?= $saveFormId ?>" name="_action" value="update_tier">
                    <input type="hidden" form="<?= $saveFormId ?>" name="id" value="<?= (int) $t['id'] ?>">
                    <td><input form="<?= $saveFormId ?>" type="text" name="label" value="<?= e($t['label']) ?>" style="min-width:120px;"></td>
                    <td><input form="<?= $saveFormId ?>" type="text" name="age_label" required value="<?= e($t['age_label']) ?>" style="min-width:90px;"></td>
                    <td><input form="<?= $saveFormId ?>" type="number" name="duration_min" min="1" required value="<?= (int) $t['duration_min'] ?>" style="width:80px;"></td>
                    <td><input form="<?= $saveFormId ?>" type="number" name="price_monthly" min="0" required value="<?= (int) $t['price_monthly'] ?>" style="width:90px;"></td>
                    <td><input form="<?= $saveFormId ?>" type="number" name="one_time_fee" min="0" value="<?= $t['one_time_fee'] !== null ? (int) $t['one_time_fee'] : '' ?>" placeholder="—" style="width:100px;"></td>
                    <td><input form="<?= $saveFormId ?>" type="number" name="sort_order" value="<?= (int) $t['sort_order'] ?>" style="width:70px;"></td>
                    <td style="white-space:nowrap; display:flex; gap:6px;">
                      <button type="submit" form="<?= $saveFormId ?>" class="btn btn-primary btn-sm">Zapisz</button>
                      <input type="hidden" form="<?= $deleteFormId ?>" name="csrf_token" value="<?= e(csrf_token()) ?>">
                      <input type="hidden" form="<?= $deleteFormId ?>" name="_action" value="delete_tier">
                      <input type="hidden" form="<?= $deleteFormId ?>" name="id" value="<?= (int) $t['id'] ?>">
                      <button type="submit" form="<?= $deleteFormId ?>" class="btn btn-danger btn-sm">Usuń</button>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <p class="text-muted mt-2">Brak pozycji cennika dla tego rodzaju zajęć.</p>
        <?php endif; ?>

        <details class="mt-4">
          <summary style="cursor:pointer; font-weight:600; font-size:0.9rem;">+ Dodaj nową pozycję cennika</summary>
          <form method="post" class="mt-4 grid grid-2">
            <?= csrf_field() ?>
            <input type="hidden" name="_action" value="create_tier">
            <input type="hidden" name="class_type_id" value="<?= (int) $ct['id'] ?>">
            <div class="field">
              <label>Wariant (opcjonalnie)</label>
              <input type="text" name="label" placeholder="np. Mix kreatywny">
            </div>
            <div class="field">
              <label>Wiek</label>
              <input type="text" name="age_label" required placeholder="np. 5–7 lat">
            </div>
            <div class="field">
              <label>Czas trwania (min)</label>
              <input type="number" name="duration_min" min="1" required value="50">
            </div>
            <div class="field">
              <label>Cena miesięczna (zł)</label>
              <input type="number" name="price_monthly" min="0" required value="199">
            </div>
            <div class="field">
              <label>Opłata jednorazowa (zł, opcjonalnie)</label>
              <input type="number" name="one_time_fee" min="0" placeholder="—">
            </div>
            <div style="grid-column:1/-1;">
              <button type="submit" class="btn btn-sm" style="background:var(--sage); color:#fff;">Dodaj pozycję</button>
            </div>
          </form>
        </details>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
