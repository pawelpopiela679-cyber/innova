<?php
/**
 * Wysyłka SMS — punkt integracji z bramką SMS (np. SMSAPI.pl, SerwerSMS.pl,
 * Vercom). ZapisyPro sam nie ma umowy z żadnym dostawcą (to wymaga Twojego
 * konta i klucza API), więc dopóki SMS_API_TOKEN jest puste w
 * config.local.php, wiadomości zamiast wysyłki trafiają do
 * php/storage/sms.log — widzisz dokładnie co i do kogo poszłoby.
 *
 * Żeby podłączyć prawdziwą bramkę: załóż konto u wybranego dostawcy, wklej
 * jego token do SMS_API_TOKEN, i podmień treść bloku "TODO" niżej na
 * wywołanie ich REST API (każdy z wymienionych dostawców ma gotowy,
 * jednoplikowy przykład w swojej dokumentacji — zwykle zwykłe POST+cURL,
 * bez żadnej dodatkowej biblioteki).
 */
function send_sms(string $phone, string $message): void
{
    $phone = preg_replace('/[^0-9+]/', '', $phone);

    if (empty(SMS_API_TOKEN)) {
        $logDir = __DIR__ . '/../storage';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0775, true);
        }
        $line = sprintf(
            "[%s] (brak SMS_API_TOKEN, SMS NIE wysłany)\nDo: %s\nTreść: %s\n---\n\n",
            date('Y-m-d H:i:s'),
            $phone,
            $message
        );
        @file_put_contents($logDir . '/sms.log', $line, FILE_APPEND);
        return;
    }

    // TODO: prawdziwa integracja z bramką SMS, np. SMSAPI.pl:
    //   $ch = curl_init('https://api.smsapi.pl/sms.do');
    //   curl_setopt_array($ch, [
    //       CURLOPT_POST => true,
    //       CURLOPT_RETURNTRANSFER => true,
    //       CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . SMS_API_TOKEN],
    //       CURLOPT_POSTFIELDS => http_build_query([
    //           'to' => $phone, 'message' => $message, 'from' => SMS_SENDER_NAME, 'format' => 'json',
    //       ]),
    //   ]);
    //   $response = curl_exec($ch);
    //   curl_close($ch);
    error_log('[ZapisyPro] SMS_API_TOKEN ustawiony, ale integracja z bramką nie jest jeszcze podpięta w includes/sms.php.');
}
