<?php
/**
 * ============================================================================
 *  INTEGRACJA Z TPAY — klient API płatności online
 * ============================================================================
 *
 * Ten plik jest podzielony na dwa kroki, dokładnie tak jak ustaliliśmy:
 *
 *   KROK 1 — płatność jednorazowa: rodzic klika "Zapłać online" przy
 *            zapisie, płaci kartą/BLIK-iem/przelewem, a status w bazie
 *            (enrollments.payment_status) aktualizuje się SAM, przez
 *            webhook (powiadomienie serwer-serwer od Tpay), a nie przez
 *            to, że ktoś "wrócił" na stronę sukcesu (to bardzo ważne
 *            rozróżnienie bezpieczeństwa — patrz komentarz przy
 *            tpay_verify_webhook_signature()).
 *
 *   KROK 2 — płatność cykliczna: przy pierwszej płatności rodzic może
 *            zgodzić się na zapisanie karty (token). Kolejne zajęcia mogą
 *            być wtedy opłacane automatycznie, bez ponownego wpisywania
 *            danych karty — patrz cron-platnosci-cykliczne.php.
 *
 * Funkcje KROKU 2 są wyraźnie oznaczone w kodzie poniżej.
 *
 * ----------------------------------------------------------------------------
 *  TRYB SYMULACJI (TPAY_SIMULATE=true w config.local.php)
 * ----------------------------------------------------------------------------
 * Każda funkcja w tym pliku na samym starcie sprawdza TPAY_SIMULATE. Gdy jest
 * włączony, ŻADNE prawdziwe połączenie z Tpay się nie wykonuje — zamiast
 * tego funkcja zwraca sfabrykowane, ale realistyczne dane (fałszywy numer
 * transakcji, fałszywy link płatności prowadzący do LOKALNEJ strony
 * platnosc-symulacja.php, gdzie możesz sam "kliknąć zapłacono" i zobaczyć,
 * jak webhook aktualizuje bazę). To pozwala przetestować cały przepływ
 * zanim jeszcze założysz konto Tpay. Wyłącz ten tryb (i uzupełnij prawdziwe
 * klucze), gdy będziesz gotowy testować na prawdziwym sandboxie Tpay.
 *
 * ----------------------------------------------------------------------------
 *  ŹRÓDŁA / CO ZWERYFIKOWAĆ PRZED PRODUKCJĄ
 * ----------------------------------------------------------------------------
 * Kod poniżej odtwarza realny kształt API Tpay (REST + OAuth2 + JSON) na
 * podstawie oficjalnej dokumentacji: https://docs-api.tpay.com/pl/ oraz
 * https://docs-api.tpay.com/en/webhooks/ (stan na dzień pisania tego kodu).
 * API bywa aktualizowane — miejsca oznaczone "⚠ ZWERYFIKUJ" to punkty,
 * gdzie WARTO porównać z aktualną dokumentacją zanim pójdziesz na produkcję
 * z prawdziwymi pieniędzmi (dokładny adres endpointu do obciążania tokenu
 * w Kroku 2, oraz dokładny host środowiska sandbox).
 */

/** Adres bazowy API — produkcja albo sandbox, zależnie od TPAY_ENV. */
function tpay_api_base(): string
{
    // ⚠ ZWERYFIKUJ: dokumentacja Tpay potwierdza produkcyjny adres
    // https://api.tpay.com. Dla sandboxa część dokumentacji Tpay sugeruje
    // analogiczny wzorzec (subdomena "sandbox"), ale zanim zaczniesz testy
    // na prawdziwym sandboxie, potwierdź dokładny adres w panelu Tpay
    // (Ustawienia -> Integracja) albo w https://docs-api.tpay.com/pl/.
    return TPAY_ENV === 'production'
        ? 'https://api.tpay.com'
        : 'https://api.sandbox.tpay.com';
}

/**
 * Pobiera token dostępowy OAuth2 (client_credentials) — wymagany w nagłówku
 * Authorization: Bearer <token> przy każdym kolejnym wywołaniu API.
 * Token jest ważny tylko przez chwilę (expires_in), więc pobieramy nowy przy
 * każdym żądaniu zamiast go trwale zapisywać — prostsze i wystarczająco
 * szybkie na skalę tej appki (jedno dodatkowe zapytanie HTTP na płatność).
 *
 * @throws RuntimeException gdy Tpay odrzuci dane logowania albo nie odpowie
 */
function tpay_get_access_token(): string
{
    if (TPAY_SIMULATE) {
        return 'symulowany-token-testowy';
    }

    $response = tpay_http_post(tpay_api_base() . '/oauth/auth', [
        'client_id' => TPAY_CLIENT_ID,
        'client_secret' => TPAY_CLIENT_SECRET,
    ], null, 'form'); // OAuth2 standardowo jako form-urlencoded, nie JSON

    if (!isset($response['access_token'])) {
        throw new RuntimeException('Tpay: nie udało się uzyskać tokenu dostępowego. Sprawdź TPAY_CLIENT_ID/TPAY_CLIENT_SECRET.');
    }
    return $response['access_token'];
}

/**
 * KROK 1 — tworzy nową transakcję (płatność jednorazową) i zwraca link,
 * na który należy przekierować rodzica, żeby dokończył płatność.
 *
 * @param array $params wymagane klucze: amountCents (int, grosze),
 *   description (string), payerEmail, payerName, successUrl, errorUrl,
 *   notificationUrl, externalId (nasz identyfikator — id zapisu w
 *   enrollments, do dopasowania webhooka).
 *   Opcjonalnie (KROK 2): requestCardTokenization (bool) — poprosi Tpay
 *   o zapisanie karty rodzica jako tokenu do przyszłych płatności
 *   cyklicznych; token przyjdzie w osobnym powiadomieniu webhook.
 *
 * @return array{transactionId: string, paymentUrl: string}
 * @throws RuntimeException gdy Tpay odrzuci żądanie
 */
function tpay_create_transaction(array $params): array
{
    if (TPAY_SIMULATE) {
        // Fałszywy, ale unikalny numer transakcji — wystarczy do przetestowania
        // dopasowania webhooka po externalId. Link prowadzi do lokalnej strony
        // symulacji zamiast do prawdziwego Tpay.
        $fakeTransactionId = 'SIM-' . bin2hex(random_bytes(8));
        return [
            'transactionId' => $fakeTransactionId,
            'paymentUrl' => url('platnosc-symulacja.php')
                . '?transactionId=' . urlencode($fakeTransactionId)
                . '&externalId=' . urlencode((string) $params['externalId'])
                . '&amountCents=' . (int) $params['amountCents']
                . '&tokenize=' . (!empty($params['requestCardTokenization']) ? '1' : '0'),
        ];
    }

    $token = tpay_get_access_token();

    $body = [
        'amount' => round($params['amountCents'] / 100, 2),
        'description' => $params['description'],
        // Nasz własny numer zapisu — wraca w webhooku jako "statement" albo
        // podobne pole (⚠ ZWERYFIKUJ dokładną nazwę pola w aktualnym API),
        // dzięki czemu jednoznacznie wiemy, którego enrollments.id dotyczy
        // powiadomienie, nawet jeśli tpay_transaction_id jeszcze go nie ma
        // (np. powiadomienie dotarło przed zapisaniem odpowiedzi z kroku
        // tworzenia transakcji).
        'hiddenDescription' => 'innovago-enrollment-' . $params['externalId'],
        'payer' => [
            'email' => $params['payerEmail'],
            'name' => $params['payerName'],
        ],
        'callbacks' => [
            'notification' => [
                'url' => $params['notificationUrl'],
            ],
            'payerUrls' => [
                'success' => $params['successUrl'],
                'error' => $params['errorUrl'],
            ],
        ],
    ];

    if (!empty($params['requestCardTokenization'])) {
        // KROK 2: prosimy Tpay o zapisanie karty do przyszłego użytku.
        // ⚠ ZWERYFIKUJ dokładną nazwę/pozycję tego pola w aktualnej
        // dokumentacji tokenizacji: https://docs-api.tpay.com/en/tokenization/
        // — poniżej najbardziej prawdopodobny kształt wg dokumentacji.
        $body['pay'] = ['groupId' => null];
        $body['tokenization'] = ['enabled' => true];
    }

    $response = tpay_http_post(tpay_api_base() . '/transactions', $body, $token, 'json');

    if (empty($response['transactionId']) || empty($response['transactionPaymentUrl'])) {
        throw new RuntimeException('Tpay: nieprawidłowa odpowiedź przy tworzeniu transakcji — brak transactionId/transactionPaymentUrl.');
    }

    return [
        'transactionId' => $response['transactionId'],
        'paymentUrl' => $response['transactionPaymentUrl'],
    ];
}

/**
 * KROK 2 — obciąża zapisany wcześniej token (kartę) na podaną kwotę, bez
 * udziału rodzica (używane przez cron-platnosci-cykliczne.php).
 *
 * @return array{success: bool, transactionId: ?string, error: ?string}
 */
function tpay_charge_token(string $cardToken, int $amountCents, string $description, string $externalId): array
{
    if (TPAY_SIMULATE) {
        // W symulacji zakładamy sukces, chyba że token zaczyna się od
        // "FAIL-" — wygodny sposób na ręczne przetestowanie ścieżki błędu
        // (patrz panel-platnosci.php, gdzie można taki testowy token dodać).
        if (str_starts_with($cardToken, 'FAIL-')) {
            return ['success' => false, 'transactionId' => null, 'error' => 'Symulowana odmowa płatności (testowy token FAIL-*).'];
        }
        return ['success' => true, 'transactionId' => 'SIM-CYCLE-' . bin2hex(random_bytes(8)), 'error' => null];
    }

    $token = tpay_get_access_token();

    // ⚠ ZWERYFIKUJ: dokładny endpoint obciążania zapisanego tokenu (karta
    // zapisana bez udziału płatnika, tzw. "merchant-initiated transaction")
    // opisany jest w https://docs-api.tpay.com/en/tokenization/ — poniżej
    // najbardziej prawdopodobny kształt zgodny z resztą API (POST
    // /transactions z referencją do tokenu zamiast pełnych danych karty).
    // Jeśli dokumentacja wskazuje inny endpoint (np. /transactions/recurring),
    // to jedyne miejsce w całym pliku, które trzeba będzie zmienić.
    try {
        $response = tpay_http_post(tpay_api_base() . '/transactions', [
            'amount' => round($amountCents / 100, 2),
            'description' => $description,
            'hiddenDescription' => 'innovago-enrollment-' . $externalId,
            'pay' => ['token' => $cardToken],
        ], $token, 'json');
    } catch (RuntimeException $e) {
        return ['success' => false, 'transactionId' => null, 'error' => $e->getMessage()];
    }

    if (empty($response['transactionId'])) {
        return ['success' => false, 'transactionId' => null, 'error' => 'Tpay nie zwrócił transactionId.'];
    }
    // Płatność tokenem rozlicza się od razu (bez przekierowania rodzica),
    // ale ostateczne potwierdzenie i tak przyjdzie przez webhook — to on,
    // nie ta odpowiedź, ustawia payment_status na PAID (patrz
    // webhook-tpay.php i komentarz przy tpay_verify_webhook_signature niżej).
    return ['success' => true, 'transactionId' => $response['transactionId'], 'error' => null];
}

/**
 * ============================================================================
 *  WSPÓLNA LOGIKA ZAPISU WYNIKU PŁATNOŚCI (używana przez KROK 1 i KROK 2)
 * ============================================================================
 * Ta funkcja jest jedynym miejscem w całej appce, które ustawia
 * enrollments.payment_status na PAID. Wywołuje ją:
 *  - webhook-tpay.php — po zweryfikowaniu prawdziwego podpisu Tpay (produkcja/sandbox),
 *  - platnosc-symulacja.php — w TPAY_SIMULATE, żeby dało się przetestować cały
 *    przepływ bez prawdziwego konta Tpay.
 * Celowo NIE wywołuje jej platnosc-powrot.php (strona, na którą wraca
 * przeglądarka rodzica) — powrót przeglądarki na "stronę sukcesu" NIE jest
 * dowodem zapłaty (rodzic mógł zamknąć kartę przed dokończeniem płatności,
 * albo ktoś mógł ręcznie wpisać ten adres w przeglądarce). Jedynym
 * wiarygodnym potwierdzeniem jest podpisane powiadomienie serwer-serwer.
 *
 * Idempotentna: bezpiecznie wywoływać wielokrotnie dla tego samego zapisu
 * (Tpay może wysłać to samo powiadomienie więcej niż raz — to normalne
 * i opisane w ich dokumentacji, nie błąd).
 *
 * @param int $enrollmentId nasze enrollments.id (patrz tpay_extract_enrollment_id)
 * @param string $transactionId numer transakcji nadany przez Tpay
 * @param string|null $cardToken KROK 2: token zapisanej karty, jeśli rodzic
 *   zgodził się na zapisanie karty przy tej płatności (patrz
 *   tpay_create_transaction -> requestCardTokenization). Dla zwykłej
 *   płatności jednorazowej (KROK 1) zawsze null.
 * @return bool false = nie znaleziono takiego zapisu w bazie (np. sfałszowany/nieaktualny externalId)
 */
function tpay_mark_enrollment_paid(int $enrollmentId, string $transactionId, ?string $cardToken = null): bool
{
    $pdo = db();
    $stmt = $pdo->prepare('SELECT * FROM enrollments WHERE id = ?');
    $stmt->execute([$enrollmentId]);
    $enrollment = $stmt->fetch();
    if (!$enrollment) {
        error_log('[InnovaGo/Tpay] Powiadomienie o płatności dla nieistniejącego enrollments.id=' . $enrollmentId . ' (transactionId=' . $transactionId . ') — zignorowano.');
        return false;
    }

    if ($enrollment['payment_status'] !== 'PAID') {
        $pdo->prepare("UPDATE enrollments SET payment_status = 'PAID', paid_at = CURRENT_TIMESTAMP, tpay_transaction_id = ? WHERE id = ?")
            ->execute([$transactionId, $enrollmentId]);
    }
    // (gdy payment_status już było PAID: nic nie robimy poza ew. zapisaniem
    // tokenu karty niżej — to jest właśnie ta "idempotencja" z komentarza wyżej)

    // --- KROK 2: jeśli razem z tą płatnością Tpay przekazał token zapisanej
    // karty (bo poprosiliśmy o to w tpay_create_transaction), zapisz go do
    // payment_tokens, żeby dało się go użyć do przyszłych płatności
    // cyklicznych bez udziału rodzica — patrz cron-platnosci-cykliczne.php. ---
    if ($cardToken !== null && $cardToken !== '') {
        $exists = $pdo->prepare('SELECT id FROM payment_tokens WHERE tpay_card_token = ? AND parent_id = ? AND revoked_at IS NULL');
        $exists->execute([$cardToken, $enrollment['parent_id']]);
        if (!$exists->fetch()) {
            $pdo->prepare('INSERT INTO payment_tokens (org_id, parent_id, tpay_card_token, card_label, created_at) VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)')
                ->execute([$enrollment['org_id'], $enrollment['parent_id'], $cardToken, 'Karta zapisana ' . date('d.m.Y')]);
        }
    }

    return true;
}

/**
 * Wyszukuje w dowolnym tekście nasz własny identyfikator zapisu, który sami
 * osadziliśmy w polu "hiddenDescription" przy tworzeniu transakcji (patrz
 * tpay_create_transaction) — np. tekst zawierający "innovago-enrollment-482"
 * daje w wyniku (int) 482.
 *
 * Dlaczego szukamy wzorca zamiast czytać jedno konkretne pole JSON: Tpay
 * może odesłać naszą wartość w różnie nazwanym polu zależnie od wersji API
 * i typu powiadomienia (⚠ ZWERYFIKUJ dokładną nazwę pola w dokumentacji: to
 * może być "tr_crc", "description", "statement" albo inne) — zamiast zgadywać
 * i ryzykować, że akurat zmienią nazwę pola, przeszukujemy CAŁĄ treść
 * powiadomienia w poszukiwaniu naszego unikalnego, trudnego do podrobienia
 * wzorca. To nie jest obejście weryfikacji podpisu (ta i tak jest zawsze
 * wymagana osobno, patrz tpay_verify_webhook_signature) — to tylko sposób na
 * odczytanie KTÓREGO zapisu dotyczy już zweryfikowane powiadomienie.
 */
function tpay_extract_enrollment_id(string $haystack): ?int
{
    if (preg_match('/innovago-enrollment-(\d+)/', $haystack, $m)) {
        return (int) $m[1];
    }
    return null;
}

/**
 * Przetwarza JEDNO powiadomienie webhook od początku do końca: weryfikuje
 * podpis, parsuje treść (JSON albo form-urlencoded), dopasowuje nasz
 * enrollments.id i — jeśli płatność się powiodła — zapisuje to w bazie
 * (przez tpay_mark_enrollment_paid). To jest CAŁA logika webhooka w jednym
 * miejscu, używana przez DWA wywołujące miejsca:
 *
 *  - webhook-tpay.php — dla prawdziwych powiadomień, które przychodzą przez
 *    HTTP bezpośrednio z serwerów Tpay,
 *  - platnosc-symulacja.php — w TPAY_SIMULATE, wywoływane BEZPOŚREDNIO w PHP
 *    (bez żadnego zapytania sieciowego "do samego siebie"), z DOKŁADNIE tą
 *    samą logiką co produkcja. Dzięki temu test w symulacji sprawdza
 *    naprawdę ten sam kod, który obsłuży prawdziwe powiadomienia — różni
 *    się tylko to, SKĄD przychodzi wywołanie (HTTP z internetu vs
 *    bezpośrednie wywołanie funkcji), a nie CO ono robi. (Pierwsza wersja
 *    tego kodu robiła to przez self-request przez cURL do własnego
 *    webhook-tpay.php — działało poprawnie, ale okazało się kruche na
 *    serwerach deweloperskich obsługujących jedno żądanie na raz, patrz
 *    historia commitów — bezpośrednie wywołanie funkcji jest prostsze
 *    i równie wierne, bez tej wady.)
 *
 * @return array{verified: bool, matched: bool, paid: bool, enrollmentId: ?int, transactionId: ?string}
 */
function tpay_process_webhook_payload(string $rawBody, string $signatureHeader): array
{
    $empty = ['verified' => false, 'matched' => false, 'paid' => false, 'enrollmentId' => null, 'transactionId' => null];

    if (!tpay_verify_webhook_signature($rawBody, $signatureHeader)) {
        return $empty;
    }

    // Tpay może wysłać powiadomienie jako JSON albo jako x-www-form-urlencoded
    // (⚠ ZWERYFIKUJ dokładny format w aktualnej dokumentacji) — obsługujemy
    // oba, żeby kod działał niezależnie od tego, który wariant dostaniemy.
    $data = json_decode($rawBody, true);
    if (!is_array($data)) {
        parse_str($rawBody, $parsed);
        $data = $parsed;
    }

    // Pole z numerem transakcji nazywa się różnie zależnie od formatu/wersji
    // API (⚠ ZWERYFIKUJ) — sprawdzamy kilka najbardziej prawdopodobnych nazw.
    $transactionId = (string) ($data['tr_id'] ?? $data['transactionId'] ?? $data['id'] ?? '');

    // Czy płatność się powiodła — klasyczne API Tpay używa tr_status = "TRUE"
    // dla sukcesu; nowsze REST API może używać np. status = "correct"/"PAID"
    // (⚠ ZWERYFIKUJ). Sprawdzamy kilka wariantów, żeby nie przeoczyć potwierdzenia.
    $statusValue = (string) ($data['tr_status'] ?? $data['status'] ?? '');
    $isPaid = in_array(strtoupper($statusValue), ['TRUE', 'PAID', 'CORRECT', 'SUCCESS'], true);

    // Nasz enrollments.id — szukamy własnego wzorca w CAŁYM surowym ciele,
    // patrz komentarz przy tpay_extract_enrollment_id() wyżej.
    $enrollmentId = tpay_extract_enrollment_id($rawBody);

    // KROK 2: token zapisanej karty, jeśli Tpay go przesłał (bo poprosiliśmy
    // o tokenizację przy tworzeniu transakcji — patrz requestCardTokenization
    // w tpay_create_transaction). ⚠ ZWERYFIKUJ dokładną nazwę pola.
    $cardToken = $data['card_token'] ?? $data['cardToken'] ?? $data['token'] ?? null;

    if ($enrollmentId === null) {
        error_log('[InnovaGo/Tpay] Webhook zweryfikowany, ale nie udało się dopasować enrollments.id (transactionId=' . $transactionId . ').');
        return ['verified' => true, 'matched' => false, 'paid' => false, 'enrollmentId' => null, 'transactionId' => $transactionId ?: null];
    }

    if ($isPaid) {
        tpay_mark_enrollment_paid($enrollmentId, $transactionId !== '' ? $transactionId : ('BRAK-ID-' . $enrollmentId), $cardToken);
    } else {
        // Powiadomienie o NIEudanej płatności — nic nie zmieniamy w statusie
        // (zapis pozostaje UNPAID, rodzic może spróbować ponownie), ale
        // zapisujemy ostatni błąd do wglądu admina.
        db()->prepare('UPDATE enrollments SET tpay_last_error = ? WHERE id = ?')
            ->execute(['Odrzucona płatność (Tpay status: ' . $statusValue . ')', $enrollmentId]);
    }

    return ['verified' => true, 'matched' => true, 'paid' => $isPaid, 'enrollmentId' => $enrollmentId, 'transactionId' => $transactionId ?: null];
}

/**
 * ----------------------------------------------------------------------------
 * BARDZO WAŻNE — dlaczego weryfikujemy podpis webhooka
 * ----------------------------------------------------------------------------
 * webhook-tpay.php aktualizuje payment_status na PAID na podstawie
 * powiadomienia, które przychodzi z internetu, od kogokolwiek, kto zna adres
 * tego pliku. Gdybyśmy ufali mu bezkrytycznie, KAŻDY mógłby ręcznie wysłać
 * fałszywe powiadomienie "zapłacono" i oznaczyć sobie dowolny zapis jako
 * opłacony za darmo. Dlatego KAŻDE powiadomienie MUSI przejść przez tę
 * funkcję, zanim cokolwiek zmienimy w bazie.
 *
 * Tpay podpisuje każde powiadomienie podpisem JWS (RFC 7515, standard
 * branżowy — to nie jest coś specyficznego dla Tpay) w nagłówku
 * X-JWS-Signature, w formacie "detached" (skrócony: nagłówek..podpis, bez
 * powtarzania treści powiadomienia w samym podpisie — treścią jest surowe
 * ciało żądania POST). Weryfikujemy go kluczem publicznym Tpay pobranym z
 * ich oficjalnego certyfikatu.
 * ----------------------------------------------------------------------------
 *
 * @param string $rawBody surowe, niezmodyfikowane ciało żądania POST (file_get_contents('php://input'))
 * @param string $signatureHeader wartość nagłówka X-JWS-Signature
 */
function tpay_verify_webhook_signature(string $rawBody, string $signatureHeader): bool
{
    if (TPAY_SIMULATE) {
        // W symulacji nie ma prawdziwego podpisu — platnosc-symulacja.php
        // sam ustawia specjalny nagłówek, który tu rozpoznajemy. Ta gałąź
        // NIGDY się nie wykona, gdy TPAY_SIMULATE=false (produkcja).
        return $signatureHeader === 'symulacja';
    }

    if ($signatureHeader === '' || !str_contains($signatureHeader, '..')) {
        error_log('[InnovaGo/Tpay] Webhook bez poprawnego nagłówka X-JWS-Signature — odrzucono.');
        return false;
    }

    // Format "detached": <base64url(nagłówek)>..<base64url(podpis)>
    [$encodedHeader, , $encodedSignature] = explode('.', $signatureHeader);
    $header = json_decode(tpay_base64url_decode($encodedHeader), true);
    $signature = tpay_base64url_decode($encodedSignature);

    if (!is_array($header) || empty($header['alg'])) {
        error_log('[InnovaGo/Tpay] Webhook: nie udało się rozkodować nagłówka podpisu JWS.');
        return false;
    }

    $publicKey = tpay_get_webhook_public_key();
    if ($publicKey === null) {
        error_log('[InnovaGo/Tpay] Webhook: brak certyfikatu do weryfikacji podpisu (patrz tpay_get_webhook_public_key).');
        return false;
    }

    // Podpisywana treść w formacie "detached JWS" to: base64url(nagłówek) . "." . rawBody
    $signingInput = $encodedHeader . '.' . $rawBody;

    // ⚠ ZWERYFIKUJ: zakładamy RS256 (najpopularniejszy algorytm dla JWS z
    // certyfikatem X.509, tak jak dostarcza go Tpay) — jeśli nagłówek $header['alg']
    // wskaże inny algorytm, dopisz go tutaj.
    $algo = match ($header['alg']) {
        'RS256' => OPENSSL_ALGO_SHA256,
        'RS384' => OPENSSL_ALGO_SHA384,
        'RS512' => OPENSSL_ALGO_SHA512,
        default => null,
    };
    if ($algo === null) {
        error_log('[InnovaGo/Tpay] Webhook: nieobsługiwany algorytm podpisu "' . $header['alg'] . '".');
        return false;
    }

    $result = openssl_verify($signingInput, $signature, $publicKey, $algo);
    return $result === 1;
}

/**
 * Pobiera (i cache'uje lokalnie na 24h) certyfikat publiczny, którym Tpay
 * podpisuje webhooki, żeby nie odpytywać Tpay o klucz przy KAŻDYM webhooku.
 * Adresy certyfikatów są publiczne i różne dla sandboxa/produkcji.
 */
function tpay_get_webhook_public_key(): ?OpenSSLAsymmetricKey
{
    $certUrl = TPAY_ENV === 'production'
        ? 'https://secure.tpay.com/x509/notifications-jws.pem'
        : 'https://secure.sandbox.tpay.com/x509/notifications-jws.pem';

    $cacheFile = __DIR__ . '/../storage/tpay-jws-cert.pem';
    $pem = null;

    if (is_file($cacheFile) && (time() - filemtime($cacheFile)) < 86400) {
        $pem = file_get_contents($cacheFile);
    }
    if ($pem === false || $pem === null) {
        $pem = @file_get_contents($certUrl);
        if ($pem === false) {
            // Brak internetu / Tpay niedostępny — jeśli mamy jakikolwiek
            // (choćby stary) plik w cache, lepiej użyć go niż nic.
            return is_file($cacheFile) ? tpay_load_public_key_from_file($cacheFile) : null;
        }
        $dir = dirname($cacheFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        @file_put_contents($cacheFile, $pem);
    }

    $cert = openssl_x509_read($pem);
    if ($cert === false) {
        return null;
    }
    $key = openssl_pkey_get_public($cert);
    return $key !== false ? $key : null;
}

function tpay_load_public_key_from_file(string $path): ?OpenSSLAsymmetricKey
{
    $pem = @file_get_contents($path);
    if ($pem === false) {
        return null;
    }
    $cert = openssl_x509_read($pem);
    if ($cert === false) {
        return null;
    }
    $key = openssl_pkey_get_public($cert);
    return $key !== false ? $key : null;
}

function tpay_base64url_decode(string $data): string
{
    return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', (4 - strlen($data) % 4) % 4));
}

/**
 * Cienki wrapper na cURL — bez żadnej biblioteki HTTP (ten projekt celowo
 * nie używa Composera, patrz includes/mailer.php dla tego samego podejścia
 * przy SMTP). $bodyFormat: "json" albo "form" (OAuth2 wymaga form-urlencoded).
 *
 * @throws RuntimeException przy błędzie połączenia albo odpowiedzi błędu z Tpay
 */
function tpay_http_post(string $url, array $body, ?string $bearerToken, string $bodyFormat): array
{
    $ch = curl_init($url);
    $headers = ['Accept: application/json'];

    if ($bodyFormat === 'json') {
        $payload = json_encode($body);
        $headers[] = 'Content-Type: application/json';
    } else {
        $payload = http_build_query($body);
        $headers[] = 'Content-Type: application/x-www-form-urlencoded';
    }
    if ($bearerToken !== null) {
        $headers[] = 'Authorization: Bearer ' . $bearerToken;
    }

    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    $raw = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($raw === false) {
        throw new RuntimeException('Tpay: błąd połączenia (' . $curlError . ').');
    }

    $decoded = json_decode($raw, true);
    if ($httpCode >= 400) {
        $message = is_array($decoded) && isset($decoded['errors'])
            ? json_encode($decoded['errors'])
            : $raw;
        throw new RuntimeException('Tpay: serwer odpowiedział błędem ' . $httpCode . ' — ' . $message);
    }

    return is_array($decoded) ? $decoded : [];
}
