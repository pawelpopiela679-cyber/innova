<?php
/** Kolory strony edytowalne przez właściciela organizacji (org_settings, jedna
 *  organizacja = jeden wiersz), z sensownym domyślnym motywem ZapisyPro. */

const DEFAULT_THEME = [
    'background' => '#f4f6fb',
    'foreground' => '#1b2340',
    'surface' => '#ffffff',
    'border' => '#e2e7f5',
    'primary' => '#4338ca',
    'primaryLight' => '#818cf8',
    'accent' => '#f97362',
    'muted' => '#6b7290',
];

function get_theme(?int $orgId): array
{
    if (!$orgId) {
        return DEFAULT_THEME;
    }
    try {
        $stmt = db()->prepare('SELECT * FROM org_settings WHERE org_id = ?');
        $stmt->execute([$orgId]);
        $row = $stmt->fetch();
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
        'muted' => $row['muted'],
    ];
}

function save_theme(int $orgId, array $theme): void
{
    $pdo = db();
    if (db_is_mysql()) {
        $sql = "INSERT INTO org_settings (org_id, background, foreground, surface, border, primary_color, primary_light, accent, muted, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
                ON DUPLICATE KEY UPDATE background=VALUES(background), foreground=VALUES(foreground),
                  surface=VALUES(surface), border=VALUES(border), primary_color=VALUES(primary_color),
                  primary_light=VALUES(primary_light), accent=VALUES(accent), muted=VALUES(muted),
                  updated_at=CURRENT_TIMESTAMP";
    } else {
        $sql = "INSERT OR REPLACE INTO org_settings (org_id, background, foreground, surface, border, primary_color, primary_light, accent, muted, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)";
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $orgId, $theme['background'], $theme['foreground'], $theme['surface'], $theme['border'],
        $theme['primary'], $theme['primaryLight'], $theme['accent'], $theme['muted'],
    ]);
}

/** Blok <style> nadpisujący domyślne kolory z assets/style.css. */
function theme_css_vars(array $theme): string
{
    return ":root {"
        . "--bg:{$theme['background']};"
        . "--fg:{$theme['foreground']};"
        . "--surface:{$theme['surface']};"
        . "--border:{$theme['border']};"
        . "--primary:{$theme['primary']};"
        . "--primary-light:{$theme['primaryLight']};"
        . "--accent:{$theme['accent']};"
        . "--muted:{$theme['muted']};"
        . "}";
}
