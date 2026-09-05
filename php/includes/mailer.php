<?php
/**
 * Wysyłka e-maili przez czysty PHP + gniazda TCP (bez Composer/PHPMailer —
 * żeby działało od razu na hostingu, na którym nie masz dostępu SSH do
 * `composer install`). Jeśli SMTP_HOST nie jest ustawiony w
 * config.local.php, treść e-maila zamiast wysyłki trafia do
 * php/storage/mail.log — wygodne przy testach bez prawdziwej skrzynki.
 */

/**
 * Niski poziom: wysyła jeden e-mail (tekst + HTML) przez SMTP albo do logu.
 * $replyTo — opcjonalnie: prywatna skrzynka prowadzącego, żeby odpowiedź
 * rodzica na potwierdzenie zapisu trafiła prosto do niego, a nie na główną
 * skrzynkę wysyłającą (SMTP_FROM_EMAIL). Wysyłka i tak zawsze idzie z
 * głównej skrzynki — Reply-To zmienia tylko adres, na który leci odpowiedź.
 */
function send_mail(string|array $to, string $subject, string $html, string $text, ?string $replyTo = null): void
{
    $recipients = is_array($to) ? $to : [$to];
    $recipients = array_map('trim', $recipients);

    if (empty(SMTP_HOST)) {
        $logDir = __DIR__ . '/../storage';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0775, true);
        }
        $line = sprintf(
            "[%s] (SMTP nieskonfigurowane, e-mail NIE wysłany)\nDo: %s\nOdpowiedź do: %s\nTemat: %s\n---\n%s\n---\n\n",
            date('Y-m-d H:i:s'),
            implode(', ', $recipients),
            $replyTo ?: '(główna skrzynka)',
            $subject,
            $text
        );
        @file_put_contents($logDir . '/mail.log', $line, FILE_APPEND);
        return;
    }

    try {
        smtp_send($recipients, $subject, $html, $text, $replyTo);
    } catch (Throwable $e) {
        error_log('[INNOVA] Błąd wysyłki e-maila: ' . $e->getMessage());
    }
}

/** Minimalny klient SMTP (EHLO/STARTTLS/AUTH LOGIN/DATA) — wystarcza dla
 *  typowego serwera pocztowego home.pl albo Gmaila/innej zewnętrznej usługi. */
function smtp_send(array $recipients, string $subject, string $html, string $text, ?string $replyTo = null): void
{
    $host = SMTP_HOST;
    $port = (int) SMTP_PORT;
    $useTls = $port !== 465; // 465 = implicit TLS, inne porty (587, 25) = STARTTLS

    $transport = $port === 465 ? 'ssl://' : '';
    $socket = @stream_socket_client(
        $transport . $host . ':' . $port,
        $errno,
        $errstr,
        15,
        STREAM_CLIENT_CONNECT
    );
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

    $read(); // powitanie serwera (220 ...)
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

    $boundary = 'innova_' . bin2hex(random_bytes(8));
    $headers = [
        'From: ' . SMTP_FROM_NAME . ' <' . SMTP_FROM_EMAIL . '>',
        'To: ' . implode(', ', $recipients),
        'Subject: ' . mime_encode_subject($subject),
        'MIME-Version: 1.0',
        'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
        'Date: ' . date(DATE_RFC2822),
    ];
    if ($replyTo) {
        $headers[] = 'Reply-To: ' . $replyTo;
    }

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

    // Dot-stuffing: linia zaczynająca się od kropki musi mieć podwojoną kropkę.
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

/** Cotygodniowy rytm grupy jako tekst, np. "poniedziałki, 13:00–14:00". */
function format_group_schedule(int $dayOfWeek, string $startTime, string $endTime): string
{
    return weekday_name_plural_iso($dayOfWeek) . ", {$startTime}–{$endTime}";
}

// ---------------------------------------------------------------------
// Szablony e-maili — odpowiedniki funkcji z src/lib/mailer.ts.
// ---------------------------------------------------------------------

/** Wysyłany do rodzica od razu po zgłoszeniu — jeszcze nie jest to potwierdzenie. */
function send_enrollment_pending_email(array $p): void
{
    $when = format_session_when($p['startsAt'], $p['endsAt']);
    $subject = "Zgłoszenie przyjęte: {$p['sessionTitle']} ({$p['childName']})";

    $text = "Cześć {$p['parentName']},\n\n"
        . "Dziękujemy za zgłoszenie! Twoje zgłoszenie oczekuje na potwierdzenie przez pracownię —\n"
        . "sprawdzimy dostępność miejsc i dobierzemy właściwą grupę wiekową dla dziecka.\n"
        . "Gdy tylko to zrobimy, wyślemy kolejnego e-maila z ostatecznym potwierdzeniem i przypisaną grupą.\n\n"
        . "Zgłoszone zajęcia: {$p['classTypeName']} — {$p['sessionTitle']}\n"
        . "Dziecko: {$p['childName']}\n"
        . "Wybrany termin: $when\n\n"
        . "INNOVA — Pracownia kreatywno-edukacyjna";

    $html = '<div style="font-family: sans-serif; max-width: 480px;">'
        . '<p>Cześć ' . esc_html_mail($p['parentName']) . ',</p>'
        . '<p>Dziękujemy za zgłoszenie! Twoje zgłoszenie oczekuje na potwierdzenie przez pracownię — '
        . 'sprawdzimy dostępność miejsc i dobierzemy właściwą grupę wiekową dla dziecka. Gdy tylko to '
        . 'zrobimy, wyślemy kolejnego e-maila z ostatecznym potwierdzeniem i przypisaną grupą.</p>'
        . '<table style="border-collapse: collapse; margin: 16px 0;">'
        . '<tr><td style="padding:4px 12px 4px 0; color:#666;">Zajęcia</td><td><strong>' . esc_html_mail($p['classTypeName']) . ' — ' . esc_html_mail($p['sessionTitle']) . '</strong></td></tr>'
        . '<tr><td style="padding:4px 12px 4px 0; color:#666;">Dziecko</td><td>' . esc_html_mail($p['childName']) . '</td></tr>'
        . '<tr><td style="padding:4px 12px 4px 0; color:#666;">Wybrany termin</td><td>' . esc_html_mail($when) . '</td></tr>'
        . '</table>'
        . '<p style="color:#999; font-size: 12px;">INNOVA — Pracownia kreatywno-edukacyjna</p></div>';

    send_mail($p['parentEmail'], $subject, $html, $text);
}

/**
 * Wysyłany do rodzica od razu po zgłoszeniu chęci zapisu na KONKRETNY
 * termin (grupę) — dziecko czeka jeszcze na potwierdzenie miejsca przez
 * pracownię, po czym idzie osobny e-mail: send_enrollment_confirmation_email.
 */
function send_signup_request_email(array $p): void
{
    $subject = "Zgłoszenie przyjęte: {$p['groupName']} ({$p['childName']})";

    $text = "Cześć {$p['parentName']},\n\n"
        . "Dziękujemy za zgłoszenie! Sprawdzimy dostępność miejsc w wybranym terminie.\n"
        . "Gdy tylko to zrobimy, wyślemy kolejnego e-maila z ostatecznym potwierdzeniem\n"
        . "(albo informacją o liście rezerwowej, jeśli termin akurat się zapełnił).\n\n"
        . "Zgłoszone zajęcia: {$p['classTypeName']} — {$p['groupName']}\n"
        . "Termin: {$p['when']}\n"
        . "Prowadzący: {$p['instructorName']}\n"
        . "Dziecko: {$p['childName']}\n\n"
        . "INNOVA — Pracownia kreatywno-edukacyjna";

    $html = '<div style="font-family: sans-serif; max-width: 480px;">'
        . '<p>Cześć ' . esc_html_mail($p['parentName']) . ',</p>'
        . '<p>Dziękujemy za zgłoszenie! Sprawdzimy dostępność miejsc w wybranym terminie. Gdy tylko '
        . 'to zrobimy, wyślemy kolejnego e-maila z ostatecznym potwierdzeniem (albo informacją o liście '
        . 'rezerwowej, jeśli termin akurat się zapełnił).</p>'
        . '<table style="border-collapse: collapse; margin: 16px 0;">'
        . '<tr><td style="padding:4px 12px 4px 0; color:#666;">Zajęcia</td><td><strong>' . esc_html_mail($p['classTypeName']) . ' — ' . esc_html_mail($p['groupName']) . '</strong></td></tr>'
        . '<tr><td style="padding:4px 12px 4px 0; color:#666;">Termin</td><td>' . esc_html_mail($p['when']) . '</td></tr>'
        . '<tr><td style="padding:4px 12px 4px 0; color:#666;">Prowadzący</td><td>' . esc_html_mail($p['instructorName']) . '</td></tr>'
        . '<tr><td style="padding:4px 12px 4px 0; color:#666;">Dziecko</td><td>' . esc_html_mail($p['childName']) . '</td></tr>'
        . '</table>'
        . '<p style="color:#999; font-size: 12px;">INNOVA — Pracownia kreatywno-edukacyjna</p></div>';

    send_mail($p['parentEmail'], $subject, $html, $text);
}

/** Powiadomienie dla pracowni o nowym zgłoszeniu chęci zapisu na konkretny termin (grupę). */
function send_studio_new_request_notification(array $p): void
{
    if (empty(STUDIO_NOTIFY_EMAIL)) {
        return;
    }
    $age = calculate_age($p['childBirthDate']);
    $subject = "Nowe zgłoszenie — czeka na potwierdzenie: {$p['childName']} ($age lat) → {$p['groupName']}";

    $noteText = trim($p['note'] ?? '') !== '' ? "\nWiadomość od rodzica: {$p['note']}\n" : '';
    $noteHtml = trim($p['note'] ?? '') !== ''
        ? '<p>Wiadomość od rodzica: <em>' . nl2br(esc_html_mail($p['note'])) . '</em></p>'
        : '';

    $text = "Nowe zgłoszenie chęci zapisu — czeka w puli na potwierdzenie!\n\n"
        . "Zajęcia: {$p['classTypeName']} — {$p['groupName']}\n"
        . "Termin: {$p['when']}\n"
        . "Zajętość wybranego terminu: {$p['confirmedCount']}/{$p['capacity']}\n\n"
        . "Dziecko: {$p['childName']} — WIEK: $age lat (ur. " . format_pl_date($p['childBirthDate']) . ")\n"
        . "Rodzic/opiekun: {$p['parentName']}\n"
        . "E-mail: {$p['parentEmail']}\n"
        . "Telefon: " . ($p['parentPhone'] ?: 'brak') . "\n"
        . $noteText
        . "\nPotwierdź zgłoszenie w panelu: " . APP_URL . "/admin-grupy.php";

    $html = '<div style="font-family: sans-serif; max-width: 480px;">'
        . '<p><strong>Nowe zgłoszenie chęci zapisu — czeka w puli na potwierdzenie!</strong></p>'
        . '<p>Zajęcia: ' . esc_html_mail($p['classTypeName']) . ' — ' . esc_html_mail($p['groupName']) . '<br>'
        . 'Termin: ' . esc_html_mail($p['when']) . '<br>'
        . 'Zajętość wybranego terminu: ' . (int) $p['confirmedCount'] . '/' . (int) $p['capacity'] . '</p>'
        . '<p>Dziecko: ' . esc_html_mail($p['childName']) . ' — <strong>wiek: ' . $age . ' lat</strong> (ur. ' . esc_html_mail(format_pl_date($p['childBirthDate'])) . ')<br>'
        . 'Rodzic/opiekun: ' . esc_html_mail($p['parentName']) . '<br>'
        . 'E-mail: ' . esc_html_mail($p['parentEmail']) . '<br>'
        . 'Telefon: ' . esc_html_mail($p['parentPhone'] ?: 'brak') . '</p>'
        . $noteHtml
        . '<p><a href="' . esc_html_mail(APP_URL . '/admin-grupy.php') . '">Potwierdź zgłoszenie →</a></p></div>';

    $recipients = array_map('trim', explode(',', STUDIO_NOTIFY_EMAIL));
    send_mail($recipients, $subject, $html, $text);
}

/** Potwierdzenie zapisu (albo lista rezerwowa, gdy $p['waitlisted'] = true). */
function send_enrollment_confirmation_email(array $p): void
{
    // 'when' — gotowy tekst terminu (np. z format_group_schedule dla grupy,
    // rytm cotygodniowy, nie jedna data) — albo, dla starszych wywołań,
    // policzony ze konkretnej daty startsAt/endsAt.
    $when = $p['when'] ?? format_session_when($p['startsAt'], $p['endsAt']);
    $waitlisted = !empty($p['waitlisted']);
    $statusLine = $waitlisted
        ? 'Grupa jest obecnie pełna — dziecko zostało zapisane na listę rezerwową. Odezwiemy się, jeśli zwolni się miejsce.'
        : 'Zapis został potwierdzony — miejsce jest zarezerwowane.';
    $subject = $waitlisted
        ? "Lista rezerwowa: {$p['sessionTitle']} ({$p['childName']})"
        : "Potwierdzenie zapisu: {$p['sessionTitle']} ({$p['childName']})";

    $meetingLine = ($p['meetingUrl'] ?? null) && !$waitlisted ? "Link do zajęć online: {$p['meetingUrl']}\n" : '';
    $instructorLine = $p['instructorName'] . (!empty($p['instructorEmail']) ? " ({$p['instructorEmail']})" : '');

    $text = "Cześć {$p['parentName']},\n\n$statusLine\n\n"
        . "Zajęcia: {$p['classTypeName']} — {$p['sessionTitle']}\n"
        . "Dziecko: {$p['childName']}\n"
        . "Termin: $when\n"
        . "Prowadzący: $instructorLine\n"
        . $meetingLine
        . "\nDo zobaczenia na zajęciach!\n\nINNOVA — Pracownia kreatywno-edukacyjna";

    $meetingRow = ($p['meetingUrl'] ?? null) && !$waitlisted
        ? '<tr><td style="padding:4px 12px 4px 0; color:#666;">Link online</td><td><a href="' . esc_html_mail($p['meetingUrl']) . '">' . esc_html_mail($p['meetingUrl']) . '</a></td></tr>'
        : '';
    $instructorCell = esc_html_mail($p['instructorName'])
        . (!empty($p['instructorEmail']) ? ' — <a href="mailto:' . esc_html_mail($p['instructorEmail']) . '">' . esc_html_mail($p['instructorEmail']) . '</a>' : '');

    $html = '<div style="font-family: sans-serif; max-width: 480px;">'
        . '<p>Cześć ' . esc_html_mail($p['parentName']) . ',</p>'
        . '<p>' . esc_html_mail($statusLine) . '</p>'
        . '<table style="border-collapse: collapse; margin: 16px 0;">'
        . '<tr><td style="padding:4px 12px 4px 0; color:#666;">Zajęcia</td><td><strong>' . esc_html_mail($p['classTypeName']) . ' — ' . esc_html_mail($p['sessionTitle']) . '</strong></td></tr>'
        . '<tr><td style="padding:4px 12px 4px 0; color:#666;">Dziecko</td><td>' . esc_html_mail($p['childName']) . '</td></tr>'
        . '<tr><td style="padding:4px 12px 4px 0; color:#666;">Termin</td><td>' . esc_html_mail($when) . '</td></tr>'
        . '<tr><td style="padding:4px 12px 4px 0; color:#666;">Prowadzący</td><td>' . $instructorCell . '</td></tr>'
        . $meetingRow
        . '</table>'
        . '<p>Do zobaczenia na zajęciach!</p>'
        . '<p style="color:#999; font-size: 12px;">INNOVA — Pracownia kreatywno-edukacyjna</p></div>';

    // Reply-To na prywatną skrzynkę prowadzącego (jeśli podana) — odpowiedź
    // rodzica na potwierdzenie trafia prosto do niego, nie na skrzynkę
    // wysyłającą.
    send_mail($p['parentEmail'], $subject, $html, $text, $p['instructorEmail'] ?? null);
}

/** Wysyłany, gdy pracownia odrzuci/anuluje zgłoszenie. 'sessionTitle' opcjonalne (np. odrzucenie z puli, przed przydzieleniem grupy). */
function send_enrollment_declined_email(array $p): void
{
    $classLabel = $p['classTypeName'] . (!empty($p['sessionTitle']) ? ' — ' . $p['sessionTitle'] : '');
    $subject = "Zgłoszenie nie zostało przyjęte: {$classLabel} ({$p['childName']})";
    $text = "Cześć {$p['parentName']},\n\n"
        . "Niestety nie możemy przyjąć zgłoszenia dziecka ({$p['childName']}) na zajęcia: {$classLabel}.\n"
        . "Zachęcamy do sprawdzenia innych terminów w kalendarzu lub kontaktu z nami.\n\n"
        . "INNOVA — Pracownia kreatywno-edukacyjna";
    $html = '<div style="font-family: sans-serif; max-width: 480px;">'
        . '<p>Cześć ' . esc_html_mail($p['parentName']) . ',</p>'
        . '<p>Niestety nie możemy przyjąć zgłoszenia dziecka (' . esc_html_mail($p['childName']) . ') na zajęcia: <strong>' . esc_html_mail($classLabel) . '</strong>.</p>'
        . '<p>Zachęcamy do sprawdzenia innych terminów w kalendarzu lub kontaktu z nami.</p>'
        . '<p style="color:#999; font-size: 12px;">INNOVA — Pracownia kreatywno-edukacyjna</p></div>';
    send_mail($p['parentEmail'], $subject, $html, $text);
}

/** Powiadomienie dla pracowni o nowym zgłoszeniu — z wiekiem dziecka. */
function send_studio_new_signup_notification(array $p): void
{
    if (empty(STUDIO_NOTIFY_EMAIL)) {
        return;
    }
    $age = calculate_age($p['childBirthDate']);
    $when = format_session_when($p['startsAt'], $p['endsAt']);
    $subject = "Nowe zgłoszenie — wymaga potwierdzenia: {$p['childName']} ($age lat) → {$p['sessionTitle']}";

    $text = "Nowe zgłoszenie na zajęcia — wymaga potwierdzenia i przypisania grupy!\n\n"
        . "Zajęcia: {$p['classTypeName']} — {$p['sessionTitle']}\n"
        . "Wybrany termin: $when\n"
        . "Zajętość wybranej grupy: {$p['confirmedCount']}/{$p['capacity']}\n\n"
        . "Dziecko: {$p['childName']} — WIEK: $age lat (ur. " . format_pl_date($p['childBirthDate']) . ")\n"
        . "Rodzic/opiekun: {$p['parentName']}\n"
        . "E-mail: {$p['parentEmail']}\n"
        . "Telefon: " . ($p['parentPhone'] ?: 'brak') . "\n\n"
        . "Potwierdź zgłoszenie i przypisz grupę w panelu: " . APP_URL . "/admin-zapisy.php";

    $html = '<div style="font-family: sans-serif; max-width: 480px;">'
        . '<p><strong>Nowe zgłoszenie na zajęcia — wymaga potwierdzenia i przypisania grupy!</strong></p>'
        . '<p>' . esc_html_mail($p['classTypeName']) . ' — ' . esc_html_mail($p['sessionTitle']) . '<br>'
        . esc_html_mail($when) . '<br>'
        . 'Zajętość wybranej grupy: ' . (int) $p['confirmedCount'] . '/' . (int) $p['capacity'] . '</p>'
        . '<p>Dziecko: ' . esc_html_mail($p['childName']) . ' — <strong>wiek: ' . $age . ' lat</strong> (ur. ' . esc_html_mail(format_pl_date($p['childBirthDate'])) . ')<br>'
        . 'Rodzic/opiekun: ' . esc_html_mail($p['parentName']) . '<br>'
        . 'E-mail: ' . esc_html_mail($p['parentEmail']) . '<br>'
        . 'Telefon: ' . esc_html_mail($p['parentPhone'] ?: 'brak') . '</p>'
        . '<p><a href="' . esc_html_mail(APP_URL . '/admin-zapisy.php') . '">Potwierdź zgłoszenie i przypisz grupę →</a></p></div>';

    $recipients = array_map('trim', explode(',', STUDIO_NOTIFY_EMAIL));
    send_mail($recipients, $subject, $html, $text);
}
