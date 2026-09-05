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

/**
 * Aktualny użytkownik — dociąga ŚWIEŻY wiersz z bazy (nie tylko to, co
 * zapisano w sesji przy logowaniu), bo sesja trzyma tylko id/name/email/role.
 * Bez tego zmiana uprawnień (can_manage_groups/can_manage_staff) albo
 * profilu przez właścicielkę nie było widać u już zalogowanego prowadzącego,
 * dopóki się nie wylogował i zalogował ponownie. Wynik cache'owany w
 * pamięci na czas jednego requestu (funkcja wywoływana wielokrotnie na
 * stronie — layout, admin-nav, sama strona).
 */
function current_user(): ?array
{
    static $cached = null;
    static $fetched = false;
    if ($fetched) {
        return $cached;
    }
    $fetched = true;

    $sessionUser = $_SESSION['user'] ?? null;
    if (!$sessionUser) {
        return null;
    }
    try {
        $stmt = db()->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$sessionUser['id']]);
        $fresh = $stmt->fetch();
        if ($fresh) {
            unset($fresh['password_hash']);
            $cached = $fresh;
            return $cached;
        }
    } catch (Throwable $e) {
        // Baza jeszcze niedostępna (np. przed install.php) — użyj danych z sesji.
    }
    $cached = $sessionUser;
    return $cached;
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

/** Właścicielka zawsze, prowadzący tylko z włączonym can_manage_groups (patrz admin-prowadzacy-edytuj.php). */
function user_can_manage_groups(?array $user): bool
{
    if (!$user) {
        return false;
    }
    return $user['role'] === 'ADMIN' || !empty($user['can_manage_groups']);
}

/** Jak require_staff(), ale tylko dla uprawnionych do panelu grup. */
function require_group_manager(): array
{
    $user = require_staff();
    if (!user_can_manage_groups($user)) {
        redirect('admin.php');
    }
    return $user;
}

/**
 * Właścicielka zawsze, plus dokładnie te konta prowadzących, którym włączono
 * can_manage_staff (patrz admin-prowadzacy-edytuj.php) — osobne uprawnienie
 * od can_manage_groups, bo dodawanie/usuwanie kont to coś innego niż
 * przydzielanie dzieci do grup.
 */
function user_can_manage_staff(?array $user): bool
{
    if (!$user) {
        return false;
    }
    return $user['role'] === 'ADMIN' || !empty($user['can_manage_staff']);
}

/** Jak require_staff(), ale tylko dla uprawnionych do zarządzania kontami prowadzących. */
function require_staff_manager(): array
{
    $user = require_staff();
    if (!user_can_manage_staff($user)) {
        redirect('admin.php');
    }
    return $user;
}
