<?php
/**
 * Instalator uruchamiany raz w przeglądarce, zamiast ręcznego importowania
 * SQL przez phpMyAdmin. Tworzy brakujące tabele i (bezpiecznie, można
 * uruchomić ponownie) ładuje/odświeża dane startowe: konto właściciela
 * pracowni, prowadzących, rodzaje zajęć, cennik i terminy na semestr.
 *
 * Po zakończeniu wdrożenia USUŃ TEN PLIK z serwera (albo dopisz mu hasło
 * poniżej) — patrz README_PHP.md.
 */

// Wymuszamy czyszczenie DWÓCH oddzielnych, niezależnych cache'y PHP, ZANIM
// cokolwiek wczyta config.local.php — samo czyszczenie opcache (skompilowany
// kod) okazało się niewystarczające: PHP osobno cache'uje też "statystyki"
// pliku (data modyfikacji, rozmiar itp. — tzw. realpath/stat cache), żeby
// nie odpytywać dysku sieciowego przy każdym żądaniu. Jeśli ten drugi cache
// trzyma starą datę modyfikacji, opcache może uznać (błędnie), że plik się
// nie zmienił, i dalej serwować starą, skompilowaną wersję.
clearstatcache(true);
if (function_exists('opcache_reset')) {
    opcache_reset();
}

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

// Czyszczenie opcache samo w sobie nie pomogło (sprawdzone) — więc zamiast
// zgadywać dalej, pokazujemy TU wprost, jakie dane naprawdę wczytał PHP z
// config.local.php na serwerze (bez ujawniania samego hasła), żeby dało się
// od razu zobaczyć, czy to literówka w loginie/nazwie bazy, stary plik, czy
// coś innego.
$configLocalFile = __DIR__ . '/config.local.php';
$configLocalRealPath = realpath($configLocalFile) ?: $configLocalFile;
$installRealPath = realpath(__FILE__) ?: __FILE__;
$configLocalExists = file_exists($configLocalFile);
$configLocalMtime = $configLocalExists ? filemtime($configLocalFile) : null;
$configLocalCachedMtime = null;
if (function_exists('opcache_get_status')) {
    $ocStatus = @opcache_get_status(true);
    if (is_array($ocStatus) && !empty($ocStatus['scripts'])) {
        foreach ($ocStatus['scripts'] as $scriptPath => $info) {
            if (realpath($scriptPath) === realpath($configLocalFile)) {
                $configLocalCachedMtime = $info['timestamp'] ?? null;
                break;
            }
        }
    }
}
$dbPassRaw = defined('DB_PASS') ? DB_PASS : '';
$dbPassLen = strlen($dbPassRaw);
$dbPassHasWhitespace = $dbPassRaw !== '' && trim($dbPassRaw) !== $dbPassRaw;
?>
<!doctype html>
<html lang="pl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Instalacja — INNOVA</title>
<style>
  body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;max-width:720px;margin:40px auto;padding:0 20px;line-height:1.6;color:#4a4326;background:#f6efdf}
  .card{background:#fdfaf2;border:1px solid #e9dcb8;border-radius:16px;padding:24px;margin-bottom:20px}
  h1{margin-top:0}
  code{background:#e9dcb8;padding:2px 6px;border-radius:4px}
  pre{background:#33301f;color:#f1ead9;padding:16px;border-radius:10px;overflow-x:auto;white-space:pre-wrap}
  .ok{color:#2f7a4f;font-weight:bold}
  .err{color:#b0413e;font-weight:bold}
  button{background:#7d7a4a;color:#fff;border:none;border-radius:999px;padding:12px 24px;font-weight:bold;font-size:1rem;cursor:pointer}
  button:hover{opacity:.9}
  .warn{background:#fbecec;border:1px solid #e6b8b8;color:#8a2f2f;border-radius:10px;padding:12px 16px}
</style>
</head>
<body>
<h1>Instalacja INNOVA</h1>

<div class="card">
  <h2>1. Połączenie z bazą danych</h2>
  <?php if ($dbOk): ?>
    <p class="ok">✓ Połączenie z bazą danych działa (sterownik: <?= e(DB_DRIVER) ?>).</p>
  <?php else: ?>
    <p class="err">✗ Nie udało się połączyć z bazą danych.</p>
    <pre><?= e($dbError) ?></pre>
    <p>Sprawdź dane w <code>config.local.php</code> (DB_HOST, DB_NAME, DB_USER, DB_PASS) — znajdziesz je
       w Panelu klienta home.pl → Bazy danych.</p>

    <div class="warn">
      <strong>Diagnostyka — co PHP naprawdę wczytał z config.local.php na serwerze</strong>
      <table style="margin-top:10px; font-size:0.85rem; border-collapse:collapse; width:100%;">
        <tr><td style="padding:3px 12px 3px 0;">Ten plik (install.php) leży w</td><td><code style="word-break:break-all;"><?= e($installRealPath) ?></code></td></tr>
        <tr><td style="padding:3px 12px 3px 0;">Szukany config.local.php to</td><td><code style="word-break:break-all;"><?= e($configLocalRealPath) ?></code></td></tr>
        <tr><td style="padding:3px 12px 3px 0;">Plik config.local.php istnieje</td><td><code><?= $configLocalExists ? 'TAK' : 'NIE — to jest problem!' ?></code></td></tr>
        <?php if ($configLocalExists): ?>
        <tr><td style="padding:3px 12px 3px 0;">Ostatnio zmodyfikowany</td><td><code><?= e(date('Y-m-d H:i:s', $configLocalMtime)) ?></code></td></tr>
        <?php endif; ?>
        <tr><td style="padding:3px 12px 3px 0;">Aktualny czas serwera</td><td><code><?= e(date('Y-m-d H:i:s')) ?></code></td></tr>
        <?php if ($configLocalCachedMtime !== null): ?>
        <tr><td style="padding:3px 12px 3px 0;">Wersja w opcache (cache PHP)</td><td><code><?= e(date('Y-m-d H:i:s', $configLocalCachedMtime)) ?><?= $configLocalCachedMtime < $configLocalMtime ? ' ⚠️ STARSZA niż plik na dysku!' : ' (zgodna z plikiem)' ?></code></td></tr>
        <?php endif; ?>
        <tr><td style="padding:3px 12px 3px 0;">DB_HOST</td><td><code><?= e(defined('DB_HOST') ? DB_HOST : '(brak)') ?></code></td></tr>
        <tr><td style="padding:3px 12px 3px 0;">DB_PORT</td><td><code><?= e(defined('DB_PORT') ? (string) DB_PORT : '(brak)') ?></code></td></tr>
        <tr><td style="padding:3px 12px 3px 0;">DB_NAME</td><td><code><?= e(defined('DB_NAME') ? DB_NAME : '(brak)') ?></code></td></tr>
        <tr><td style="padding:3px 12px 3px 0;">DB_USER</td><td><code><?= e(defined('DB_USER') ? DB_USER : '(brak)') ?></code></td></tr>
        <tr><td style="padding:3px 12px 3px 0;">DB_PASS — długość</td><td><code><?= $dbPassLen ?> znaków</code><?= $dbPassLen === 0 ? ' ⚠️ PUSTE!' : '' ?></td></tr>
        <tr><td style="padding:3px 12px 3px 0;">DB_PASS — spacje na początku/końcu</td><td><code><?= $dbPassHasWhitespace ? '⚠️ TAK — to prawdopodobna przyczyna!' : 'nie' ?></code></td></tr>
      </table>
      <p style="margin-top:10px; margin-bottom:0;">
        Porównaj <strong>DB_HOST</strong>, <strong>DB_NAME</strong> i <strong>DB_USER</strong> powyżej z tym, co
        widzisz w Panelu klienta home.pl → Bazy danych (i z loginem, którym logujesz się do phpMyAdmin) —
        muszą być identyczne co do litery. Jeśli któraś z tych trzech wartości się nie zgadza, hasło nie ma
        tu żadnego znaczenia — MySQL i tak odrzuci połączenie. Jeśli wszystko się zgadza, a mimo to błąd
        „Access denied" nie znika, to znaczy że <code>config.local.php</code> na serwerze wciąż zawiera
        <em>stare</em> hasło (plik nie został faktycznie nadpisany przy wgrywaniu przez FTP) — sprawdź datę
        modyfikacji powyżej: jeśli jest sprzed Twojej ostatniej zmiany hasła, to właśnie to jest przyczyną.
      </p>
    </div>
  <?php endif; ?>
</div>

<?php if ($dbOk): ?>
<div class="card">
  <h2>2. Utwórz tabele i wczytaj dane startowe</h2>
  <p>Możesz to uruchomić wielokrotnie — nic się nie zduplikuje (dane startowe
     są nadpisywane, a nie dodawane ponownie).</p>
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
  <p><strong>Zaloguj się kontem właściciela pracowni:</strong><br>
     e-mail: <code><?= e(SEED_ADMIN_EMAIL) ?></code><br>
     hasło: <code><?= e(SEED_ADMIN_PASSWORD) ?></code></p>
  <p><a href="<?= e(url('index.php')) ?>">→ Przejdź do strony głównej</a> ·
     <a href="<?= e(url('logowanie.php')) ?>">→ Przejdź do logowania</a></p>
  <div class="warn">
    <strong>Ważne — zrób to teraz:</strong>
    <ol>
      <li>Zaloguj się i w „Mój profil” zmień hasło admina na własne.</li>
      <li>Usuń plik <code>install.php</code> z serwera (albo przynajmniej
          zmień/usuń dane logowania w <code>config.local.php</code>, jeśli
          zostawiasz plik) — każdy, kto go znajdzie, może nadpisać Twoje dane
          startowe.</li>
      <li>Usuń konto demo (<code>rodzic@example.com</code>) albo zmień jego hasło.</li>
    </ol>
  </div>
</div>
<?php endif; ?>

</body>
</html>
