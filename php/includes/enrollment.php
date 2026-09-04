<?php
/** Logika zgłoszeń — odpowiednik lib/actions/enrollment-actions.ts + lib/enrollment-helpers.ts. */

/**
 * Gdy zwalnia się potwierdzone miejsce w GRUPIE (anulowanie / przeniesienie
 * kogoś do innej grupy), awansuje najwcześniejsze zgłoszenie z listy
 * rezerwowej tej grupy na potwierdzone i wysyła e-mail rodzicowi.
 * Współdzielone przez anulowanie przez rodzica i akcje pracowni (żeby
 * zachowanie było spójne wszędzie).
 */
function promote_next_waitlisted(int $groupId): void
{
    if (!$groupId) {
        return;
    }
    $stmt = db()->prepare("SELECT e.*, c.first_name, c.last_name, u.name AS parent_name, u.email AS parent_email
        FROM enrollments e
        JOIN children c ON c.id = e.child_id
        JOIN users u ON u.id = e.parent_id
        WHERE e.group_id = ? AND e.status = 'WAITLIST'
        ORDER BY e.created_at ASC LIMIT 1");
    $stmt->execute([$groupId]);
    $next = $stmt->fetch();
    if (!$next) {
        return;
    }

    db()->prepare("UPDATE enrollments SET status = 'CONFIRMED', confirmed_at = CURRENT_TIMESTAMP WHERE id = ?")
        ->execute([$next['id']]);

    $group = db()->prepare('SELECT g.*, ct.name AS ct_name FROM class_groups g JOIN class_types ct ON ct.id = g.class_type_id WHERE g.id = ?');
    $group->execute([$groupId]);
    $g = $group->fetch();

    send_enrollment_confirmation_email([
        'parentEmail' => $next['parent_email'],
        'parentName' => $next['parent_name'],
        'childName' => $next['first_name'] . ' ' . $next['last_name'],
        'classTypeName' => $g['ct_name'],
        'sessionTitle' => $g['name'],
        'when' => format_group_schedule((int) $g['day_of_week'], $g['start_time'], $g['end_time']),
        'instructorName' => $g['instructor_name'],
        'meetingUrl' => $g['meeting_url'],
        'waitlisted' => false,
    ]);
}
