<?php
/**
 * ============================================================================
 *  PŁATNOŚĆ ONLINE — KROK 1 (strona powrotna po płatności)
 * ============================================================================
 * Tu ląduje przeglądarka rodzica PO próbie płatności — niezależnie, czy się
 * udała, czy nie (patrz successUrl/errorUrl w platnosc.php).
 *
 * ⚠ NAJWAŻNIEJSZA ZASADA TEGO PLIKU — i całej integracji płatności:
 * Ta strona TYLKO WYŚWIETLA aktualny status zapisu z bazy. NIE ZMIENIA go.
 * To, że przeglądarka tu trafiła, absolutnie NICZEGO nie dowodzi — rodzic
 * mógł np. zamknąć okno płatności przed jej dokończeniem, stracić
 * połączenie, albo (teoretycznie) ktoś mógłby ręcznie wpisać ten adres
 * w przeglądarce, żeby spróbować "oszukać" system. Jedynym wiarygodnym
 * źródłem prawdy o tym, czy pieniądze faktycznie wpłynęły, jest podpisane
 * powiadomienie serwer-serwer obsłużone w webhook-tpay.php — ta strona
 * tylko odświeża widok, żeby pokazać to, co webhook (być może już, być może
 * jeszcze nie) zdążył zapisać.
 *
 * Dlatego jeśli status w bazie jest wciąż "UNPAID" mimo że Tpay przekierował
 * tu jako "success", pokazujemy komunikat "przetwarzamy" zamiast fałszywego
 * "opłacono" — webhook zwykle przychodzi w ciągu kilku sekund, ale nie ma
 * gwarancji, że zdąży PRZED przekierowaniem przeglądarki.
 */
require_once __DIR__ . '/includes/bootstrap.php';

$user = require_role(['PARENT'], 'platnosc-powrot.php');
$org = require_org();

$enrollmentId = (int) ($_GET['id'] ?? 0);
$tpayStatus = $_GET['status'] ?? ''; // "success" albo "error" — tylko podpowiedź UI, nie źródło prawdy (patrz komentarz wyżej)

$stmt = db()->prepare("SELECT e.*, ct.name AS ct_name, cs.title AS session_title
    FROM enrollments e JOIN class_sessions cs ON cs.id = e.session_id JOIN class_types ct ON ct.id = cs.class_type_id
    WHERE e.id = ? AND e.parent_id = ? AND e.org_id = ?");
$stmt->execute([$enrollmentId, $user['id'], $org['id']]);
$enrollment = $stmt->fetch();

$pageTitle = 'Status płatności';
require __DIR__ . '/includes/layout_top.php';
?>
<div class="container section" style="max-width:520px;">
  <div class="card reveal" style="text-align:center;padding:2.5rem 2rem;">
    <?php if (!$enrollment): ?>
      <h1 class="section-title">Nie znaleziono zapisu</h1>
      <p class="text-muted mt-2">Ten link płatności nie dotyczy żadnego Twojego zapisu.</p>

    <?php elseif ($enrollment['payment_status'] === 'PAID'): ?>
      <div style="font-size:3rem;">✅</div>
      <h1 class="section-title mt-2">Płatność zaksięgowana!</h1>
      <p class="text-muted mt-2"><?= e($enrollment['ct_name']) ?> — <?= e($enrollment['session_title']) ?><br>
        <?= e(format_money((int) $enrollment['amount_due_cents'])) ?></p>

    <?php elseif ($tpayStatus === 'success'): ?>
      <div style="font-size:3rem;">⏳</div>
      <h1 class="section-title mt-2">Przetwarzamy płatność…</h1>
      <p class="text-muted mt-2">Tpay potwierdził próbę płatności, a my czekamy jeszcze na ostateczne potwierdzenie
        (zwykle to kwestia kilku sekund). Odśwież tę stronę za chwilę albo sprawdź status później w
        <a href="<?= e(url('panel-zapisy.php')) ?>">Moich zapisach</a> — status zaktualizuje się sam,
        bez potrzeby robienia czegokolwiek więcej.</p>
      <button onclick="location.reload()" class="btn btn-outline mt-4">Odśwież</button>

    <?php else: ?>
      <div style="font-size:3rem;">❌</div>
      <h1 class="section-title mt-2">Płatność nie została zrealizowana</h1>
      <p class="text-muted mt-2">Nic się nie stało — możesz spróbować ponownie w dowolnym momencie
        z listy <a href="<?= e(url('panel-zapisy.php')) ?>">Moich zapisów</a>, albo opłacić zajęcia
        tradycyjnie (przelew/gotówka) — zapytaj o to organizację.</p>
    <?php endif; ?>

    <a href="<?= e(url('panel-zapisy.php')) ?>" class="btn btn-primary mt-6">Wróć do moich zapisów</a>
  </div>
</div>
<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
