<?php
/** Skróty i drobne funkcje pomocnicze używane na każdej stronie. */

/** Bezpieczne wypisanie tekstu w HTML (skrót na htmlspecialchars). */
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/** Przekierowanie + natychmiastowe zakończenie skryptu. Musi być wywołane
 *  zanim cokolwiek zostanie wypisane (żadnego echo/HTML przed redirect()). */
function redirect(string $path): never
{
    if (!str_starts_with($path, 'http://') && !str_starts_with($path, 'https://')) {
        $base = rtrim((string) parse_base_path(), '/');
        $path = $base . '/' . ltrim($path, '/');
    }
    header('Location: ' . $path);
    exit;
}

/** Katalog, w którym leży aplikacja, względem katalogu głównego domeny —
 *  potrzebne, gdy aplikacja siedzi w podfolderze (np. domena.pl/system/). */
function parse_base_path(): string
{
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    return rtrim(str_replace('\\', '/', dirname($script)), '/');
}

/** Buduje URL względem katalogu aplikacji, np. url('kalendarz.php?view=day'). */
function url(string $path = ''): string
{
    $base = parse_base_path();
    return $base . '/' . ltrim($path, '/');
}

/** Przekierowanie na $path z doklejonymi parametrami GET (błędy, komunikaty). */
function redirect_with(string $path, array $params): never
{
    $sep = str_contains($path, '?') ? '&' : '?';
    redirect($path . ($params ? $sep . http_build_query($params) : ''));
}

/** Token CSRF trzymany w sesji — jeden na całą sesję logowania. */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Ukryte pole do wklejenia w każdym formularzu POST. */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/** Sprawdza token CSRF z $_POST; przy braku/niezgodności przerywa z 400. */
function csrf_check(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
        http_response_code(400);
        exit('Nieprawidłowy token bezpieczeństwa (CSRF). Wróć i spróbuj ponownie.');
    }
}

/** Wiek w pełnych latach na dany dzień (domyślnie dziś) — jak w wersji Next.js. */
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
const PL_DAYS_SHORT = [
    'Mon' => 'Pon', 'Tue' => 'Wt', 'Wed' => 'Śr', 'Thu' => 'Czw', 'Fri' => 'Pt', 'Sat' => 'Sob', 'Sun' => 'Nd',
];

/** "14 września 2026" — bez zależności od rozszerzenia intl (nie zawsze
 *  włączone na hostingu współdzielonym). */
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

/** Skrócony format dnia tygodnia + data, np. "poniedziałek 14.09" (widoki tygodniowe). */
function format_pl_weekday_short(string $datetime): string
{
    $d = new DateTime($datetime);
    return PL_DAYS[$d->format('l')] . ' ' . $d->format('d.m');
}

function h_m(string $datetime): string
{
    return (new DateTime($datetime))->format('H:i');
}

/** Ikony per rodzaj zajęć, jak w wersji Next.js (src/lib/class-type-icons.ts). */
const CLASS_TYPE_ICONS = [
    'ENGLISH' => '🔤', 'THEATER' => '🎭', 'ROBOTICS' => '🤖',
    'CREATIVE' => '🧶', 'MATH' => '🧮', 'SCIENCE' => '🧪', 'OPEN_DAY' => '🎉',
];
function class_icon(string $key): string
{
    return CLASS_TYPE_ICONS[$key] ?? '⭐';
}

/** ?, ?, ? placeholders dla PDO IN (...) z tablicą wartości. */
function in_placeholders(array $values): string
{
    return implode(',', array_fill(0, count($values), '?'));
}

/**
 * Wyświetla logo — jeśli w php/logo.png jest prawdziwy plik (wgrany ręcznie,
 * patrz README_PHP.md), pokazuje dokładnie ten plik. W przeciwnym razie
 * (i tylko wtedy) pokazuje odtworzony w CSS wordmark "INNOVA". W PHP, w
 * odróżnieniu od wersji Next.js, nie ma wyścigu przeglądarka/hydratacja —
 * sprawdzamy plik po prostu na serwerze przed wygenerowaniem HTML.
 */
function render_logo(string $size = 'md', bool $withSubtitle = false): string
{
    $sizeClass = 'logo-' . $size;
    $heights = ['sm' => 36, 'md' => 60, 'lg' => 110];
    $height = $heights[$size] ?? 60;

    if (is_file(__DIR__ . '/../logo.png')) {
        // Prawdziwy plik logo ma dopisek "Pracownia kreatywno-edukacyjna" już
        // wbudowany w grafikę — nie dokładamy go drugi raz pod spodem
        // (w przeciwieństwie do odtworzonej w kodzie wersji zastępczej niżej).
        $src = e(url('logo.png')) . '?v=' . filemtime(__DIR__ . '/../logo.png');
        $img = '<img src="' . $src . '" alt="INNOVA — Pracownia kreatywno-edukacyjna" style="height:' . $height . 'px;width:auto;">';
        return '<span class="logo ' . $sizeClass . '">' . $img . '</span>';
    }

    $sub = $withSubtitle ? '<span class="logo-sub"><span class="line"></span>Pracownia kreatywno-edukacyjna<span class="line"></span></span>' : '';
    return '<span class="logo ' . $sizeClass . '">'
        . '<span class="logo-word"><span class="g1">IN</span><span class="g2">N</span><span class="g3">O</span><span class="g4">VA</span></span>'
        . $sub . '</span>';
}

/**
 * Ścieżka (względna, BEZ przepuszczenia przez url()), pod którą mają
 * prowadzić przyciski "Zapisz się"/"Zapisz dziecko": zalogowanego rodzica
 * od razu do formularza zapisu, a gościa najpierw do założenia konta
 * (z powrotem do zapisu zaraz po rejestracji/logowaniu). Osobno od
 * signup_url() — bo nb_tab_defs() sam przepuszcza wszystkie adresy zakładek
 * przez url(), więc potrzebuje surowej ścieżki, a nie gotowego URL-a.
 */
function signup_path(): string
{
    return current_user() ? 'zapisz.php' : 'rejestracja.php?next=' . urlencode(url('zapisz.php'));
}

/** Gotowy URL (przepuszczony przez url()) do użycia bezpośrednio w href. */
function signup_url(): string
{
    return url(signup_path());
}

/** Pobiera aktualnie zalogowanego użytkownika (albo null) — patrz auth.php. */
