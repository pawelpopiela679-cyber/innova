<?php
/** Wymaga zalogowanego $user (ADMIN lub INSTRUCTOR) w zmiennej globalnej z require_staff(). */
$isMasterAdmin = $user['role'] === 'ADMIN';
$canManageGroups = user_can_manage_groups($user);
$poolCount = $canManageGroups
    ? (int) db()->query("SELECT COUNT(*) c FROM enrollments WHERE status = 'PENDING' AND group_id IS NULL")->fetch()['c']
    : 0;
?>
<nav class="mt-2" style="margin-bottom:32px; display:flex; flex-wrap:wrap; gap:8px; border-radius:999px; border:1px solid var(--border); background:var(--surface); padding:4px; width:fit-content; font-size:0.9rem;">
  <a href="<?= e(url('admin.php')) ?>" style="border-radius:999px; padding:6px 16px;">Zaplanowane zajęcia</a>
  <a href="<?= e(url('admin-zajecia-nowe.php')) ?>" style="border-radius:999px; padding:6px 16px;">+ Nowa grupa</a>
  <?php if ($canManageGroups): ?>
    <a href="<?= e(url('admin-grupy.php')) ?>" class="flex items-center gap-2" style="border-radius:999px; padding:6px 16px;">
      Grupy
      <?php if ($poolCount > 0): ?><span class="badge-nav"><?= $poolCount ?></span><?php endif; ?>
    </a>
  <?php endif; ?>
  <a href="<?= e(url('admin-profil.php')) ?>" style="border-radius:999px; padding:6px 16px;">Mój profil</a>
  <?php if ($isMasterAdmin): ?>
    <a href="<?= e(url('admin-prowadzacy.php')) ?>" style="border-radius:999px; padding:6px 16px;">Prowadzący</a>
    <a href="<?= e(url('admin-cennik.php')) ?>" style="border-radius:999px; padding:6px 16px;">Cennik</a>
    <a href="<?= e(url('admin-strony.php')) ?>" style="border-radius:999px; padding:6px 16px;">Strony</a>
    <a href="<?= e(url('admin-wyglad.php')) ?>" style="border-radius:999px; padding:6px 16px;">Wygląd</a>
  <?php endif; ?>
</nav>
