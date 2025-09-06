<?php
/**
 * Admin actions endpoint
 *
 * This file receives form submissions and AJAX posts from the admin panel
 * and applies changes to JSON data and configuration.
 */
session_start();
require_once 'helpers.php';
require_once 'config.php';

$publicActions = ['contact_form', 'request_password_reset'];
$isAjax = isset($_POST['ajax']) && $_POST['ajax'] === '1';
$action = $_POST['action'] ?? ($_GET['action'] ?? null);

if ($isAjax) {
    header('Content-Type: application/json; charset=utf-8');
}

if (!in_array($action, $publicActions)) {
    if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
        if (!$isAjax) {
            header('Location: login.php');
        } else {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'Authentication required.']);
        }
        exit;
    }
}

$contentFilePath = CONTENT_FILE;
$content = loadJsonFile($contentFilePath);
$teamFilePath = TEAM_FILE;
$practiceFilePath = PRACTICE_FILE;
$linksFilePath = LINKS_FILE;


switch ($action) {
    case 'update_content':
        $content['hero']['title'] = $_POST['hero_title'] ?? ($content['hero']['title'] ?? '');
        $content['hero']['body'] = $_POST['hero_body'] ?? ($content['hero']['body'] ?? '');
        $content['meta_title'] = $_POST['meta_title'] ?? ($content['meta_title'] ?? '');
        $content['meta_description'] = $_POST['meta_description'] ?? ($content['meta_description'] ?? '');
        if (!isset($content['welcome']) || !is_array($content['welcome'])) $content['welcome'] = [];
        $content['welcome']['title'] = $_POST['welcome_title'] ?? ($content['welcome']['title'] ?? '');
        $content['welcome']['text'] = $_POST['welcome_text'] ?? ($content['welcome']['text'] ?? '');
        $whtml = $_POST['welcome_card_html'] ?? [];
        $wcards = [];
        if (is_array($whtml)) {
            foreach ($whtml as $html) { if (trim($html) !== '') { $wcards[] = ['html' => $html]; } }
        }
        $content['welcome']['cards'] = $wcards;
        saveJsonFile($contentFilePath, $content);
        break;

    case 'change_password':
        $old_password = $_POST['old_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (!defined('ADMIN_PASSWORD_HASH') || !password_verify($old_password, ADMIN_PASSWORD_HASH)) {
            header('Location: ' . (defined('ADMIN_PANEL_FILE') ? ADMIN_PANEL_FILE : 'beheer-gpe-a4x7.php') . '?password_change=error_wrong#tab-security');
            exit;
        }
        if (empty($new_password) || $new_password !== $confirm_password) {
            header('Location: ' . (defined('ADMIN_PANEL_FILE') ? ADMIN_PANEL_FILE : 'beheer-gpe-a4x7.php') . '?password_change=error_mismatch#tab-security');
            exit;
        }

        $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
        $configLines = file('config.php');
        $newConfigContent = '';
        foreach ($configLines as $line) {
            if (str_starts_with(trim($line), "define('ADMIN_PASSWORD_HASH'")) {
                $newConfigContent .= "define('ADMIN_PASSWORD_HASH', '" . addslashes($new_hash) . "');\n";
            } else {
                $newConfigContent .= $line;
            }
        }

        if (file_put_contents('config.php', $newConfigContent)) {
            header('Location: ' . (defined('ADMIN_PANEL_FILE') ? ADMIN_PANEL_FILE : 'beheer-gpe-a4x7.php') . '?password_change=success#tab-security');
        } else {
            header('Location: ' . (defined('ADMIN_PANEL_FILE') ? ADMIN_PANEL_FILE : 'beheer-gpe-a4x7.php') . '?password_change=error_file#tab-security');
        }
        exit;

    case 'save_practice_hero':
        $data = loadJsonFile($practiceFilePath);
        if (!isset($data['hero']) || !is_array($data['hero'])) $data['hero'] = [];
        // Title is not saved here, only image via upload_ajax. This action is for form submission.
        // We can add a title field if needed in the future. For now, it just needs a form to submit.
        saveJsonFile($practiceFilePath, $data);
        break;

    case 'save_phones_hero':
        if (!isset($content['phones_hero']) || !is_array($content['phones_hero'])) $content['phones_hero'] = [];
        $content['phones_hero']['title'] = $_POST['phones_hero_title'] ?? '';
        saveJsonFile($contentFilePath, $content);
        break;

    // --- Team management ---
    case 'add_team_member':
        $name = trim($_POST['name'] ?? '');
        $role = trim($_POST['role'] ?? '');
        $appt = trim($_POST['appointment_url'] ?? '');
        $groupId = trim($_POST['group_id'] ?? '');
        $visible = isset($_POST['visible']);
        if ($name !== '' && $role !== '') {
            $data = loadJsonFile($teamFilePath);
            if (!isset($data['members']) || !is_array($data['members'])) $data['members'] = [];
            $id = uniqid('tm_', true);
            $data['members'][] = [
                'id' => $id,
                'name' => $name,
                'role' => $role,
                'appointment_url' => $appt,
                'group_id' => $groupId,
                'visible' => (bool)$visible,
                'image' => '',
                'webp' => ''
            ];
            @mkdir(dirname($teamFilePath), 0755, true);
            saveJsonFile($teamFilePath, $data);
        }
        break;

    case 'update_team_member':
        $id = $_POST['id'] ?? '';
        if ($id !== '') {
            $data = loadJsonFile($teamFilePath);
            if (isset($data['members']) && is_array($data['members'])) {
                foreach ($data['members'] as &$m) {
                    if (($m['id'] ?? '') === $id) {
                        $m['name'] = trim($_POST['name'] ?? ($m['name'] ?? ''));
                        $m['role'] = trim($_POST['role'] ?? ($m['role'] ?? ''));
                        $m['appointment_url'] = trim($_POST['appointment_url'] ?? ($m['appointment_url'] ?? ''));
                        $m['group_id'] = trim($_POST['group_id'] ?? ($m['group_id'] ?? ''));
                        $m['visible'] = isset($_POST['visible']);
                        break;
                    }
                }
                unset($m);
                saveJsonFile($teamFilePath, $data);
            }
        }
        break;

    case 'delete_team_member':
        $id = $_POST['id'] ?? '';
        if ($id !== '') {
            $data = loadJsonFile($teamFilePath);
            if (!empty($data['members'])) {
                $initialCount = count($data['members']);
                $data['members'] = array_values(array_filter($data['members'], function($m) use ($id) {
                    if (($m['id'] ?? '') === $id) {
                        foreach (['image','webp'] as $k) {
                            if (!empty($m[$k])) {
                                $absPath = BASE_DIR . '/' . toPublicPath($m[$k]);
                                if (file_exists($absPath)) { @unlink($absPath); }
                            }
                        }
                        return false;
                    }
                    return true;
                }));
                if (count($data['members']) < $initialCount) {
                    saveJsonFile($teamFilePath, $data);
                }
            }
        }
        break;

    // --- Team Groups management ---
    case 'add_team_group':
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $visible = isset($_POST['visible']);
        if ($name !== '') {
            $data = loadJsonFile($teamFilePath);
            if (!isset($data['groups']) || !is_array($data['groups'])) $data['groups'] = [];
            $gid = uniqid('grp_', true);
            $data['groups'][] = [ 'id' => $gid, 'name' => $name, 'description' => $description, 'visible' => $visible ];
            @mkdir(dirname($teamFilePath), 0755, true);
            saveJsonFile($teamFilePath, $data);
        }
        break;

    case 'update_team_group':
        $gid = $_POST['id'] ?? '';
        if ($gid !== '') {
            $data = loadJsonFile($teamFilePath);
            if (isset($data['groups']) && is_array($data['groups'])) {
                foreach ($data['groups'] as &$g) {
                    if (($g['id'] ?? '') === $gid) {
                        $g['name'] = trim($_POST['name'] ?? ($g['name'] ?? ''));
                        $g['description'] = trim($_POST['description'] ?? ($g['description'] ?? ''));
                        $g['visible'] = isset($_POST['visible']);
                        break;
                    }
                }
                unset($g);
                saveJsonFile($teamFilePath, $data);
            }
        }
        break;

    case 'delete_team_group':
        $gid = $_POST['id'] ?? '';
        if ($gid !== '') {
            $data = loadJsonFile($teamFilePath);
            if (!empty($data['groups'])) {
                $data['groups'] = array_values(array_filter($data['groups'], fn($g) => ($g['id'] ?? '') !== $gid));
            }
            if (!empty($data['members'])) {
                foreach ($data['members'] as &$m) { if (($m['group_id'] ?? '') === $gid) { $m['group_id'] = ''; } }
                unset($m);
            }
            saveJsonFile($teamFilePath, $data);
        }
        break;
    
    // --- Praktijkinfo management ---
    case 'save_practice_page':
        $slug = trim($_POST['slug'] ?? '');
        $title = trim($_POST['title'] ?? '');
        $data = loadJsonFile($practiceFilePath);
        if (!isset($data['pages']) || !is_array($data['pages'])) $data['pages'] = [];
        if ($slug === '') {
            $base = sanitizeThemeName($title !== '' ? $title : ('pagina-' . (count($data['pages']) + 1)));
            $slug = $base;
            $i = 2;
            while (isset($data['pages'][$slug])) { $slug = $base . '-' . $i; $i++; }
        }
        $page = $data['pages'][$slug] ?? [];
        $page['title'] = $title !== '' ? $title : ($page['title'] ?? $slug);
        $htmls = $_POST['card_html'] ?? [];
        $cards = [];
        if (is_array($htmls)) { foreach ($htmls as $h) { if (trim($h) !== '') $cards[] = ['html' => $h]; } }
        $page['cards'] = $cards;
        $data['pages'][$slug] = $page;
        @mkdir(dirname($practiceFilePath), 0755, true);
        saveJsonFile($practiceFilePath, $data);
        break;

    case 'delete_practice_page':
        $slug = trim($_POST['slug'] ?? '');
        if ($slug !== '') {
            $data = loadJsonFile($practiceFilePath);
            if (isset($data['pages'][$slug])) {
                unset($data['pages'][$slug]);
                saveJsonFile($practiceFilePath, $data);
            }
        }
        break;

    // --- Nuttige links management ---
    case 'save_links':
        $data = loadJsonFile($linksFilePath);
        if (!isset($data['hero'])) $data['hero'] = [];
        $data['hero']['title'] = $_POST['hero_title'] ?? ($data['hero']['title'] ?? '');
        $labels = $_POST['link_label'] ?? [];
        $urls   = $_POST['link_url'] ?? [];
        $tels   = $_POST['link_tel'] ?? [];
        $descs  = $_POST['link_desc'] ?? [];
        $cats   = $_POST['link_category'] ?? [];
        $ids    = $_POST['link_id'] ?? [];
        $items = [];
        $max = is_array($labels) ? count($labels) : 0;
        for ($i = 0; $i < $max; $i++) {
            $label = trim($labels[$i] ?? '');
            $url = trim($urls[$i] ?? '');
            $tel = trim($tels[$i] ?? '');
            $desc = trim($descs[$i] ?? '');
            $cat = trim($cats[$i] ?? '');
            if ($label !== '' && ($url !== '' || $tel !== '')) {
                $id = $ids[$i] ?? uniqid('link_', true);
                $items[] = ['id' => $id, 'label' => $label, 'url' => $url, 'tel' => $tel, 'description' => $desc, 'category' => $cat];
            }
        }
        $data['items'] = $items;
        @mkdir(dirname($linksFilePath), 0755, true);
        saveJsonFile($linksFilePath, $data);
        break;

    case 'save_pinned':
        $ids = $_POST['pinned_id'] ?? [];
        $titles = $_POST['pinned_title'] ?? [];
        $texts = $_POST['pinned_text'] ?? [];
        $home = $_POST['pinned_scope_home'] ?? [];
        $team = $_POST['pinned_scope_team'] ?? [];
        $practice = $_POST['pinned_scope_practice'] ?? [];
        $links = $_POST['pinned_scope_links'] ?? [];
        $phones = $_POST['pinned_scope_phones'] ?? [];
        $all = $_POST['pinned_scope_all'] ?? [];
        $items = [];
        $n = is_array($ids) ? count($ids) : 0;
        for ($i = 0; $i < $n; $i++) {
            $pid = $ids[$i] ?? uniqid('pin_', true);
            $title = trim($titles[$i] ?? '');
            $text = $texts[$i] ?? '';
            if ($title === '' && trim(strip_tags($text)) === '') continue;
            $scope = [];
            if (in_array($pid, (array)$all, true) && $pid !== '') { $scope = ['all']; }
            else {
                if (in_array($pid, (array)$home, true)) $scope[] = 'home';
                if (in_array($pid, (array)$team, true)) $scope[] = 'team';
                if (in_array($pid, (array)$practice, true)) $scope[] = 'practice';
                if (in_array($pid, (array)$links, true)) $scope[] = 'links';
                if (in_array($pid, (array)$phones, true)) $scope[] = 'phones';
            }
            $items[] = [ 'id' => $pid, 'title' => $title, 'text' => $text, 'scope' => $scope ];
        }
        $content['pinned'] = $items;
        saveJsonFile($contentFilePath, $content);
        break;
    
    case 'save_settings':
        if (!isset($content['settings']) || !is_array($content['settings'])) $content['settings'] = [];
        $content['settings']['appointment_url'] = $_POST['appointment_url'] ?? '';
        $content['settings']['phone'] = $_POST['phone'] ?? '';
        $content['settings']['address_line_1'] = $_POST['address_line_1'] ?? '';
        $content['settings']['address_line_2'] = $_POST['address_line_2'] ?? '';
        $content['settings']['map_embed'] = $_POST['map_embed'] ?? '';
        $labels = $_POST['phone_label'] ?? [];
        $tels = $_POST['phone_tel'] ?? [];
        $descs = $_POST['phone_desc'] ?? [];
        $urls  = $_POST['phone_url'] ?? [];
        $list = [];
        $n = is_array($labels) ? count($labels) : 0;
        for ($i=0; $i<$n; $i++) {
            $l = trim($labels[$i] ?? '');
            $t = trim($tels[$i] ?? '');
            $d = trim($descs[$i] ?? '');
            $u = trim($urls[$i] ?? '');
            if ($l !== '' && $t !== '') { $list[] = ['label' => $l, 'tel' => $t, 'desc' => $d, 'url' => $u]; }
        }
        $content['settings']['footer_phones'] = $list;
        saveJsonFile($contentFilePath, $content);
        break;
}

// Redirect back to the correct tab in the admin panel.
if (!$isAjax) {
    $hash = '#tab-homepage';
    $tabMap = [
        'add_team_member' => '#tab-team', 'update_team_member' => '#tab-team', 'delete_team_member' => '#tab-team',
        'add_team_group' => '#tab-team', 'update_team_group' => '#tab-team', 'delete_team_group' => '#tab-team',
        'save_practice_page' => '#tab-practice', 'delete_practice_page' => '#tab-practice', 'save_practice_hero' => '#tab-practice',
        'save_links' => '#tab-links', 'save_phones_hero' => '#tab-links',
        'save_settings' => '#tab-settings',
        'save_pinned' => '#tab-pinned',
    ];
    if (isset($tabMap[$action])) {
        $hash = $tabMap[$action];
    }
    
    $status = (strpos($action, 'delete') !== false || strpos($action, 'add') !== false || strpos($action, 'save') !== false) ? 'success' : '';
    header('Location: ' . (defined('ADMIN_PANEL_FILE') ? ADMIN_PANEL_FILE : 'beheer-gpe-a4x7.php') . '?save_status='.$status . $hash);
    exit;
}
