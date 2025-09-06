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

    
    case 'contact_form':
        $hp = trim($_POST['address2'] ?? '');
        if ($hp !== '') { header('Location: index.php?sent=0'); exit; }
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $message = trim($_POST['message'] ?? '');

        if ($name === '' || $message === '' || ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL))) {
            header('Location: index.php?sent=0');
            exit;
        }

        $messages = file_exists(MESSAGES_FILE) ? (json_decode(file_get_contents(MESSAGES_FILE), true) ?: []) : [];
        $messages[] = [ 'ts' => date('c'), 'ip' => $_SERVER['REMOTE_ADDR'] ?? '', 'ua' => $_SERVER['HTTP_USER_AGENT'] ?? '', 'name' => $name, 'email' => $email, 'message' => $message ];
        saveJsonFile(MESSAGES_FILE, $messages);

        $subject = 'Nieuw bericht via website';
        $body = "Naam: {$name}\nEmail: {$email}\nIP: ".($_SERVER['REMOTE_ADDR'] ?? '')."\n---\n{$message}\n";
        $sent = false;
        $transport = 'mail';

        if (defined('SMTP_ENABLED') && SMTP_ENABLED) {
            if (file_exists(__DIR__ . '/vendor/autoload.php')) { require_once __DIR__ . '/vendor/autoload.php'; }
            if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
                $mailer = new PHPMailer\PHPMailer\PHPMailer(true);
                try {
                    $mailer->isSMTP(); $mailer->Host = SMTP_HOST; $mailer->Port = SMTP_PORT; $mailer->SMTPAuth = true;
                    if (defined('SMTP_SECURE') && SMTP_SECURE) { $mailer->SMTPSecure = SMTP_SECURE; }
                    $mailer->Username = SMTP_USERNAME; $mailer->Password = SMTP_PASSWORD; $mailer->CharSet = 'UTF-8';
                    $mailer->setFrom(MAIL_FROM, MAIL_FROM_NAME); $mailer->addAddress(MAIL_TO);
                    if ($email) { $mailer->addReplyTo($email, $name ?: $email); }
                    $mailer->Subject = $subject; $mailer->Body = $body;
                    $sent = $mailer->send(); $transport = 'smtp-phpmailer';
                } catch (\Throwable $e) { $sent = false; }
            }
        }
        if (!$sent) {
            $headers = "From: " . (defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : 'Website') . " <" . (defined('MAIL_FROM') ? MAIL_FROM : 'no-reply@localhost') . ">\r\n";
            if ($email) { $headers .= "Reply-To: {$email}\r\n"; }
            $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
            $sent = @mail(defined('MAIL_TO') ? MAIL_TO : (defined('MAIL_FROM') ? MAIL_FROM : ''), $subject, $body, $headers);
        }

        logMailAttempt([ 'type' => 'contact_form', 'sent' => $sent, 'transport' => $transport ]);
        header('Location: index.php?sent=' . ($sent ? '1' : '0'));
        exit;

    case 'request_password_reset':
        header('Content-Type: application/json');
        $token = bin2hex(random_bytes(32));
        $hash = hash('sha256', $token);
        $expires = time() + 3600; // 1 hour
        $tokensFile = DATA_DIR . '/reset_tokens.json';
        $tokens = file_exists($tokensFile) ? (json_decode(file_get_contents($tokensFile), true) ?: []) : [];
        $tokens = array_values(array_filter($tokens, function($t){ return isset($t['expires']) && $t['expires'] > time() && empty($t['used']); }));
        $tokens[] = ['hash' => $hash, 'expires' => $expires, 'created' => time(), 'used' => false];
        saveJsonFile($tokensFile, $tokens);

        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $basePath = rtrim(dirname($_SERVER['REQUEST_URI'] ?? '/'), '/\\');
        $resetLink = $scheme . '://' . $host . $basePath . '/reset.php?token=' . $token;

        $subject = 'Wachtwoord resetten';
        $body = "Er werd een reset van je wachtwoord aangevraagd. Was jij dit niet? Dan mag je deze mail negeren.\nWens je je wachtwoord te resetten, klik op deze link:\n{$resetLink}";
        $sent = false;
        $transport = 'mail';
        if (defined('SMTP_ENABLED') && SMTP_ENABLED) {
            if (file_exists(__DIR__ . '/vendor/autoload.php')) { require_once __DIR__ . '/vendor/autoload.php'; }
            if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
                $mailer = new PHPMailer\PHPMailer\PHPMailer(true);
                try {
                    $mailer->isSMTP(); $mailer->Host = SMTP_HOST; $mailer->Port = SMTP_PORT; $mailer->SMTPAuth = true;
                    if (defined('SMTP_SECURE') && SMTP_SECURE) { $mailer->SMTPSecure = SMTP_SECURE; }
                    $mailer->Username = SMTP_USERNAME; $mailer->Password = SMTP_PASSWORD; $mailer->CharSet = 'UTF-8';
                    $mailer->setFrom(MAIL_FROM, MAIL_FROM_NAME); $mailer->addAddress(MAIL_TO);
                    $mailer->Subject = $subject; $mailer->Body = $body;
                    $sent = $mailer->send(); $transport = 'smtp-phpmailer';
                } catch (\Throwable $e) { $sent = false; }
            }
        }
        if (!$sent) {
            $headers = "From: " . (defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : 'Website') . " <" . (defined('MAIL_FROM') ? MAIL_FROM : 'no-reply@localhost') . ">\r\n";
            $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
            $sent = @mail(defined('MAIL_TO') ? MAIL_TO : (defined('MAIL_FROM') ? MAIL_FROM : ''), $subject, $body, $headers);
        }
        logMailAttempt([ 'type' => 'password_reset', 'sent' => $sent, 'transport' => $transport ]);
        echo json_encode(['status' => $sent ? 'success' : 'error']);
        exit;

    // --- Team management ---
    case 'add_team_member':
        $name = trim($_POST['name'] ?? '');
        $role = trim($_POST['role'] ?? '');
        $appt = trim($_POST['appointment_url'] ?? '');
        $groupId = trim($_POST['group_id'] ?? '');
        $visible = isset($_POST['visible']) ? true : true; // default visible
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
                         // Delete associated images
                        foreach (['image','webp'] as $k) {
                            if (!empty($m[$k])) {
                                $absPath = BASE_DIR . '/' . toPublicPath($m[$k]);
                                if (file_exists($absPath)) { @unlink($absPath); }
                            }
                        }
                        return false; // Remove item from array
                    }
                    return true; // Keep item
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

    case 'reorder_team_groups':
        $order = $_POST['order'] ?? [];
        $data = loadJsonFile($teamFilePath);
        $groups = $data['groups'] ?? [];
        if (is_array($groups)) {
            $byId = [];
            foreach ($groups as $g) { $byId[$g['id'] ?? ''] = $g; }
            $new = [];
            foreach ($order as $id) { if (isset($byId[$id])) $new[] = $byId[$id]; }
            foreach ($groups as $g) { if (!in_array($g['id'] ?? '', $order, true)) $new[] = $g; }
            $data['groups'] = $new;
            saveJsonFile($teamFilePath, $data);
        }
        if ($isAjax) { echo json_encode(['status' => 'success']); exit; }
        break;

    case 'reorder_team_members':
        $order = $_POST['order'] ?? [];
        $groupId = $_POST['group_id'] ?? '';
        $data = loadJsonFile($teamFilePath);
        $members = $data['members'] ?? [];
        if (is_array($members)) {
            // Group current members by group_id
            $byGroup = [];
            foreach ($members as $m) { $byGroup[$m['group_id'] ?? ''] = $byGroup[$m['group_id'] ?? ''] ?? []; $byGroup[$m['group_id'] ?? ''][] = $m; }
            $list = $byGroup[$groupId] ?? [];
            // Index by id
            $byId = [];
            foreach ($list as $m) { $byId[$m['id'] ?? ''] = $m; }
            // Build new ordered list for this group
            $newList = [];
            foreach ($order as $id) { if (isset($byId[$id])) $newList[] = $byId[$id]; }
            foreach ($list as $m) { if (!in_array($m['id'] ?? '', $order, true)) $newList[] = $m; }
            $byGroup[$groupId] = $newList;
            // Rebuild global members by current groups order
            $groups = $data['groups'] ?? [];
            $rebuilt = [];
            // First, groups in defined order
            foreach ($groups as $g) {
                $gid = $g['id'] ?? '';
                if (isset($byGroup[$gid])) { foreach ($byGroup[$gid] as $m) { $rebuilt[] = $m; unset($byGroup[$gid]); } }
            }
            // Then any remaining (ungrouped or missing groups)
            foreach ($byGroup as $gid => $arr) { foreach ($arr as $m) { $rebuilt[] = $m; } }
            $data['members'] = $rebuilt;
            saveJsonFile($teamFilePath, $data);
        }
        if ($isAjax) { echo json_encode(['status' => 'success']); exit; }
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
        $all = $_POST['pinned_scope_all'] ?? [];
        $items = [];
        $n = is_array($ids) ? count($ids) : 0;
        for ($i = 0; $i < $n; $i++) {
            $pid = $ids[$i] ?? uniqid('pin_', true);
            $title = trim($titles[$i] ?? '');
            $text = $texts[$i] ?? '';
            if ($title === '' && trim(strip_tags($text)) === '') continue;
            $scope = [];
            if (in_array($pid, (array)$all, true)) { $scope = ['all']; }
            else {
                if (in_array($pid, (array)$home, true)) $scope[] = 'home';
                if (in_array($pid, (array)$team, true)) $scope[] = 'team';
                if (in_array($pid, (array)$practice, true)) $scope[] = 'practice';
                if (in_array($pid, (array)$links, true)) $scope[] = 'links';
            }
            $items[] = [ 'id' => $pid, 'title' => $title, 'text' => $text, 'scope' => $scope ];
        }
        $content['pinned'] = $items;
        saveJsonFile($contentFilePath, $content);
        break;

    case 'reorder_practice_pages':
        $order = $_POST['order'] ?? [];
        $data = loadJsonFile($practiceFilePath);
        if (isset($data['pages']) && is_array($data['pages'])) {
            $new = [];
            foreach ($order as $slug) { if (isset($data['pages'][$slug])) $new[$slug] = $data['pages'][$slug]; }
            foreach ($data['pages'] as $slug => $page) { if (!isset($new[$slug])) $new[$slug] = $page; }
            $data['pages'] = $new;
            saveJsonFile($practiceFilePath, $data);
        }
        if ($isAjax) { echo json_encode(['status'=>'success']); exit; }
        break;

    case 'reorder_pinned':
        $order = $_POST['order'] ?? [];
        if (isset($content['pinned']) && is_array($content['pinned'])) {
            $byId = array_column($content['pinned'], null, 'id');
            $new = [];
            foreach ($order as $id) { if (isset($byId[$id])) $new[] = $byId[$id]; }
            foreach ($content['pinned'] as $p) { if (!in_array($p['id'] ?? '', $order, true)) $new[] = $p; }
            $content['pinned'] = $new;
            saveJsonFile($contentFilePath, $content);
        }
        if ($isAjax) { echo json_encode(['status'=>'success']); exit; }
        break;

    case 'reorder_links':
        $order = $_POST['order'] ?? [];
        $data = loadJsonFile($linksFilePath);
        $items = $data['items'] ?? [];
        if (is_array($items)) {
            $byId = array_column($items, null, 'id');
            $new = [];
            foreach ($order as $id) { if (isset($byId[$id])) $new[] = $byId[$id]; }
            foreach ($items as $it) { if (!in_array($it['id'] ?? '', $order, true)) $new[] = $it; }
            $data['items'] = $new;
            saveJsonFile($linksFilePath, $data);
        }
        if ($isAjax) { echo json_encode(['status'=>'success']); exit; }
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

    case 'update_mailbox':
        $newEmail = trim($_POST['mail_address'] ?? '');
        $newPass = $_POST['mail_password'] ?? '';
        if ($newEmail === '') { break; }
        $configLines = file('config.php');
        $output = '';
        foreach ($configLines as $line) {
            if (str_starts_with(trim($line), "define('SMTP_USERNAME'")) {
                $output .= "define('SMTP_USERNAME', '" . addslashes($newEmail) . "');\n";
            } elseif (str_starts_with(trim($line), "define('MAIL_FROM'")) {
                $output .= "define('MAIL_FROM', '" . addslashes($newEmail) . "');\n";
            } elseif (str_starts_with(trim($line), "define('MAIL_TO'")) {
                $output .= "define('MAIL_TO', '" . addslashes($newEmail) . "');\n";
            } elseif ($newPass !== '' && str_starts_with(trim($line), "define('SMTP_PASSWORD'")) {
                $output .= "define('SMTP_PASSWORD', '" . addslashes($newPass) . "');\n";
            } else {
                $output .= $line;
            }
        }
        if (file_put_contents('config.php', $output) !== false) {
            header('Location: ' . (defined('ADMIN_PANEL_FILE') ? ADMIN_PANEL_FILE : 'beheer-gpe-a4x7.php') . '?mailbox_update=success#tab-mailbox');
        } else {
            header('Location: ' . (defined('ADMIN_PANEL_FILE') ? ADMIN_PANEL_FILE : 'beheer-gpe-a4x7.php') . '?mailbox_update=error_file#tab-mailbox');
        }
        exit;
}

// Redirect back to the correct tab in the admin panel.
if (!$isAjax) {
    $hash = '#tab-homepage';
    $tabMap = [
        'add_team_member' => '#tab-team', 'update_team_member' => '#tab-team', 'delete_team_member' => '#tab-team',
        'add_team_group' => '#tab-team', 'update_team_group' => '#tab-team', 'delete_team_group' => '#tab-team',
        'reorder_team_groups' => '#tab-team', 'reorder_team_members' => '#tab-team',
        'save_practice_page' => '#tab-practice', 'delete_practice_page' => '#tab-practice',
        'save_links' => '#tab-links', 'save_settings' => '#tab-settings',
        'save_pinned' => '#tab-pinned',
    ];
    if (isset($tabMap[$action])) {
        $hash = $tabMap[$action];
    }
    
    header('Location: ' . (defined('ADMIN_PANEL_FILE') ? ADMIN_PANEL_FILE : 'beheer-gpe-a4x7.php') . $hash);
    exit;
}

