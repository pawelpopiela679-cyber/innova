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
