<?php
/** Umowy organizacji z poziomu rodzica — podgląd treści i akceptacja. */
require_once __DIR__ . '/includes/bootstrap.php';

$user = require_role(['PARENT'], 'panel-umowy.php');
$org = require_org();
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $contractId = (int) ($_POST['contract_id'] ?? 0);
    $signerName = trim((string) ($_POST['signer_name'] ?? ''));

    $own = db()->prepare('SELECT id FROM contracts WHERE id = ? AND org_id = ?');
    $own->execute([$contractId, $org['id']]);

    if (mb_strlen($signerName) < 3) {
        $error = 'Wpisz imię i nazwisko jako potwierdzenie.';
    } elseif (!$own->fetch()) {
        $error = 'Nie znaleziono umowy.';
    } else {
        $existing = db()->prepare('SELECT id FROM contract_acceptances WHERE contract_id = ? AND parent_id = ?');
        $existing->execute([$contractId, $user['id']]);
        if (!$existing->fetch()) {
            db()->prepare('INSERT INTO contract_acceptances (org_id, contract_id, parent_id, signer_name) VALUES (?,?,?,?)')
                ->execute([$org['id'], $contractId, $user['id'], $signerName]);
        }
        redirect('panel-umowy.php');
    }
}

$stmt = db()->prepare("SELECT c.*, a.accepted_at, a.signer_name AS my_signer_name
    FROM contracts c
    LEFT JOIN contract_acceptances a ON a.contract_id = c.id AND a.parent_id = ?
    WHERE c.org_id = ? ORDER BY c.updated_at DESC");
$stmt->execute([$user['id'], $org['id']]);
$contracts = $stmt->fetchAll();

$pageTitle = 'Moje umowy — ' . $org['name'];
require __DIR__ . '/includes/layout_top.php';
?>
<div class="container-md section">
  <h1 class="section-title">Moje umowy</h1>
  <p class="text-muted"><?= e($org['name']) ?></p>
  <?php if ($error): ?><p class="alert alert-error"><?= e($error) ?></p><?php endif; ?>

  <?php if (!$contracts): ?>
    <p class="text-muted mt-6">Organizacja nie dodała jeszcze żadnej umowy.</p>
  <?php endif; ?>

  <?php foreach ($contracts as $c): ?>
    <div class="form-card mt-6 reveal">
      <div class="flex items-center gap-2" style="justify-content:space-between;">
        <h2 style="margin:0;"><?= e($c['title']) ?></h2>
        <?php if ($c['accepted_at']): ?>
          <span class="badge badge-confirmed">✓ Zaakceptowano <?= e(format_pl_date($c['accepted_at'], false, true)) ?></span>
        <?php else: ?>
          <span class="badge badge-pending">Wymaga akceptacji</span>
        <?php endif; ?>
      </div>
      <div class="mt-4" style="white-space:pre-wrap; max-height:280px; overflow-y:auto; border:1px solid var(--border); border-radius:10px; padding:14px; font-size:.9rem;"><?= e($c['content']) ?></div>

      <?php if (!$c['accepted_at']): ?>
        <form method="post" class="mt-4">
          <?= csrf_field() ?>
          <input type="hidden" name="contract_id" value="<?= $c['id'] ?>">
          <div class="field"><label>Imię i nazwisko (jako potwierdzenie akceptacji)</label><input name="signer_name" required value="<?= e($user['name']) ?>"></div>
          <label class="checkbox-inline"><input type="checkbox" required> Przeczytałem/-am i akceptuję treść powyżej.</label>
          <button type="submit" class="btn btn-primary mt-2">Akceptuję</button>
        </form>
      <?php else: ?>
        <p class="text-muted mt-2" style="font-size:.85rem;">Zaakceptowano jako „<?= e($c['my_signer_name']) ?>”.</p>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
</div>
<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
