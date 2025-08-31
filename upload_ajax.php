<?php
// upload_ajax.php
// Endpoint for asynchronous image uploads with progress support.

session_start();
require_once 'helpers.php';
require_once 'config.php';

// Only allow logged-in admin to use this endpoint
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Niet gemachtigd']);
    exit;
}

header('Content-Type: application/json');

// Determine target type: portfolio, gallery, hero, bio
$target = $_POST['target'] ?? '';

// Validate file
if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['status' => 'error', 'message' => 'Geen bestand ontvangen']);
    exit;
}

$file = $_FILES['file'];

// Process upload depending on the target
switch ($target) {
    case 'portfolio':
        $theme = $_POST['theme'] ?? '';
        if ($theme === '') {
            echo json_encode(['status' => 'error', 'message' => 'Thema ontbreekt']);
            exit;
        }
        // Determine destination directory
          $destDir = ASSETS_DIR . '/portfolio/' . $theme;
        // Upload image and resize
          $result = handleImageUpload($file, $destDir, 1200);
          if (!$result) {
              echo json_encode(['status' => 'error', 'message' => 'Upload mislukt']);
              exit;
          }
          $paths = $result;
        // Update portfolio JSON
          $portfolioFile = PORTFOLIO_FILE;
        $portfolioData = loadJsonFile($portfolioFile);
        if (!isset($portfolioData['themes'][$theme])) {
            $portfolioData['themes'][$theme] = ['images' => []];
        }
        $portfolioData['themes'][$theme]['images'][] = [
            'path' => $paths['path'],
            'webp' => $paths['webp'],
            'title' => '',
            'description' => '',
            'featured' => false,
            'alt' => ''
        ];
        saveJsonFile($portfolioFile, $portfolioData);
        $index = count($portfolioData['themes'][$theme]['images']) - 1;
        echo json_encode(['status' => 'success', 'path' => $paths['path'], 'webp' => $paths['webp'], 'index' => $index]);
        exit;

    case 'gallery':
        $slug = $_POST['slug'] ?? '';
        if ($slug === '') {
            echo json_encode(['status' => 'error', 'message' => 'Galerij ontbreekt']);
            exit;
        }
          $galleryDir = GALLERY_ASSETS_DIR . '/' . $slug;
          $galleryFile = GALLERIES_DIR . '/' . $slug . '/gallery.json';
        if (!file_exists($galleryFile)) {
            echo json_encode(['status' => 'error', 'message' => 'Galerij niet gevonden']);
            exit;
        }
        // Upload and resize image
          $result = handleImageUpload($file, $galleryDir, 1200);
          if (!$result) {
              echo json_encode(['status' => 'error', 'message' => 'Upload mislukt']);
              exit;
          }
          $paths = $result;
        // Update gallery JSON
        $galleryData = loadJsonFile($galleryFile);
        $galleryData['photos'][] = [
            'path' => $paths['path'],
            'webp' => $paths['webp'],
            'favorite' => false,
            'comment' => '',
            'original_name' => $file['name']
        ];
        saveJsonFile($galleryFile, $galleryData);
        $index = count($galleryData['photos']) - 1;
        echo json_encode(['status' => 'success', 'path' => $paths['path'], 'webp' => $paths['webp'], 'index' => $index]);
        exit;

    case 'hero':
    case 'bio':
        // Both hero and bio images update CONTENT_FILE
          $contentFile = CONTENT_FILE;
        $contentData = loadJsonFile($contentFile);
        $section = ($target === 'hero') ? 'hero' : 'bio';
        // Upload with default max width (1920)
          $result = handleImageUpload($file, ASSETS_DIR . '/images');
          if (!$result) {
              echo json_encode(['status' => 'error', 'message' => 'Upload mislukt']);
              exit;
          }
          $paths = $result;
        // Delete existing files if present (support relative paths)
        if (isset($contentData[$section]['image'])) {
            $oldImg = $contentData[$section]['image'];
            $oldImgAbs = (strpos($oldImg, ':') !== false || str_starts_with($oldImg, '/')) ? $oldImg : (BASE_DIR . '/' . $oldImg);
            if (file_exists($oldImgAbs)) { unlink($oldImgAbs); }
        }
        if (isset($contentData[$section]['webp'])) {
            $oldWebp = $contentData[$section]['webp'];
            $oldWebpAbs = (strpos($oldWebp, ':') !== false || str_starts_with($oldWebp, '/')) ? $oldWebp : (BASE_DIR . '/' . $oldWebp);
            if (file_exists($oldWebpAbs)) { unlink($oldWebpAbs); }
        }
        $contentData[$section]['image'] = $paths['path'];
        $contentData[$section]['webp'] = $paths['webp'];
        saveJsonFile($contentFile, $contentData);
        echo json_encode(['status' => 'success', 'path' => $paths['path'], 'webp' => $paths['webp']]);
        exit;

    default:
        echo json_encode(['status' => 'error', 'message' => 'Ongeldig doel']);
        exit;
}
