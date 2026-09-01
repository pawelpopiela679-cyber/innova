<?php
/**
 * Realizacja praw RODO: dostęp do danych (eksport) i usunięcie danych.
 * Dotyczy kont rodziców — to oni (i ich dzieci) są głównym podmiotem
 * danych w tej appce. Konta ADMIN/INSTRUCTOR mają już swój przepływ
 * usuwania w admin-prowadzacy.php (i nie powinny móc same się skasować —
 * ryzyko zablokowania jedynego konta administratora).
 */

/** Pełny zrzut danych osobowych jednego rodzica — do pobrania jako JSON. */
function rodo_export_user_data(int $userId): array
{
    $pdo = db();

    $stmt = $pdo->prepare('SELECT id, email, name, phone, role, created_at FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    if (!$user) {
        throw new RuntimeException('Nie znaleziono konta.');
    }

    $stmt = $pdo->prepare('SELECT id, first_name, last_name, birth_date, notes, created_at FROM children WHERE parent_id = ? ORDER BY id');
    $stmt->execute([$userId]);
    $children = $stmt->fetchAll();

    $stmt = $pdo->prepare("SELECT e.id, e.status, e.created_at, e.confirmed_at, e.canceled_at,
            c.first_name AS dziecko_imie, c.last_name AS dziecko_nazwisko,
            ct.name AS rodzaj_zajec, cs.title AS termin_nazwa, cs.starts_at AS termin_data
        FROM enrollments e
        JOIN children c ON c.id = e.child_id
        JOIN class_sessions cs ON cs.id = e.session_id
        JOIN class_types ct ON ct.id = cs.class_type_id
        WHERE e.parent_id = ? ORDER BY e.id");
    $stmt->execute([$userId]);
    $enrollments = $stmt->fetchAll();

    return [
        'wygenerowano' => date('c'),
        'konto' => $user,
        'dzieci' => $children,
        'zgloszenia_na_zajecia' => $enrollments,
    ];
}

/**
 * Trwale usuwa konto rodzica wraz z danymi dzieci i historią zgłoszeń
 * (kaskadowo, przez ON DELETE CASCADE w bazie — patrz includes/schema.php).
 * Nieodwracalne — wywołujący MUSI wcześniej zaproponować eksport.
 */
function rodo_delete_user(int $userId): void
{
    $pdo = db();
    $stmt = $pdo->prepare('SELECT avatar_url FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    if ($user && !empty($user['avatar_url'])) {
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
