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

// Determine target type: hero, team, links_hero
$target = $_POST['target'] ?? '';

// Validate file
if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['status' => 'error', 'message' => 'Geen bestand ontvangen']);
    exit;
}

$file = $_FILES['file'];

// Process upload depending on the target
switch ($target) {
    

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

    case 'team':
        // Upload team member photo and update team.json
        $memberId = $_POST['member_id'] ?? '';
        $teamFile = defined('TEAM_FILE') ? TEAM_FILE : (defined('DATA_DIR') ? DATA_DIR . '/team/team.json' : __DIR__ . '/data/team/team.json');
        if ($memberId === '' || !file_exists($teamFile)) {
            echo json_encode(['status' => 'error', 'message' => 'Teamlid niet gevonden']);
            exit;
        }
        $destDir = (defined('ASSETS_DIR') ? ASSETS_DIR : (__DIR__ . '/assets')) . '/team';
        $result = handleImageUpload($file, $destDir, 1200);
        if (!$result) { echo json_encode(['status' => 'error', 'message' => 'Upload mislukt']); exit; }
        $paths = $result;
        $data = loadJsonFile($teamFile);
        if (!isset($data['members']) || !is_array($data['members'])) $data['members'] = [];
        foreach ($data['members'] as &$m) {
            if (($m['id'] ?? '') === $memberId) {
                // delete old files if inside project
                foreach (['image','webp'] as $k) {
                    if (!empty($m[$k])) {
                        $p = $m[$k];
                        $abs = (strpos($p, ':') !== false || str_starts_with($p, '/')) ? $p : (BASE_DIR . '/' . $p);
                        if (file_exists($abs)) { @unlink($abs); }
                    }
                }
                $m['image'] = $paths['path'];
                $m['webp'] = $paths['webp'];
                break;
            }
        }
        unset($m);
        saveJsonFile($teamFile, $data);
        echo json_encode(['status' => 'success', 'path' => $paths['path'], 'webp' => $paths['webp']]);
        exit;

    case 'links_hero':
        // Upload hero image for Useful Links page
        $linksFile = defined('LINKS_FILE') ? LINKS_FILE : (defined('DATA_DIR') ? DATA_DIR . '/links/links.json' : __DIR__ . '/data/links/links.json');
        $destDir = (defined('ASSETS_DIR') ? ASSETS_DIR : (__DIR__ . '/assets')) . '/images';
        $result = handleImageUpload($file, $destDir, 1920);
        if (!$result) { echo json_encode(['status' => 'error', 'message' => 'Upload mislukt']); exit; }
        $paths = $result;
        $data = loadJsonFile($linksFile);
        if (!isset($data['hero'])) $data['hero'] = [];
        $data['hero']['image'] = $paths['path'];
        $data['hero']['webp'] = $paths['webp'];
        saveJsonFile($linksFile, $data);
        echo json_encode(['status' => 'success', 'path' => $paths['path'], 'webp' => $paths['webp']]);
        exit;

    default:
        echo json_encode(['status' => 'error', 'message' => 'Ongeldig doel']);
        exit;
}
