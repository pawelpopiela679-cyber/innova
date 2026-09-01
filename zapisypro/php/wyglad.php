<?php
require_once __DIR__ . '/includes/bootstrap.php';

$user = require_org_admin();
$org = require_org();
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_GET['reset'])) {
    csrf_check();
    db()->prepare('DELETE FROM org_settings WHERE org_id = ?')->execute([$org['id']]);
    $success = 'Przywrócono domyślne kolory.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    save_theme((int) $org['id'], [
        'background' => (string) ($_POST['background'] ?? DEFAULT_THEME['background']),
        'foreground' => (string) ($_POST['foreground'] ?? DEFAULT_THEME['foreground']),
        'surface' => (string) ($_POST['surface'] ?? DEFAULT_THEME['surface']),
        'border' => (string) ($_POST['border'] ?? DEFAULT_THEME['border']),
        'primary' => (string) ($_POST['primary'] ?? DEFAULT_THEME['primary']),
        'primaryLight' => (string) ($_POST['primaryLight'] ?? DEFAULT_THEME['primaryLight']),
        'accent' => (string) ($_POST['accent'] ?? DEFAULT_THEME['accent']),
        'muted' => (string) ($_POST['muted'] ?? DEFAULT_THEME['muted']),
    ]);
    $success = 'Zapisano kolory — widoczne od razu na całej stronie.';
}

$theme = get_theme((int) $org['id']);

$pageTitle = 'Wygląd — ' . $org['name'];
require __DIR__ . '/includes/layout_top.php';
?>
<div class="container-sm section">
  <h1 class="section-title">Wygląd</h1>
  <p class="text-muted">Dopasuj kolory do marki swojej organizacji — bez dotykania kodu.</p>

  <?php if ($success): ?><p class="alert alert-success"><?= e($success) ?></p><?php endif; ?>

  <form method="post" class="mt-6 form-card reveal">
    <?= csrf_field() ?>
    <div class="grid grid-2">
      <?php foreach ([
          'primary' => 'Kolor główny', 'accent' => 'Akcent',
          'background' => 'Tło strony', 'surface' => 'Tło kart',
          'foreground' => 'Kolor tekstu', 'border' => 'Obramowania',
          'primaryLight' => 'Kolor główny (jasny)', 'muted' => 'Tekst przygaszony',
      ] as $key => $label): ?>
        <div class="field">
          <label for="<?= $key ?>"><?= $label ?></label>
          <input type="color" id="<?= $key ?>" name="<?= $key ?>" value="<?= e($theme[$key]) ?>">
        </div>
      <?php endforeach; ?>
    </div>
    <button type="submit" class="btn btn-primary">Zapisz kolory</button>
    <button type="submit" formaction="<?= e(url('wyglad.php?reset=1')) ?>" class="btn btn-outline">Przywróć domyślne</button>
  </form>
</div>
<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
