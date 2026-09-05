<?php
/**
 * Jedno połączenie PDO na cały request, wspiera MySQL (produkcja / home.pl)
 * i SQLite (szybkie testy lokalne bez zakładania bazy MySQL). Kod aplikacji
 * pisze SQL w składni, która działa w obu — patrz helpery niżej dla różnic
 * (AUTO_INCREMENT, funkcje daty, itp.).
 */

function db(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    try {
        if (DB_DRIVER === 'sqlite') {
            $dsn = 'sqlite:' . SQLITE_PATH;
            $pdo = new PDO($dsn);
            $pdo->exec('PRAGMA foreign_keys = ON');
        } else {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                DB_HOST,
                DB_PORT,
                DB_NAME
            );
            $pdo = new PDO($dsn, DB_USER, DB_PASS);
        }
    } catch (Throwable $e) {
        // Bez tego złe dane w config.local.php (literówka w haśle, złej
        // nazwie bazy itp.) dawały pustą "białą" stronę 500 bez żadnej
        // wskazówki, co jest nie tak. Tu pokazujemy dokładny komunikat —
        // widoczny tylko dla Ciebie, nikt inny normalnie tej strony nie
        // odwiedza w takim stanie (baza w ogóle nie działa).
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><html lang="pl"><head><meta charset="utf-8">'
            . '<title>Błąd połączenia z bazą danych</title>'
            . '<style>body{font-family:sans-serif;max-width:640px;margin:60px auto;line-height:1.6;color:#333}'
            . 'pre{background:#fbecec;border:1px solid #e6b8b8;color:#8a2f2f;border-radius:8px;padding:12px 16px;white-space:pre-wrap;word-break:break-word}'
            . 'code{background:#f0f0f0;padding:2px 6px;border-radius:4px}</style></head><body>'
            . '<h1>Nie można połączyć się z bazą danych</h1>'
            . '<p>Sprawdź dane w <code>config.local.php</code> (DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS) — dokładny błąd poniżej:</p>'
            . '<pre>' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</pre>'
            . '</body></html>';
        exit;
    }

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    return $pdo;
}

/** True when running against MySQL (vs. the local SQLite testing mode). */
function db_is_mysql(): bool
{
    return DB_DRIVER !== 'sqlite';
}

/** Autoincrement primary-key column definition for the current driver. */
function db_pk(): string
{
    return db_is_mysql()
        ? 'INT AUTO_INCREMENT PRIMARY KEY'
        : 'INTEGER PRIMARY KEY AUTOINCREMENT';
}

/** Portable "current timestamp" SQL default for CREATE TABLE. */
function db_now_default(): string
{
    return db_is_mysql() ? 'DEFAULT CURRENT_TIMESTAMP' : "DEFAULT CURRENT_TIMESTAMP";
}

/** Last inserted row id (works the same for both drivers via PDO). */
function db_last_id(PDO $pdo): int
{
    return (int) $pdo->lastInsertId();
}
