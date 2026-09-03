<?php
require_once __DIR__ . '/includes/bootstrap.php';

$instructors = db()->query("SELECT * FROM users WHERE role = 'INSTRUCTOR' ORDER BY name ASC")->fetchAll();

$pageTitle = 'Poznaj nas — INNOVA';
$notebookTheme = true;
$notebookActive = 'about';
require __DIR__ . '/includes/layout_top.php';
?>
<h1 class="nb-section-title" style="font-size:1.8rem;">Poznaj nas</h1>
<p class="text-center text-muted mt-2">Zespół prowadzących pracowni INNOVA.</p>

<?php if (!$instructors): ?>
  <p class="text-center text-muted mt-8">Wkrótce pojawi się tu zespół prowadzących.</p>
<?php else: ?>
  <div class="nb-cards mt-8" style="grid-template-columns:repeat(2,1fr);">
    <?php foreach ($instructors as $u): $avatarBg = '#' . substr(md5($u['name']), 0, 6); ?>
      <div class="nb-card" style="background:var(--nb-surface); text-align:center; cursor:default;">
        <?php if ($u['avatar_url']): ?>
          <img src="<?= e(url($u['avatar_url'])) ?>" alt="<?= e($u['name']) ?>" class="avatar" style="width:96px; height:96px; margin:0 auto;">
        <?php else: ?>
          <div style="width:96px; height:96px; margin:0 auto; border-radius:50%; background:<?= e($avatarBg) ?>; color:#fff; display:flex; align-items:center; justify-content:center; font-family:'Baloo 2','Nunito',sans-serif; font-weight:700; font-size:2rem;"><?= e(mb_substr($u['name'], 0, 1)) ?></div>
        <?php endif; ?>
        <h3 class="mt-4"><?= e($u['name']) ?></h3>
        <?php if ($u['bio']): ?><p class="text-muted mt-2"><?= e($u['bio']) ?></p><?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
