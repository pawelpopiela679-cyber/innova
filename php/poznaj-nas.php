<?php
require_once __DIR__ . '/includes/bootstrap.php';

$instructors = db()->query("SELECT * FROM users WHERE role = 'INSTRUCTOR' ORDER BY name ASC")->fetchAll();

$pageTitle = 'Poznaj nas — INNOVA';
require __DIR__ . '/includes/layout_top.php';
?>
<div class="container-md" style="padding:48px 16px;">
  <h1 class="text-center" style="font-size:1.8rem;">Poznaj nas</h1>
  <p class="text-center text-muted mt-4">Zespół prowadzących pracowni INNOVA.</p>

  <?php if (!$instructors): ?>
    <p class="text-center text-muted mt-8">Wkrótce pojawi się tu zespół prowadzących.</p>
  <?php else: ?>
    <div class="grid grid-2 mt-8">
      <?php foreach ($instructors as $u): ?>
        <div class="card text-center">
          <?php if ($u['avatar_url']): ?>
            <img src="<?= e(url($u['avatar_url'])) ?>" alt="<?= e($u['name']) ?>" class="avatar" style="width:96px; height:96px; margin:0 auto;">
          <?php else: ?>
            <div class="avatar-placeholder" style="width:96px; height:96px; margin:0 auto; font-size:2rem;"><?= e(mb_substr($u['name'], 0, 1)) ?></div>
          <?php endif; ?>
          <h3 class="mt-4"><?= e($u['name']) ?></h3>
          <?php if ($u['bio']): ?><p class="text-muted mt-2"><?= e($u['bio']) ?></p><?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
