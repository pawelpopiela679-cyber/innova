<?php
require_once __DIR__ . '/includes/bootstrap.php';

$error = null;

// --- Zgłoszenie chęci zapisu (odpowiednik enrollAction) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_action'] ?? '') === 'enroll') {
    csrf_check();
    $user = current_user();
    if (!$user) {
        redirect('logowanie.php?next=' . urlencode(url('kalendarz.php')));
    }
    $sessionId = (int) ($_POST['sessionId'] ?? 0);
    $childId = (int) ($_POST['childId'] ?? 0);

    $child = db()->prepare('SELECT * FROM children WHERE id = ?');
    $child->execute([$childId]);
    $child = $child->fetch();

    if (!$child || (int) $child['parent_id'] !== (int) $user['id']) {
        redirect_with('kalendarz.php', ['error' => 'To nie jest Twoje dziecko.']);
    }

    $cs = db()->prepare('SELECT cs.*, ct.name AS ct_name FROM class_sessions cs JOIN class_types ct ON ct.id = cs.class_type_id WHERE cs.id = ?');
    $cs->execute([$sessionId]);
    $cs = $cs->fetch();

    if (!$cs || $cs['status'] !== 'SCHEDULED') {
        redirect_with('kalendarz.php', ['error' => 'Te zajęcia nie są już dostępne.']);
    }

    $already = db()->prepare('SELECT * FROM enrollments WHERE session_id = ? AND child_id = ?');
    $already->execute([$sessionId, $childId]);
    $already = $already->fetch();

    if ($already && $already['status'] !== 'CANCELED') {
        redirect_with('panel-zapisy.php', ['info' => 'Dziecko ma już zgłoszenie na te zajęcia.']);
    }

    if ($already) {
        db()->prepare("UPDATE enrollments SET status='PENDING', canceled_at=NULL, confirmed_at=NULL WHERE id=?")->execute([$already['id']]);
        $enrollmentId = (int) $already['id'];
    } else {
        db()->prepare('INSERT INTO enrollments (session_id, child_id, parent_id, status) VALUES (?,?,?,?)')
            ->execute([$sessionId, $childId, $user['id'], 'PENDING']);
        $enrollmentId = db_last_id(db());
    }

    $parent = db()->prepare('SELECT * FROM users WHERE id = ?');
    $parent->execute([$user['id']]);
    $parent = $parent->fetch();

    $confirmedCount = (int) db()->query("SELECT COUNT(*) c FROM enrollments WHERE session_id = $sessionId AND status = 'CONFIRMED'")->fetch()['c'];

    send_enrollment_pending_email([
        'parentEmail' => $parent['email'], 'parentName' => $parent['name'],
        'childName' => $child['first_name'] . ' ' . $child['last_name'],
        'classTypeName' => $cs['ct_name'], 'sessionTitle' => $cs['title'],
        'startsAt' => $cs['starts_at'], 'endsAt' => $cs['ends_at'],
    ]);
    send_studio_new_signup_notification([
        'childName' => $child['first_name'] . ' ' . $child['last_name'], 'childBirthDate' => $child['birth_date'],
        'parentName' => $parent['name'], 'parentEmail' => $parent['email'], 'parentPhone' => $parent['phone'],
        'classTypeName' => $cs['ct_name'], 'sessionTitle' => $cs['title'],
        'startsAt' => $cs['starts_at'], 'endsAt' => $cs['ends_at'],
        'confirmedCount' => $confirmedCount, 'capacity' => $cs['capacity'],
    ]);

    redirect('panel-zapisy-potwierdzenie.php?id=' . $enrollmentId);
}

$error = $_GET['error'] ?? null;
$view = in_array($_GET['view'] ?? '', ['week', 'day'], true) ? $_GET['view'] : 'month';
$anchor = parse_date_param($_GET['date'] ?? null);
$classTypeId = !empty($_GET['classType']) ? (int) $_GET['classType'] : null;
[$from, $to] = range_for_view($view, $anchor);

$classTypes = db()->query('SELECT * FROM class_types ORDER BY id ASC')->fetchAll();
$sessions = get_sessions_with_availability($from, $to, $classTypeId);

$user = current_user();
$children = [];
if ($user) {
    $stmt = db()->prepare('SELECT * FROM children WHERE parent_id = ? ORDER BY first_name ASC');
    $stmt->execute([$user['id']]);
    $children = $stmt->fetchAll();
}

$extraParams = $classTypeId ? ['classType' => $classTypeId] : [];
$basePath = 'kalendarz.php';

// --- Siatka "przykładowy tydzień" (poglądowa, u góry strony) — bierzemy
// pierwszy pełny tydzień semestru jako reprezentatywny wzorzec zajęć.
$exampleAnchor = new DateTime(SEMESTER_START);
[$ewFrom, $ewTo] = range_for_view('week', $exampleAnchor);
$exampleSessions = get_sessions_with_availability($ewFrom, $ewTo, null);
$weekDayLabels = ['PON', 'WT', 'ŚR', 'CZW', 'PT', 'SOB', 'NIE'];
$exampleGrid = []; // ['H:i' => ['PON' => [sessionRow, ...], ...]]
foreach ($exampleSessions as $s) {
    if ($s['status'] !== 'SCHEDULED') {
        continue;
    }
    $dt = new DateTime($s['starts_at']);
    $hourLabel = $dt->format('H') . ':00';
    $dayLabel = $weekDayLabels[(int) $dt->format('N') - 1];
    $exampleGrid[$hourLabel][$dayLabel][] = $s;
}
ksort($exampleGrid);

$pageTitle = 'Grafik zajęć — INNOVA';
$notebookTheme = true;
$notebookActive = 'schedule';
require __DIR__ . '/includes/layout_top.php';
?>
<div class="nb-hero" style="grid-template-columns:1fr 1.4fr; margin-top:0;">
  <div class="nb-photo-block" style="max-width:260px;">
    <img src="<?= e(url('assets/img/banners/grafik.png')) ?>" alt="Grafik — sprawdź aktualne terminy zajęć i zaplanuj swoją przygodę z INNOVA!" style="width:100%; height:auto; filter:drop-shadow(0 8px 16px rgba(74,67,38,.18));">
  </div>
  <div>
    <div class="nb-step-title">3. Sprawdź grafik <span class="nb-box"></span></div>
    <div class="nb-step-sub">Wybierz dogodny termin! Poniżej przykładowy, cotygodniowy rytm zajęć.</div>
    <a href="#szczegolowy-kalendarz" class="nb-pill-dark">Przykładowy tydzień ↓</a>
  </div>
</div>

<div class="nb-grid-wrap">
  <table class="nb-week">
    <thead><tr><th></th><?php foreach (['PON', 'WT', 'ŚR', 'CZW', 'PT'] as $d): ?><th><?= $d ?></th><?php endforeach; ?></tr></thead>
    <tbody>
      <?php foreach ($exampleGrid as $hour => $days): ?>
        <tr>
          <td class="nb-time"><?= e($hour) ?></td>
          <?php foreach (['PON', 'WT', 'ŚR', 'CZW', 'PT'] as $d): ?>
            <td>
              <?php foreach ($days[$d] ?? [] as $s): [$bg, $ink] = nb_pastel($s['ct_key']); ?>
                <a href="<?= e(url('kalendarz.php?classType=' . $s['class_type_id'])) ?>" class="nb-slot" title="<?= e($s['ct_name']) ?>">
                  <span class="nb-dot" style="background:<?= e($bg) ?>;"><?= nb_icon_svg($s['ct_key'], '') ?></span>
                  <small><?= h_m($s['starts_at']) ?></small>
                </a>
              <?php endforeach; ?>
            </td>
          <?php endforeach; ?>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<div class="nb-two-col" style="margin-top:34px;">
  <div>
    <div class="nb-step-title">4. Zapisz się <span class="nb-box"></span></div>
    <div class="nb-step-sub">To proste!</div>
    <div class="nb-process-item">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="12" rx="1.5"/><path d="M2 20h20"/></svg>
      <div>Wybierz dokładny termin w kalendarzu poniżej i wypełnij zgłoszenie <b>(2 minuty)</b></div>
    </div>
    <div class="nb-process-item">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 6l9 7 9-7"/></svg>
      <div>Pracownia potwierdza zapis <b>e-mailem</b></div>
    </div>
    <div class="nb-process-item">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M8 14s1.5 2 4 2 4-2 4-2M9 9h.01M15 9h.01"/></svg>
      <div>Dołącz do INNOVA i odkryj swoje pasje!</div>
    </div>
  </div>
  <div>
    <img src="<?= e(url('assets/img/banners/zapisz-sie.png')) ?>" alt="Zapisz się już dziś!" style="width:130px; height:auto; display:block; margin:0 auto 16px; transform:rotate(-3deg);">
    <div class="nb-callout">
      <div style="display:flex; gap:10px; align-items:center; margin-bottom:6px;">
        <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" style="color:var(--nb-gold);"><path d="M12 2l2.9 6.3 6.9.7-5.2 4.6 1.6 6.8L12 16.9 5.8 20.4l1.6-6.8L2.2 9l6.9-.7z"/></svg>
        <b>Dzień otwarty — bezpłatne zajęcia pokazowe</b>
      </div>
      <p style="margin:0 0 12px; font-size:.85rem;"><?= e(format_pl_date(OPEN_DAY_DATE)) ?>, 10:00–13:00 — poznaj pracownię, prowadzących i ofertę zajęć przed startem zapisów na semestr.</p>
      <a href="<?= e(url('rejestracja.php')) ?>" class="nb-btn solid uppercase" style="width:100%; justify-content:center; box-sizing:border-box;">Zapisz się teraz</a>
    </div>
  </div>
</div>

<div id="szczegolowy-kalendarz" style="margin-top:56px; border-top:2px dashed var(--nb-rule); padding-top:26px;">
  <h2 class="nb-section-title" style="text-align:left; font-size:1.4rem;">📅 Szczegółowy kalendarz i zapisy</h2>
  <p class="text-muted mt-2">Wybierz dzień, żeby zobaczyć opis zajęć i zapisać dziecko na konkretny termin.</p>

  <?php if ($error): ?><p class="alert alert-error"><?= e($error) ?></p><?php endif; ?>

  <form method="get" class="flex items-center gap-2 mt-6" style="font-size:0.9rem;">
    <input type="hidden" name="view" value="<?= e($view) ?>">
    <input type="hidden" name="date" value="<?= e(date_param($anchor)) ?>">
    <label for="classType" class="text-muted" style="margin:0;">Rodzaj zajęć:</label>
    <select id="classType" name="classType" style="width:auto;">
      <option value="">Wszystkie</option>
      <?php foreach ($classTypes as $ct): ?>
        <option value="<?= $ct['id'] ?>" <?= $classTypeId === (int) $ct['id'] ? 'selected' : '' ?>><?= e($ct['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-outline btn-sm">Filtruj</button>
  </form>

  <?php include __DIR__ . '/includes/partials/calendar-nav.php'; ?>

  <div class="mt-6">
    <?php if ($view === 'month'): ?>
      <?php include __DIR__ . '/includes/partials/calendar-month.php'; ?>
    <?php elseif ($view === 'week'): ?>
      <?php include __DIR__ . '/includes/partials/calendar-week.php'; ?>
    <?php else: ?>
      <?php $showEnrollForm = true; include __DIR__ . '/includes/partials/calendar-day.php'; ?>
    <?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
