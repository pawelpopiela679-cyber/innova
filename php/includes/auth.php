<?php
/** Logowanie i sesje — PHP session natywna (bez JWT, niepotrzebne poza Node). */

function hash_password(string $password): string
{
    return password_hash($password, PASSWORD_DEFAULT);
}

function verify_password(string $password, string $hash): bool
{
    return password_verify($password, $hash);
}

/** Zapisuje zalogowanego użytkownika w sesji (odświeża ID sesji dla bezpieczeństwa). */
function login_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user'] = [
        'id' => (int) $user['id'],
        'name' => $user['name'],
        'email' => $user['email'],
        'role' => $user['role'],
    ];
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

/** Aktualny użytkownik z sesji, albo null. */
function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

/** Wymaga zalogowania — inaczej przekierowuje na logowanie z powrotem do $next. */
function require_login(?string $next = null): array
{
    $user = current_user();
    if (!$user) {
        redirect('logowanie.php' . ($next ? '?next=' . urlencode($next) : ''));
    }
    return $user;
}

/** Wymaga jednej z podanych ról — inaczej przekierowuje na logowanie. */
function require_role(array $roles, ?string $next = null): array
{
    $user = current_user();
    if (!$user || !in_array($user['role'], $roles, true)) {
        redirect('logowanie.php' . ($next ? '?next=' . urlencode($next) : ''));
    }
    return $user;
}

/** Prowadzący LUB master admin (panel /admin/*). */
function require_staff(): array
{
    return require_role(['ADMIN', 'INSTRUCTOR'], 'admin.php');
}

/** Tylko właściciel pracowni (master admin). */
function require_admin(): array
{
    return require_role(['ADMIN'], 'admin.php');
}
