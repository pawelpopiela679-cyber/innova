<?php
require_once __DIR__ . '/includes/bootstrap.php';
$user = require_admin();

$fields = [
    ['key' => 'background', 'label' => 'Tło strony', 'hint' => 'Główny kolor tła.'],
    ['key' => 'surface', 'label' => 'Karty i panele', 'hint' => 'Tło kart, formularzy, nawigacji.'],
    ['key' => 'border', 'label' => 'Obramowania', 'hint' => 'Cienkie linie/ramki elementów.'],
    ['key' => 'foreground', 'label' => 'Tekst główny', 'hint' => 'Kolor podstawowego tekstu.'],
    ['key' => 'muted', 'label' => 'Tekst pomocniczy', 'hint' => 'Podpisy, mniej ważne informacje.'],
    ['key' => 'primary', 'label' => 'Kolor główny', 'hint' => 'Przyciski, akcenty (oliwkowy).'],
    ['key' => 'primaryLight', 'label' => 'Kolor główny (jasny)', 'hint' => 'Jaśniejszy wariant koloru głównego.'],
    ['key' => 'accent', 'label' => 'Akcent', 'hint' => 'Wyróżnienia, np. pudrowy róż.'],
    ['key' => 'gold', 'label' => 'Złoty akcent', 'hint' => 'Dodatkowy kolor ozdobny.'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['_action'] ?? '';

    if ($action === 'reset') {
        reset_theme();
        redirect('admin-wyglad.php?reset=1');
    }

    $theme = [];
    foreach ($fields as $f) {
        $val = (string) ($_POST[$f['key']] ?? '');
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $val)) {
            redirect_with('admin-wyglad.php', ['error' => 'Nieprawidłowy kolor: ' . $f['label']]);
        }
        $theme[$f['key']] = $val;
    }
    save_theme($theme);
    redirect('admin-wyglad.php?saved=1');
}

$theme = get_theme();

$pageTitle = 'Wygląd — INNOVA';
$notebookTheme = true;
require __DIR__ . '/includes/layout_top.php';
?>
<div class="container-sm" style="padding:40px 16px;">
  <?php include __DIR__ . '/includes/partials/admin-nav.php'; ?>

  <h1 style="font-size:1.6rem;">Wygląd strony</h1>
  <p class="text-muted mt-2">Zmień kolory całej strony — zmiany obowiązują natychmiast dla wszystkich odwiedzających.</p>

  <?php if (isset($_GET['error'])): ?><p class="alert alert-error"><?= e($_GET['error']) ?></p><?php endif; ?>
  <?php if (isset($_GET['saved'])): ?><p class="alert alert-success">Zapisano nowe kolory.</p><?php endif; ?>
  <?php if (isset($_GET['reset'])): ?><p class="alert alert-info">Przywrócono domyślne kolory.</p><?php endif; ?>

  <form method="post" class="card mt-6">
    <?= csrf_field() ?>
    <input type="hidden" name="_action" value="save">
    <div class="grid grid-2">
      <?php foreach ($fields as $f): ?>
        <div class="field">
          <label for="<?= e($f['key']) ?>_hex"><?= e($f['label']) ?></label>
          <div class="flex items-center gap-2">
            <input
              id="<?= e($f['key']) ?>_swatch"
              type="color"
              value="<?= e($theme[$f['key']]) ?>"
              style="height:44px; width:44px; padding:2px; flex-shrink:0;"
              oninput="document.getElementById('<?= e($f['key']) ?>_hex').value=this.value"
            >
            <input
              id="<?= e($f['key']) ?>_hex"
              name="<?= e($f['key']) ?>"
              type="text"
              value="<?= e($theme[$f['key']]) ?>"
              placeholder="#rrggbb"
              pattern="#[0-9a-fA-F]{6}"
              maxlength="7"
              style="font-family:monospace;"
              oninput="if(/^#[0-9a-fA-F]{6}$/.test(this.value)){document.getElementById('<?= e($f['key']) ?>_swatch').value=this.value}"
            >
          </div>
          <p class="field-hint"><?= e($f['hint']) ?> Możesz wkleić kod HEX (np. #4d6b3f) bezpośrednio w pole tekstowe.</p>
        </div>
      <?php endforeach; ?>
    </div>
    <button type="submit" class="btn btn-primary mt-4">Zapisz kolory</button>
  </form>

  <form method="post" class="mt-4">
    <?= csrf_field() ?>
    <input type="hidden" name="_action" value="reset">
    <button type="submit" class="btn btn-outline" onclick="return confirm('Przywrócić domyślną kolorystykę?')">Przywróć domyślne kolory</button>
  </form>
</div>
<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
