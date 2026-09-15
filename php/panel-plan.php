<?php
require_once __DIR__ . '/includes/bootstrap.php';
$user = require_login('panel-plan.php');

/**
 * Spersonalizowany plan zajęć rodzica — osobny widok dla KAŻDEGO dziecka
 * (nie zbiorczy grafik wszystkich zajęć pracowni, jak kalendarz.php).
 * Pokazuje tylko potwierdzone (CONFIRMED) grupy danego dziecka — to samo
 * źródło danych co harmonogram-dziecka.php (widok dla prowadzących), tylko
 * tutaj ograniczone do własnych dzieci zalogowanego rodzica.
 */

$children = db()->prepare('SELECT * FROM children WHERE parent_id = ? ORDER BY first_name ASC');
$children->execute([$user['id']]);
$children = $children->fetchAll();

$selectedChildId = (int) ($_GET['child_id'] ?? 0);
$child = null;
foreach ($children as $c) {
    if ((int) $c['id'] === $selectedChildId) {
        $child = $c;
        break;
    }
}
if (!$child && $children) {
    $child = $children[0];
}

$groups = [];
if ($child) {
    $stmt = db()->prepare("SELECT g.*, ct.name AS ct_name, ct.key_name AS ct_key
        FROM enrollments e
        JOIN class_groups g ON g.id = e.group_id
        JOIN class_types ct ON ct.id = g.class_type_id
        WHERE e.child_id = ? AND e.status = 'CONFIRMED'
        ORDER BY g.day_of_week ASC, g.start_time ASC");
    $stmt->execute([(int) $child['id']]);
    $groups = $stmt->fetchAll();
}

// Najbliższe zajęcia (do wyróżnionego dymku) — najmniejsza liczba dni od
// dziś do dnia tygodnia danej grupy (bez sprawdzania godziny — te same
// uproszczenie co w innych miejscach kalendarza w tej aplikacji).
$nextUp = null;
$nextUpWhen = '';
if ($groups) {
    $todayIso = (int) (new DateTime('today'))->format('N');
    $bestDiff = 8;
    foreach ($groups as $g) {
        $diff = ((int) $g['day_of_week'] - $todayIso + 7) % 7;
        if ($diff < $bestDiff) {
            $bestDiff = $diff;
            $nextUp = $g;
        }
    }
    if ($nextUp) {
        $nextUpWhen = $bestDiff === 0 ? 'dziś' : ($bestDiff === 1 ? 'jutro' : 'w ' . mb_strtolower(weekday_name_iso((int) $nextUp['day_of_week'])));
    }
}

$pageTitle = 'Plan zajęć' . ($child ? ' — ' . $child['first_name'] : '') . ' — INNOVA';
$notebookTheme = true;
require __DIR__ . '/includes/layout_top.php';
?>
<div class="container-md" style="padding:40px 16px;">
  <?php include __DIR__ . '/includes/partials/panel-nav.php'; ?>

  <?php if (!$children): ?>
    <h1 style="font-size:1.6rem;">Plan zajęć</h1>
    <p class="text-muted mt-2">Dodaj najpierw dziecko, żeby zobaczyć jego plan zajęć.</p>
    <a href="<?= e(url('panel-dzieci.php')) ?>" class="nb-btn solid mt-4">Dodaj dziecko →</a>
  <?php else: ?>

    <?php if (count($children) > 1): ?>
      <div class="flex flex-wrap gap-2 mt-2" style="margin-bottom:8px;">
        <?php foreach ($children as $c): ?>
          <a href="<?= e(url('panel-plan.php?child_id=' . $c['id'])) ?>"
             class="nb-btn <?= (int) $c['id'] === (int) $child['id'] ? 'solid' : '' ?>" style="font-size:.82rem; padding:8px 16px;">
            <?= e($c['first_name']) ?>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <span class="nb-callout" style="display:inline-block; padding:6px 14px; margin:0 0 10px;">Panel rodzica</span>
    <h1 style="font-size:2.2rem;">Plan zajęć — <?= e($child['first_name']) ?></h1>
    <p class="text-muted mt-1">Tylko zajęcia, na które zapisan<?= mb_substr($child['first_name'], -1) === 'a' ? 'a jest' : 'y jest' ?> <?= e($child['first_name']) ?>.</p>

    <?php if (!$groups): ?>
      <p class="text-muted mt-6">Jeszcze żadne zajęcia nie są potwierdzone dla <?= e($child['first_name']) ?>. <a href="<?= e(url('zapisz.php')) ?>" style="color:var(--primary); text-decoration:underline;">Zgłoś zapis</a>, jeśli jeszcze tego nie zrobiłaś/eś.</p>
    <?php else: ?>
      <div class="mt-6" style="display:flex; flex-direction:column;">
        <?php foreach ($groups as $g): [$bg, $ink] = nb_pastel($g['ct_key']); ?>
          <div class="flex items-center gap-4" style="padding:16px 0; border-bottom:1px dashed var(--nb-rule,#b7cbe8);">
            <div style="width:96px; flex:none;">
              <div style="font-family:var(--nb-font-heading); font-weight:700; font-size:1.25rem; color:var(--nb-ink);"><?= e(ucfirst(weekday_name_iso((int) $g['day_of_week']))) ?></div>
              <div class="text-muted" style="font-size:.68rem; text-transform:uppercase; letter-spacing:.03em;">co tydzień</div>
            </div>
            <div class="flex items-center gap-3" style="flex:1; border-radius:14px; padding:12px 16px; background:<?= e($bg) ?>; color:<?= e($ink) ?>; box-shadow:0 10px 22px -16px rgba(74,67,38,.4);">
              <span style="width:34px; height:34px; flex:none;"><?= nb_icon_svg($g['ct_key'], '') ?></span>
              <div>
                <strong style="display:block; font-family:var(--nb-font-heading); font-weight:700; font-size:1.2rem;"><?= e($g['ct_name']) ?></strong>
                <span style="font-family:var(--nb-font-heading); font-weight:600; font-size:.98rem; opacity:.85;"><?= e($g['start_time']) ?>–<?= e($g['end_time']) ?> · z <?= e($g['instructor_name']) ?></span>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <?php if ($nextUp): ?>
        <div class="nb-callout mt-6">
          <b>✏️ Najbliżej:</b>
          <p style="margin:6px 0 0; font-size:.88rem;"><?= e($nextUp['ct_name']) ?> <?= e($nextUpWhen) ?> o <?= e($nextUp['start_time']) ?> z <?= e($nextUp['instructor_name']) ?>.</p>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
