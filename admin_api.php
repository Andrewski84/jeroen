<?php
// admin_api.php - JSON-only admin endpoint for Groepspraktijk Elewijt
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';

header('Content-Type: application/json; charset=utf-8');

function jexit($ok, $data = []) {
    echo json_encode($ok ? array_merge(['status' => 'success'], $data) : array_merge(['status' => 'error'], $data), JSON_UNESCAPED_UNICODE);
    exit;
}

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    jexit(false, ['message' => 'Niet gemachtigd']);
}

$action = $_POST['action'] ?? ($_GET['action'] ?? '');

// Resolve files
$contentFile = defined('CONTENT_FILE') ? CONTENT_FILE : (__DIR__ . '/data/content.json');
$teamFile = defined('TEAM_FILE') ? TEAM_FILE : (defined('DATA_DIR') ? DATA_DIR . '/team/team.json' : __DIR__ . '/data/team/team.json');
$practiceFile = defined('PRACTICE_FILE') ? PRACTICE_FILE : (defined('DATA_DIR') ? DATA_DIR . '/practice/practice.json' : __DIR__ . '/data/practice/practice.json');
$linksFile = defined('LINKS_FILE') ? LINKS_FILE : (defined('DATA_DIR') ? DATA_DIR . '/links/links.json' : __DIR__ . '/data/links/links.json');

$content = loadJsonFile($contentFile);
$team = loadJsonFile($teamFile);
$practice = loadJsonFile($practiceFile);
$links = loadJsonFile($linksFile);

switch ($action) {
    // Team
    case 'add_team_member':
        $name = trim($_POST['name'] ?? '');
        $role = trim($_POST['role'] ?? '');
        $appt = trim($_POST['appointment_url'] ?? '');
        if ($name === '' || $role === '') jexit(false, ['message' => 'Naam en functie verplicht']);
        if (!isset($team['members']) || !is_array($team['members'])) $team['members'] = [];
        $id = uniqid('tm_', true);
        $team['members'][] = ['id' => $id, 'name' => $name, 'role' => $role, 'appointment_url' => $appt, 'image' => '', 'webp' => ''];
        $dir = dirname($teamFile);
        if (!is_dir($dir)) { @mkdir($dir, 0755, true); }
        if (!saveJsonFile($teamFile, $team)) {
            jexit(false, ['message' => 'Schrijven naar team.json mislukt', 'file' => $teamFile]);
        }
        clearstatcache(true, $teamFile);
        jexit(true, ['member' => ['id' => $id, 'name' => $name, 'role' => $role, 'appointment_url' => $appt]]);

    case 'update_team_member':
        $id = $_POST['id'] ?? '';
        if ($id === '') jexit(false, ['message' => 'ID ontbreekt']);
        if (!isset($team['members']) || !is_array($team['members'])) $team['members'] = [];
        foreach ($team['members'] as &$m) {
            if (($m['id'] ?? '') === $id) {
                $m['name'] = trim($_POST['name'] ?? ($m['name'] ?? ''));
                $m['role'] = trim($_POST['role'] ?? ($m['role'] ?? ''));
                $m['appointment_url'] = trim($_POST['appointment_url'] ?? ($m['appointment_url'] ?? ''));
                break;
            }
        }
        unset($m);
        if (!saveJsonFile($teamFile, $team)) { jexit(false, ['message' => 'Opslaan teamlid mislukt']); }
        clearstatcache(true, $teamFile);
        jexit(true, ['id' => $id]);

    case 'delete_team_member':
        $id = $_POST['id'] ?? '';
        if ($id === '') jexit(false, ['message' => 'ID ontbreekt']);
        if (!empty($team['members'])) {
            foreach ($team['members'] as $i => $m) {
                if (($m['id'] ?? '') === $id) {
                    foreach (['image','webp'] as $k) {
                        if (!empty($m[$k])) {
                            $p = $m[$k];
                            $abs = (strpos($p, ':') !== false || str_starts_with($p, '/')) ? $p : (BASE_DIR . '/' . $p);
                            if (file_exists($abs)) { @unlink($abs); }
                        }
                    }
                    array_splice($team['members'], $i, 1);
                    break;
                }
            }
        }
        if (!saveJsonFile($teamFile, $team)) { jexit(false, ['message' => 'Verwijderen teamlid mislukt']); }
        clearstatcache(true, $teamFile);
        jexit(true, ['id' => $id]);

    // Praktijkinfo
    case 'save_practice_page':
        $slug = trim($_POST['slug'] ?? '');
        $title = trim($_POST['title'] ?? '');
        if (!isset($practice['pages']) || !is_array($practice['pages'])) $practice['pages'] = [];
        if ($slug === '') {
            $base = sanitizeThemeName($title !== '' ? $title : ('pagina-' . (count($practice['pages']) + 1)));
            $slug = $base; $i=2; while (isset($practice['pages'][$slug])) { $slug = $base.'-'.$i; $i++; }
        }
        $page = $practice['pages'][$slug] ?? [];
        $page['title'] = $title !== '' ? $title : ($page['title'] ?? $slug);
        $htmls = $_POST['card_html'] ?? [];
        $cards = [];
        if (is_array($htmls)) { foreach ($htmls as $h) { if (trim($h) !== '') $cards[] = ['html' => $h]; } }
        $page['cards'] = $cards;
        $practice['pages'][$slug] = $page;
        @mkdir(dirname($practiceFile), 0755, true);
        saveJsonFile($practiceFile, $practice);
        jexit(true, ['slug' => $slug, 'title' => $page['title']]);

    case 'delete_practice_page':
        $slug = trim($_POST['slug'] ?? '');
        if ($slug === '' || empty($practice['pages'][$slug])) jexit(false, ['message' => 'Pagina niet gevonden']);
        unset($practice['pages'][$slug]);
        saveJsonFile($practiceFile, $practice);
        jexit(true, ['slug' => $slug]);

    case 'reorder_practice_pages':
        $order = $_POST['order'] ?? [];
        $new = [];
        foreach ($order as $s) { if (isset($practice['pages'][$s])) $new[$s] = $practice['pages'][$s]; }
        foreach ($practice['pages'] as $s => $p) { if (!isset($new[$s])) $new[$s] = $p; }
        $practice['pages'] = $new;
        saveJsonFile($practiceFile, $practice);
        jexit(true);

    // Links
    case 'save_links':
        if (!isset($links['hero'])) $links['hero'] = [];
        $links['hero']['title'] = $_POST['hero_title'] ?? ($links['hero']['title'] ?? '');
        $labels = $_POST['link_label'] ?? [];
        $urls   = $_POST['link_url'] ?? [];
        $tels   = $_POST['link_tel'] ?? [];
        $ids    = $_POST['link_id'] ?? [];
        $items = [];
        $max = max(count((array)$labels), count((array)$urls), count((array)$tels));
        for ($i = 0; $i < $max; $i++) {
            $label = trim($labels[$i] ?? '');
            $url = trim($urls[$i] ?? '');
            $tel = trim($tels[$i] ?? '');
            if ($label !== '' && ($url !== '' || $tel !== '')) {
                $id = $ids[$i] ?? uniqid('link_', true);
                $items[] = ['id' => $id, 'label' => $label, 'url' => $url, 'tel' => $tel];
            }
        }
        $links['items'] = $items;
        @mkdir(dirname($linksFile), 0755, true);
        saveJsonFile($linksFile, $links);
        jexit(true);

    case 'reorder_links':
        $order = $_POST['order'] ?? [];
        $items = isset($links['items']) && is_array($links['items']) ? $links['items'] : [];
        $byId = [];
        foreach ($items as $it) { if (!empty($it['id'])) $byId[$it['id']] = $it; }
        $new = [];
        foreach ($order as $id) { if (isset($byId[$id])) $new[] = $byId[$id]; }
        foreach ($items as $it) { if (!in_array($it['id'] ?? '', $order, true)) $new[] = $it; }
        $links['items'] = $new;
        saveJsonFile($linksFile, $links);
        jexit(true);

    // Pinned
    case 'save_pinned':
        $ids = $_POST['pinned_id'] ?? [];
        $titles = $_POST['pinned_title'] ?? [];
        $texts = $_POST['pinned_text'] ?? [];
        $home = $_POST['pinned_scope_home'] ?? [];
        $teamScope = $_POST['pinned_scope_team'] ?? [];
        $practiceScope = $_POST['pinned_scope_practice'] ?? [];
        $linksScope = $_POST['pinned_scope_links'] ?? [];
        $all = $_POST['pinned_scope_all'] ?? [];
        $items = [];
        $n = max(count((array)$ids), count((array)$titles), count((array)$texts));
        for ($i = 0; $i < $n; $i++) {
            $pid = $ids[$i] ?? uniqid('pin_', true);
            $title = trim($titles[$i] ?? '');
            $text = $texts[$i] ?? '';
            if ($title === '' && trim(strip_tags($text)) === '') continue;
            $scope = [];
            if (in_array($pid, (array)$all, true)) { $scope = ['all']; }
            else {
                if (in_array($pid, (array)$home, true)) $scope[] = 'home';
                if (in_array($pid, (array)$teamScope, true)) $scope[] = 'team';
                if (in_array($pid, (array)$practiceScope, true)) $scope[] = 'practice';
                if (in_array($pid, (array)$linksScope, true)) $scope[] = 'links';
            }
            $items[] = [ 'id' => $pid, 'title' => $title, 'text' => $text, 'scope' => $scope ];
        }
        if (!isset($content['pinned']) || !is_array($content['pinned'])) $content['pinned'] = [];
        $content['pinned'] = $items;
        saveJsonFile($contentFile, $content);
        jexit(true);

    case 'reorder_pinned':
        $order = $_POST['order'] ?? [];
        if (!isset($content['pinned']) || !is_array($content['pinned'])) $content['pinned'] = [];
        $byId = [];
        foreach ($content['pinned'] as $p) { if (!empty($p['id'])) $byId[$p['id']] = $p; }
        $new = [];
        foreach ($order as $id) { if (isset($byId[$id])) $new[] = $byId[$id]; }
        foreach ($content['pinned'] as $p) { if (!in_array($p['id'] ?? '', $order, true)) $new[] = $p; }
        $content['pinned'] = $new;
        saveJsonFile($contentFile, $content);
        jexit(true);

    // Settings
    case 'save_settings':
        if (!isset($content['settings']) || !is_array($content['settings'])) $content['settings'] = [];
        $content['settings']['appointment_url'] = $_POST['appointment_url'] ?? ($content['settings']['appointment_url'] ?? '');
        $content['settings']['phone'] = $_POST['phone'] ?? ($content['settings']['phone'] ?? '');
        $content['settings']['address_line_1'] = $_POST['address_line_1'] ?? ($content['settings']['address_line_1'] ?? '');
        $content['settings']['address_line_2'] = $_POST['address_line_2'] ?? ($content['settings']['address_line_2'] ?? '');
        $content['settings']['map_embed'] = $_POST['map_embed'] ?? ($content['settings']['map_embed'] ?? '');
        $labels = $_POST['phone_label'] ?? [];
        $tels = $_POST['phone_tel'] ?? [];
        $list = [];
        $n = max(count((array)$labels), count((array)$tels));
        for ($i=0; $i<$n; $i++) {
            $l = trim($labels[$i] ?? ''); $t = trim($tels[$i] ?? '');
            if ($l !== '' && $t !== '') { $list[] = ['label' => $l, 'tel' => $t]; }
        }
        $content['settings']['footer_phones'] = $list;
        saveJsonFile($contentFile, $content);
        jexit(true);

    default:
        jexit(false, ['message' => 'Onbekende actie']);
}
