<?php
/**
 * Umowy online — właściciel organizacji tworzy/edytuje treść (np. regulamin,
 * umowa o świadczenie zajęć, zgoda RODO) jako zwykły tekst; rodzice akceptują
 * ją z poziomu panel-umowy.php (wpisując imię i nazwisko jako podpis +
 * potwierdzenie), a tu widać, kto już zaakceptował którą wersję.
 *
 * To NIE jest prawdziwy podpis elektroniczny (kwalifikowany/zaufany, w
 * rozumieniu eIDAS) — to prosta akceptacja "checkbox + imię i nazwisko +
 * znacznik czasu", wystarczająca do wielu regulaminów, ale nie do umów
 * wymagających prawnie wiążącego podpisu. Patrz README_PHP.md.
 */
require_once __DIR__ . '/includes/bootstrap.php';

$user = require_org_admin();
$org = require_org();
$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $formAction = $_POST['_action'] ?? '';

    if ($formAction === 'save') {
        $id = (int) ($_POST['id'] ?? 0);
        $title = trim((string) ($_POST['title'] ?? ''));
        $content = (string) ($_POST['content'] ?? '');

        if (mb_strlen($title) < 2 || trim($content) === '') {
            $error = 'Podaj tytuł i treść umowy.';
        } elseif ($id) {
            $own = db()->prepare('SELECT id FROM contracts WHERE id = ? AND org_id = ?');
            $own->execute([$id, $org['id']]);
            if ($own->fetch()) {
                db()->prepare('UPDATE contracts SET title = ?, content = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?')
                    ->execute([$title, $content, $id]);
                // Nowa wersja treści = trzeba zaakceptować ponownie — kasujemy stare akceptacje.
                db()->prepare('DELETE FROM contract_acceptances WHERE contract_id = ?')->execute([$id]);
                $success = 'Zapisano zmiany. Treść zmieniła się, więc wszyscy rodzice muszą zaakceptować ją ponownie.';
            }
        } else {
            db()->prepare('INSERT INTO contracts (org_id, title, content) VALUES (?,?,?)')
                ->execute([$org['id'], $title, $content]);
            $success = 'Dodano nową umowę.';
        }
    } elseif ($formAction === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        db()->prepare('DELETE FROM contracts WHERE id = ? AND org_id = ?')->execute([$id, $org['id']]);
        redirect('umowy.php');
    }
}

$stmt = db()->prepare('SELECT c.*, (SELECT COUNT(*) FROM contract_acceptances a WHERE a.contract_id = c.id) AS accepted_count,
        (SELECT COUNT(*) FROM users u WHERE u.org_id = c.org_id AND u.role = \'PARENT\') AS parents_count
    FROM contracts c WHERE c.org_id = ? ORDER BY c.updated_at DESC');
$stmt->execute([$org['id']]);
$contracts = $stmt->fetchAll();

$editId = (int) ($_GET['edit'] ?? 0);
$editing = null;
if ($editId) {
    $stmt = db()->prepare('SELECT * FROM contracts WHERE id = ? AND org_id = ?');
    $stmt->execute([$editId, $org['id']]);
    $editing = $stmt->fetch() ?: null;
}

$pageTitle = 'Umowy — ' . $org['name'];
require __DIR__ . '/includes/layout_top.php';
?>
<div class="container section">
  <h1 class="section-title">Umowy</h1>
  <p class="text-muted">Regulaminy, umowy, zgody RODO itp. — rodzice akceptują je w swoim panelu.
    Zmiana treści wymaga ponownej akceptacji przez wszystkich.</p>

  <?php if ($error): ?><p class="alert alert-error"><?= e($error) ?></p><?php endif; ?>
  <?php if ($success): ?><p class="alert alert-success"><?= e($success) ?></p><?php endif; ?>

  <h2 class="mt-8"><?= $editing ? 'Edytuj: ' . e($editing['title']) : 'Nowa umowa' ?></h2>
  <form method="post" class="mt-4 form-card reveal">
    <?= csrf_field() ?>
    <input type="hidden" name="_action" value="save">
    <input type="hidden" name="id" value="<?= $editing['id'] ?? 0 ?>">
    <div class="field"><label>Tytuł</label><input name="title" required value="<?= e($editing['title'] ?? '') ?>" placeholder="np. Regulamin zajęć"></div>
    <div class="field"><label>Treść</label><textarea name="content" rows="10" required placeholder="Wklej treść umowy/regulaminu..."><?= e($editing['content'] ?? '') ?></textarea></div>
    <button type="submit" class="btn btn-primary"><?= $editing ? 'Zapisz zmiany' : 'Dodaj umowę' ?></button>
    <?php if ($editing): ?><a href="<?= e(url('umowy.php')) ?>" class="btn btn-outline">Anuluj edycję</a><?php endif; ?>
  </form>

  <h2 class="mt-8">Wszystkie umowy</h2>
  <?php if (!$contracts): ?>
    <p class="text-muted mt-2">Jeszcze żadnej — dodaj pierwszą powyżej.</p>
  <?php else: ?>
    <div class="table-wrap mt-4 reveal">
      <table class="data-table">
        <thead><tr><th>Tytuł</th><th>Zaktualizowano</th><th>Akceptacje</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($contracts as $c): ?>
            <tr>
              <td><?= e($c['title']) ?></td>
              <td><?= e(format_pl_date($c['updated_at'], false, true)) ?></td>
              <td><?= (int) $c['accepted_count'] ?> / <?= (int) $c['parents_count'] ?> rodziców</td>
              <td>
                <a href="<?= e(url('umowy.php?edit=' . $c['id'])) ?>" class="btn btn-outline btn-sm">Edytuj</a>
                <form method="post" class="inline" onsubmit="return confirm('Usunąć tę umowę? Zniknie też historia akceptacji.');">
                  <?= csrf_field() ?><input type="hidden" name="_action" value="delete"><input type="hidden" name="id" value="<?= $c['id'] ?>">
                  <button class="btn btn-outline btn-sm">Usuń</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
