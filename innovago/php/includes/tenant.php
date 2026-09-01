<?php
/**
 * Wielo-najemczość (multi-tenant): jedna instalacja InnovaGo obsługuje wiele
 * organizacji (szkółek/klubów), każda widzi tylko swoje dane.
 *
 * Który "org_id" jest aktywny dla bieżącego żądania:
 *  - użytkownik zalogowany (ORG_ADMIN / INSTRUCTOR / PARENT) -> zawsze jego
 *    własne $_SESSION['user']['org_id'] (nie da się "przełączyć" na cudzą
 *    organizację przez URL — to byłaby dziura bezpieczeństwa),
 *  - niezalogowany -> tylko tam, gdzie to jawnie potrzebne (np.
 *    rejestracja.php?org=slug), przez current_org_by_slug().
 *  - SUPER_ADMIN nie ma własnej organizacji — zarządza wszystkimi z poziomu
 *    superadmin*.php i nie korzysta z current_org().
 */

/** Organizacja zalogowanego użytkownika (ORG_ADMIN/INSTRUCTOR/PARENT), albo null. */
function current_org(): ?array
{
    $user = current_user();
    if (!$user || empty($user['org_id'])) {
        return null;
    }
    static $cache = null;
    if ($cache !== null && $cache['id'] === (int) $user['org_id']) {
        return $cache;
    }
    $stmt = db()->prepare('SELECT * FROM organizations WHERE id = ?');
    $stmt->execute([$user['org_id']]);
    $org = $stmt->fetch() ?: null;
    if ($org) {
        $cache = $org;
    }
    return $org;
}

/** Wymaga zalogowania w kontekście organizacji (nie super-admina) — zwraca organizację. */
function require_org(): array
{
    require_login();
    $org = current_org();
    if (!$org) {
        // Konto bez organizacji (np. super-admin zalogowany na złej stronie).
        redirect('logowanie.php');
    }
    return $org;
}

/** Znajduje aktywną organizację po jej publicznym slugu (do rejestracji nowych rodziców). */
function find_org_by_slug(string $slug): ?array
{
    $stmt = db()->prepare('SELECT * FROM organizations WHERE slug = ?');
    $stmt->execute([$slug]);
    return $stmt->fetch() ?: null;
}

/** Plan subskrypcji danej organizacji. */
function org_plan(array $org): ?array
{
    if (empty($org['plan_id'])) {
        return null;
    }
    $stmt = db()->prepare('SELECT * FROM subscription_plans WHERE id = ?');
    $stmt->execute([$org['plan_id']]);
    return $stmt->fetch() ?: null;
}

/** Czy organizacja może w tej chwili korzystać z systemu (trial trwa albo płaci). */
function org_is_active(array $org): bool
{
    if ($org['status'] === 'ACTIVE') {
        return true;
    }
    if ($org['status'] === 'TRIAL') {
        return $org['trial_ends_at'] === null || $org['trial_ends_at'] >= date('Y-m-d');
    }
    return false; // SUSPENDED, CANCELED
}

/** Liczba aktywnych prowadzących/instruktorów w organizacji (do limitu planu). */
function org_instructor_count(int $orgId): int
{
    $stmt = db()->prepare("SELECT COUNT(*) c FROM users WHERE org_id = ? AND role IN ('ORG_ADMIN','INSTRUCTOR')");
    $stmt->execute([$orgId]);
    return (int) $stmt->fetch()['c'];
}

/** Liczba dzieci zapisanych choć raz w organizacji (do limitu planu). */
function org_student_count(int $orgId): int
{
    $stmt = db()->prepare(
        "SELECT COUNT(DISTINCT child_id) c FROM enrollments WHERE org_id = ?"
    );
    $stmt->execute([$orgId]);
    return (int) $stmt->fetch()['c'];
}
