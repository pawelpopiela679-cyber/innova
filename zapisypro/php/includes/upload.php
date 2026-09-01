<?php
/** Zapis zdjęć (prowadzących, profilu) bezpośrednio na dysk — żadnej chmury,
 *  żadnego zewnętrznego storage, działa na każdym hostingu z zapisem na dysk. */

const ALLOWED_IMAGE_MIME = [
    'image/png' => 'png',
    'image/jpeg' => 'jpg',
    'image/webp' => 'webp',
    'image/gif' => 'gif',
];

function save_uploaded_image(array $file, string $subfolder, string $baseName): ?string
{
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Błąd podczas przesyłania pliku (kod ' . $file['error'] . ').');
    }
    if ($file['size'] > UPLOAD_MAX_BYTES) {
        throw new RuntimeException('Plik jest za duży — maksymalny rozmiar to 3 MB.');
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!isset(ALLOWED_IMAGE_MIME[$mime])) {
        throw new RuntimeException('Nieobsługiwany format pliku — dozwolone: PNG, JPG, WEBP, GIF.');
    }
    $ext = ALLOWED_IMAGE_MIME[$mime];

    $safeBase = preg_replace('/[^a-z0-9\-]+/i', '-', $baseName);
    $filename = $safeBase . '-' . time() . '.' . $ext;

    $dir = __DIR__ . '/../uploads/' . $subfolder;
    if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
        throw new RuntimeException('Nie udało się utworzyć folderu na zdjęcia — sprawdź uprawnienia zapisu.');
    }

    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $filename)) {
        throw new RuntimeException('Nie udało się zapisać zdjęcia na dysku.');
    }

    return 'uploads/' . $subfolder . '/' . $filename;
}

function delete_uploaded_file(?string $publicPath): void
{
    if (!$publicPath || !str_starts_with($publicPath, 'uploads/')) {
        return;
    }
    $full = __DIR__ . '/../' . $publicPath;
    if (is_file($full)) {
        @unlink($full);
    }
}
