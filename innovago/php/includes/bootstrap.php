<?php
/** Ładowany jako pierwsza linijka na każdej stronie: sesja, config, DB, helpery. */

declare(strict_types=1);

require_once __DIR__ . '/../config.php';

// Sesja logowania — własna nazwa ciasteczka (żeby nie kolidowało z INNOVA
// albo inną aplikacją PHP w tym samym katalogu na hostingu).
session_name('innovago_session');
session_set_cookie_params([
    'lifetime' => 60 * 60 * 24 * 30, // 30 dni
    'path' => parse_base_path_early() === '/' ? '/' : parse_base_path_early() . '/',
    'httponly' => true,
    'samesite' => 'Lax',
    'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
]);
session_start();

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/rate_limit.php';
require_once __DIR__ . '/rodo.php';
require_once __DIR__ . '/tenant.php';
require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/sms.php';
require_once __DIR__ . '/theme.php';
require_once __DIR__ . '/upload.php';
require_once __DIR__ . '/availability.php';
require_once __DIR__ . '/enrollment.php';

/** Wersja parse_base_path() bezpieczna do wywołania przed załadowaniem helpers.php. */
function parse_base_path_early(): string
{
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    $base = rtrim(str_replace('\\', '/', dirname($script)), '/');
    return $base === '' ? '/' : $base;
}
