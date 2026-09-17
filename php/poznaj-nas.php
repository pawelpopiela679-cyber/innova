<?php
require_once __DIR__ . '/includes/bootstrap.php';

$instructors = db()->query("SELECT * FROM users WHERE role = 'INSTRUCTOR' ORDER BY name ASC")->fetchAll();

$pageTitle = 'Poznaj nas — INNOVA';
$notebookTheme = true;
$notebookBare = true;
$notebookActive = 'about';
require __DIR__ . '/includes/layout_top.php';
?>
<img class="nb-header-banner" src="<?= e(url('assets/img/headers/o-nas.png')) ?>" alt="O nas — tworzymy przestrzeń, w której dzieci i młodzież odkrywają swoje talenty">

<div class="nb-why-grid" style="grid-template-columns: repeat(3, 1fr); margin-bottom:40px;">
  <div class="nb-card" style="background:#cfe6f7; cursor:default;">
    <h3 style="margin:0 0 8px;">🎯 Nasza misja</h3>
    <p><?= nl2br(e(get_content('about.mission', 'Inspirujemy, wspieramy i dajemy narzędzia dzieciom i młodzieży, aby mogły odkrywać swoje pasje, rozwijać umiejętności i z odwagą sięgać po więcej.'))) ?></p>
  </div>
  <div class="nb-card" style="background:#faedc4; cursor:default;">
    <h3 style="margin:0 0 8px;">💎 Nasze wartości</h3>
    <ul style="margin:0; padding-left:18px; font-size:.86rem; line-height:1.7;">
      <li>Szacunek do każdego dziecka</li>
      <li>Kreatywność w działaniu</li>
      <li>Współpraca i otwartość</li>
      <li>Rozwój przez doświadczenie</li>
      <li>Przyjazna atmosfera</li>
    </ul>
  </div>
  <div class="nb-card" style="background:#f7d9e6; cursor:default;">
    <h3 style="margin:0 0 8px;">🏠 Nasza przestrzeń</h3>
    <p><?= nl2br(e(get_content('about.space', 'INNOVA to przytulne, twórcze miejsce, w którym dzieci i młodzież mogą czuć się swobodnie, rozwijać swoje pomysły i spędzać czas w inspirującym otoczeniu.'))) ?></p>
  </div>
</div>

<p class="text-center text-muted">Poznaj nasz zespół — ludzie, którzy tworzą INNOVA.</p>

<?php if (!$instructors): ?>
  <p class="text-center text-muted mt-8">Wkrótce pojawi się tu zespół prowadzących.</p>
<?php else: ?>
  <?php
    // Karteczki samoprzylepne — kolor i obrót cyklicznie z ustalonej palety
    // (te same barwy co reszta motywu zeszytu), żeby wyglądały jak naprawdę
    // poprzyklejane na tablicy, a nie idealnie równym rzędem.
    $stickyColors = ['#fff3c4', '#dcebd6', '#cfe6f7', '#f7d9e6', '#f0e2c9', '#d3f0df'];
    $stickyRotations = [-3, 2, -1.5, 3, -2, 1.5];
  ?>
  <div class="nb-sticky-board mt-8">
    <?php foreach ($instructors as $i => $u): $avatarBg = '#' . substr(md5($u['name']), 0, 6); ?>
      <div class="sticky-note" style="background:<?= e($stickyColors[$i % count($stickyColors)]) ?>; transform:rotate(<?= $stickyRotations[$i % count($stickyRotations)] ?>deg);">
        <div class="pin"></div>
        <?php if ($u['avatar_url']): ?>
          <img src="<?= e(url($u['avatar_url'])) ?>" alt="<?= e($u['name']) ?>" class="avatar" style="border-radius:50%; object-fit:cover;">
        <?php else: ?>
          <div class="avatar-placeholder" style="border-radius:50%; background:<?= e($avatarBg) ?>; color:#fff; display:flex; align-items:center; justify-content:center; font-family:var(--nb-font-heading); font-weight:700; font-size:2.1rem;"><?= e(mb_substr($u['name'], 0, 1)) ?></div>
        <?php endif; ?>
        <h3><?= e($u['name']) ?></h3>
        <?php if ($u['bio']): ?><p><?= e($u['bio']) ?></p><?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
