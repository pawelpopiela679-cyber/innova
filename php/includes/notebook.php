<?php
/**
 * Dane i helpery do stylu "Zeszyt szkolny" (assets/notebook.css) — osobne
 * od CLASS_TYPE_ICONS/emoji używanych w reszcie serwisu.
 *
 * Prawdziwe grafiki dostarczone przez właścicielkę pracowni (fiszki i
 * zakładki) mieszkają w assets/img/{tabs,classes,banners}/ — patrz
 * NB_CLASS_ART poniżej. Matematyka nie miała odpowiednika w dostarczonym
 * komplecie, więc dla niej (i jako ogólny fallback) zostaje odrysowana
 * kreską ikonka SVG z NB_SKETCH_ICONS.
 */

/** Prawdziwa grafika fiszki na dany rodzaj zajęć (assets/img/classes/*.png). */
const NB_CLASS_ART = [
    'ENGLISH' => 'ENGLISH.png',
    'ROBOTICS' => 'ROBOTICS.png',
    'CREATIVE' => 'CREATIVE.png',
    'SCIENCE' => 'SCIENCE.png',
    'THEATER' => 'THEATER.png',
    'MATH' => 'MATH.png',
];

function nb_class_art_url(string $key): ?string
{
    return isset(NB_CLASS_ART[$key]) ? url('assets/img/classes/' . NB_CLASS_ART[$key]) : null;
}

/** [tło, kolor kreski/tekstu] — jasna, "papierowa" paleta tej koncepcji. */
const NB_PASTELS = [
    'ENGLISH' => ['#cfe6f7', '#2f6ea3'],
    'ROBOTICS' => ['#dcebd6', '#4d7a3f'],
    'CREATIVE' => ['#faedc4', '#b8872a'],
    'SCIENCE' => ['#d3f0df', '#2f9166'],
    'THEATER' => ['#f7d9e6', '#c15a86'],
    'MATH' => ['#f0e2c9', '#a8781f'],
];

/** Kreski (bez <svg>/<path> zamykającego wrappera) per rodzaj zajęć. */
const NB_SKETCH_ICONS = [
    // Big Ben + flaga (Angielski) — patrz ustalenia w rozmowie o makiecie.
    'ENGLISH' => '<rect x="8.5" y="9" width="7" height="12"/><path d="M8.5 9L12 3l3.5 6"/><circle cx="12" cy="7.5" r="1.1" fill="currentColor"/><path d="M10.5 13h3M10.5 16h3M10.5 19h3"/><path d="M7 21h10"/><path d="M18 5v9"/><path d="M18 5l4.5 1.8L18 8.6z" fill="currentColor"/>',
    'ROBOTICS' => '<rect x="6" y="9" width="12" height="10" rx="2"/><circle cx="9.5" cy="14" r="1.4"/><circle cx="14.5" cy="14" r="1.4"/><path d="M12 9V5M9 5h6"/><path d="M3 13h3M18 13h3"/>',
    'CREATIVE' => '<path d="M12 3a9 9 0 100 18c1.4 0 1.6-1.6.6-2.3-.9-.7-.5-2.2.7-2.2H16a4 4 0 004-4c0-5-3.6-9.5-8-9.5z"/><circle cx="8" cy="10" r="1"/><circle cx="12" cy="7.5" r="1"/><circle cx="16" cy="10" r="1"/>',
    'SCIENCE' => '<path d="M9 3h6M10 3v6l-5.5 8.5A2 2 0 006.2 21h11.6a2 2 0 001.7-3.5L14 9V3"/><path d="M8 15h8"/>',
    'THEATER' => '<path d="M8 4c2 2 2 4 0 6-2 2-2 4 0 6"/><circle cx="7" cy="4" r="1"/><path d="M16 4c-2 2-2 4 0 6 2 2 2 4 0 6"/><circle cx="17" cy="4" r="1"/>',
    'MATH' => '<path d="M6 6h5M6 10h3M14 15h4M16 13v4"/><path d="M6 16l4-4M6 12l4 4"/>',
];

function nb_pastel(string $key): array
{
    return NB_PASTELS[$key] ?? ['#eee7d2', '#7d7a4a'];
}

/** Pełny <svg> z ikoną szkicową danego rodzaju zajęć. */
function nb_icon_svg(string $key, string $class = 'nb-icon'): string
{
    $paths = NB_SKETCH_ICONS[$key] ?? '<circle cx="12" cy="12" r="8"/>';
    [, $ink] = nb_pastel($key);
    return '<svg class="' . e($class) . '" viewBox="0 0 24 24" fill="none" stroke="' . e($ink) . '" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">' . $paths . '</svg>';
}

/** Definicja 6 realnych zakładek — jedna lista, żeby nawigacja była spójna
 *  na wszystkich stronach ze stylem "zeszytu". */
function nb_tab_defs(): array
{
    return [
        ['key' => 'home', 'label' => 'Strona główna', 'url' => 'index.php',
            'icon' => '<path d="M3 11l9-7 9 7"/><path d="M5 10v9a1 1 0 001 1h4v-6h4v6h4a1 1 0 001-1v-9"/>'],
        ['key' => 'about', 'label' => 'O nas', 'url' => 'poznaj-nas.php',
            'icon' => '<circle cx="9" cy="8" r="3"/><path d="M3 20c0-3.3 2.7-6 6-6s6 2.7 6 6"/><circle cx="17" cy="9" r="2.4"/><path d="M15.5 14.2c2.3.3 4 2.4 4 5.8"/>'],
        ['key' => 'classes', 'label' => 'Zajęcia', 'url' => 'zajecia.php',
            'icon' => '<path d="M4 5.5C4 4.7 4.7 4 5.5 4H12v16H5.5A1.5 1.5 0 014 18.5v-13z"/><path d="M20 5.5c0-.8-.7-1.5-1.5-1.5H12v16h6.5a1.5 1.5 0 001.5-1.5v-13z"/>'],
        ['key' => 'schedule', 'label' => 'Grafik', 'url' => 'kalendarz.php',
            'icon' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/>'],
        ['key' => 'signup', 'label' => 'Zapisy', 'url' => 'rejestracja.php',
            'icon' => '<path d="M4 20l1.2-4.2L16.6 4.4a1.5 1.5 0 012.1 0l1 1a1.5 1.5 0 010 2.1L8.3 18.8 4 20z"/>'],
        ['key' => 'contact', 'label' => 'Kontakt', 'url' => 'kontakt.php',
            'icon' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 6l9 7 9-7"/>'],
    ];
}

/** Zakładki po prawej krawędzi kartki — prawdziwe grafiki (assets/img/tabs). */
function nb_render_tabs(string $active): string
{
    $out = '<nav class="nb-tabs">';
    foreach (nb_tab_defs() as $t) {
        $cls = 'nb-tab tab-' . $t['key'] . ($t['key'] === $active ? ' active' : '');
        $out .= '<a href="' . e(url($t['url'])) . '" class="' . $cls . '">'
            . '<img src="' . e(url('assets/img/tabs/' . $t['key'] . '.png')) . '" alt="' . e($t['label']) . '" loading="lazy"></a>';
    }
    return $out . '</nav>';
}

/** Zwykłe linki nawigacyjne w górnej belce (te same 6 stron). */
function nb_render_toplinks(string $active): string
{
    $out = '<div class="nb-toplinks">';
    foreach (nb_tab_defs() as $t) {
        $cls = $t['key'] === $active ? ' class="current"' : '';
        $out .= '<a href="' . e(url($t['url'])) . '"' . $cls . '>' . e($t['label']) . '</a>';
    }
    return $out . '</div>';
}
