<?php
require_once __DIR__ . '/includes/bootstrap.php';

$classTypes = db()->query("SELECT * FROM class_types WHERE key_name != 'OPEN_DAY' ORDER BY id ASC")->fetchAll();
$tiersStmt = db()->prepare('SELECT * FROM pricing_tiers WHERE class_type_id = ? ORDER BY sort_order ASC');

$instructors = db()->query("SELECT * FROM users WHERE role = 'INSTRUCTOR' ORDER BY name ASC")->fetchAll();
$roleStmt = db()->prepare("SELECT DISTINCT ct.name FROM class_sessions cs JOIN class_types ct ON ct.id = cs.class_type_id WHERE cs.instructor_id = ?");

$pageTitle = 'Zajęcia i cennik — INNOVA';
$notebookTheme = true;
$notebookActive = 'classes';
require __DIR__ . '/includes/layout_top.php';
?>
<svg class="nb-doodle" style="left:-44px; top:-6px; width:40px; height:34px; transform:rotate(-25deg);" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20l8-8-3-3-8 8v3h3zM17 5l2 2"/></svg>

<h1 class="nb-section-title" style="text-align:left; max-width:640px; font-size:1.7rem;">Zajęcia, w których dzieci i młodzież odkrywają, zdobywają nowe umiejętności i świetnie się bawią!</h1>
<p class="text-muted" style="max-width:640px;">Zajęcia odbywają się 1x w tygodniu, w małych grupach (maks. 10 dzieci). Pełny terminarz i wolne miejsca znajdziesz w <a href="<?= e(url('kalendarz.php')) ?>" style="color:var(--nb-coral); text-decoration:underline;">grafiku</a>.</p>
<div class="nb-cta-row">
  <a href="#cennik" class="nb-btn solid uppercase big">Zobacz cennik →</a>
  <a href="<?= e(url('rejestracja.php')) ?>" class="nb-btn uppercase big">Zapisz się na zajęcia →</a>
</div>

<div class="nb-two-col">
  <div>
    <div class="nb-step-title">1. Wybierz zajęcia <span class="nb-box"></span></div>
    <div class="nb-step-sub">Zaznacz to, co Cię interesuje!</div>
    <?php foreach ($classTypes as $ct): [$bg, $ink] = nb_pastel($ct['key_name']); ?>
      <label class="nb-class-row">
        <span class="nb-bubble" style="background:<?= e($bg) ?>;"><?= nb_icon_svg($ct['key_name'], '') ?></span>
        <span class="nb-txt">
          <b style="color:<?= e($ink) ?>;"><?= e($ct['name']) ?></b>
          <span><?= e($ct['description']) ?></span>
        </span>
        <input type="checkbox">
      </label>
    <?php endforeach; ?>
    <div class="nb-more-hint">↖ Możesz wybrać więcej <a href="#cennik" class="nb-plus-pill">+</a></div>
  </div>
  <div>
    <div class="nb-step-title">2. Poznaj prowadzących <span class="nb-box"></span></div>
    <div class="nb-step-sub">Nasi prowadzący z doświadczeniem i pasją</div>
    <?php if (!$instructors): ?>
      <p class="text-muted">Wkrótce pojawi się tu zespół prowadzących.</p>
    <?php endif; ?>
    <?php foreach ($instructors as $u):
        $roleStmt->execute([$u['id']]);
        $taught = array_column($roleStmt->fetchAll(), 'name');
        $roleLine = $taught ? implode(', ', $taught) : ($u['bio'] ? mb_strimwidth($u['bio'], 0, 50, '…') : 'Prowadząca/y zajęć');
        $avatarBg = '#' . substr(md5($u['name']), 0, 6);
    ?>
      <a href="<?= e(url('poznaj-nas.php')) ?>" class="nb-staff-row">
        <?php if ($u['avatar_url']): ?>
          <img class="nb-avatar" src="<?= e(url($u['avatar_url'])) ?>" alt="<?= e($u['name']) ?>">
        <?php else: ?>
          <span class="nb-avatar" style="background:<?= e($avatarBg) ?>;"><?= e(mb_substr($u['name'], 0, 1)) ?></span>
        <?php endif; ?>
        <span><b><?= e($u['name']) ?></b><span><?= e($roleLine) ?></span></span>
      </a>
    <?php endforeach; ?>
  </div>
</div>

<div class="nb-section" id="cennik" style="border-top:2px dashed var(--nb-rule); padding-top:26px;">
  <h2 class="nb-section-title" style="font-size:1.5rem;">💰 Cennik</h2>
  <div style="display:flex; flex-direction:column; gap:24px;">
    <?php foreach ($classTypes as $ct):
        [$bg, $ink] = nb_pastel($ct['key_name']);
        $tiersStmt->execute([$ct['id']]);
        $tiers = $tiersStmt->fetchAll();
        $hasLabels = array_filter($tiers, fn($t) => $t['label'] !== '');
    ?>
      <section id="cennik-<?= e($ct['key_name']) ?>" class="nb-card" style="background:<?= e($bg) ?>; cursor:default;">
        <div class="flex items-center gap-3">
          <?= nb_icon_svg($ct['key_name']) ?>
          <h2 style="font-family:'Baloo 2','Nunito',sans-serif; font-size:1.2rem; color:<?= e($ink) ?>; margin:0;"><?= e($ct['name']) ?></h2>
        </div>
        <p class="mt-2"><?= e($ct['description']) ?></p>
        <?php if ($tiers): ?>
        <div class="mt-4" style="overflow-x:auto;">
          <table style="background:var(--nb-surface, #fff); border-radius:10px; overflow:hidden;">
            <thead><tr>
              <?php if ($hasLabels): ?><th>Wariant</th><?php endif; ?>
              <th>Wiek</th><th>Czas</th><th>Cena</th>
            </tr></thead>
            <tbody>
              <?php foreach ($tiers as $t): ?>
                <tr>
                  <?php if ($hasLabels): ?><td><?= e($t['label'] ?: $ct['name']) ?></td><?php endif; ?>
                  <td class="text-muted"><?= e($t['age_label']) ?></td>
                  <td class="text-muted"><?= (int) $t['duration_min'] ?> min</td>
                  <td style="font-weight:700; color:<?= e($ink) ?>;">
                    <?= (int) $t['price_monthly'] ?> zł / mies.
                    <?php if ($t['one_time_fee'] !== null): ?>
                      <span class="text-muted" style="font-weight:400;"> (+ <?= (int) $t['one_time_fee'] ?> zł pakiet startowy, jednorazowo)</span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>
        <p class="text-muted mt-4">Wiek uczestników: <?= (int) $ct['age_min'] ?>–<?= (int) $ct['age_max'] ?> lat</p>
        <a href="<?= e(url('kalendarz.php?classType=' . $ct['id'])) ?>" class="nb-btn solid">Zobacz terminy →</a>
      </section>
    <?php endforeach; ?>
  </div>
  <p class="text-center text-muted mt-8"><strong style="color:var(--nb-ink);">Zniżki:</strong> rodzeństwo −15% · Karta Dużej Rodziny −10%</p>
</div>
<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
