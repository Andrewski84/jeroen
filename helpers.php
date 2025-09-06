<?php

/**
 * Schrijf een bericht naar het applicatielog.
 *
 * @param string $message Het logbericht.
 */
function appLog(string $message): void {
    $logFile = '/data/logs/app.log';
    $dir = dirname($logFile);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    error_log('[' . date('c') . '] ' . $message . PHP_EOL, 3, $logFile);
}

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
 * @return bool True bij succes, false bij een fout.
 */
function saveJsonFile(string $filePath, array $data): bool {
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (file_put_contents($filePath, $json, LOCK_EX) === false) {
        $error = error_get_last()['message'] ?? 'Unknown error';
        appLog("Failed to save JSON file {$filePath}: {$error}");
        return false;
    }
    return true;
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
        @mkdir($destinationPath, 0755, true);
    }

    list($width, $height) = getimagesize($file['tmp_name']);
    $newWidth = ($width > $maxWidth) ? $maxWidth : $width;
    $newHeight = (int)(($newWidth / $width) * $height);

    $sourceImage = null;
    switch ($file['type']) {
        case 'image/jpeg': $sourceImage = imagecreatefromjpeg($file['tmp_name']); break;
        case 'image/png': $sourceImage = imagecreatefrompng($file['tmp_name']); break;
        case 'image/gif': $sourceImage = imagecreatefromgif($file['tmp_name']); break;
        case 'image/webp': $sourceImage = imagecreatefromwebp($file['tmp_name']); break;
    }
    if (!$sourceImage) return null;

    $destinationImage = imagecreatetruecolor($newWidth, $newHeight);
    if ($file['type'] === 'image/png' || $file['type'] === 'image/gif') {
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

    return [
        'path' => toPublicPath($finalPath),
        'webp' => $webpPath ? toPublicPath($webpPath) : null,
    ];
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
    if (strpos($u, ' ') === false && strpos($u, '.') !== false) return 'https://' . $u;
    return $u;
}

/**
 * Reorders an array of associative arrays based on a given order of IDs.
 *
 * @param array $array The array to reorder (e.g., list of members).
 * @param array $order The array of IDs in the desired order.
 * @param string $key The key in the associative arrays that holds the ID.
 * @return array The reordered array.
 */
function reorder_array(array $array, array $order, string $key = 'id'): array {
    $indexed = [];
    foreach ($array as $item) {
        if (isset($item[$key])) {
            $indexed[$item[$key]] = $item;
        }
    }
    $reordered = [];
    foreach ($order as $id) {
        if (isset($indexed[$id])) {
            $reordered[] = $indexed[$id];
            unset($indexed[$id]);
        }
    }
    // Append any remaining items that were not in the order array
    return array_merge($reordered, array_values($indexed));
}
