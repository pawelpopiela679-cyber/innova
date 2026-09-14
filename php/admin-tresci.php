<?php
require_once __DIR__ . '/includes/bootstrap.php';
$user = require_content_manager();

/**
 * Panel edycji tekstów widocznych na stronie głównej i w stopce — pierwszy
 * krok w stronę "każdy element strony edytowalny z panelu" (zdjęcia,
 * treści innych stron/cennika i graficzny edytor grafiku zajęć to kolejne,
 * osobne etapy — patrz rozmowa z właścicielką pracowni z 2026-09-14).
 *
 * Każde pole ma klucz w tabeli site_content (patrz get_content()/set_content()
 * w includes/helpers.php) i domyślną wartość używaną, dopóki nikt niczego
 * tu nie zmieni — więc świeża instalacja wygląda tak samo jak dotychczas.
 */
$fields = [
    'Strona główna — nagłówek' => [
        ['key' => 'home.tagline', 'label' => 'Hasło pod tytułem', 'type' => 'textarea', 'default' => "Rozwijamy pasje. Odkrywamy talenty.\nTworzymy przyszłość!", 'hint' => 'Każda linijka to osobny wiersz na stronie.'],
        ['key' => 'home.lead', 'label' => 'Akapit powitalny', 'type' => 'textarea', 'default' => 'Zgłoś dziecko na zajęcia w kilka minut: sprawdź kalendarz, wybierz termin, a my dobierzemy odpowiednią grupę i potwierdzimy zapis e-mailem. Każde dziecko jest wyjątkowe — pomagamy mu rozkwitać.'],
        ['key' => 'home.cta_offer_label', 'label' => 'Przycisk — oferta', 'type' => 'text', 'default' => 'Poznaj ofertę →'],
        ['key' => 'home.cta_signup_label', 'label' => 'Przycisk — zapisy', 'type' => 'text', 'default' => 'Zapisz dziecko →'],
    ],
    'Strona główna — daty' => [
        ['key' => 'home.open_day_date', 'label' => 'Dzień otwarty', 'type' => 'date', 'default' => OPEN_DAY_DATE],
        ['key' => 'home.semester_start_date', 'label' => 'Start zajęć', 'type' => 'date', 'default' => SEMESTER_START],
    ],
    'Stopka (widoczna na każdej stronie)' => [
        ['key' => 'footer.address_text', 'label' => 'Adres', 'type' => 'text', 'default' => 'ul. Kolejowa, Czechowice-Dziedzice'],
        ['key' => 'footer.phone_number', 'label' => 'Telefon', 'type' => 'text', 'default' => '790 250 363', 'hint' => 'Same cyfry i spacje — link "zadzwoń" zbuduje się automatycznie.'],
        ['key' => 'footer.facebook_handle', 'label' => 'Facebook (nazwa profilu)', 'type' => 'text', 'default' => 'innova.pracownia', 'hint' => 'Bez facebook.com/ — sama końcówka adresu.'],
        ['key' => 'footer.instagram_handle', 'label' => 'Instagram (nazwa profilu)', 'type' => 'text', 'default' => 'innova_pracownia', 'hint' => 'Bez instagram.com/ — sama końcówka adresu.'],
    ],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    // Uwaga: pola nazywają się "content[klucz]", NIE "klucz" wprost — PHP przy
    // odbiorze automatycznie zamienia kropki w nazwach pól na podkreślenia
    // (np. "home.cta_offer_label" stałoby się "home_cta_offer_label"), więc
    // klucze z kropką trzeba schować w zagnieżdżonej tablicy, której PHP nie
    // dotyka.
    $posted = $_POST['content'] ?? [];
    foreach ($fields as $group) {
        foreach ($group as $f) {
            $val = trim((string) ($posted[$f['key']] ?? ''));
            if ($f['type'] === 'date' && $val !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $val)) {
                redirect_with('admin-tresci.php', ['error' => 'Nieprawidłowa data: ' . $f['label']]);
            }
            set_content($f['key'], $val);
        }
    }
    redirect('admin-tresci.php?saved=1');
}

$pageTitle = 'Treści strony — INNOVA';
$notebookTheme = true;
$notebookActive = '';
require __DIR__ . '/includes/layout_top.php';
?>
<div class="container" style="padding:40px 16px; max-width:760px;">
  <?php include __DIR__ . '/includes/partials/admin-nav.php'; ?>

  <h1 style="font-size:1.6rem;">Treści strony</h1>
  <p class="text-muted mt-2">
    Edytuj teksty widoczne na stronie głównej i w stopce — zmiana zapisuje się od razu po kliknięciu
    "Zapisz" i jest widoczna dla wszystkich odwiedzających. Zdjęcia/grafiki, treści innych podstron
    (w tym cennika) i graficzny edytor grafiku zajęć to kolejne etapy tej samej pracy.
  </p>

  <?php if (isset($_GET['saved'])): ?><p class="alert alert-success mt-4">Zapisano zmiany.</p><?php endif; ?>
  <?php if (isset($_GET['error'])): ?><p class="alert alert-error mt-4"><?= e($_GET['error']) ?></p><?php endif; ?>

  <form method="post">
    <?= csrf_field() ?>
    <?php foreach ($fields as $groupTitle => $group): ?>
      <div class="card mt-4">
        <h2 style="font-size:1rem;"><?= e($groupTitle) ?></h2>
        <?php foreach ($group as $f): ?>
          <div class="field mt-3">
            <label for="<?= e($f['key']) ?>"><?= e($f['label']) ?></label>
            <?php $current = get_content($f['key'], $f['default']); ?>
            <?php if ($f['type'] === 'textarea'): ?>
              <textarea id="<?= e($f['key']) ?>" name="content[<?= e($f['key']) ?>]" rows="3"><?= e($current) ?></textarea>
            <?php else: ?>
              <input type="<?= $f['type'] === 'date' ? 'date' : 'text' ?>" id="<?= e($f['key']) ?>" name="content[<?= e($f['key']) ?>]" value="<?= e($current) ?>">
            <?php endif; ?>
            <?php if (!empty($f['hint'])): ?><p class="text-muted mt-1" style="font-size:0.8rem;"><?= e($f['hint']) ?></p><?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endforeach; ?>
    <button type="submit" class="btn btn-primary mt-4">Zapisz</button>
  </form>
</div>
<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
