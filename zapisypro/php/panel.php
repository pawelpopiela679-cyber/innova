<?php
/** Wygodny "rozdzielacz" — przekierowuje zalogowanego użytkownika do jego panelu. */
require_once __DIR__ . '/includes/bootstrap.php';

$user = require_login();
redirect(match ($user['role']) {
    'SUPER_ADMIN' => 'superadmin.php',
    'ORG_ADMIN', 'INSTRUCTOR' => 'admin.php',
    default => 'panel-rodzic.php',
});
