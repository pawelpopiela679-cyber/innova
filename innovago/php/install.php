<?php
/**
 * Instalator uruchamiany raz w przeglądarce — tworzy tabele i ładuje dane
 * startowe (plany subskrypcji, konto super-admina, demo-organizację).
 * Bezpieczne do wielokrotnego uruchomienia.
 *
 * Po zakończeniu wdrożenia USUŃ TEN PLIK z serwera — patrz README_PHP.md.
 */

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/schema.php';
require_once __DIR__ . '/includes/seed.php';

$log = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    try {
        ensure_schema();
        $log = run_seed();
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$dbOk = null;
$dbError = null;
try {
    db();
    $dbOk = true;
} catch (Throwable $e) {
    $dbOk = false;
    $dbError = $e->getMessage();
}
?>
<!doctype html>
<html lang="pl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Instalacja — InnovaGo</title>
<style>
  body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;max-width:720px;margin:40px auto;padding:0 20px;line-height:1.6;color:#4a4326;background:#efe4cf}
  .card{background:#f8f3e6;border:1px solid #e2d3ac;border-radius:16px;padding:24px;margin-bottom:20px;box-shadow:0 4px 20px rgba(74,67,38,.08)}
  h1{margin-top:0}
  code{background:#f0ecdc;padding:2px 6px;border-radius:4px}
  pre{background:#4a4326;color:#f0ecdc;padding:16px;border-radius:10px;overflow-x:auto;white-space:pre-wrap}
  .ok{color:#1f9d55;font-weight:bold}
  .err{color:#d64545;font-weight:bold}
  button{background:#7d7a4a;color:#fff;border:none;border-radius:999px;padding:12px 24px;font-weight:bold;font-size:1rem;cursor:pointer}
  button:hover{opacity:.9}
  .warn{background:#fff4ec;border:1px solid #f3c89a;color:#92400e;border-radius:10px;padding:12px 16px}
</style>
</head>
<body>
<h1>Instalacja InnovaGo</h1>

<div class="card">
  <h2>1. Połączenie z bazą danych</h2>
  <?php if ($dbOk): ?>
    <p class="ok">✓ Połączenie z bazą danych działa (sterownik: <?= e(DB_DRIVER) ?>).</p>
  <?php else: ?>
    <p class="err">✗ Nie udało się połączyć z bazą danych.</p>
    <pre><?= e($dbError) ?></pre>
    <p>Sprawdź dane w <code>config.local.php</code> (DB_HOST, DB_NAME, DB_USER, DB_PASS).</p>
  <?php endif; ?>
</div>

<?php if ($dbOk): ?>
<div class="card">
  <h2>2. Utwórz tabele i wczytaj dane startowe</h2>
  <p>Można uruchomić wielokrotnie — nic się nie zduplikuje.</p>
  <form method="post">
    <?= csrf_field() ?>
    <button type="submit">Zainstaluj / zaktualizuj bazę danych</button>
  </form>
</div>
<?php endif; ?>

<?php if ($error): ?>
<div class="card">
  <p class="err">✗ Błąd podczas instalacji:</p>
  <pre><?= e($error) ?></pre>
</div>
<?php endif; ?>

<?php if ($log): ?>
<div class="card">
  <h2>Gotowe! ✓</h2>
  <pre><?= e(implode("\n", $log)) ?></pre>
  <p><strong>Zaloguj się kontem super-admina (Ty — właściciel platformy):</strong><br>
     e-mail: <code><?= e(SEED_SUPERADMIN_EMAIL) ?></code><br>
     hasło: <code><?= e(SEED_SUPERADMIN_PASSWORD) ?></code></p>
  <p><a href="<?= e(url('index.php')) ?>">→ Strona główna</a> ·
     <a href="<?= e(url('logowanie.php')) ?>">→ Logowanie</a></p>
  <div class="warn">
    <strong>Ważne — zrób to teraz:</strong>
    <ol>
      <li>Zaloguj się kontem super-admina i w „Mój profil” zmień hasło.</li>
      <li>Usuń plik <code>install.php</code> z serwera (każdy, kto go znajdzie, może
          nadpisać Twoje dane startowe).</li>
      <li>Usuń/zmień hasła kont demo (organizacja „demo-szkola”, rodzic demo)
          przed pokazaniem systemu prawdziwym klientom.</li>
    </ol>
  </div>
</div>
<?php endif; ?>

</body>
</html>
