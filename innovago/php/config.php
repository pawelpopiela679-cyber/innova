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
if (!defined('SMTP_FROM_NAME')) define('SMTP_FROM_NAME', 'InnovaGo');
if (!defined('SMS_API_TOKEN')) define('SMS_API_TOKEN', '');
if (!defined('SMS_SENDER_NAME')) define('SMS_SENDER_NAME', 'InnovaGo');
if (!defined('SEED_SUPERADMIN_EMAIL')) define('SEED_SUPERADMIN_EMAIL', 'super@innovago.pl');
if (!defined('SEED_SUPERADMIN_PASSWORD')) define('SEED_SUPERADMIN_PASSWORD', 'ZmienMnie123!');
if (!defined('SEED_DEMO_ORG_ADMIN_EMAIL')) define('SEED_DEMO_ORG_ADMIN_EMAIL', 'admin@demo-szkola.pl');
if (!defined('SEED_DEMO_ORG_ADMIN_PASSWORD')) define('SEED_DEMO_ORG_ADMIN_PASSWORD', 'Demo123!');
if (!defined('TPAY_CLIENT_ID')) define('TPAY_CLIENT_ID', '');
if (!defined('TPAY_CLIENT_SECRET')) define('TPAY_CLIENT_SECRET', '');
if (!defined('TPAY_MERCHANT_ID')) define('TPAY_MERCHANT_ID', '');
if (!defined('TPAY_ENV')) define('TPAY_ENV', 'sandbox');
if (!defined('TPAY_WEBHOOK_SECRET')) define('TPAY_WEBHOOK_SECRET', '');
if (!defined('TPAY_SIMULATE')) define('TPAY_SIMULATE', false); // domyślnie WYŁĄCZONE — trzeba świadomie włączyć w config.local.php

// KROK 2 (płatności cykliczne, cron-platnosci-cykliczne.php):
// - CRON_SECRET: jeśli cron wywoływany jest przez adres URL (np. wget/curl
//   z harmonogramu zadań home.pl) zamiast bezpośrednio z linii poleceń,
//   ten sekret w parametrze ?secret=... chroni skrypt przed uruchomieniem
//   przez kogokolwiek z internetu (skrypt pobiera prawdziwe pieniądze!).
//   Puste (domyślnie) = dostęp przez URL całkowicie zablokowany, działa
//   tylko uruchomienie z linii poleceń (php cron-platnosci-cykliczne.php).
// - TPAY_CRON_CHARGE_DAYS_AHEAD: ile dni przed terminem zajęć wolno
//   automatycznie obciążyć kartę (żeby nie pobierać pieniędzy tygodnie
//   wcześniej za zajęcia, które rodzic może jeszcze odwołać).
// - TPAY_CRON_MAX_ATTEMPTS: po ilu nieudanych próbach cron przestaje
//   próbować (i wyłącza automatyczne pobieranie tym tokenem), żeby nie
//   próbować bez końca kartą, która np. wygasła.
if (!defined('CRON_SECRET')) define('CRON_SECRET', '');
if (!defined('TPAY_CRON_CHARGE_DAYS_AHEAD')) define('TPAY_CRON_CHARGE_DAYS_AHEAD', 3);
if (!defined('TPAY_CRON_MAX_ATTEMPTS')) define('TPAY_CRON_MAX_ATTEMPTS', 3);

// Płatności online są aktywne tylko, gdy mamy klucze ALBO jesteśmy jawnie w
// trybie symulacji. Używane wszędzie tam, gdzie appka decyduje, czy pokazać
// przycisk "Zapłać online", czy tylko tradycyjny opis "przelew/gotówka".
define('TPAY_ENABLED', TPAY_SIMULATE || (TPAY_CLIENT_ID !== '' && TPAY_CLIENT_SECRET !== ''));

if (TPAY_ENV === 'production' && TPAY_SIMULATE) {
    // Zabezpieczenie przed najgorszym scenariuszem: ktoś wdraża appkę na
    // produkcję i zapomina wyłączyć tryb symulacji — bez tego "płatności"
    // wyglądałyby jak opłacone, a pieniądze nigdy by nie wpłynęły.
    error_log('[InnovaGo] KRYTYCZNE: TPAY_ENV=production razem z TPAY_SIMULATE=true w config.local.php! Płatności NIE są prawdziwe. Ustaw TPAY_SIMULATE na false.');
}

if (!defined('AUTH_SECRET') || AUTH_SECRET === 'ZMIEN-MNIE-NA-DLUGI-LOSOWY-CIAG-ZNAKOW-1234567890') {
    error_log('[InnovaGo] UWAGA: AUTH_SECRET nie został zmieniony na własną wartość w config.local.php!');
}

define('UPLOAD_MAX_BYTES', 3 * 1024 * 1024); // 3 MB
define('TRIAL_DAYS', 14); // ile dni darmowego okresu próbnego dostaje nowa organizacja
