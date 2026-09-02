<?php
/**
 * ============================================================================
 *  WEBHOOK TPAY — punkt końcowy wywoływany przez SERWER Tpay (nie przez
 *  przeglądarkę rodzica!) po każdej zmianie statusu płatności.
 * ============================================================================
 * To jest jedyne miejsce w całej appce, które (pośrednio, przez
 * tpay_process_webhook_payload -> tpay_mark_enrollment_paid w
 * includes/tpay.php) ustawia enrollments.payment_status na 'PAID'. Patrz
 * obszerny komentarz w includes/tpay.php, dlaczego to musi być właśnie
 * potwierdzenie webhookiem, a nie sam powrót przeglądarki na stronę sukcesu
 * (platnosc-powrot.php) — ten plik CELOWO niczego sam nie ustawia.
 *
 * BEZPIECZEŃSTWO — to jest publiczny adres URL, dostępny dla całego
 * internetu, bez logowania i bez ciasteczka sesji (Tpay nie ma naszej sesji
 * przeglądarki). Jedyne, co odróżnia prawdziwe powiadomienie od kogoś, kto
 * po prostu odgadł ten adres i wysłał sfałszowane żądanie, to podpis
 * kryptograficzny w nagłówku X-JWS-Signature — dlatego cała logika (patrz
 * tpay_process_webhook_payload w includes/tpay.php) weryfikuje ten podpis
 * PRZED dotknięciem bazy danych. Zero wyjątków od tej zasady.
 *
 * Ten plik świadomie NIE używa require_role()/require_login() (Tpay nie
 * loguje się do naszej appki) ani csrf_check() (CSRF chroni przed
 * fałszywymi żądaniami wysłanymi z przeglądarki OFIARY, która ma już nasze
 * ciasteczko sesji — to zupełnie inny model zagrożenia niż serwer-serwer;
 * tutaj rolę "CSRF tokena" pełni właśnie podpis JWS).
 */
require_once __DIR__ . '/includes/bootstrap.php';

// Surowe, niezmodyfikowane ciało żądania — MUSI być odczytane w ten sposób
// (nie przez $_POST), bo weryfikacja podpisu liczy się na dokładnych,
// oryginalnych bajtach, a $_POST przechodzi przez parsowanie PHP, które
// mogłoby (w rzadkich przypadkach) zmienić np. kolejność/kodowanie pól.
$rawBody = file_get_contents('php://input');

// Nagłówek bywa dostępny pod różną nazwą zależnie od konfiguracji serwera
// (Apache/mod_php vs PHP-FPM za nginx) — sprawdzamy oba warianty.
$signatureHeader = $_SERVER['HTTP_X_JWS_SIGNATURE']
    ?? (function_exists('getallheaders') ? (getallheaders()['X-JWS-Signature'] ?? '') : '');

$result = tpay_process_webhook_payload($rawBody, $signatureHeader);

if (!$result['verified']) {
    // Podpis nieprawidłowy albo brakujący — NIC nie zostało dotknięte w
    // bazie. Logujemy (bez treści body — mogłoby zawierać dane osobowe
    // rodzica — tylko fakt odrzucenia) i odpowiadamy błędem.
    error_log('[InnovaGo/Tpay] Webhook odrzucony — nieprawidłowy albo brakujący podpis X-JWS-Signature.');
    http_response_code(400);
    exit('invalid signature');
}

if (!$result['matched']) {
    // Podpis był poprawny (to naprawdę Tpay), ale nie udało się dopasować
    // do żadnego naszego zapisu — logujemy do diagnostyki, ale i tak
    // odpowiadamy 200, żeby Tpay nie próbował wysyłać tego samego
    // powiadomienia w kółko (to nie jest błąd, który zniknie przy powtórce).
    http_response_code(200);
    echo 'TRUE';
    exit;
}

// Tpay (klasyczne API) oczekuje w odpowiedzi dokładnie tekstu "TRUE" jako
// potwierdzenia odebrania powiadomienia (⚠ ZWERYFIKUJ w aktualnej
// dokumentacji — jeśli nowsze API oczekuje np. JSON {"status":"ok"}, to
// jedyna linijka do zmiany w całym pliku).
http_response_code(200);
echo 'TRUE';
