<?php
/** Panel zgłoszeń dla organizacji: potwierdzanie, lista rezerwowa, odrzucanie, status płatności. */
require_once __DIR__ . '/includes/bootstrap.php';

$user = require_staff();
$org = require_org();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['_action'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);

    $stmt = db()->prepare('SELECT e.*, cs.title, cs.starts_at, cs.ends_at, cs.meeting_url, cs.instructor_name, cs.capacity, ct.name AS ct_name, c.first_name, c.last_name, u.name AS parent_name, u.email AS parent_email
        FROM enrollments e
        JOIN class_sessions cs ON cs.id = e.session_id
        JOIN class_types ct ON ct.id = cs.class_type_id
        JOIN children c ON c.id = e.child_id
        JOIN users u ON u.id = e.parent_id
        WHERE e.id = ? AND e.org_id = ?');
    $stmt->execute([$id, $org['id']]);
    $enrollment = $stmt->fetch();

    if ($enrollment) {
        if ($action === 'confirm') {
            $confirmedStmt = db()->prepare("SELECT COUNT(*) c FROM enrollments WHERE session_id = ? AND status = 'CONFIRMED'");
            $confirmedStmt->execute([$enrollment['session_id']]);
            $confirmedCount = (int) $confirmedStmt->fetch()['c'];
            $waitlisted = $confirmedCount >= (int) $enrollment['capacity'];
            $newStatus = $waitlisted ? 'WAITLIST' : 'CONFIRMED';
            db()->prepare("UPDATE enrollments SET status = ?, confirmed_at = CASE WHEN ? = 'CONFIRMED' THEN CURRENT_TIMESTAMP ELSE confirmed_at END WHERE id = ?")
                ->execute([$newStatus, $newStatus, $id]);
            send_enrollment_confirmation_email([
                'parentEmail' => $enrollment['parent_email'], 'parentName' => $enrollment['parent_name'], 'orgName' => $org['name'],
                'childName' => $enrollment['first_name'] . ' ' . $enrollment['last_name'],
                'classTypeName' => $enrollment['ct_name'], 'sessionTitle' => $enrollment['title'],
                'startsAt' => $enrollment['starts_at'], 'endsAt' => $enrollment['ends_at'],
                'instructorName' => $enrollment['instructor_name'], 'meetingUrl' => $enrollment['meeting_url'],
                'waitlisted' => $waitlisted,
            ]);
        } elseif ($action === 'reject') {
            db()->prepare("UPDATE enrollments SET status = 'REJECTED', canceled_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$id]);
            send_enrollment_declined_email([
                'parentEmail' => $enrollment['parent_email'], 'parentName' => $enrollment['parent_name'], 'orgName' => $org['name'],
                'childName' => $enrollment['first_name'] . ' ' . $enrollment['last_name'],
                'classTypeName' => $enrollment['ct_name'], 'sessionTitle' => $enrollment['title'],
            ]);
        } elseif ($action === 'cancel') {
            db()->prepare("UPDATE enrollments SET status = 'CANCELED', canceled_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$id]);
            if ($enrollment['status'] === 'CONFIRMED') {
                promote_next_waitlisted((int) $enrollment['session_id']);
            }
        } elseif ($action === 'mark_paid') {
            set_payment_status($id, (int) $org['id'], 'PAID');
        } elseif ($action === 'mark_unpaid') {
            set_payment_status($id, (int) $org['id'], 'UNPAID');
        }
    }
    redirect('zapisy.php' . (!empty($_GET['filter']) ? '?filter=' . urlencode($_GET['filter']) : ''));
}

$filter = $_GET['filter'] ?? 'pending';
$where = match ($filter) {
    'confirmed' => "e.status = 'CONFIRMED'",
    'waitlist' => "e.status = 'WAITLIST'",
    'unpaid' => "e.status = 'CONFIRMED' AND e.payment_status = 'UNPAID'",
    'all' => '1=1',
    default => "e.status = 'PENDING'",
};

$stmt = db()->prepare("SELECT e.*, cs.title, cs.starts_at, ct.name AS ct_name, c.first_name, c.last_name, c.birth_date, u.name AS parent_name, u.email AS parent_email, u.phone AS parent_phone
    FROM enrollments e
    JOIN class_sessions cs ON cs.id = e.session_id
    JOIN class_types ct ON ct.id = cs.class_type_id
    JOIN children c ON c.id = e.child_id
    JOIN users u ON u.id = e.parent_id
    WHERE e.org_id = ? AND $where
    ORDER BY e.created_at DESC");
$stmt->execute([$org['id']]);
$rows = $stmt->fetchAll();

$pageTitle = 'Zgłoszenia — ' . $org['name'];
require __DIR__ . '/includes/layout_top.php';
?>
<div class="container section">
  <h1 class="section-title">Zgłoszenia</h1>

  <div class="tabs mt-4">
    <?php foreach (['pending' => 'Oczekujące', 'confirmed' => 'Potwierdzone', 'waitlist' => 'Lista rezerwowa', 'unpaid' => 'Nieopłacone', 'all' => 'Wszystkie'] as $key => $label): ?>
      <a href="<?= e(url('zapisy.php?filter=' . $key)) ?>" class="tab <?= $filter === $key ? 'active' : '' ?>"><?= $label ?></a>
    <?php endforeach; ?>
  </div>

  <?php if (!$rows): ?>
    <p class="text-muted mt-6">Brak zgłoszeń w tej kategorii.</p>
  <?php endif; ?>

  <div class="enroll-list mt-6">
    <?php foreach ($rows as $r): $age = calculate_age($r['birth_date']); ?>
      <div class="enroll-card reveal">
        <div class="enroll-main">
          <div class="enroll-title"><?= e($r['first_name'] . ' ' . $r['last_name']) ?> <span class="text-muted">(<?= $age ?> lat)</span></div>
          <div class="text-muted"><?= e($r['ct_name']) ?> — <?= e($r['title']) ?> · <?= e(format_pl_date($r['starts_at'], false, true)) ?></div>
          <div class="text-muted" style="font-size:.8rem;">Rodzic: <?= e($r['parent_name']) ?> · <?= e($r['parent_email']) ?><?= $r['parent_phone'] ? ' · ' . e($r['parent_phone']) : '' ?></div>
        </div>
        <div class="enroll-badges">
          <span class="badge badge-<?= strtolower($r['status']) ?>"><?= e($r['status']) ?></span>
          <?php if ($r['status'] === 'CONFIRMED'): ?>
            <span class="badge badge-<?= $r['payment_status'] === 'PAID' ? 'confirmed' : 'pending' ?>"><?= $r['payment_status'] === 'PAID' ? 'Opłacone' : 'Nieopłacone' ?></span>
          <?php endif; ?>
        </div>
        <div class="enroll-actions">
          <?php if ($r['status'] === 'PENDING'): ?>
            <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="_action" value="confirm"><input type="hidden" name="id" value="<?= $r['id'] ?>"><button class="btn btn-primary btn-sm">Potwierdź</button></form>
            <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="_action" value="reject"><input type="hidden" name="id" value="<?= $r['id'] ?>"><button class="btn btn-outline btn-sm">Odrzuć</button></form>
          <?php elseif (in_array($r['status'], ['CONFIRMED', 'WAITLIST'], true)): ?>
            <?php if ($r['status'] === 'CONFIRMED'): ?>
              <?php if ($r['payment_status'] === 'UNPAID'): ?>
                <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="_action" value="mark_paid"><input type="hidden" name="id" value="<?= $r['id'] ?>"><button class="btn btn-primary btn-sm">Oznacz jako opłacone</button></form>
              <?php else: ?>
                <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="_action" value="mark_unpaid"><input type="hidden" name="id" value="<?= $r['id'] ?>"><button class="btn btn-outline btn-sm">Cofnij opłacenie</button></form>
              <?php endif; ?>
            <?php endif; ?>
            <form method="post" class="inline" onsubmit="return confirm('Na pewno anulować ten zapis?');"><?= csrf_field() ?><input type="hidden" name="_action" value="cancel"><input type="hidden" name="id" value="<?= $r['id'] ?>"><button class="btn btn-outline btn-sm">Anuluj</button></form>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
