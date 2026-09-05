<?php
require_once __DIR__ . '/includes/bootstrap.php';
$user = require_admin();

/**
 * Diagnostyka wysyłki e-maili — bo próba wysyłki z normalnego formularza
 * (zapisz.php) NIE pokazuje błędu SMTP użytkownikowi: send_mail() łapie
 * wyjątek i tylko zapisuje go do dziennika błędów PHP serwera (do którego
 * właścicielka pracowni raczej nie ma wglądu). Ta strona wysyła TEN SAM
 * mechanizm (smtp_send) wprost, bez łapania wyjątku — więc jeśli coś jest
 * nie tak (złe hasło, zły host, port zablokowany przez hosting), zobaczysz
 * tu dokładny komunikat błędu zamiast zgadywać.
 */

$result = null;
$resultOk = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $to = trim($_POST['to'] ?? '') ?: $user['email'];
    try {
        if (empty(SMTP_HOST)) {
            throw new RuntimeException('SMTP_HOST jest puste w config.local.php — e-maile NIE są wysyłane, tylko zapisują się do pliku php/storage/mail.log. Uzupełnij dane SMTP w config.local.php, żeby wysyłka działała naprawdę.');
        }
        smtp_send(
            [$to],
            'Test wysyłki e-mail — INNOVA',
            '<p>To jest testowa wiadomość wysłana z panelu diagnostyki (admin-test-email.php).</p><p>Jeśli to czytasz — wysyłka SMTP działa poprawnie. 🎉</p>',
            "To jest testowa wiadomość wysłana z panelu diagnostyki (admin-test-email.php).\n\nJeśli to czytasz — wysyłka SMTP działa poprawnie."
        );
        $result = "Wysłano pomyślnie na adres: $to. Sprawdź skrzynkę (i folder SPAM) w ciągu kilku minut.";
        $resultOk = true;
    } catch (Throwable $e) {
        $result = $e->getMessage();
    }
}

$pageTitle = 'Test e-maila — INNOVA';
$notebookTheme = true;
require __DIR__ . '/includes/layout_top.php';
?>
<div class="container" style="padding:40px 16px; max-width:640px;">
  <?php include __DIR__ . '/includes/partials/admin-nav.php'; ?>

  <h1 style="font-size:1.6rem;">Test wysyłki e-mail</h1>
  <p class="text-muted mt-2">
    Ta strona wysyła prawdziwy testowy e-mail przez to samo połączenie SMTP, którego używa cała
    strona — i, w odróżnieniu od zwykłych zgłoszeń, pokazuje dokładny komunikat błędu, jeśli
    wysyłka się nie uda. Użyj jej, żeby sprawdzić, dlaczego e-maile nie przychodzą.
  </p>

  <div class="card mt-4">
    <h2 style="font-size:1rem;">Aktualna konfiguracja (z config.local.php)</h2>
    <table style="margin-top:10px; font-size:0.88rem; border-collapse:collapse;">
      <tr><td style="padding:3px 12px 3px 0; color:#777;">SMTP_HOST</td><td><code><?= e(SMTP_HOST !== '' ? SMTP_HOST : '(puste)') ?></code></td></tr>
      <tr><td style="padding:3px 12px 3px 0; color:#777;">SMTP_PORT</td><td><code><?= e((string) SMTP_PORT) ?></code></td></tr>
      <tr><td style="padding:3px 12px 3px 0; color:#777;">SMTP_USER</td><td><code><?= e(SMTP_USER !== '' ? SMTP_USER : '(puste)') ?></code></td></tr>
      <tr><td style="padding:3px 12px 3px 0; color:#777;">SMTP_FROM_EMAIL</td><td><code><?= e(SMTP_FROM_EMAIL) ?></code></td></tr>
      <tr><td style="padding:3px 12px 3px 0; color:#777;">STUDIO_NOTIFY_EMAIL</td><td><code><?= e(STUDIO_NOTIFY_EMAIL !== '' ? STUDIO_NOTIFY_EMAIL : '(puste)') ?></code></td></tr>
    </table>
    <?php if (empty(SMTP_HOST)): ?>
      <p class="alert alert-error mt-3">SMTP_HOST jest puste — e-maile na pewno nie są wysyłane, tylko lądują w <code>php/storage/mail.log</code>. To najpierw trzeba uzupełnić w <code>config.local.php</code> na serwerze.</p>
    <?php endif; ?>
    <?php if (SMTP_USER !== '' && strtolower(trim(SMTP_USER)) !== strtolower(trim(SMTP_FROM_EMAIL))): ?>
      <p class="alert alert-warning mt-3">SMTP_USER (<?= e(SMTP_USER) ?>) różni się od SMTP_FROM_EMAIL (<?= e(SMTP_FROM_EMAIL) ?>). Wiele serwerów pocztowych (w tym home.pl) odrzuca albo oznacza jako spam wiadomości, gdzie adres nadawcy nie zgadza się z kontem, którym się logujesz — najbezpieczniej ustawić oba na ten sam adres.</p>
    <?php endif; ?>
  </div>

  <?php if ($result): ?>
    <p class="alert <?= $resultOk ? 'alert-success' : 'alert-error' ?> mt-4" style="white-space:pre-wrap;"><?= e($result) ?></p>
  <?php endif; ?>

  <div class="card mt-4">
    <form method="post">
      <?= csrf_field() ?>
      <div class="field mt-2">
        <label for="to">Wyślij testowy e-mail na adres</label>
        <input type="email" id="to" name="to" value="<?= e($user['email']) ?>" required>
      </div>
      <button type="submit" class="btn btn-primary mt-3">Wyślij test</button>
    </form>
  </div>

  <p class="text-muted mt-4" style="font-size:0.85rem;">
    Jeśli wysyłka się nie uda, komunikat błędu powyżej pokaże dokładny powód (złe hasło/login,
    zły host albo port, serwer odrzucił połączenie itp.) — prześlij mi ten komunikat, a powiem
    Ci, co dokładnie poprawić w <code>config.local.php</code>.
  </p>
</div>
<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
