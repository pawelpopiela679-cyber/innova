<?php
/**
 * Realizacja praw RODO: dostęp do danych (eksport) i usunięcie danych.
 * Dotyczy kont rodziców w ramach JEDNEJ organizacji — wszystkie zapytania
 * są zawężone przez org_id, żeby właściciel jednej szkółki nie mógł
 * przypadkiem (ani celowo) dotknąć danych rodzica z innej organizacji.
 * Konta ORG_ADMIN/INSTRUCTOR mają swój przepływ usuwania w prowadzacy.php.
 */

/** Pełny zrzut danych osobowych jednego rodzica (w obrębie danej organizacji) — do pobrania jako JSON. */
function rodo_export_user_data(int $userId, int $orgId): array
{
    $pdo = db();

    $stmt = $pdo->prepare("SELECT id, email, name, phone, role, created_at FROM users WHERE id = ? AND org_id = ? AND role = 'PARENT'");
    $stmt->execute([$userId, $orgId]);
    $user = $stmt->fetch();
    if (!$user) {
        throw new RuntimeException('Nie znaleziono konta w tej organizacji.');
    }

    $stmt = $pdo->prepare('SELECT id, first_name, last_name, birth_date, notes, created_at FROM children WHERE parent_id = ? AND org_id = ? ORDER BY id');
    $stmt->execute([$userId, $orgId]);
    $children = $stmt->fetchAll();

    $stmt = $pdo->prepare("SELECT e.id, e.status, e.payment_status, e.amount_due_cents, e.attendance_status, e.created_at, e.confirmed_at, e.canceled_at, e.paid_at,
            c.first_name AS dziecko_imie, c.last_name AS dziecko_nazwisko,
            ct.name AS rodzaj_zajec, cs.title AS termin_nazwa, cs.starts_at AS termin_data
        FROM enrollments e
        JOIN children c ON c.id = e.child_id
        JOIN class_sessions cs ON cs.id = e.session_id
        JOIN class_types ct ON ct.id = cs.class_type_id
        WHERE e.parent_id = ? AND e.org_id = ? ORDER BY e.id");
    $stmt->execute([$userId, $orgId]);
    $enrollments = $stmt->fetchAll();

    $stmt = $pdo->prepare("SELECT c.title AS umowa, a.signer_name, a.accepted_at
        FROM contract_acceptances a JOIN contracts c ON c.id = a.contract_id
        WHERE a.parent_id = ? AND a.org_id = ? ORDER BY a.id");
    $stmt->execute([$userId, $orgId]);
    $contracts = $stmt->fetchAll();

    return [
        'wygenerowano' => date('c'),
        'konto' => $user,
        'dzieci' => $children,
        'zgloszenia_na_zajecia' => $enrollments,
        'zaakceptowane_umowy' => $contracts,
    ];
}

/**
 * Trwale usuwa konto rodzica (w obrębie danej organizacji) wraz z danymi
 * dzieci, historią zgłoszeń i akceptacji umów (kaskadowo, przez
 * ON DELETE CASCADE — patrz includes/schema.php). Nieodwracalne.
 * @throws RuntimeException gdy konto nie istnieje / nie należy do tej organizacji / nie jest rodzicem
 */
function rodo_delete_user(int $userId, int $orgId): void
{
    $pdo = db();
    $stmt = $pdo->prepare("SELECT avatar_url FROM users WHERE id = ? AND org_id = ? AND role = 'PARENT'");
    $stmt->execute([$userId, $orgId]);
    $user = $stmt->fetch();
    if (!$user) {
        throw new RuntimeException('Nie znaleziono konta w tej organizacji.');
    }
    if (!empty($user['avatar_url'])) {
        delete_uploaded_file($user['avatar_url']);
    }
    $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$userId]);
}

/** Wysyła $data jako plik JSON do pobrania i kończy request. */
function rodo_send_export_download(array $data, string $filenamePrefix): never
{
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filenamePrefix . '-' . date('Y-m-d') . '.json"');
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
