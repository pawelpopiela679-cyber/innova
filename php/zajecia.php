<?php
require_once __DIR__ . '/includes/bootstrap.php';

$classTypes = db()->query("SELECT * FROM class_types WHERE key_name != 'OPEN_DAY' ORDER BY id ASC")->fetchAll();
$zajeciaFotoMap = ['ENGLISH', 'THEATER', 'ROBOTICS', 'CREATIVE', 'MATH', 'SCIENCE'];

$pageTitle = 'Zajęcia — INNOVA';
$notebookTheme = true;
$notebookBare = true;
$notebookActive = 'classes';
require __DIR__ . '/includes/layout_top.php';
?>
<img class="nb-header-banner" src="<?= e(url('assets/img/headers/zajecia.png')) ?>" alt="Zajęcia — twórcze, rozwijające i pełne dobrej energii">

<div class="nb-cards" style="grid-template-columns: repeat(3, 1fr);">
  <?php foreach ($classTypes as $ct): [$bg, $ink] = nb_pastel($ct['key_name']);
    $foto = in_array($ct['key_name'], $zajeciaFotoMap, true) ? url('assets/img/zajecia-foto/' . $ct['key_name'] . '.png') : null;
  ?>
    <a href="<?= e(signup_url()) ?>" class="nb-card" style="background:<?= e($bg) ?>;">
      <div class="flex items-center gap-2">
        <?= nb_icon_svg($ct['key_name']) ?>
        <h3 style="color:<?= e($ink) ?>; margin:0;"><?= e($ct['name']) ?></h3>
      </div>
      <p class="mt-2"><?= e($ct['description']) ?></p>
      <?php if ($foto): ?>
        <div class="nb-photo-frame" style="max-width:170px; margin:10px 0;"><img src="<?= e($foto) ?>" alt="<?= e($ct['name']) ?>"></div>
      <?php endif; ?>
      <span class="nb-more" style="color:<?= e($ink) ?>;">Zobacz szczegóły →</span>
    </a>
  <?php endforeach; ?>
</div>
<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
