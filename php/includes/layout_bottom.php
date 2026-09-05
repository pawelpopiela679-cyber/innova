</main>
<?php if (!empty($notebookTheme)): ?>
  </div><!-- .notebook -->
</div><!-- .notebook-wrap -->
<?php endif; ?>
<footer class="site-footer">
  <div style="margin-bottom:16px; display:flex; justify-content:center;"><?= render_logo('sm') ?></div>
  <div class="footer-links">
    <span>📍 ul. Kolejowa, Czechowice-Dziedzice</span>
    <a href="tel:+48790250363">📞 790 250 363</a>
    <a href="https://facebook.com/innova.pracownia">📘 fb /innova.pracownia</a>
    <a href="https://instagram.com/innova_pracownia">📷 ig /innova_pracownia</a>
    <a href="https://innova-pracownia.pl">🌐 www.innova-pracownia.pl</a>
  </div>
  <p class="text-muted mt-4">© <?= date('Y') ?> INNOVA — Pracownia kreatywno-edukacyjna</p>
  <p class="text-muted" style="font-size:0.65rem; opacity:0.5;">wersja plików: <?= e(defined('APP_BUILD') ? APP_BUILD : '?') ?></p>
</footer>
</body>
</html>
