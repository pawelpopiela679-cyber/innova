<?php
/** Wymaga zalogowanego $user (ADMIN lub INSTRUCTOR) w zmiennej globalnej z require_staff(). */
$pendingCount = (int) db()->query("SELECT COUNT(*) c FROM enrollments WHERE status = 'PENDING'")->fetch()['c'];
$isMasterAdmin = $user['role'] === 'ADMIN';
?>
<nav class="mt-2" style="margin-bottom:32px; display:flex; flex-wrap:wrap; gap:8px; border-radius:999px; border:1px solid var(--border); background:var(--surface); padding:4px; width:fit-content; font-size:0.9rem;">
  <a href="<?= e(url('admin-zapisy.php')) ?>" class="flex items-center gap-2" style="border-radius:999px; padding:6px 16px;">
    Zgłoszenia
    <?php if ($pendingCount > 0): ?><span class="badge-nav"><?= $pendingCount ?></span><?php endif; ?>
  </a>
  <a href="<?= e(url('admin.php')) ?>" style="border-radius:999px; padding:6px 16px;">Dostępność terminów</a>
  <a href="<?= e(url('admin-zajecia-nowe.php')) ?>" style="border-radius:999px; padding:6px 16px;">+ Nowe zajęcia</a>
  <a href="<?= e(url('admin-profil.php')) ?>" style="border-radius:999px; padding:6px 16px;">Mój profil</a>
  <?php if ($isMasterAdmin): ?>
    <a href="<?= e(url('admin-prowadzacy.php')) ?>" style="border-radius:999px; padding:6px 16px;">Prowadzący</a>
    <a href="<?= e(url('admin-cennik.php')) ?>" style="border-radius:999px; padding:6px 16px;">Cennik</a>
    <a href="<?= e(url('admin-strony.php')) ?>" style="border-radius:999px; padding:6px 16px;">Strony</a>
    <a href="<?= e(url('admin-wyglad.php')) ?>" style="border-radius:999px; padding:6px 16px;">Wygląd</a>
  <?php endif; ?>
</nav>
