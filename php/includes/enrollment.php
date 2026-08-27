<?php
/** Logika zgłoszeń — odpowiednik lib/actions/enrollment-actions.ts + lib/enrollment-helpers.ts. */

/**
 * Gdy zwalnia się potwierdzone miejsce (anulowanie / przeniesienie kogoś do
 * innej grupy), awansuje najwcześniejsze zgłoszenie z listy rezerwowej na
 * potwierdzone i wysyła e-mail rodzicowi. Współdzielone przez anulowanie
 * przez rodzica i akcje pracowni (żeby zachowanie było spójne wszędzie).
 */
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
