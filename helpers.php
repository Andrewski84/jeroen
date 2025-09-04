<?php

/**
 * Laadt en decodeert een JSON-bestand.
 * @param string $filePath Het pad naar het JSON-bestand.
 * @return array De gedecodeerde data of een lege array bij een fout.
 */
function loadJsonFile(string $filePath): array {
    if (!file_exists($filePath)) {
        return [];
    }
    $json = file_get_contents($filePath);
    return json_decode($json, true) ?? [];
}

/**
 * Slaat een array op als een JSON-bestand met een exclusieve lock om corruptie te voorkomen.
 * @param string $filePath Het pad naar het JSON-bestand.
 * @param array $data De data om op te slaan.
 * @return array ['ok' => true] bij succes of ['ok' => false, 'error' => string] bij een fout.
 */
function saveJsonFile(string $filePath, array $data): array {
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    // Gebruik LOCK_EX om te voorkomen dat meerdere processen tegelijk schrijven.
    $lastError = null;
    set_error_handler(function ($errno, $errstr) use (&$lastError) {
        $lastError = $errstr;
        return true;
    });
    $result = file_put_contents($filePath, $json, LOCK_EX);
    restore_error_handler();
    if ($result === false) {
        $err = $lastError ?? (error_get_last()['message'] ?? 'Onbekende fout');
        return ['ok' => false, 'error' => $err];
    }
    return ['ok' => true];
}

/**
 * Maakt een veilige, URL-vriendelijke naam voor een thema.
 * @param string $name De originele naam van het thema.
 * @return string De opgeschoonde naam.
 */
function sanitizeThemeName(string $name): string {
    $name = strtolower($name);
    $name = preg_replace('/[^a-z0-9\s-]/', '', $name); // Verwijder speciale tekens
    $name = preg_replace('/[\s-]+/', '-', $name); // Vervang spaties en streepjes door een enkel streepje
    $name = trim($name, '-');
    return $name;
}

/**
 * Convert an absolute filesystem path inside BASE_DIR to a web-relative path.
 * Always uses forward slashes for browser compatibility.
 */
function toPublicPath(string $path): string {
    $normalized = str_replace('\\', '/', $path);
    if (defined('BASE_DIR')) {
        $base = rtrim(str_replace('\\', '/', BASE_DIR), '/') . '/';
        if (strpos($normalized, $base) === 0) {
            $normalized = substr($normalized, strlen($base));
        }
    }
    // Ensure no accidental leading slash remains so paths are project-relative
    return ltrim($normalized, '/');
}

/**
 * Handle an uploaded image: validate, resize/compress and store it.
 *
 * @param array $file          File data from $_FILES.
 * @param string $destinationPath Target directory for the image.
 * @param int $maxWidth        Maximum width in pixels.
 * @return array|null          Paths for stored image and webp variant or null on failure.
 */
function handleImageUpload(array $file, string $destinationPath, int $maxWidth = 1920): ?array {
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($file['type'], $allowedTypes, true) || !in_array($extension, $allowedExtensions, true)) {
        return null;
    }

    $filename = uniqid('img_', true) . '.' . $extension;
    $finalPath = $destinationPath . '/' . $filename;

    if (!is_dir($destinationPath)) {
        mkdir($destinationPath, 0755, true);
    }

    list($width, $height) = getimagesize($file['tmp_name']);
    $newWidth = ($width > $maxWidth) ? $maxWidth : $width;
    $newHeight = ($newWidth / $width) * $height;

    switch ($file['type']) {
        case 'image/jpeg': $sourceImage = imagecreatefromjpeg($file['tmp_name']); break;
        case 'image/png': $sourceImage = imagecreatefrompng($file['tmp_name']); break;
        case 'image/gif': $sourceImage = imagecreatefromgif($file['tmp_name']); break;
        case 'image/webp': $sourceImage = imagecreatefromwebp($file['tmp_name']); break;
        default: return null;
    }

    $destinationImage = imagecreatetruecolor($newWidth, $newHeight);
    if ($file['type'] === 'image/png') {
        imagealphablending($destinationImage, false);
        imagesavealpha($destinationImage, true);
        $transparent = imagecolorallocatealpha($destinationImage, 255, 255, 255, 127);
        imagefilledrectangle($destinationImage, 0, 0, $newWidth, $newHeight, $transparent);
    }

    imagecopyresampled($destinationImage, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

    switch ($file['type']) {
        case 'image/jpeg': imagejpeg($destinationImage, $finalPath, 85); break;
        case 'image/png': imagepng($destinationImage, $finalPath, 9); break;
        case 'image/gif': imagegif($destinationImage, $finalPath); break;
        case 'image/webp': imagewebp($destinationImage, $finalPath, 85); break;
    }

    $webpPath = null;
    if ($file['type'] !== 'image/webp' && function_exists('imagewebp')) {
        $webpFilename = pathinfo($filename, PATHINFO_FILENAME) . '.webp';
        $webpPath = $destinationPath . '/' . $webpFilename;
        imagewebp($destinationImage, $webpPath, 85);
    }

    imagedestroy($sourceImage);
    imagedestroy($destinationImage);

    // Return web-friendly, project-relative paths
    return [
        'path' => toPublicPath($finalPath),
        'webp' => $webpPath ? toPublicPath($webpPath) : null,
    ];
}

/**
 * Append a mail delivery attempt to a JSON log.
 * The log path is controlled by MAIL_LOG_FILE. Best-effort only; errors are suppressed.
 */
function logMailAttempt(array $entry): void {
    try {
        $file = defined('MAIL_LOG_FILE') ? MAIL_LOG_FILE : (defined('DATA_DIR') ? DATA_DIR . '/mail_log.json' : __DIR__ . '/data/mail_log.json');
        $entry['ts'] = date('c');
        $dir = dirname($file);
        if (!is_dir($dir)) { @mkdir($dir, 0755, true); }
        $list = file_exists($file) ? (json_decode(@file_get_contents($file), true) ?: []) : [];
        if (!is_array($list)) { $list = []; }
        $list[] = $entry;
        @file_put_contents($file, json_encode($list, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
    } catch (\Throwable $e) { /* ignore logging errors */ }
}

/**
 * Normalize user-entered URLs. If it starts with 'www.' or lacks a scheme but
 * looks like a domain, prefix with 'https://'. Preserve tel: links.
 */
function safeUrl(string $url): string {
    $u = trim($url);
    if ($u === '' ) return '';
    if (stripos($u, 'tel:') === 0) return $u;
    if (preg_match('~^https?://~i', $u)) return $u;
    if (stripos($u, 'www.') === 0) return 'https://' . $u;
    // If it has a dot and no spaces, assume domain-like
    if (strpos($u, ' ') === false && strpos($u, '.') !== false) return 'https://' . $u;
    return $u;
}
