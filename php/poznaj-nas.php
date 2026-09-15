<?php
require_once __DIR__ . '/includes/bootstrap.php';

$instructors = db()->query("SELECT * FROM users WHERE role = 'INSTRUCTOR' ORDER BY name ASC")->fetchAll();

$pageTitle = 'Poznaj nas — INNOVA';
$notebookTheme = true;
$notebookActive = 'about';
require __DIR__ . '/includes/layout_top.php';
?>
<div class="text-center">
  <img src="<?= e(url('assets/img/banners/o-nas.png')) ?>" alt="O nas — INNOVA to miejsce, gdzie pasja spotyka się z edukacją." style="max-width:340px; width:100%; height:auto;">
</div>
<p class="text-center text-muted mt-4">Zespół prowadzących pracowni INNOVA.</p>

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
