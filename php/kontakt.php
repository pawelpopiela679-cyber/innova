<?php
require_once __DIR__ . '/includes/bootstrap.php';

$kontaktPhone = get_content('footer.phone_number', '790 250 363');
$kontaktAddress = get_content('footer.address_text', 'ul. Kolejowa, Czechowice-Dziedzice');
$kontaktEmail = get_content('footer.contact_email', 'kontakt@innova-pracownia.pl');
$fbHandle = get_content('footer.facebook_handle', 'innova.pracownia');
$igHandle = get_content('footer.instagram_handle', 'innova_pracownia');

$sent = false;
$error = null;
$nameValue = trim((string) ($_POST['name'] ?? ''));
$emailValue = trim((string) ($_POST['email'] ?? ''));
$messageValue = trim((string) ($_POST['message'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $name = mb_substr($nameValue, 0, 150);
    $email = mb_substr($emailValue, 0, 150);
    $message = mb_substr($messageValue, 0, 3000);
    $consent = isset($_POST['consent']);

    if ($name === '' || $email === '' || $message === '' || !$consent) {
        $error = 'Uzupełnij imię i nazwisko, adres e-mail, wiadomość i zaznacz zgodę.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Podaj poprawny adres e-mail.';
    } else {
        $topic = mb_substr(trim((string) ($_POST['topic'] ?? '')), 0, 200);
        if (!empty(STUDIO_NOTIFY_EMAIL)) {
            $recipients = array_map('trim', explode(',', STUDIO_NOTIFY_EMAIL));
            $subject = 'Wiadomość ze strony — ' . ($topic !== '' ? $topic : $name);
            $htmlMsg = '<p><b>Od:</b> ' . e($name) . ' (' . e($email) . ')</p>'
                . ($topic !== '' ? '<p><b>Temat:</b> ' . e($topic) . '</p>' : '')
                . '<p>' . nl2br(e($message)) . '</p>';
            $textMsg = "Od: $name ($email)\n" . ($topic !== '' ? "Temat: $topic\n" : '') . "\n$message";
            send_mail($recipients, $subject, $htmlMsg, $textMsg, $email);
        }
        $sent = true;
        $nameValue = $emailValue = $messageValue = '';
    }
}

$pageTitle = 'Kontakt — INNOVA';
$notebookTheme = true;
$notebookBare = true;
$notebookActive = 'contact';
require __DIR__ . '/includes/layout_top.php';
?>
<img class="nb-header-banner" src="<?= e(url('assets/img/headers/kontakt.png')) ?>" alt="Kontakt — masz pytania? Napisz do nas!">

<div class="nb-two-col" style="grid-template-columns: 1.1fr .9fr 1fr;">
  <div>
    <div class="nb-step-title" style="justify-content:flex-start; gap:10px;">Napisz do nas</div>
    <?php if ($sent): ?>
      <p class="nb-alert-success" style="margin-top:14px;">Dziękujemy! Wiadomość została wysłana — odpowiemy najszybciej, jak to możliwe.</p>
    <?php else: ?>
      <?php if ($error): ?><p class="nb-alert-error" style="margin-top:14px;"><?= e($error) ?></p><?php endif; ?>
      <form method="post" style="margin-top:16px; display:flex; flex-direction:column; gap:12px;">
        <?= csrf_field() ?>
        <div class="nb-field">
          <label for="name">Imię i nazwisko *</label>
          <input type="text" id="name" name="name" required value="<?= e($nameValue) ?>">
        </div>
        <div class="nb-field">
          <label for="email">Adres e-mail *</label>
          <input type="email" id="email" name="email" required value="<?= e($emailValue) ?>">
        </div>
        <div class="nb-field">
          <label for="topic">Temat</label>
          <input type="text" id="topic" name="topic" value="<?= e(trim((string) ($_POST['topic'] ?? ''))) ?>">
        </div>
        <div class="nb-field">
          <label for="message">Wiadomość *</label>
          <textarea id="message" name="message" rows="5" required><?= e($messageValue) ?></textarea>
        </div>
        <label style="display:flex; gap:8px; align-items:flex-start; font-size:.8rem; color:var(--nb-muted);">
          <input type="checkbox" name="consent" required style="margin-top:3px;">
          Wyrażam zgodę na przetwarzanie moich danych osobowych w celu odpowiedzi na wiadomość. *
        </label>
        <button type="submit" class="nb-btn solid" style="justify-content:center;">Wyślij wiadomość →</button>
      </form>
    <?php endif; ?>
  </div>
  <div>
    <div class="nb-step-title" style="justify-content:flex-start; gap:10px;">Nasze dane</div>
    <div style="margin-top:16px; display:flex; flex-direction:column; gap:12px; font-size:.92rem;">
      <div>📍 <?= e($kontaktAddress) ?></div>
      <div>✉️ <a href="mailto:<?= e($kontaktEmail) ?>" style="color:var(--nb-ink);"><?= e($kontaktEmail) ?></a></div>
      <div>📞 <a href="tel:+48<?= e(preg_replace('/[^0-9]/', '', $kontaktPhone)) ?>" style="color:var(--nb-ink);"><?= e($kontaktPhone) ?></a></div>
    </div>
    <p class="nb-step-sub" style="margin-top:18px;">Znajdź nas w social mediach</p>
    <div class="nb-social" style="justify-content:flex-start;">
      <a href="https://facebook.com/<?= e($fbHandle) ?>" style="background:#3b5998;">f</a>
      <a href="https://instagram.com/<?= e($igHandle) ?>" style="background:linear-gradient(45deg,#f58529,#dd2a7b,#8134af);">ig</a>
    </div>
  </div>
  <div>
    <div class="nb-step-title" style="justify-content:flex-start; gap:10px;">Tu nas znajdziesz</div>
    <p style="margin-top:16px; font-size:.92rem;"><?= e($kontaktAddress) ?></p>
    <a href="https://www.google.com/maps/search/?api=1&query=<?= urlencode($kontaktAddress) ?>" class="nb-btn" style="margin-top:8px;">Zobacz na mapie →</a>
  </div>
</div>
<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
