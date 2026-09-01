<?php
/**
 * Logika zgłoszeń: potwierdzanie, lista rezerwowa, PLUS dwie funkcje, których
 * nie ma wersja INNOVA — nieobecność z odrobieniem zajęć i status płatności.
 */

/** Gdy zwalnia się potwierdzone miejsce, awansuje najstarsze zgłoszenie z listy rezerwowej. */
function promote_next_waitlisted(int $sessionId): void
{
    $stmt = db()->prepare("SELECT e.*, c.first_name, c.last_name, u.name AS parent_name, u.email AS parent_email
        FROM enrollments e
        JOIN children c ON c.id = e.child_id
        JOIN users u ON u.id = e.parent_id
        WHERE e.session_id = ? AND e.status = 'WAITLIST'
        ORDER BY e.created_at ASC LIMIT 1");
    $stmt->execute([$sessionId]);
    $next = $stmt->fetch();
    if (!$next) {
        return;
    }

    db()->prepare("UPDATE enrollments SET status = 'CONFIRMED', confirmed_at = CURRENT_TIMESTAMP WHERE id = ?")
        ->execute([$next['id']]);

    $session = db()->prepare('SELECT cs.*, ct.name AS ct_name FROM class_sessions cs JOIN class_types ct ON ct.id = cs.class_type_id WHERE cs.id = ?');
    $session->execute([$sessionId]);
    $s = $session->fetch();

    send_enrollment_confirmation_email([
        'parentEmail' => $next['parent_email'],
        'parentName' => $next['parent_name'],
        'childName' => $next['first_name'] . ' ' . $next['last_name'],
        'classTypeName' => $s['ct_name'],
        'sessionTitle' => $s['title'],
        'startsAt' => $s['starts_at'],
        'endsAt' => $s['ends_at'],
        'instructorName' => $s['instructor_name'],
        'meetingUrl' => $s['meeting_url'],
        'waitlisted' => false,
    ]);
}

/**
 * Rodzic zgłasza nieobecność dziecka na potwierdzonych zajęciach — funkcja,
 * której nie ma INNOVA (tam jest tylko lista rezerwowa). Zwalnia miejsce
 * (awansując kogoś z listy rezerwowej) i oznacza zgłoszenie jako ABSENT,
 * zachowując je w historii, żeby rodzic mógł je "odrobić" (patrz niżej).
 */
function report_absence(int $enrollmentId, int $orgId): void
{
    $stmt = db()->prepare("SELECT * FROM enrollments WHERE id = ? AND org_id = ? AND status = 'CONFIRMED'");
    $stmt->execute([$enrollmentId, $orgId]);
    $enrollment = $stmt->fetch();
    if (!$enrollment) {
        throw new RuntimeException('Nie znaleziono potwierdzonego zapisu do zgłoszenia nieobecności.');
    }

    db()->prepare("UPDATE enrollments SET attendance_status = 'ABSENT', absence_reported_at = CURRENT_TIMESTAMP WHERE id = ?")
        ->execute([$enrollmentId]);

    promote_next_waitlisted((int) $enrollment['session_id']);
}

/**
 * Rodzic "odrabia" wcześniej zgłoszoną nieobecność, zapisując dziecko na inny
 * termin tych samych zajęć (musi mieć wolne miejsce). Tworzy nowe zgłoszenie
 * powiązane z oryginalnym, od razu POTWIERDZONE (bez ponownej akceptacji
 * prowadzącego — odrobienie to nie nowy zapis, tylko przesunięcie terminu).
 */
function reclaim_absence(int $absentEnrollmentId, int $makeupSessionId, int $orgId): void
{
    $pdo = db();
    $stmt = $pdo->prepare("SELECT * FROM enrollments WHERE id = ? AND org_id = ? AND attendance_status = 'ABSENT' AND rescheduled_to_enrollment_id IS NULL");
    $stmt->execute([$absentEnrollmentId, $orgId]);
    $absence = $stmt->fetch();
    if (!$absence) {
        throw new RuntimeException('To zgłoszenie nie kwalifikuje się do odrobienia (albo zostało już odrobione).');
    }

    $sessStmt = $pdo->prepare('SELECT cs.*, (SELECT COUNT(*) FROM enrollments e WHERE e.session_id = cs.id AND e.status = \'CONFIRMED\') AS confirmed_count FROM class_sessions cs WHERE cs.id = ? AND cs.org_id = ?');
    $sessStmt->execute([$makeupSessionId, $orgId]);
    $session = $sessStmt->fetch();
    if (!$session) {
        throw new RuntimeException('Nie znaleziono wybranego terminu odrobienia.');
    }
    if ((int) $session['confirmed_count'] >= (int) $session['capacity']) {
        throw new RuntimeException('Wybrany termin jest już pełny — wybierz inny.');
    }

    $exists = $pdo->prepare('SELECT id FROM enrollments WHERE session_id = ? AND child_id = ?');
    $exists->execute([$makeupSessionId, $absence['child_id']]);
    if ($exists->fetch()) {
        throw new RuntimeException('Dziecko ma już zapis na wybrany termin.');
    }

    $pdo->beginTransaction();
    try {
        $insert = $pdo->prepare("INSERT INTO enrollments (org_id, session_id, child_id, parent_id, status, payment_status, amount_due_cents, created_at, confirmed_at, paid_at)
            VALUES (?, ?, ?, ?, 'CONFIRMED', 'PAID', ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");
        // payment_status = PAID od razu (z tą samą kwotą co oryginał): rodzic już
        // zapłacił za odrabiane zajęcia w pierwotnym zapisie, to nie jest nowa opłata.
        $insert->execute([$orgId, $makeupSessionId, $absence['child_id'], $absence['parent_id'], $absence['amount_due_cents']]);
        $newId = db_last_id($pdo);

        $pdo->prepare('UPDATE enrollments SET rescheduled_to_enrollment_id = ? WHERE id = ?')
            ->execute([$newId, $absentEnrollmentId]);

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/** Oznacza zgłoszenie jako opłacone/nieopłacone — ręczne śledzenie płatności (przelew/gotówka). */
function set_payment_status(int $enrollmentId, int $orgId, string $status, ?int $amountDueCents = null): void
{
    if (!in_array($status, ['PAID', 'UNPAID'], true)) {
        throw new InvalidArgumentException('Nieprawidłowy status płatności.');
    }
    $paidAt = $status === 'PAID' ? date('Y-m-d H:i:s') : null;
    $stmt = db()->prepare('UPDATE enrollments SET payment_status = ?, amount_due_cents = COALESCE(?, amount_due_cents), paid_at = ? WHERE id = ? AND org_id = ?');
    $stmt->execute([$status, $amountDueCents, $paidAt, $enrollmentId, $orgId]);
}
