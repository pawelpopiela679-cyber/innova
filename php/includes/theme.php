<?php
/** Kolory strony edytowalne przez master admina w admin-wyglad.php (tabela
 *  site_settings, jeden wiersz o id="singleton"), z domyślnymi wartościami
 *  identycznymi jak w wersji Next.js (oliwkowy/beżowy/pudrowy róż). */

const DEFAULT_THEME = [
    'background' => '#efe4cf',
    'foreground' => '#4a4326',
    'surface' => '#f8f3e6',
    'border' => '#e2d3ac',
    'primary' => '#7d7a4a',
    'primaryLight' => '#b3af86',
    'accent' => '#c9848a',
    'gold' => '#c2a05e',
    'muted' => '#8a7f5c',
];

function get_theme(): array
{
    try {
        $row = db()->query("SELECT * FROM site_settings WHERE id = 'singleton'")->fetch();
    } catch (Throwable $e) {
        return DEFAULT_THEME; // tabela jeszcze nie istnieje (przed install.php)
    }
    if (!$row) {
        return DEFAULT_THEME;
    }
    return [
        'background' => $row['background'],
        'foreground' => $row['foreground'],
        'surface' => $row['surface'],
        'border' => $row['border'],
        'primary' => $row['primary_color'],
        'primaryLight' => $row['primary_light'],
        'accent' => $row['accent'],
        'gold' => $row['gold'],
        'muted' => $row['muted'],
    ];
}

/** Zapisuje (upsert) nowe kolory. */
function save_theme(array $theme): void
{
    $pdo = db();
    if (db_is_mysql()) {
        $sql = "INSERT INTO site_settings (id, background, foreground, surface, border, primary_color, primary_light, accent, gold, muted, updated_at)
                VALUES ('singleton', ?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
                ON DUPLICATE KEY UPDATE background=VALUES(background), foreground=VALUES(foreground),
                  surface=VALUES(surface), border=VALUES(border), primary_color=VALUES(primary_color),
                  primary_light=VALUES(primary_light), accent=VALUES(accent), gold=VALUES(gold),
                  muted=VALUES(muted), updated_at=CURRENT_TIMESTAMP";
    } else {
        $sql = "INSERT OR REPLACE INTO site_settings (id, background, foreground, surface, border, primary_color, primary_light, accent, gold, muted, updated_at)
                VALUES ('singleton', ?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)";
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $theme['background'], $theme['foreground'], $theme['surface'], $theme['border'],
        $theme['primary'], $theme['primaryLight'], $theme['accent'], $theme['gold'], $theme['muted'],
    ]);
}

function reset_theme(): void
{
    db()->exec("DELETE FROM site_settings WHERE id = 'singleton'");
}

/** Blok <style> nadpisujący domyślne kolory z assets/style.css — identyczny
 *  mechanizm jak inline <style> w RootLayout wersji Next.js. */
function theme_css_vars(array $theme): string
{
    return ":root {"
        . "--background:{$theme['background']};"
        . "--foreground:{$theme['foreground']};"
        . "--surface:{$theme['surface']};"
        . "--border:{$theme['border']};"
        . "--primary:{$theme['primary']};"
        . "--sage:{$theme['primary']};"
        . "--sage-light:{$theme['primaryLight']};"
        . "--coral:{$theme['accent']};"
        . "--mustard:{$theme['gold']};"
        . "--muted:{$theme['muted']};"
        . "}";
}
