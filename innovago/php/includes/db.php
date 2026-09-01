<?php
/**
 * Jedno połączenie PDO na cały request, wspiera MySQL (produkcja / home.pl)
 * i SQLite (szybkie testy lokalne bez zakładania bazy MySQL).
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

function db_is_mysql(): bool
{
    return DB_DRIVER !== 'sqlite';
}

function db_pk(): string
{
    return db_is_mysql()
        ? 'INT AUTO_INCREMENT PRIMARY KEY'
        : 'INTEGER PRIMARY KEY AUTOINCREMENT';
}

function db_now_default(): string
{
    return 'DEFAULT CURRENT_TIMESTAMP';
}

function db_last_id(PDO $pdo): int
{
    return (int) $pdo->lastInsertId();
}
