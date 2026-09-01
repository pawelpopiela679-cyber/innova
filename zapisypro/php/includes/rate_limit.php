<?php
/**
 * Ochrona logowania przed atakami brute-force / credential stuffing.
 * Liczy nieudane próby osobno per e-mail (chroni jedno konto przed próbami
 * z wielu adresów IP) i per adres IP (chroni przed jednym atakującym
 * próbującym wiele kont) — blokada włącza się, gdy KTÓRYKOLWIEK licznik
 * przekroczy limit. Stan trzymany w bazie (tabela login_attempts), nie w
 * sesji — sesja jest per-przeglądarka, więc nie chroniłaby przed botem.
 */

const LOGIN_MAX_ATTEMPTS = 5;
const LOGIN_LOCKOUT_MINUTES = 15;
// Nieudane próby starsze niż to okno nie liczą się do limitu — zapobiega
// trwałej blokadzie konta przez pojedyncze stare literówki sprzed tygodni.
const LOGIN_WINDOW_MINUTES = 30;

/** Prawdziwy adres IP klienta. Celowo TYLKO REMOTE_ADDR — nagłówki typu
 *  X-Forwarded-For może podać sobie sam atakujący, chyba że jest się
 *  pewnym, że stoi przed appką zaufany reverse proxy (na zwykłym hostingu
 *  współdzielonym typu home.pl zwykle nie ma takiej gwarancji). */
function client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/**
 * Sprawdza, czy logowanie na dany e-mail/IP jest zablokowane.
 * @return string|null komunikat do pokazania użytkownikowi, albo null gdy można próbować dalej
 */
function login_rate_limit_check(string $email): ?string
{
    $pdo = db();
    // Przy okazji sprzątamy stare, nieaktywne wpisy — tabela sama się nie rozrasta w nieskończoność.
    $pdo->prepare("DELETE FROM login_attempts WHERE (locked_until IS NULL OR locked_until < ?) AND last_attempt_at < ?")
        ->execute([date('Y-m-d H:i:s'), (new DateTime('-' . LOGIN_WINDOW_MINUTES . ' minutes'))->format('Y-m-d H:i:s')]);

    foreach (login_rate_limit_identifiers($email) as $identifier) {
        $stmt = $pdo->prepare('SELECT locked_until FROM login_attempts WHERE identifier = ?');
        $stmt->execute([$identifier]);
        $row = $stmt->fetch();
        if ($row && $row['locked_until'] && $row['locked_until'] > date('Y-m-d H:i:s')) {
            $minutesLeft = max(1, (int) ceil((strtotime($row['locked_until']) - time()) / 60));
            return "Zbyt wiele nieudanych prób logowania. Spróbuj ponownie za $minutesLeft min.";
        }
    }
    return null;
}

/** Zapisuje nieudaną próbę logowania (e-mail + IP) i blokuje, jeśli przekroczono limit. */
function login_rate_limit_record_failure(string $email): void
{
    foreach (login_rate_limit_identifiers($email) as $identifier) {
        login_rate_limit_touch($identifier);
    }
}

/** Czyści liczniki po udanym logowaniu. */
function login_rate_limit_record_success(string $email): void
{
    $pdo = db();
    foreach (login_rate_limit_identifiers($email) as $identifier) {
        $pdo->prepare('DELETE FROM login_attempts WHERE identifier = ?')->execute([$identifier]);
    }
}

function login_rate_limit_identifiers(string $email): array
{
    return ['email:' . mb_strtolower(trim($email)), 'ip:' . client_ip()];
}

function login_rate_limit_touch(string $identifier): void
{
    $pdo = db();
    $stmt = $pdo->prepare('SELECT attempts, last_attempt_at FROM login_attempts WHERE identifier = ?');
    $stmt->execute([$identifier]);
    $row = $stmt->fetch();

    $windowStart = (new DateTime('-' . LOGIN_WINDOW_MINUTES . ' minutes'))->format('Y-m-d H:i:s');
    $attempts = ($row && $row['last_attempt_at'] >= $windowStart) ? (int) $row['attempts'] + 1 : 1;
    $lockedUntil = $attempts >= LOGIN_MAX_ATTEMPTS
        ? (new DateTime('+' . LOGIN_LOCKOUT_MINUTES . ' minutes'))->format('Y-m-d H:i:s')
        : null;

    if (db_is_mysql()) {
        $pdo->prepare('INSERT INTO login_attempts (identifier, attempts, last_attempt_at, locked_until) VALUES (?,?,CURRENT_TIMESTAMP,?)
            ON DUPLICATE KEY UPDATE attempts = VALUES(attempts), last_attempt_at = CURRENT_TIMESTAMP, locked_until = VALUES(locked_until)')
            ->execute([$identifier, $attempts, $lockedUntil]);
    } else {
        $pdo->prepare('INSERT OR REPLACE INTO login_attempts (identifier, attempts, last_attempt_at, locked_until) VALUES (?,?,CURRENT_TIMESTAMP,?)')
            ->execute([$identifier, $attempts, $lockedUntil]);
    }
}
