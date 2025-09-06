<?php
// upload_ajax.php
// Endpoint for asynchronous image uploads.

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
        $contentFile = CONTENT_FILE;
        $contentData = loadJsonFile($contentFile);
        $result = handleImageUpload($file, ASSETS_DIR . '/images');
          if (!$result) {
              echo json_encode(['status' => 'error', 'message' => 'Upload mislukt']);
              exit;
          }
        $paths = $result;
        
        // Delete existing files
        if (isset($contentData['hero']['image'])) { @unlink(BASE_DIR . '/' . $contentData['hero']['image']); }
        if (isset($contentData['hero']['webp'])) { @unlink(BASE_DIR . '/' . $contentData['hero']['webp']); }

        $contentData['hero']['image'] = $paths['path'];
        $contentData['hero']['webp'] = $paths['webp'];
        
        saveJsonFile($contentFile, $contentData);
        echo json_encode(['status' => 'success', 'path' => $paths['path'], 'webp' => $paths['webp']]);
        exit;

    case 'team':
        $memberId = $_POST['member_id'] ?? '';
        $teamFile = TEAM_FILE;
        if ($memberId === '' || !file_exists($teamFile)) {
            echo json_encode(['status' => 'error', 'message' => 'Teamlid niet gevonden of team.json bestaat niet.']);
            exit;
        }
        $destDir = ASSETS_DIR . '/team';
        $result = handleImageUpload($file, $destDir, 1200);
        if (!$result) { echo json_encode(['status' => 'error', 'message' => 'Upload mislukt']); exit; }
        
        $paths = $result;
        $data = loadJsonFile($teamFile);
        if (!isset($data['members']) || !is_array($data['members'])) $data['members'] = [];
        
        foreach ($data['members'] as &$m) {
            if (($m['id'] ?? '') === $memberId) {
                if (!empty($m['image'])) { @unlink(BASE_DIR . '/' . $m['image']); }
                if (!empty($m['webp'])) { @unlink(BASE_DIR . '/' . $m['webp']); }
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
    case 'practice_hero':
    case 'phones_hero':
        $destDir = ASSETS_DIR . '/images';
        $result = handleImageUpload($file, $destDir, 1920);
        if (!$result) { echo json_encode(['status' => 'error', 'message' => 'Upload mislukt']); exit; }
        
        $paths = $result;
        $fileToUpdate = '';
        $dataKey = '';

        if ($target === 'links_hero') {
            $fileToUpdate = LINKS_FILE;
            $dataKey = 'hero';
        } elseif ($target === 'practice_hero') {
            $fileToUpdate = PRACTICE_FILE;
            $dataKey = 'hero';
        } elseif ($target === 'phones_hero') {
            $fileToUpdate = CONTENT_FILE;
            $dataKey = 'phones_hero';
        }

        $data = loadJsonFile($fileToUpdate);
        if (!isset($data[$dataKey])) $data[$dataKey] = [];
        
        if (!empty($data[$dataKey]['image'])) { @unlink(BASE_DIR . '/' . $data[$dataKey]['image']); }
        if (!empty($data[$dataKey]['webp'])) { @unlink(BASE_DIR . '/' . $data[$dataKey]['webp']); }

        $data[$dataKey]['image'] = $paths['path'];
        $data[$dataKey]['webp'] = $paths['webp'];
        
        saveJsonFile($fileToUpdate, $data);
        echo json_encode(['status' => 'success', 'path' => $paths['path'], 'webp' => $paths['webp']]);
        exit;

    default:
        echo json_encode(['status' => 'error', 'message' => 'Ongeldig doel']);
        exit;
}
