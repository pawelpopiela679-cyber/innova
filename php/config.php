<?php
/**
 * Wczytuje konfigurację z config.local.php (Twoje prawdziwe dane, nigdy nie
 * commitowane do gita). Jeśli ten plik jeszcze nie istnieje, pokazuje czytelną
 * instrukcję zamiast niezrozumiałego błędu PHP.
 */

$localConfig = __DIR__ . '/config.local.php';

if (!file_exists($localConfig)) {
    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="pl"><head><meta charset="utf-8">'
        . '<title>Brak konfiguracji</title>'
        . '<style>body{font-family:sans-serif;max-width:640px;margin:60px auto;line-height:1.6;color:#333}'
        . 'code{background:#f0f0f0;padding:2px 6px;border-radius:4px}</style></head><body>'
        . '<h1>Brak pliku konfiguracyjnego</h1>'
        . '<p>Skopiuj plik <code>config.local.php.example</code> jako '
        . '<code>config.local.php</code> (w tym samym folderze) i uzupełnij go '
        . 'prawdziwymi danymi do bazy danych — instrukcja krok po kroku jest w '
        . '<code>README_PHP.md</code>.</p>'
        . '</body></html>';
    exit;
}

require_once $localConfig;

// Bezpieczne wartości domyślne, gdyby czegoś zabrakło w config.local.php.
if (!defined('DB_DRIVER')) define('DB_DRIVER', 'mysql');
if (!defined('DB_PORT')) define('DB_PORT', 3306);
if (!defined('APP_URL')) define('APP_URL', '');
if (!defined('SMTP_PORT')) define('SMTP_PORT', 587);
if (!defined('SMTP_FROM_NAME')) define('SMTP_FROM_NAME', 'INNOVA');
if (!defined('SEED_ADMIN_EMAIL')) define('SEED_ADMIN_EMAIL', 'admin@innova-pracownia.pl');
if (!defined('SEED_ADMIN_PASSWORD')) define('SEED_ADMIN_PASSWORD', 'ZmienMnie123!');

if (!defined('AUTH_SECRET') || AUTH_SECRET === 'ZMIEN-MNIE-NA-DLUGI-LOSOWY-CIAG-ZNAKOW-1234567890') {
    // Nie blokujemy działania (żeby install.php mógł jeszcze zadziałać), ale
    // ostrzegamy głośno w logu serwera — to realne zagrożenie bezpieczeństwa.
    error_log('[INNOVA] UWAGA: AUTH_SECRET nie został zmieniony na własną wartość w config.local.php!');
}

define('UPLOAD_MAX_BYTES', 3 * 1024 * 1024); // 3 MB, jak w wersji Next.js
define('MAX_GROUP_SIZE', 10); // twarda zasada pracowni: max 10 dzieci na grupę
define('SEMESTER_START', '2026-09-14'); // poniedziałek — start realnego semestru
define('OPEN_DAY_DATE', '2026-09-12');
