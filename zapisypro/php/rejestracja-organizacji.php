<?php
/**
 * Samoobsługowa rejestracja NOWEJ organizacji (klienta SaaS-a) — zakłada
 * organizację w statusie TRIAL na wybranym planie i pierwsze konto ORG_ADMIN.
 * To jest "sprzedaż w modelu subskrypcji": po założeniu Ty (super-admin)
 * widzisz nową organizację w /superadmin.php i możesz ją aktywować po
 * otrzymaniu płatności (patrz README_PHP.md — płatności online to TODO).
 */
require_once __DIR__ . '/includes/bootstrap.php';

$plans = db()->query('SELECT * FROM subscription_plans ORDER BY sort_order ASC')->fetchAll();
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $orgName = trim((string) ($_POST['org_name'] ?? ''));
    $planId = (int) ($_POST['plan_id'] ?? 0);
    $name = trim((string) ($_POST['name'] ?? ''));
    $email = trim(strtolower((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');

    $planIds = array_column($plans, 'id');
    if (mb_strlen($orgName) < 2) {
        $error = 'Podaj nazwę organizacji (np. nazwę szkółki/klubu).';
    } elseif (!in_array($planId, $planIds, true)) {
        $error = 'Wybierz plan subskrypcji.';
    } elseif (mb_strlen($name) < 2) {
        $error = 'Podaj imię i nazwisko.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Podaj poprawny adres e-mail.';
    } elseif (mb_strlen($password) < 8) {
        $error = 'Hasło musi mieć co najmniej 8 znaków.';
    } else {
        $exists = db()->prepare('SELECT id FROM users WHERE email = ?');
        $exists->execute([$email]);
        if ($exists->fetch()) {
            $error = 'Konto z tym adresem e-mail już istnieje.';
        } else {
            $slugBase = slugify($orgName);
            $slug = $slugBase;
            $n = 1;
            while (find_org_by_slug($slug)) {
                $slug = $slugBase . '-' . (++$n);
            }
            $trialEnds = (new DateTime('+' . TRIAL_DAYS . ' days'))->format('Y-m-d');

            $pdo = db();
            $pdo->beginTransaction();
            try {
                $pdo->prepare('INSERT INTO organizations (name, slug, notify_email, status, plan_id, trial_ends_at) VALUES (?,?,?,?,?,?)')
                    ->execute([$orgName, $slug, $email, 'TRIAL', $planId, $trialEnds]);
                $orgId = db_last_id($pdo);

                $pdo->prepare('INSERT INTO users (org_id, name, email, password_hash, role) VALUES (?,?,?,?,?)')
                    ->execute([$orgId, $name, $email, hash_password($password), 'ORG_ADMIN']);
                $userId = db_last_id($pdo);

                $pdo->commit();
            } catch (Throwable $e) {
                $pdo->rollBack();
                throw $e;
            }

            login_user(['id' => $userId, 'org_id' => $orgId, 'name' => $name, 'email' => $email, 'role' => 'ORG_ADMIN']);
            redirect('admin.php?welcome=1');
        }
    }
}

$pageTitle = 'Załóż organizację — ZapisyPro';
require __DIR__ . '/includes/layout_top.php';
?>
<div class="container-sm section">
  <h1 class="section-title">Załóż organizację</h1>
  <p class="text-muted">14 dni okresu próbnego, bez karty płatniczej. Po rejestracji od razu masz dostęp do panelu.</p>

  <?php if ($error): ?><p class="alert alert-error"><?= e($error) ?></p><?php endif; ?>

  <form method="post" class="mt-6 auth-card reveal">
    <?= csrf_field() ?>
    <div class="field">
      <label for="org_name">Nazwa organizacji (szkółki/klubu)</label>
      <input id="org_name" name="org_name" required value="<?= e($_POST['org_name'] ?? '') ?>">
    </div>
    <div class="field">
      <label>Plan subskrypcji</label>
      <div class="plan-radio-group">
        <?php foreach ($plans as $p): ?>
          <label class="plan-radio">
            <input type="radio" name="plan_id" value="<?= $p['id'] ?>" <?= (int) ($_POST['plan_id'] ?? 0) === (int) $p['id'] || (!isset($_POST['plan_id']) && $p['key_name'] === 'START') ? 'checked' : '' ?>>
            <span><strong><?= e($p['name']) ?></strong> — <?= number_format($p['price_monthly'] / 100, 0, ',', ' ') ?> zł/mies.</span>
          </label>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="field">
      <label for="name">Twoje imię i nazwisko</label>
      <input id="name" name="name" required value="<?= e($_POST['name'] ?? '') ?>">
    </div>
    <div class="field">
      <label for="email">E-mail (login administratora)</label>
      <input id="email" name="email" type="email" required value="<?= e($_POST['email'] ?? '') ?>">
    </div>
    <div class="field">
      <label for="password">Hasło</label>
      <input id="password" name="password" type="password" required minlength="8" autocomplete="new-password">
    </div>
    <button type="submit" class="btn btn-primary" style="width:100%;">Załóż organizację i zacznij trial</button>
  </form>

  <p class="mt-6 text-muted">Masz już konto? <a href="<?= e(url('logowanie.php')) ?>">Zaloguj się</a></p>
</div>
<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
