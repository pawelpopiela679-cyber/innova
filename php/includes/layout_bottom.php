</main>
<?php if (!empty($notebookTheme)): ?>
  </div><!-- .notebook -->
</div><!-- .notebook-wrap -->
<?php endif; ?>
<footer class="site-footer">
  <div style="margin-bottom:16px; display:flex; justify-content:center;"><?= render_logo('sm') ?></div>
  <?php
    $footerPhone = get_content('footer.phone_number', '790 250 363');
    $footerPhoneDigits = preg_replace('/[^0-9]/', '', $footerPhone);
    $footerFb = get_content('footer.facebook_handle', 'innova.pracownia');
    $footerIg = get_content('footer.instagram_handle', 'innova_pracownia');
  ?>
  <div class="footer-links">
    <span>📍 <?= e(get_content('footer.address_text', 'ul. Kolejowa, Czechowice-Dziedzice')) ?></span>
    <a href="tel:+48<?= e($footerPhoneDigits) ?>">📞 <?= e($footerPhone) ?></a>
    <a href="https://facebook.com/<?= e($footerFb) ?>">📘 fb /<?= e($footerFb) ?></a>
    <a href="https://instagram.com/<?= e($footerIg) ?>">📷 ig /<?= e($footerIg) ?></a>
    <a href="https://innova-pracownia.pl">🌐 www.innova-pracownia.pl</a>
  </div>
  <p class="text-muted mt-4">© <?= date('Y') ?> INNOVA — Pracownia kreatywno-edukacyjna</p>
  <p class="text-muted" style="font-size:0.8rem;">🌱 Odwiedzin strony: <?= number_format((int) ($visitCount ?? 0), 0, ',', ' ') ?></p>
  <p class="text-muted" style="font-size:0.65rem; opacity:0.5;">wersja plików: <?= e(defined('APP_BUILD') ? APP_BUILD : '?') ?></p>
</footer>
</body>
</html>
