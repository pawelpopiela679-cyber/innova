<?php /** Wymaga: $activePanel (opcjonalnie: string identyfikujący aktywną zakładkę). */ ?>
<nav style="margin-bottom:32px; display:inline-flex; flex-wrap:wrap; gap:8px; border-radius:999px; border:1px solid var(--border); background:var(--surface); padding:4px; font-size:0.9rem;">
  <a href="<?= e(url('panel.php')) ?>" style="border-radius:999px; padding:6px 16px;">Przegląd</a>
  <a href="<?= e(url('panel-dzieci.php')) ?>" style="border-radius:999px; padding:6px 16px;">Moje dzieci</a>
  <a href="<?= e(url('panel-zapisy.php')) ?>" style="border-radius:999px; padding:6px 16px;">Moje zapisy</a>
  <a href="<?= e(url('kalendarz.php')) ?>" style="border-radius:999px; padding:6px 16px;">Kalendarz zajęć</a>
  <a href="<?= e(url('panel-konto.php')) ?>" style="border-radius:999px; padding:6px 16px;">Moje dane</a>
</nav>
