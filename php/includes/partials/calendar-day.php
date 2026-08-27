<?php
/**
 * Wymaga: $anchor, $sessions, $basePath. Widok publiczny (kalendarz.php)
 * ustawia $showEnrollForm=true, $user, $children. Widok admina (admin.php)
 * ustawia $showCancelForm=true.
 */
$showEnrollForm = $showEnrollForm ?? false;
$showCancelForm = $showCancelForm ?? false;
$scheduled = array_values(array_filter($sessions, fn($s) => $s['status'] === 'SCHEDULED'));
usort($scheduled, fn($a, $b) => strcmp($a['starts_at'], $b['starts_at']));
?>
<?php if (!$scheduled): ?>
  <p class="card text-center text-muted" style="border-style:dashed;">Brak zajęć w tym dniu (<?= e(format_pl_date(date_param($anchor))) ?>).</p>
<?php else: ?>
  <?php foreach ($scheduled as $s): ?>
    <div class="session-card">
      <div class="flex items-center gap-2">
        <span class="dot" style="background:<?= e($s['ct_color']) ?>;"></span>
        <strong><?= e($s['ct_name']) ?> — <?= e($s['title']) ?></strong>
      </div>
      <p class="text-muted mt-2"><?= h_m($s['starts_at']) ?>–<?= h_m($s['ends_at']) ?> · prowadzi <?= e($s['instructor_name']) ?></p>
      <?php if ($s['description']): ?><p class="mt-2"><?= e($s['description']) ?></p><?php endif; ?>
      <p class="mt-2" style="font-weight:700; color:<?= $s['is_full'] ? '#b0413e' : '#1f7a4d' ?>;">
        <?= $s['is_full'] ? 'Brak wolnych miejsc' : "{$s['spots_left']} wolnych miejsc" ?> ( <?= $s['confirmed_count'] ?>/<?= (int) $s['capacity'] ?> )
      </p>

      <?php if ($showCancelForm): ?>
        <p class="text-muted mt-2">Zapisanych: <?= $s['confirmed_count'] ?>/<?= (int) $s['capacity'] ?> · prowadzi <?= e($s['instructor_name']) ?></p>
        <form method="post" action="<?= e(url('admin.php')) ?>" class="mt-4">
          <?= csrf_field() ?>
          <input type="hidden" name="_action" value="cancel_session">
          <input type="hidden" name="sessionId" value="<?= (int) $s['id'] ?>">
          <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Na pewno odwołać te zajęcia?')">Odwołaj zajęcia</button>
        </form>
      <?php elseif ($showEnrollForm): ?>
        <?php if (!$user): ?>
          <a href="<?= e(url('logowanie.php?next=' . urlencode(url('kalendarz.php?view=day&date=' . date_param($anchor))))) ?>" class="btn btn-primary btn-sm mt-4">Zaloguj się, aby zapisać dziecko</a>
        <?php elseif (!$children): ?>
          <a href="<?= e(url('panel-dzieci.php')) ?>" class="btn btn-primary btn-sm mt-4">Dodaj dziecko, aby się zapisać</a>
        <?php else: ?>
          <form method="post" action="<?= e(url('kalendarz.php')) ?>" class="flex flex-wrap items-center gap-2 mt-4">
            <?= csrf_field() ?>
            <input type="hidden" name="_action" value="enroll">
            <input type="hidden" name="sessionId" value="<?= (int) $s['id'] ?>">
            <select name="childId" required style="width:auto;">
              <?php foreach ($children as $c): ?>
                <option value="<?= (int) $c['id'] ?>"><?= e($c['first_name'] . ' ' . $c['last_name']) ?></option>
              <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-primary btn-sm">Zgłoś chęć zapisu</button>
          </form>
          <p class="field-hint mt-2">Zgłoszenie wymaga potwierdzenia przez pracownię — dobierzemy grupę odpowiednią do wieku dziecka i odpowiemy e-mailem.</p>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
<?php endif; ?>
