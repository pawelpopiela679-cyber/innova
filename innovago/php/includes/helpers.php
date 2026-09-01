<?php
/** Skróty i drobne funkcje pomocnicze używane na każdej stronie. */

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function redirect(string $path): never
{
    if (!str_starts_with($path, 'http://') && !str_starts_with($path, 'https://')) {
        $base = rtrim((string) parse_base_path(), '/');
        $path = $base . '/' . ltrim($path, '/');
    }
    header('Location: ' . $path);
    exit;
}

function parse_base_path(): string
{
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    return rtrim(str_replace('\\', '/', dirname($script)), '/');
}

function url(string $path = ''): string
{
    $base = parse_base_path();
    return $base . '/' . ltrim($path, '/');
}

function redirect_with(string $path, array $params): never
{
    $sep = str_contains($path, '?') ? '&' : '?';
    redirect($path . ($params ? $sep . http_build_query($params) : ''));
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function csrf_check(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
        http_response_code(400);
        exit('Nieprawidłowy token bezpieczeństwa (CSRF). Wróć i spróbuj ponownie.');
    }
}

function calculate_age(string $birthDate, ?string $at = null): int
{
    $birth = new DateTime($birthDate);
    $now = new DateTime($at ?? 'now');
    return (int) $birth->diff($now)->y;
}

const PL_MONTHS = [
    1 => 'stycznia', 2 => 'lutego', 3 => 'marca', 4 => 'kwietnia', 5 => 'maja',
    6 => 'czerwca', 7 => 'lipca', 8 => 'sierpnia', 9 => 'września', 10 => 'października',
    11 => 'listopada', 12 => 'grudnia',
];
const PL_MONTHS_NOM = [
    1 => 'Styczeń', 2 => 'Luty', 3 => 'Marzec', 4 => 'Kwiecień', 5 => 'Maj',
    6 => 'Czerwiec', 7 => 'Lipiec', 8 => 'Sierpień', 9 => 'Wrzesień', 10 => 'Październik',
    11 => 'Listopad', 12 => 'Grudzień',
];
const PL_DAYS = [
    'Monday' => 'poniedziałek', 'Tuesday' => 'wtorek', 'Wednesday' => 'środa',
    'Thursday' => 'czwartek', 'Friday' => 'piątek', 'Saturday' => 'sobota', 'Sunday' => 'niedziela',
];

function format_pl_date(string $datetime, bool $withDayName = false, bool $withTime = false): string
{
    $d = new DateTime($datetime);
    $out = '';
    if ($withDayName) {
        $out .= ucfirst(PL_DAYS[$d->format('l')]) . ' ';
    }
    $out .= (int) $d->format('j') . ' ' . PL_MONTHS[(int) $d->format('n')] . ' ' . $d->format('Y');
    if ($withTime) {
        $out .= ', ' . $d->format('H:i');
    }
    return $out;
}

function format_pl_weekday_short(string $datetime): string
{
    $d = new DateTime($datetime);
    return PL_DAYS[$d->format('l')] . ' ' . $d->format('d.m');
}

function h_m(string $datetime): string
{
    return (new DateTime($datetime))->format('H:i');
}

function in_placeholders(array $values): string
{
    return implode(',', array_fill(0, count($values), '?'));
}

/** Slug URL-owy z nazwy organizacji (np. do rejestracja.php?org=...). */
function slugify(string $text): string
{
    $text = trim($text);
    $map = [
        'ą' => 'a', 'ć' => 'c', 'ę' => 'e', 'ł' => 'l', 'ń' => 'n',
        'ó' => 'o', 'ś' => 's', 'ź' => 'z', 'ż' => 'z',
    ];
    $text = strtr(mb_strtolower($text), $map);
    $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';
    return trim($text, '-') ?: 'organizacja';
}

/** Kwota w groszach -> "123,45 zł". */
function format_money(int $cents): string
{
    return number_format($cents / 100, 2, ',', ' ') . ' zł';
}

/**
 * Link "Dodaj do Kalendarza Google" — jednym kliknięciem otwiera Google
 * Calendar z wypełnionym wydarzeniem, bez żadnej integracji API/OAuth (Google
 * nie wymaga klucza do tego linku, działa dla każdego, kto ma konto Google).
 * To jest ta "integracja jednym przyciskiem" — pełna dwukierunkowa
 * synchronizacja (automatyczne dopisywanie się do kalendarza organizatora,
 * wykrywanie zmian) wymagałaby już prawdziwej integracji OAuth z własnym
 * projektem w Google Cloud Console — patrz README_PHP.md.
 */
function google_calendar_link(string $title, string $startsAt, string $endsAt, string $details = '', string $location = ''): string
{
    $fmt = fn(string $dt) => (new DateTime($dt))->format('Ymd\THis');
    $params = [
        'action' => 'TEMPLATE',
        'text' => $title,
        'dates' => $fmt($startsAt) . '/' . $fmt($endsAt),
        'details' => $details,
        'location' => $location,
    ];
    return 'https://calendar.google.com/calendar/render?' . http_build_query($params);
}

/**
 * Logo platformy — jeśli w php/logo.png jest plik, użyj go, inaczej wordmark
 * z CSS. Ten sam wielokolorowy wzorzec co logo INNOVA (../../php: IN/N/O/VA
 * w 4 kolorach), tu na "In/n/o/va" + dopisane "Go" jako akcent produktu —
 * jedno spójne logo rodziny marek Innova.
 */
function render_logo(string $size = 'md'): string
{
    $sizeClass = 'logo-' . $size;
    $heights = ['sm' => 32, 'md' => 48, 'lg' => 90];
    $height = $heights[$size] ?? 48;

    if (is_file(__DIR__ . '/../logo.png')) {
        $src = e(url('logo.png')) . '?v=' . filemtime(__DIR__ . '/../logo.png');
        return '<span class="logo ' . $sizeClass . '"><img src="' . $src . '" alt="InnovaGo" style="height:' . $height . 'px;width:auto;"></span>';
    }

    return '<span class="logo ' . $sizeClass . '"><span class="logo-word">'
        . '<span class="g1">In</span><span class="g2">n</span><span class="g3">o</span><span class="g4">va</span><span class="go">Go</span>'
        . '</span></span>';
}
