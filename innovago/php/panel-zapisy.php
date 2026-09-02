<?php
/** Zapisy rodzica: lista, anulowanie, zgłoszenie nieobecności i odrobienie zajęć. */
require_once __DIR__ . '/includes/bootstrap.php';

$user = require_role(['PARENT'], 'panel-zapisy.php');
$org = require_org();
$error = null;
$info = $_GET['info'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $formAction = $_POST['_action'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);

    $own = db()->prepare('SELECT * FROM enrollments WHERE id = ? AND parent_id = ? AND org_id = ?');
    $own->execute([$id, $user['id'], $org['id']]);
    $enrollment = $own->fetch();

    if (!$enrollment) {
        redirect_with('panel-zapisy.php', ['error' => 'Nie znaleziono zapisu.']);
    }

    if ($formAction === 'cancel') {
        db()->prepare("UPDATE enrollments SET status = 'CANCELED', canceled_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$id]);
        if ($enrollment['status'] === 'CONFIRMED') {
            promote_next_waitlisted((int) $enrollment['session_id']);
        }
        redirect_with('panel-zapisy.php', ['info' => 'Zapis anulowany.']);
    } elseif ($formAction === 'report_absence') {
        try {
            report_absence($id, (int) $org['id']);
            redirect_with('panel-zapisy.php', ['info' => 'Zgłoszono nieobecność — możesz teraz wybrać termin odrobienia poniżej.']);
        } catch (RuntimeException $e) {
            redirect_with('panel-zapisy.php', ['error' => $e->getMessage()]);
        }
    } elseif ($formAction === 'reclaim') {
        $makeupSessionId = (int) ($_POST['makeupSessionId'] ?? 0);
        try {
            reclaim_absence($id, $makeupSessionId, (int) $org['id']);
            redirect_with('panel-zapisy.php', ['info' => 'Zajęcia odrobione — nowy termin jest już potwierdzony.']);
        } catch (RuntimeException $e) {
            redirect_with('panel-zapisy.php', ['error' => $e->getMessage()]);
        }
    }
}

$stmt = db()->prepare("SELECT e.*, cs.title, cs.starts_at, cs.ends_at, cs.meeting_url, cs.class_type_id, ct.name AS ct_name, ct.color AS ct_color, c.first_name, c.last_name
    FROM enrollments e JOIN class_sessions cs ON cs.id = e.session_id JOIN class_types ct ON ct.id = cs.class_type_id
    JOIN children c ON c.id = e.child_id
    WHERE e.parent_id = ? AND e.org_id = ?
    ORDER BY cs.starts_at DESC");
$stmt->execute([$user['id'], $org['id']]);
$rows = $stmt->fetchAll();

// Dla każdej nieobecności bez odrobienia, znajdź dostępne terminy tych samych zajęć (przyszłe, z wolnym miejscem).
$makeupOptions = [];
foreach ($rows as $r) {
    if ($r['attendance_status'] === 'ABSENT' && !$r['rescheduled_to_enrollment_id']) {
        $opts = db()->prepare("SELECT cs.*, (SELECT COUNT(*) FROM enrollments e2 WHERE e2.session_id = cs.id AND e2.status = 'CONFIRMED') AS confirmed_count
            FROM class_sessions cs
            WHERE cs.org_id = ? AND cs.class_type_id = ? AND cs.status = 'SCHEDULED' AND cs.starts_at > ?
            ORDER BY cs.starts_at ASC LIMIT 6");
        $opts->execute([$org['id'], $r['class_type_id'], date('Y-m-d H:i:s')]);
        $makeupOptions[$r['id']] = array_values(array_filter($opts->fetchAll(), fn($s) => (int) $s['confirmed_count'] < (int) $s['capacity']));
    }
}

$pageTitle = 'Moje zapisy — ' . $org['name'];
require __DIR__ . '/includes/layout_top.php';
?>
<div class="container section">
  <h1 class="section-title">Moje zapisy</h1>
  <?php if ($error): ?><p class="alert alert-error"><?= e($error) ?></p><?php endif; ?>
  <?php if ($info): ?><p class="alert alert-success"><?= e($info) ?></p><?php endif; ?>

  <?php if (!$rows): ?><p class="text-muted mt-6">Brak zapisów. <a href="<?= e(url('kalendarz.php')) ?>">Zapisz dziecko na zajęcia →</a></p><?php endif; ?>

  <div class="enroll-list mt-6">
    <?php foreach ($rows as $r): ?>
      <div class="enroll-card reveal">
        <div class="enroll-main">
          <div class="agenda-dot" style="background:<?= e($r['ct_color']) ?>;display:inline-block;"></div>
          <span class="enroll-title"><?= e($r['ct_name']) ?> — <?= e($r['title']) ?></span>
          <div class="text-muted"><?= e($r['first_name'] . ' ' . $r['last_name']) ?> · <?= e(format_pl_date($r['starts_at'], true, true)) ?></div>
          <?php if (in_array($r['status'], ['CONFIRMED', 'WAITLIST'], true)): ?>
            <div style="font-size:.78rem;">
              <a href="<?= e(google_calendar_link($r['ct_name'] . ' — ' . $r['title'], $r['starts_at'], $r['ends_at'], '', $r['meeting_url'] ?? '')) ?>" target="_blank" rel="noopener" class="text-muted">+ Kalendarz Google</a>
              <?php if ($r['meeting_url']): ?> · <a href="<?= e($r['meeting_url']) ?>" target="_blank" rel="noopener">🔗 dołącz online</a><?php endif; ?>
            </div>
          <?php endif; ?>
        </div>
        <div class="enroll-badges">
          <span class="badge badge-<?= strtolower($r['status']) ?>"><?= match ($r['status']) { 'PENDING' => 'Oczekuje', 'CONFIRMED' => 'Potwierdzone', 'WAITLIST' => 'Lista rezerwowa', 'REJECTED' => 'Odrzucone', 'CANCELED' => 'Anulowane', default => $r['status'] } ?></span>
          <?php if ($r['status'] === 'CONFIRMED'): ?>
            <span class="badge badge-<?= $r['payment_status'] === 'PAID' ? 'confirmed' : 'pending' ?>"><?= $r['payment_status'] === 'PAID' ? 'Opłacone' : 'Do opłacenia' ?></span>
          <?php endif; ?>
          <?php if ($r['attendance_status'] === 'ABSENT'): ?>
            <span class="badge badge-pending"><?= $r['rescheduled_to_enrollment_id'] ? 'Odrobione' : 'Nieobecność — do odrobienia' ?></span>
          <?php endif; ?>
        </div>
        <div class="enroll-actions">
          <?php if ($r['status'] === 'CONFIRMED' && $r['payment_status'] === 'UNPAID' && (int) $r['amount_due_cents'] > 0 && TPAY_ENABLED): ?>
            <!-- Płatność online (Tpay, KROK 1) — patrz platnosc.php. Przycisk
                 pojawia się tylko, gdy jest kwota do zapłaty i płatności
                 online są włączone; ręczne "Oznacz jako opłacone" po stronie
                 organizacji (zapisy.php) zostaje jako metoda zapasowa.
                 Checkbox "zapamiętaj kartę" to KROK 2 (tokenizacja) — patrz
                 platnosc.php ($requestTokenization) i panel-platnosci.php. -->
            <form method="post" action="<?= e(url('platnosc.php')) ?>" class="inline flex items-center gap-2">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= $r['id'] ?>">
              <label class="text-muted" style="font-size:.78rem;display:flex;align-items:center;gap:4px;">
                <input type="checkbox" name="saveCard" value="1"> zapamiętaj kartę
              </label>
              <button class="btn btn-primary btn-sm">💳 Zapłać online</button>
            </form>
          <?php endif; ?>
          <?php if (in_array($r['status'], ['PENDING', 'CONFIRMED', 'WAITLIST'], true)): ?>
            <form method="post" class="inline" onsubmit="return confirm('Anulować ten zapis?');"><?= csrf_field() ?><input type="hidden" name="_action" value="cancel"><input type="hidden" name="id" value="<?= $r['id'] ?>"><button class="btn btn-outline btn-sm">Anuluj</button></form>
          <?php endif; ?>
          <?php if ($r['status'] === 'CONFIRMED' && !$r['attendance_status'] && strtotime($r['starts_at']) < time()): ?>
            <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="_action" value="report_absence"><input type="hidden" name="id" value="<?= $r['id'] ?>"><button class="btn btn-outline btn-sm">Zgłoś nieobecność</button></form>
          <?php endif; ?>
        </div>

        <?php if ($r['attendance_status'] === 'ABSENT' && !$r['rescheduled_to_enrollment_id']): ?>
          <div class="reclaim-box mt-4">
            <?php if (empty($makeupOptions[$r['id']])): ?>
              <p class="text-muted">Brak wolnych terminów tych zajęć w najbliższym czasie do odrobienia — spróbuj później.</p>
            <?php else: ?>
              <form method="post" class="flex items-center gap-2">
                <?= csrf_field() ?>
                <input type="hidden" name="_action" value="reclaim">
                <input type="hidden" name="id" value="<?= $r['id'] ?>">
                <label class="text-muted">Odrób na:</label>
                <select name="makeupSessionId" required>
                  <?php foreach ($makeupOptions[$r['id']] as $opt): ?>
                    <option value="<?= $opt['id'] ?>"><?= e(format_pl_date($opt['starts_at'], true, true)) ?> (<?= (int) $opt['capacity'] - (int) $opt['confirmed_count'] ?> wolnych)</option>
                  <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-primary btn-sm">Odrób zajęcia</button>
              </form>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
