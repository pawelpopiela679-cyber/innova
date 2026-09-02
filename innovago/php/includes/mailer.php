<?php
/**
 * Wysyłka e-maili przez czysty PHP + gniazda TCP (bez Composer/PHPMailer —
 * działa od razu na hostingu bez dostępu SSH do `composer install`). Jeśli
 * SMTP_HOST nie jest ustawiony w config.local.php, treść trafia do
 * php/storage/mail.log zamiast być wysyłana — wygodne do testów.
 */

function send_mail(string|array $to, string $subject, string $html, string $text): void
{
    $recipients = is_array($to) ? $to : [$to];
    $recipients = array_map('trim', $recipients);

    if (empty(SMTP_HOST)) {
        $logDir = __DIR__ . '/../storage';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0775, true);
        }
        $line = sprintf(
            "[%s] (SMTP nieskonfigurowane, e-mail NIE wysłany)\nDo: %s\nTemat: %s\n---\n%s\n---\n\n",
            date('Y-m-d H:i:s'),
            implode(', ', $recipients),
            $subject,
            $text
        );
        @file_put_contents($logDir . '/mail.log', $line, FILE_APPEND);
        return;
    }

    try {
        smtp_send($recipients, $subject, $html, $text);
    } catch (Throwable $e) {
        error_log('[InnovaGo] Błąd wysyłki e-maila: ' . $e->getMessage());
    }
}

function smtp_send(array $recipients, string $subject, string $html, string $text): void
{
    $host = SMTP_HOST;
    $port = (int) SMTP_PORT;
    $useTls = $port !== 465;

    $transport = $port === 465 ? 'ssl://' : '';
    $socket = @stream_socket_client($transport . $host . ':' . $port, $errno, $errstr, 15, STREAM_CLIENT_CONNECT);
    if (!$socket) {
        throw new RuntimeException("Nie można połączyć się z serwerem SMTP ($host:$port): $errstr");
    }

    $read = function () use ($socket): string {
        $data = '';
        while ($line = fgets($socket, 515)) {
            $data .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        return $data;
    };
    $write = function (string $cmd) use ($socket): void {
        fwrite($socket, $cmd . "\r\n");
    };
    $expect = function (string $expectedCode, string $context) use ($read): string {
        $response = $read();
        if (!str_starts_with($response, $expectedCode)) {
            throw new RuntimeException("SMTP: nieoczekiwana odpowiedź przy $context: $response");
        }
        return $response;
    };

    $read();
    $hostname = $_SERVER['SERVER_NAME'] ?? 'localhost';

    $write("EHLO $hostname");
    $ehloResponse = $expect('250', 'EHLO');

    if ($useTls && str_contains($ehloResponse, 'STARTTLS')) {
        $write('STARTTLS');
        $expect('220', 'STARTTLS');
        if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            throw new RuntimeException('SMTP: nie udało się nawiązać szyfrowanego połączenia TLS.');
        }
        $write("EHLO $hostname");
        $expect('250', 'EHLO po STARTTLS');
    }

    if (!empty(SMTP_USER)) {
        $write('AUTH LOGIN');
        $expect('334', 'AUTH LOGIN');
        $write(base64_encode(SMTP_USER));
        $expect('334', 'login (użytkownik)');
        $write(base64_encode(SMTP_PASS));
        $expect('235', 'login (hasło) — sprawdź SMTP_USER/SMTP_PASS w config.local.php');
    }

    $write('MAIL FROM:<' . SMTP_FROM_EMAIL . '>');
    $expect('250', 'MAIL FROM');

    foreach ($recipients as $rcpt) {
        $write('RCPT TO:<' . $rcpt . '>');
        $expect('250', 'RCPT TO (' . $rcpt . ')');
    }

    $write('DATA');
    $expect('354', 'DATA');

    $boundary = 'innovago_' . bin2hex(random_bytes(8));
    $headers = [
        'From: ' . SMTP_FROM_NAME . ' <' . SMTP_FROM_EMAIL . '>',
        'To: ' . implode(', ', $recipients),
        'Subject: ' . mime_encode_subject($subject),
        'MIME-Version: 1.0',
        'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
        'Date: ' . date(DATE_RFC2822),
    ];

    $body = implode("\r\n", $headers) . "\r\n\r\n"
        . "--$boundary\r\n"
        . "Content-Type: text/plain; charset=UTF-8\r\n"
        . "Content-Transfer-Encoding: 8bit\r\n\r\n"
        . $text . "\r\n\r\n"
        . "--$boundary\r\n"
        . "Content-Type: text/html; charset=UTF-8\r\n"
        . "Content-Transfer-Encoding: 8bit\r\n\r\n"
        . $html . "\r\n\r\n"
        . "--$boundary--\r\n";

    $body = preg_replace('/^\./m', '..', $body);

    fwrite($socket, $body . "\r\n.\r\n");
    $expect('250', 'DATA (treść)');

    $write('QUIT');
    fclose($socket);
}

function mime_encode_subject(string $subject): string
{
    return '=?UTF-8?B?' . base64_encode($subject) . '?=';
}

function esc_html_mail(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function format_session_when(string $startsAt, string $endsAt): string
{
    return format_pl_date($startsAt, true, true) . ' – ' . h_m($endsAt);
}

// ---------------------------------------------------------------------
// Szablony e-maili. Każdy przyjmuje 'orgName' — organizacja, w imieniu
// której wiadomość jest wysyłana (nie ma jednej stałej "pracowni" jak w
// INNOVA — tu każdy klient SaaS-a ma własną nazwę w stopce).
// ---------------------------------------------------------------------

function send_enrollment_pending_email(array $p): void
{
    $when = format_session_when($p['startsAt'], $p['endsAt']);
    $subject = "Zgłoszenie przyjęte: {$p['sessionTitle']} ({$p['childName']})";

    $text = "Cześć {$p['parentName']},\n\n"
        . "Dziękujemy za zgłoszenie! Oczekuje ono na potwierdzenie przez {$p['orgName']}.\n"
        . "Gdy tylko to zrobimy, wyślemy kolejnego e-maila z potwierdzeniem.\n\n"
        . "Zajęcia: {$p['classTypeName']} — {$p['sessionTitle']}\n"
        . "Dziecko: {$p['childName']}\n"
        . "Wybrany termin: $when\n\n{$p['orgName']} (przez InnovaGo)";

    $html = '<div style="font-family: sans-serif; max-width: 480px;">'
        . '<p>Cześć ' . esc_html_mail($p['parentName']) . ',</p>'
        . '<p>Dziękujemy za zgłoszenie! Oczekuje ono na potwierdzenie przez <strong>' . esc_html_mail($p['orgName']) . '</strong>.</p>'
        . '<table style="border-collapse: collapse; margin: 16px 0;">'
        . '<tr><td style="padding:4px 12px 4px 0; color:#666;">Zajęcia</td><td><strong>' . esc_html_mail($p['classTypeName']) . ' — ' . esc_html_mail($p['sessionTitle']) . '</strong></td></tr>'
        . '<tr><td style="padding:4px 12px 4px 0; color:#666;">Dziecko</td><td>' . esc_html_mail($p['childName']) . '</td></tr>'
        . '<tr><td style="padding:4px 12px 4px 0; color:#666;">Wybrany termin</td><td>' . esc_html_mail($when) . '</td></tr>'
        . '</table>'
        . '<p style="color:#999; font-size: 12px;">' . esc_html_mail($p['orgName']) . ' (przez InnovaGo)</p></div>';

    send_mail($p['parentEmail'], $subject, $html, $text);
}

function send_enrollment_confirmation_email(array $p): void
{
    $when = format_session_when($p['startsAt'], $p['endsAt']);
    $waitlisted = !empty($p['waitlisted']);
    $statusLine = $waitlisted
        ? 'Grupa jest obecnie pełna — dziecko zostało zapisane na listę rezerwową.'
        : 'Zapis został potwierdzony — miejsce jest zarezerwowane.';
    $subject = $waitlisted
        ? "Lista rezerwowa: {$p['sessionTitle']} ({$p['childName']})"
        : "Potwierdzenie zapisu: {$p['sessionTitle']} ({$p['childName']})";

    $meetingLine = ($p['meetingUrl'] ?? null) && !$waitlisted ? "Link do zajęć online: {$p['meetingUrl']}\n" : '';

    $text = "Cześć {$p['parentName']},\n\n$statusLine\n\n"
        . "Zajęcia: {$p['classTypeName']} — {$p['sessionTitle']}\n"
        . "Dziecko: {$p['childName']}\n"
        . "Termin: $when\n"
        . "Prowadzący: {$p['instructorName']}\n"
        . $meetingLine
        . "\nDo zobaczenia na zajęciach!\n\n" . ($p['orgName'] ?? 'InnovaGo');

    $meetingRow = ($p['meetingUrl'] ?? null) && !$waitlisted
        ? '<tr><td style="padding:4px 12px 4px 0; color:#666;">Link online</td><td><a href="' . esc_html_mail($p['meetingUrl']) . '">' . esc_html_mail($p['meetingUrl']) . '</a></td></tr>'
        : '';

    $html = '<div style="font-family: sans-serif; max-width: 480px;">'
        . '<p>Cześć ' . esc_html_mail($p['parentName']) . ',</p>'
        . '<p>' . esc_html_mail($statusLine) . '</p>'
        . '<table style="border-collapse: collapse; margin: 16px 0;">'
        . '<tr><td style="padding:4px 12px 4px 0; color:#666;">Zajęcia</td><td><strong>' . esc_html_mail($p['classTypeName']) . ' — ' . esc_html_mail($p['sessionTitle']) . '</strong></td></tr>'
        . '<tr><td style="padding:4px 12px 4px 0; color:#666;">Dziecko</td><td>' . esc_html_mail($p['childName']) . '</td></tr>'
        . '<tr><td style="padding:4px 12px 4px 0; color:#666;">Termin</td><td>' . esc_html_mail($when) . '</td></tr>'
        . '<tr><td style="padding:4px 12px 4px 0; color:#666;">Prowadzący</td><td>' . esc_html_mail($p['instructorName']) . '</td></tr>'
        . $meetingRow
        . '</table>'
        . '<p>Do zobaczenia na zajęciach!</p>'
        . '<p style="color:#999; font-size: 12px;">' . esc_html_mail($p['orgName'] ?? 'InnovaGo') . '</p></div>';

    send_mail($p['parentEmail'], $subject, $html, $text);
}

function send_enrollment_declined_email(array $p): void
{
    $subject = "Zgłoszenie nie zostało przyjęte: {$p['sessionTitle']} ({$p['childName']})";
    $text = "Cześć {$p['parentName']},\n\n"
        . "Niestety nie możemy przyjąć zgłoszenia dziecka ({$p['childName']}) na zajęcia: {$p['classTypeName']} — {$p['sessionTitle']}.\n"
        . "Zachęcamy do sprawdzenia innych terminów w kalendarzu.\n\n" . ($p['orgName'] ?? 'InnovaGo');
    $html = '<div style="font-family: sans-serif; max-width: 480px;">'
        . '<p>Cześć ' . esc_html_mail($p['parentName']) . ',</p>'
        . '<p>Niestety nie możemy przyjąć zgłoszenia dziecka (' . esc_html_mail($p['childName']) . ') na zajęcia: <strong>' . esc_html_mail($p['classTypeName']) . ' — ' . esc_html_mail($p['sessionTitle']) . '</strong>.</p>'
        . '<p>Zachęcamy do sprawdzenia innych terminów w kalendarzu.</p>'
        . '<p style="color:#999; font-size: 12px;">' . esc_html_mail($p['orgName'] ?? 'InnovaGo') . '</p></div>';
    send_mail($p['parentEmail'], $subject, $html, $text);
}

/** Powiadomienie dla organizacji o nowym zgłoszeniu (na jej notify_email). */
function send_org_new_signup_notification(array $p): void
{
    if (empty($p['orgNotifyEmail'])) {
        return;
    }
    $age = calculate_age($p['childBirthDate']);
    $when = format_session_when($p['startsAt'], $p['endsAt']);
    $subject = "Nowe zgłoszenie — wymaga potwierdzenia: {$p['childName']} ($age lat) → {$p['sessionTitle']}";

    $text = "Nowe zgłoszenie na zajęcia — wymaga potwierdzenia!\n\n"
        . "Zajęcia: {$p['classTypeName']} — {$p['sessionTitle']}\n"
        . "Wybrany termin: $when\n"
        . "Zajętość grupy: {$p['confirmedCount']}/{$p['capacity']}\n\n"
        . "Dziecko: {$p['childName']} — wiek: $age lat (ur. " . format_pl_date($p['childBirthDate']) . ")\n"
        . "Rodzic/opiekun: {$p['parentName']}\n"
        . "E-mail: {$p['parentEmail']}\n"
        . "Telefon: " . ($p['parentPhone'] ?: 'brak') . "\n\n"
        . "Potwierdź w panelu: " . APP_URL . "/zapisy.php";

    $html = '<div style="font-family: sans-serif; max-width: 480px;">'
        . '<p><strong>Nowe zgłoszenie na zajęcia — wymaga potwierdzenia!</strong></p>'
        . '<p>' . esc_html_mail($p['classTypeName']) . ' — ' . esc_html_mail($p['sessionTitle']) . '<br>'
        . esc_html_mail($when) . '<br>Zajętość: ' . (int) $p['confirmedCount'] . '/' . (int) $p['capacity'] . '</p>'
        . '<p>Dziecko: ' . esc_html_mail($p['childName']) . ' — <strong>wiek: ' . $age . ' lat</strong><br>'
        . 'Rodzic/opiekun: ' . esc_html_mail($p['parentName']) . '<br>'
        . 'E-mail: ' . esc_html_mail($p['parentEmail']) . '<br>'
        . 'Telefon: ' . esc_html_mail($p['parentPhone'] ?: 'brak') . '</p>'
        . '<p><a href="' . esc_html_mail(APP_URL . '/zapisy.php') . '">Potwierdź zgłoszenie →</a></p></div>';

    $recipients = array_map('trim', explode(',', $p['orgNotifyEmail']));
    send_mail($recipients, $subject, $html, $text);
}

/**
 * KROK 2: powiadomienie rodzica, że automatyczne pobranie płatności zapisaną
 * kartą się nie powiodło (np. karta wygasła, brak środków) — wysyłane przez
 * cron-platnosci-cykliczne.php. Ważne, żeby rodzic NIE dowiedział się o
 * zaległości dopiero przy okazji zajęć — dajemy mu szansę dopłacić ręcznie
 * (patrz link do panel-zapisy.php).
 */
function send_payment_charge_failed_email(array $p): void
{
    $subject = "Nie udało się pobrać płatności: {$p['sessionTitle']} ({$p['childName']})";
    $text = "Cześć {$p['parentName']},\n\n"
        . "Nie udało się automatycznie pobrać płatności zapisaną kartą za zajęcia:\n"
        . "{$p['classTypeName']} — {$p['sessionTitle']} ({$p['childName']})\n"
        . "Kwota: " . format_money((int) $p['amountCents']) . "\n"
        . "Powód: {$p['reason']}\n\n"
        . "Opłać ręcznie tutaj: " . APP_URL . "/panel-zapisy.php\n"
        . "albo zaktualizuj/zapisz nową kartę przy najbliższej płatności.\n\n" . ($p['orgName'] ?? 'InnovaGo');

    $html = '<div style="font-family: sans-serif; max-width: 480px;">'
        . '<p>Cześć ' . esc_html_mail($p['parentName']) . ',</p>'
        . '<p>Nie udało się automatycznie pobrać płatności zapisaną kartą za zajęcia:</p>'
        . '<p><strong>' . esc_html_mail($p['classTypeName']) . ' — ' . esc_html_mail($p['sessionTitle']) . '</strong> (' . esc_html_mail($p['childName']) . ')<br>'
        . 'Kwota: ' . esc_html_mail(format_money((int) $p['amountCents'])) . '<br>'
        . 'Powód: ' . esc_html_mail($p['reason']) . '</p>'
        . '<p><a href="' . esc_html_mail(APP_URL . '/panel-zapisy.php') . '">Opłać ręcznie →</a></p>'
        . '<p style="color:#999; font-size: 12px;">' . esc_html_mail($p['orgName'] ?? 'InnovaGo') . '</p></div>';

    send_mail($p['parentEmail'], $subject, $html, $text);
}
