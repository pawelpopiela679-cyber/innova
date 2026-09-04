<?php
require_once __DIR__ . '/includes/bootstrap.php';
$user = require_group_manager();

/**
 * Panel zarządzania grupami — widoczny tylko dla właścicielki i prowadzących
 * z can_manage_groups (patrz includes/auth.php: user_can_manage_groups()).
 *
 * Pula (lewa kolumna) = zgłoszenia PENDING bez przypisanej jeszcze grupy —
 * dziecko rodzic zgłosił chęć zapisu na rodzaj zajęć, ale nie ma jeszcze
 * konkretnej grupy/terminu. Karty grup (prawa strona) = class_groups.
 * Przypisanie: przeciągnij kafelek dziecka na kartę grupy (JS niżej) albo,
 * jeśli przeciąganie nie leży komuś/czemuś — wybierz grupę z listy i kliknij
 * "Przydziel" (to jest to samo działanie, drag-and-drop tylko wypełnia i
 * wysyła ten sam formularz).
 */

$error = $_GET['error'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['_action'] ?? '';
    $enrollmentId = (int) ($_POST['enrollmentId'] ?? 0);

    $enrollment = db()->prepare('SELECT * FROM enrollments WHERE id = ?');
    $enrollment->execute([$enrollmentId]);
    $enrollment = $enrollment->fetch();

    if (!$enrollment) {
        redirect_with('admin-grupy.php', ['error' => 'Nie znaleziono zgłoszenia — może już zostało zmienione w innym oknie.']);
    }

    $child = db()->prepare('SELECT * FROM children WHERE id = ?');
    $child->execute([$enrollment['child_id']]);
    $child = $child->fetch();

    $parent = db()->prepare('SELECT * FROM users WHERE id = ?');
    $parent->execute([$enrollment['parent_id']]);
    $parent = $parent->fetch();

    if ($action === 'assign') {
        $groupId = (int) ($_POST['groupId'] ?? 0);
        $group = db()->prepare('SELECT g.*, ct.name AS ct_name FROM class_groups g JOIN class_types ct ON ct.id = g.class_type_id WHERE g.id = ?');
        $group->execute([$groupId]);
        $group = $group->fetch();

        if (!$group) {
            redirect_with('admin-grupy.php', ['error' => 'Nie znaleziono grupy.']);
        }

        // Kolizja godzin z INNYMI grupami, do których dziecko jest już
        // potwierdzone (ten sam dzień tygodnia + nakładające się godziny).
        $others = db()->prepare("SELECT g2.name, g2.day_of_week, g2.start_time, g2.end_time
            FROM enrollments e2 JOIN class_groups g2 ON g2.id = e2.group_id
            WHERE e2.child_id = ? AND e2.status = 'CONFIRMED' AND e2.group_id != ?");
        $others->execute([$enrollment['child_id'], $groupId]);
        $conflict = null;
        foreach ($others->fetchAll() as $og) {
            if ((int) $og['day_of_week'] === (int) $group['day_of_week']
                && $group['start_time'] < $og['end_time'] && $og['start_time'] < $group['end_time']) {
                $conflict = $og;
                break;
            }
        }
        if ($conflict) {
            redirect_with('admin-grupy.php', [
                'error' => "Kolizja godzin: {$child['first_name']} {$child['last_name']} ma już zajęcia „{$conflict['name']}” w tym samym czasie ({$conflict['start_time']}–{$conflict['end_time']}).",
            ]);
        }

        $countStmt = db()->prepare("SELECT COUNT(*) c FROM enrollments WHERE group_id = ? AND status = 'CONFIRMED'");
        $countStmt->execute([$groupId]);
        $confirmedCount = (int) $countStmt->fetch()['c'];
        $isFull = $confirmedCount >= (int) $group['capacity'];
        $newStatus = $isFull ? 'WAITLIST' : 'CONFIRMED';

        db()->prepare("UPDATE enrollments SET group_id=?, class_type_id=?, status=?, confirmed_at=? WHERE id=?")
            ->execute([$groupId, $group['class_type_id'], $newStatus, $isFull ? null : date('Y-m-d H:i:s'), $enrollmentId]);

        send_enrollment_confirmation_email([
            'parentEmail' => $parent['email'], 'parentName' => $parent['name'],
            'childName' => $child['first_name'] . ' ' . $child['last_name'],
            'classTypeName' => $group['ct_name'], 'sessionTitle' => $group['name'],
            'when' => format_group_schedule((int) $group['day_of_week'], $group['start_time'], $group['end_time']),
            'instructorName' => $group['instructor_name'], 'meetingUrl' => $group['meeting_url'],
            'waitlisted' => $isFull,
        ]);

        redirect('admin-grupy.php?assigned=1');
    }

    if ($action === 'unassign') {
        $prevGroupId = (int) $enrollment['group_id'];
        db()->prepare("UPDATE enrollments SET group_id=NULL, status='PENDING', confirmed_at=NULL WHERE id=?")->execute([$enrollmentId]);
        if ($prevGroupId) {
            promote_next_waitlisted($prevGroupId);
        }
        redirect('admin-grupy.php?unassigned=1');
    }

    if ($action === 'decline') {
        db()->prepare("UPDATE enrollments SET status='CANCELED', canceled_at=CURRENT_TIMESTAMP WHERE id=?")->execute([$enrollmentId]);
        $classType = db()->prepare('SELECT name FROM class_types WHERE id = ?');
        $classType->execute([$enrollment['class_type_id']]);
        $classType = $classType->fetch();
        send_enrollment_declined_email([
            'parentEmail' => $parent['email'], 'parentName' => $parent['name'],
            'childName' => $child['first_name'] . ' ' . $child['last_name'],
            'classTypeName' => $classType['name'] ?? '',
        ]);
        redirect('admin-grupy.php?declined=1');
    }
}

// --- Pula: zgłoszenia czekające, bez grupy ---
$pool = db()->query("SELECT e.*, c.first_name, c.last_name, c.birth_date, ct.id AS class_type_id, ct.name AS ct_name, ct.key_name AS ct_key
    FROM enrollments e
    JOIN children c ON c.id = e.child_id
    JOIN class_types ct ON ct.id = e.class_type_id
    WHERE e.status = 'PENDING' AND e.group_id IS NULL
    ORDER BY e.created_at ASC")->fetchAll();

// --- Grupy + ich obecny skład ---
$groups = db()->query("SELECT g.*, ct.name AS ct_name, ct.key_name AS ct_key
    FROM class_groups g JOIN class_types ct ON ct.id = g.class_type_id
    WHERE g.active = 1
    ORDER BY ct.id ASC, g.day_of_week ASC, g.start_time ASC")->fetchAll();

$membersByGroup = [];
$membersRows = db()->query("SELECT e.*, c.first_name, c.last_name, u.email AS parent_email, u.name AS parent_name
    FROM enrollments e JOIN children c ON c.id = e.child_id JOIN users u ON u.id = e.parent_id
    WHERE e.group_id IS NOT NULL AND e.status IN ('CONFIRMED','WAITLIST')
    ORDER BY e.status ASC, e.confirmed_at ASC")->fetchAll();
foreach ($membersRows as $m) {
    $membersByGroup[(int) $m['group_id']][] = $m;
}

/** mailto: z BCC do rodziców aktualnych (potwierdzonych) dzieci w grupie. */
function group_mailto_link(array $members, string $groupName): string
{
    $emails = [];
    foreach ($members as $m) {
        if ($m['status'] === 'CONFIRMED') {
            $emails[] = $m['parent_email'];
        }
    }
    $emails = array_unique($emails);
    if (!$emails) {
        return '';
    }
    return 'mailto:?bcc=' . rawurlencode(implode(',', $emails)) . '&subject=' . rawurlencode("INNOVA — {$groupName}");
}

$pageTitle = 'Grupy — INNOVA';
$notebookTheme = true;
require __DIR__ . '/includes/layout_top.php';
?>
<div class="container" style="padding:40px 16px;">
  <?php include __DIR__ . '/includes/partials/admin-nav.php'; ?>

  <h1 style="font-size:1.6rem;">Grupy</h1>
  <p class="text-muted mt-2">
    Przeciągnij dziecko z puli na kartę grupy, żeby je przydzielić — albo wybierz grupę z listy przy
    kafelku i kliknij „Przydziel”. Nową grupę utworzysz w <a href="<?= e(url('admin-zajecia-nowe.php')) ?>" style="color:var(--primary); text-decoration:underline;">+ Nowa grupa</a>.
  </p>

  <?php if ($error): ?><p class="alert alert-error"><?= e($error) ?></p><?php endif; ?>
  <?php if (isset($_GET['assigned'])): ?><p class="alert alert-success">Dziecko zostało przydzielone do grupy.</p><?php endif; ?>
  <?php if (isset($_GET['unassigned'])): ?><p class="alert alert-info">Dziecko wróciło do puli oczekujących.</p><?php endif; ?>
  <?php if (isset($_GET['declined'])): ?><p class="alert alert-info">Zgłoszenie zostało odrzucone.</p><?php endif; ?>

  <div class="nb-groups-layout mt-6">
    <div>
      <h2 style="font-size:1.05rem;">Pula oczekujących (<?= count($pool) ?>)</h2>
      <?php if (!$pool): ?>
        <p class="text-muted mt-2" style="font-size:0.85rem;">Pusto — wszystkie zgłoszenia mają już przydzieloną grupę.</p>
      <?php endif; ?>
      <div class="nb-pool mt-3">
        <?php foreach ($pool as $p): ?>
          <div class="nb-tile" draggable="true" data-enrollment-id="<?= (int) $p['id'] ?>" data-class-type="<?= (int) $p['class_type_id'] ?>">
            <strong><?= e($p['first_name'] . ' ' . $p['last_name']) ?></strong>
            <span class="text-muted" style="font-size:0.78rem;">(<?= calculate_age($p['birth_date']) ?> lat)</span>
            <p class="text-muted mt-2" style="font-size:0.82rem;"><?= e($p['ct_name']) ?></p>
            <form method="post" class="mt-2 nb-tile-form">
              <?= csrf_field() ?>
              <input type="hidden" name="enrollmentId" value="<?= (int) $p['id'] ?>">
              <select name="groupId" style="width:100%; font-size:0.78rem; margin-bottom:6px;">
                <option value="">Wybierz grupę…</option>
                <?php foreach ($groups as $g): if ((int) $g['class_type_id'] !== (int) $p['class_type_id']) {
                    continue;
                } ?>
                  <option value="<?= (int) $g['id'] ?>"><?= e($g['name']) ?> (<?= e(weekday_name_iso((int) $g['day_of_week'])) ?> <?= e($g['start_time']) ?>)</option>
                <?php endforeach; ?>
              </select>
              <div class="flex gap-2">
                <button type="submit" name="_action" value="assign" class="btn btn-primary btn-sm">Przydziel</button>
                <button type="submit" name="_action" value="decline" class="btn btn-danger btn-sm" onclick="return confirm('Na pewno odrzucić to zgłoszenie?')">Odrzuć</button>
              </div>
            </form>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div>
      <h2 style="font-size:1.05rem;">Grupy (<?= count($groups) ?>)</h2>
      <div class="nb-groups-grid mt-3">
        <?php foreach ($groups as $g):
            $members = $membersByGroup[(int) $g['id']] ?? [];
            $confirmed = array_values(array_filter($members, fn($m) => $m['status'] === 'CONFIRMED'));
            $waitlist = array_values(array_filter($members, fn($m) => $m['status'] === 'WAITLIST'));
            $mailto = group_mailto_link($members, $g['name']);
        ?>
          <div class="nb-group-card" data-group-id="<?= (int) $g['id'] ?>" data-class-type="<?= (int) $g['class_type_id'] ?>">
            <div class="flex items-center justify-between" style="justify-content:space-between;">
              <strong><?= e($g['ct_name']) ?> — <?= e($g['name']) ?></strong>
              <span style="font-weight:700; color:<?= count($confirmed) >= (int) $g['capacity'] ? '#b0413e' : '#1f7a4d' ?>;"><?= count($confirmed) ?>/<?= (int) $g['capacity'] ?></span>
            </div>
            <p class="text-muted mt-2" style="font-size:0.82rem;">
              <?= e(weekday_name_iso((int) $g['day_of_week'])) ?>i, <?= e($g['start_time']) ?>–<?= e($g['end_time']) ?> · <?= e($g['instructor_name']) ?>
            </p>
            <?php if ($mailto): ?>
              <p class="mt-2"><a href="<?= e($mailto) ?>" style="font-size:0.8rem; color:var(--primary);">✉️ Wyślij e-mail do grupy</a></p>
            <?php endif; ?>
            <ul class="nb-group-members mt-3">
              <?php foreach ($confirmed as $m): ?>
                <li>
                  <span><?= e($m['first_name'] . ' ' . $m['last_name']) ?>
                    <a href="<?= e(url('harmonogram-dziecka.php?child_id=' . $m['child_id'])) ?>" title="Grafik zajęć tego dziecka" style="font-size:0.78rem; color:var(--primary);">📅</a>
                  </span>
                  <form method="post" style="display:inline;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="_action" value="unassign">
                    <input type="hidden" name="enrollmentId" value="<?= (int) $m['id'] ?>">
                    <button type="submit" class="nb-remove-btn" title="Usuń z grupy (wróci do puli)" onclick="return confirm('Usunąć z tej grupy? Dziecko wróci do puli oczekujących.')">✕</button>
                  </form>
                </li>
              <?php endforeach; ?>
              <?php foreach ($waitlist as $m): ?>
                <li class="text-muted">
                  <?= e($m['first_name'] . ' ' . $m['last_name']) ?> (lista rezerwowa)
                  <form method="post" style="display:inline;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="_action" value="unassign">
                    <input type="hidden" name="enrollmentId" value="<?= (int) $m['id'] ?>">
                    <button type="submit" class="nb-remove-btn" title="Usuń z grupy (wróci do puli)" onclick="return confirm('Usunąć z tej grupy? Dziecko wróci do puli oczekujących.')">✕</button>
                  </form>
                </li>
              <?php endforeach; ?>
              <?php if (!$members): ?><li class="text-muted">Brak dzieci w tej grupie.</li><?php endif; ?>
            </ul>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<style>
  .nb-groups-layout { display:grid; grid-template-columns: 300px 1fr; gap:28px; align-items:start; }
  .nb-pool { display:flex; flex-direction:column; gap:10px; }
  .nb-tile {
    background:var(--surface); border:1px solid var(--border); border-radius:12px; padding:12px;
    cursor:grab; box-shadow:2px 3px 0 rgba(74,67,38,.08);
  }
  .nb-tile:active { cursor:grabbing; }
  .nb-tile.dragging { opacity:.4; }
  .nb-groups-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(260px,1fr)); gap:16px; }
  .nb-group-card {
    background:var(--surface); border:1px solid var(--border); border-radius:14px; padding:16px;
    transition:box-shadow .15s ease, border-color .15s ease;
  }
  .nb-group-card.drag-over { border-color:var(--primary); box-shadow:0 0 0 3px color-mix(in srgb, var(--primary) 25%, transparent); }
  .nb-group-members { list-style:none; margin:0; padding:0; display:flex; flex-direction:column; gap:6px; font-size:0.85rem; }
  .nb-group-members li { display:flex; align-items:center; justify-content:space-between; gap:8px; }
  .nb-remove-btn { background:none; border:none; color:#b0413e; cursor:pointer; font-size:0.9rem; padding:0 4px; }
  @media (max-width: 800px) { .nb-groups-layout { grid-template-columns:1fr; } }
</style>
<script>
(function () {
  var tiles = document.querySelectorAll('.nb-tile');
  var cards = document.querySelectorAll('.nb-group-card');
  tiles.forEach(function (tile) {
    tile.addEventListener('dragstart', function (e) {
      e.dataTransfer.setData('text/plain', tile.dataset.enrollmentId);
      e.dataTransfer.effectAllowed = 'move';
      tile.classList.add('dragging');
    });
    tile.addEventListener('dragend', function () { tile.classList.remove('dragging'); });
  });
  cards.forEach(function (card) {
    card.addEventListener('dragover', function (e) { e.preventDefault(); card.classList.add('drag-over'); });
    card.addEventListener('dragleave', function () { card.classList.remove('drag-over'); });
    card.addEventListener('drop', function (e) {
      e.preventDefault();
      card.classList.remove('drag-over');
      var enrollmentId = e.dataTransfer.getData('text/plain');
      var tile = document.querySelector('.nb-tile[data-enrollment-id="' + enrollmentId + '"]');
      if (!tile) { return; }
      if (tile.dataset.classType !== card.dataset.classType) {
        alert('Ten rodzaj zajęć nie pasuje do tej grupy.');
        return;
      }
      var form = tile.querySelector('form');
      form.querySelector('select[name=groupId]').value = card.dataset.groupId;
      var actionInput = document.createElement('input');
      actionInput.type = 'hidden';
      actionInput.name = '_action';
      actionInput.value = 'assign';
      form.appendChild(actionInput);
      form.requestSubmit();
    });
  });
})();
</script>
<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
