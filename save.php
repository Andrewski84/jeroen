<?php
/**
 * Admin actions endpoint
 *
 * This file receives form submissions and AJAX posts from the admin panel
 * and applies changes to JSON data, images and configuration.
 *
 * Design principles:
 * - Security: all actions (except public ones like contact form) require an admin session.
 * - PRG pattern: regular form submissions end with a redirect back to admin.php.
 * The redirect includes a hash (e.g. #tab-portfolio&theme=portrait) so the
 * UI restores the exact context the user was working in.
 * - AJAX: endpoints that are called from fetch() return JSON and exit early.
 */
session_start();
require_once 'helpers.php';
require_once 'config.php';

$publicActions = ['contact_form', 'request_password_reset'];
$action = $_POST['action'] ?? ($_GET['action'] ?? null);

if (!in_array($action, $publicActions)) {
    if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
        header('Location: login.php');
        exit;
    }
}

$contentFilePath = CONTENT_FILE;
$portfolioFilePath = PORTFOLIO_FILE;
$content = loadJsonFile($contentFilePath);
$portfolioData = loadJsonFile($portfolioFilePath);

/*
 * Kleurenthema's worden niet meer dynamisch beheerd. Voorheen werd
 * $themesData ingeladen uit een JSON-bestand om de kleuren aan te passen via
 * apply_theme/update_theme/create_theme. Dit themasysteem is verwijderd,
 * dus er wordt geen themabestand meer geladen en alle kleuren zijn statisch.
 */

switch ($action) {
    case 'update_content':
         $content['hero']['title'] = $_POST['hero_title'] ?? ($content['hero']['title'] ?? '');
        $content['bio']['title'] = $_POST['bio_title'] ?? ($content['bio']['title'] ?? '');
        $content['bio']['text'] = $_POST['bio_text'] ?? ($content['bio']['text'] ?? '');
        $content['contact']['instagram_url'] = $_POST['instagram_url'] ?? ($content['contact']['instagram_url'] ?? '');
        $content['meta_title'] = $_POST['meta_title'] ?? ($content['meta_title'] ?? '');
        $content['meta_description'] = $_POST['meta_description'] ?? ($content['meta_description'] ?? '');
        saveJsonFile($contentFilePath, $content);
        break;

    case 'change_password':
        $old_password = $_POST['old_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (!defined('ADMIN_PASSWORD_HASH') || !password_verify($old_password, ADMIN_PASSWORD_HASH)) {
            header('Location: admin.php?password_change=error_wrong#tab-security');
            exit;
        }
        if (empty($new_password) || $new_password !== $confirm_password) {
            header('Location: admin.php?password_change=error_mismatch#tab-security');
            exit;
        }

        $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
        $configLines = file('config.php');
        $newConfigContent = '';
        foreach ($configLines as $line) {
            if (strpos($line, 'ADMIN_PASSWORD_HASH') !== false) {
                $newConfigContent .= "define('ADMIN_PASSWORD_HASH', '" . $new_hash . "');\n";
            } else {
                $newConfigContent .= $line;
            }
        }

        if (file_put_contents('config.php', $newConfigContent)) {
            header('Location: admin.php?password_change=success#tab-security');
        } else {
            header('Location: admin.php?password_change=error_file#tab-security');
        }
        exit;

    
    case 'contact_form':
        // Honeypot + (relaxed) time trap validation
        $hp = trim($_POST['address2'] ?? '');
        if ($hp !== '') {
            header('Location: index.php?sent=0');
            exit;
        }
        // Use time trap only as a soft signal (do not block if missing)
        $formStart = isset($_SESSION['form_start']) ? (int)$_SESSION['form_start'] : 0;
        $elapsed = $formStart > 0 ? (time() - $formStart) : null;
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $message = trim($_POST['message'] ?? '');

        if ($name === '' || $message === '') {
            header('Location: index.php?sent=0');
            exit;
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            header('Location: index.php?sent=0');
            exit;
        }

        // Persist to messages.json
        $messages = file_exists(MESSAGES_FILE) ? (json_decode(file_get_contents(MESSAGES_FILE), true) ?: []) : [];
        $messages[] = [
            'ts' => date('c'),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
            'ua' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'name' => $name,
            'email' => $email,
            'message' => $message
        ];
        @file_put_contents(MESSAGES_FILE, json_encode($messages, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));

        // Send email
        $subject = 'Nieuw bericht via website';
        $body = "Naam: {$name}\nEmail: {$email}\nIP: ".($_SERVER['REMOTE_ADDR'] ?? '')."\n---\n{$message}\n";
        $sent = false;

        $transport = 'mail';
        if (defined('SMTP_ENABLED') && SMTP_ENABLED) {
            // Try PHPMailer via Composer autoload if available
            if (file_exists(__DIR__ . '/vendor/autoload.php')) {
                require_once __DIR__ . '/vendor/autoload.php';
            }
            if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
                $mailer = new PHPMailer\PHPMailer\PHPMailer(true);
                try {
                    $mailer->isSMTP();
                    $mailer->Host = SMTP_HOST;
                    $mailer->Port = SMTP_PORT;
                    $mailer->SMTPAuth = true;
                    if (defined('SMTP_SECURE') && SMTP_SECURE) { $mailer->SMTPSecure = SMTP_SECURE; }
                    $mailer->Username = SMTP_USERNAME;
                    $mailer->Password = SMTP_PASSWORD;
                    $mailer->CharSet = 'UTF-8';
                    $mailer->setFrom(MAIL_FROM, MAIL_FROM_NAME);
                    $mailer->addAddress(MAIL_TO);
                    if ($email) { $mailer->addReplyTo($email, $name ?: $email); }
                    $mailer->Subject = $subject;
                    $mailer->Body = $body;
                    $sent = $mailer->send();
                    $transport = 'smtp-phpmailer';
                } catch (\Throwable $e) {
                    $sent = false;
                }
            }
        }
        if (!$sent) {
            // Fallback to PHP mail()
            $headers = "From: " . (defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : 'Website') . " <" . (defined('MAIL_FROM') ? MAIL_FROM : 'no-reply@localhost') . ">\r\n";
            if ($email) { $headers .= "Reply-To: {$email}\r\n"; }
            $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
            $sent = @mail(defined('MAIL_TO') ? MAIL_TO : (defined('MAIL_FROM') ? MAIL_FROM : ''), $subject, $body, $headers);
        }

        // Log mail attempt
        logMailAttempt([
            'type' => 'contact_form',
            'to' => (defined('MAIL_TO') ? MAIL_TO : (defined('MAIL_FROM') ? MAIL_FROM : '')),
            'from' => (defined('MAIL_FROM') ? MAIL_FROM : 'no-reply@localhost'),
            'subject' => $subject,
            'sent' => $sent,
            'transport' => $transport,
            'meta' => [ 'name' => $name, 'email' => $email, 'ip' => $_SERVER['REMOTE_ADDR'] ?? '' ]
        ]);

        header('Location: index.php?sent=' . ($sent ? '1' : '0'));
        exit;
        break;

    case 'request_password_reset':
        header('Content-Type: application/json');
        // Generate token and store hash with expiry
        $token = bin2hex(random_bytes(32));
        $hash = hash('sha256', $token);
        $expires = time() + 3600; // 1 hour
        $tokensFile = DATA_DIR . '/reset_tokens.json';
        $tokens = file_exists($tokensFile) ? (json_decode(file_get_contents($tokensFile), true) ?: []) : [];
        // Prune expired
        $tokens = array_values(array_filter($tokens, function($t){ return isset($t['expires']) && $t['expires'] > time() && empty($t['used']); }));
        $tokens[] = ['hash' => $hash, 'expires' => $expires, 'created' => time(), 'used' => false];
        @file_put_contents($tokensFile, json_encode($tokens, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));

        // Build absolute reset link
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $basePath = rtrim(dirname($_SERVER['REQUEST_URI'] ?? '/'), '/\\');
        $resetLink = $scheme . '://' . $host . $basePath . '/reset.php?token=' . $token;

        // Compose mail
        $subject = 'Wachtwoord resetten';
        $body = "Er werd een reset van je wachtwoord aangevraagd. Was jij dit niet? Dan mag je deze mail negeren. Wens je je wachtwoord te resetten, klik op deze link: {$resetLink}";
        $sent = false;
        $transport = 'mail';
        if (defined('SMTP_ENABLED') && SMTP_ENABLED) {
            if (file_exists(__DIR__ . '/vendor/autoload.php')) { require_once __DIR__ . '/vendor/autoload.php'; }
            if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
                $mailer = new PHPMailer\PHPMailer\PHPMailer(true);
                try {
                    $mailer->isSMTP();
                    $mailer->Host = SMTP_HOST;
                    $mailer->Port = SMTP_PORT;
                    $mailer->SMTPAuth = true;
                    if (defined('SMTP_SECURE') && SMTP_SECURE) { $mailer->SMTPSecure = SMTP_SECURE; }
                    $mailer->Username = SMTP_USERNAME;
                    $mailer->Password = SMTP_PASSWORD;
                    $mailer->CharSet = 'UTF-8';
                    $mailer->setFrom(MAIL_FROM, MAIL_FROM_NAME);
                    $mailer->addAddress(MAIL_TO);
                    $mailer->Subject = $subject;
                    $mailer->Body = $body;
                    $sent = $mailer->send();
                    $transport = 'smtp-phpmailer';
                } catch (\Throwable $e) { $sent = false; }
            }
        }
        if (!$sent) {
            $headers = "From: " . (defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : 'Website') . " <" . (defined('MAIL_FROM') ? MAIL_FROM : 'no-reply@localhost') . ">\r\n";
            $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
            $sent = @mail(defined('MAIL_TO') ? MAIL_TO : (defined('MAIL_FROM') ? MAIL_FROM : ''), $subject, $body, $headers);
        }
        logMailAttempt([
            'type' => 'password_reset',
            'to' => (defined('MAIL_TO') ? MAIL_TO : (defined('MAIL_FROM') ? MAIL_FROM : '')),
            'from' => (defined('MAIL_FROM') ? MAIL_FROM : 'no-reply@localhost'),
            'subject' => $subject,
            'sent' => $sent,
            'transport' => $transport
        ]);
        echo json_encode(['status' => $sent ? 'success' : 'error']);
        exit;
case 'add_theme':
        $themeName = trim($_POST['theme_name'] ?? '');
        if ($themeName && !isset($portfolioData['themes'][$themeName])) {
            $portfolioData['themes'][$themeName] = ['images' => []];
            saveJsonFile($portfolioFilePath, $portfolioData);
            // Sanitize for directory creation
            $safeDirName = sanitizeThemeName($themeName);
            if (!is_dir(ASSETS_DIR . '/portfolio/' . $safeDirName)) {
                mkdir(ASSETS_DIR . '/portfolio/' . $safeDirName, 0755, true);
            }
        }
        break;

    case 'delete_theme':
        $themeName = $_POST['theme_name'] ?? '';
        if ($themeName && isset($portfolioData['themes'][$themeName])) {
            foreach ($portfolioData['themes'][$themeName]['images'] as $image) {
                if (isset($image['path']) && file_exists($image['path'])) unlink($image['path']);
                if (isset($image['webp']) && file_exists($image['webp'])) unlink($image['webp']);
            }
            // Sanitize for directory deletion
            $safeDirName = sanitizeThemeName($themeName);
            $themeDir = ASSETS_DIR . '/portfolio/' . $safeDirName;
            if (is_dir($themeDir)) {
                // This is a safer way to remove a directory and its contents
                $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($themeDir, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
                foreach ($files as $fileinfo) {
                    $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
                    $todo($fileinfo->getRealPath());
                }
                rmdir($themeDir);
            }
            unset($portfolioData['themes'][$themeName]);
            saveJsonFile($portfolioFilePath, $portfolioData);
        }
        break;

    case 'update_photo_details':
        header('Content-Type: application/json');
        $themeName = $_POST['theme_name'] ?? '';
        $photoIndex = $_POST['photo_index'] ?? -1;
        if ($themeName && $photoIndex >= 0 && isset($portfolioData['themes'][$themeName]['images'][$photoIndex])) {
            $portfolioData['themes'][$themeName]['images'][$photoIndex]['title'] = $_POST['title'] ?? '';
            $portfolioData['themes'][$themeName]['images'][$photoIndex]['description'] = $_POST['description'] ?? '';
            $portfolioData['themes'][$themeName]['images'][$photoIndex]['featured'] = isset($_POST['featured']);
            $portfolioData['themes'][$themeName]['images'][$photoIndex]['alt'] = $_POST['alt'] ?? '';
            if (saveJsonFile($portfolioFilePath, $portfolioData)) {
                echo json_encode(['status' => 'success', 'message' => 'Foto details opgeslagen.']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Kon data niet opslaan.']);
            }
            exit;
        }
        echo json_encode(['status' => 'error', 'message' => 'Ongeldige data.']);
        exit;

    case 'delete_photo':
        $themeName = $_POST['theme_name'] ?? '';
        $photoIndex = $_POST['photo_index'] ?? -1;
        if ($themeName && $photoIndex >= 0 && isset($portfolioData['themes'][$themeName]['images'][$photoIndex])) {
            $image = $portfolioData['themes'][$themeName]['images'][$photoIndex];
            if (isset($image['path']) && file_exists($image['path'])) unlink($image['path']);
            if (isset($image['webp']) && file_exists($image['webp'])) unlink($image['webp']);
            array_splice($portfolioData['themes'][$themeName]['images'], $photoIndex, 1);
            saveJsonFile($portfolioFilePath, $portfolioData);
        }
        break;

    case 'rename_theme':
        $oldName = $_POST['old_theme_name'] ?? '';
        $newName = trim($_POST['new_theme_name'] ?? '');
        if ($oldName && $newName && isset($portfolioData['themes'][$oldName]) && !isset($portfolioData['themes'][$newName])) {
            // Sanitize for directory operations
            $oldSafeDirName = sanitizeThemeName($oldName);
            $newSafeDirName = sanitizeThemeName($newName);

            $oldDir = ASSETS_DIR . '/portfolio/' . $oldSafeDirName;
            $newDir = ASSETS_DIR . '/portfolio/' . $newSafeDirName;
            
            if (is_dir($oldDir) && $oldDir !== $newDir) {
                rename($oldDir, $newDir);
            }

            $themeData = $portfolioData['themes'][$oldName];
            unset($portfolioData['themes'][$oldName]);
            $portfolioData['themes'][$newName] = $themeData;
            saveJsonFile($portfolioFilePath, $portfolioData);
        }
        break;

    case 'update_theme_intro':
        $themeName = $_POST['theme_name'] ?? '';
        $intro = $_POST['intro_text'] ?? '';
        if ($themeName !== '' && isset($portfolioData['themes'][$themeName])) {
            $portfolioData['themes'][$themeName]['intro_text'] = $intro;
            saveJsonFile($portfolioFilePath, $portfolioData);
        }
        break;

    case 'update_photo_order':
        header('Content-Type: application/json');
        $themeName = $_POST['theme'] ?? '';
        $order = $_POST['order'] ?? [];
        if ($themeName && isset($portfolioData['themes'][$themeName])) {
            $originalImages = $portfolioData['themes'][$themeName]['images'];
            $newOrderedImages = [];
            foreach ($order as $originalIndex) {
                if (isset($originalImages[$originalIndex])) {
                    $newOrderedImages[] = $originalImages[$originalIndex];
                }
            }
            $portfolioData['themes'][$themeName]['images'] = $newOrderedImages;
            if (saveJsonFile($portfolioFilePath, $portfolioData)) {
                echo json_encode(['status' => 'success']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Kon volgorde niet opslaan.']);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Ongeldige data.']);
        }
        exit;
    
    case 'update_portfolio_order':
        header('Content-Type: application/json');
        $order = $_POST['order'] ?? [];
        if (!empty($order) && isset($portfolioData['themes'])) {
            $reorderedThemes = [];
            foreach ($order as $themeName) {
                if (isset($portfolioData['themes'][$themeName])) {
                    $reorderedThemes[$themeName] = $portfolioData['themes'][$themeName];
                }
            }
            foreach ($portfolioData['themes'] as $themeName => $themeData) {
                if (!array_key_exists($themeName, $reorderedThemes)) {
                    $reorderedThemes[$themeName] = $themeData;
                }
            }
            $portfolioData['themes'] = $reorderedThemes;
            if (saveJsonFile($portfolioFilePath, $portfolioData)) {
                echo json_encode(['status' => 'success', 'message' => 'Portfolio volgorde opgeslagen.']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Kon volgorde niet opslaan.']);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Ongeldige data.']);
        }
        exit;

    case 'create_gallery':
        $title = trim($_POST['gallery_title'] ?? '');
        $password = trim($_POST['gallery_password'] ?? '');
        if ($title === '' || $password === '') break;
        if (!function_exists('generateRandomSlug')) {
            function generateRandomSlug($length = 10) {
                return bin2hex(random_bytes($length / 2));
            }
        }
        if (!is_dir(GALLERY_ASSETS_DIR)) mkdir(GALLERY_ASSETS_DIR, 0755, true);
        do {
            $slug = generateRandomSlug(10);
        } while (is_dir(GALLERY_ASSETS_DIR . '/' . $slug));
        $galleryDir = GALLERY_ASSETS_DIR . '/' . $slug;
        mkdir($galleryDir, 0755, true);
        $galleryData = [
            'slug' => $slug,
            'title' => $title,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'active' => true,
            'max_select' => 0,
            'photos' => []
        ];
        if (!is_dir(GALLERIES_DIR . '/' . $slug)) mkdir(GALLERIES_DIR . '/' . $slug, 0755, true);
        saveJsonFile(GALLERIES_DIR . '/' . $slug . '/gallery.json', $galleryData);
        break;

    case 'reset_gallery_password':
        $slug = $_POST['gallery_slug'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        if ($slug !== '' && $newPassword !== '') {
            $file = GALLERIES_DIR . '/' . $slug . '/gallery.json';
            $galleryData = loadJsonFile($file);
            if ($galleryData) {
                $galleryData['password_hash'] = password_hash($newPassword, PASSWORD_DEFAULT);
                saveJsonFile($file, $galleryData);
            }
        }
        break;

    case 'delete_gallery':
        $slug = $_POST['gallery_slug'] ?? '';
        if ($slug !== '') {
            $dir = GALLERY_ASSETS_DIR . '/' . $slug;
            if (is_dir($dir)) {
                $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
                foreach ($files as $fileinfo) {
                    $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
                    $todo($fileinfo->getRealPath());
                }
                rmdir($dir);
            }
            $dataDir = GALLERIES_DIR . '/' . $slug;
            if (is_dir($dataDir)) {
                $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dataDir, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
                foreach ($files as $fileinfo) {
                    $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
                    $todo($fileinfo->getRealPath());
                }
                rmdir($dataDir);
            }
        }
        break;

    case 'delete_gallery_photo':
        $slug = $_POST['gallery_slug'] ?? '';
        $photoIndex = isset($_POST['photo_index']) ? intval($_POST['photo_index']) : -1;
        if ($slug !== '' && $photoIndex >= 0) {
            $file = GALLERIES_DIR . '/' . $slug . '/gallery.json';
            $galleryData = loadJsonFile($file);
            if ($galleryData && isset($galleryData['photos'][$photoIndex])) {
                $p = $galleryData['photos'][$photoIndex];
                if (isset($p['path']) && file_exists($p['path'])) unlink($p['path']);
                if (isset($p['webp']) && file_exists($p['webp'])) unlink($p['webp']);
                array_splice($galleryData['photos'], $photoIndex, 1);
                saveJsonFile($file, $galleryData);
            }
        }
        break;

    case 'update_gallery_photo_order':
        header('Content-Type: application/json');
        $slug = $_POST['slug'] ?? '';
        $order = $_POST['order'] ?? [];
        $galleryFile = GALLERIES_DIR . '/' . $slug . '/gallery.json';

        if ($slug && !empty($order) && file_exists($galleryFile)) {
            $galleryData = loadJsonFile($galleryFile);
            $originalPhotos = $galleryData['photos'];
            $newOrderedPhotos = [];

            foreach ($order as $originalIndex) {
                if (isset($originalPhotos[$originalIndex])) {
                    $newOrderedPhotos[] = $originalPhotos[$originalIndex];
                }
            }

            if (count($newOrderedPhotos) === count($originalPhotos)) {
                $galleryData['photos'] = $newOrderedPhotos;
                if (saveJsonFile($galleryFile, $galleryData)) {
                    echo json_encode(['status' => 'success']);
                } else {
                    echo json_encode(['status' => 'error', 'message' => 'Kon volgorde niet opslaan in bestand.']);
                }
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Fout in data, foto-aantallen komen niet overeen.']);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Ongeldige data.']);
        }
        exit;

    case 'toggle_gallery_active':
        $slug = $_POST['gallery_slug'] ?? '';
        if ($slug !== '') {
            $file = GALLERIES_DIR . '/' . $slug . '/gallery.json';
            $galleryData = loadJsonFile($file);
            if ($galleryData) {
                // Toggle only the 'active' flag; never clear 'confirmed'
                $galleryData['active'] = !($galleryData['active'] ?? true);
                if (!empty($galleryData['confirmed'])) { $galleryData['confirmed'] = true; }
                saveJsonFile($file, $galleryData);
            }
        }
        break;

    case 'rename_gallery':
        $slug = $_POST['gallery_slug'] ?? '';
        $newTitle = trim($_POST['new_title'] ?? '');
        if ($slug !== '' && $newTitle !== '') {
            $file = GALLERIES_DIR . '/' . $slug . '/gallery.json';
            $galleryData = loadJsonFile($file);
            if ($galleryData) {
                $galleryData['title'] = $newTitle;
                saveJsonFile($file, $galleryData);
            }
        }
        break;

    case 'update_mailbox':
        $newEmail = trim($_POST['mail_address'] ?? '');
        $newPass = $_POST['mail_password'] ?? '';
        if ($newEmail === '') { break; }
        $configLines = file('config.php');
        $output = '';
        foreach ($configLines as $line) {
            if (strpos($line, "define('SMTP_USERNAME'") !== false) {
                $output .= "define('SMTP_USERNAME', '" . addslashes($newEmail) . "');\n";
            } elseif (strpos($line, "define('MAIL_FROM'" ) !== false) {
                $output .= "define('MAIL_FROM', '" . addslashes($newEmail) . "');\n";
            } elseif (strpos($line, "define('MAIL_TO'" ) !== false) {
                $output .= "define('MAIL_TO', '" . addslashes($newEmail) . "');\n";
            } elseif ($newPass !== '' && strpos($line, "define('SMTP_PASSWORD'" ) !== false) {
                $output .= "define('SMTP_PASSWORD', '" . addslashes($newPass) . "');\n";
            } else {
                $output .= $line;
            }
        }
        if (file_put_contents('config.php', $output) !== false) {
            header('Location: admin.php?mailbox_update=success#tab-mailbox');
        } else {
            header('Location: admin.php?mailbox_update=error_file#tab-mailbox');
        }
        exit;
        
    case 'update_gallery_max':
        $slug = $_POST['gallery_slug'] ?? '';
        $max = isset($_POST['max_select']) ? intval($_POST['max_select']) : 0;
        if ($slug !== '') {
            $file = GALLERIES_DIR . '/' . $slug . '/gallery.json';
            $galleryData = loadJsonFile($file);
            if ($galleryData) {
                $galleryData['max_select'] = max(0, $max);
                saveJsonFile($file, $galleryData);
            }
        }
        break;

    /*
     * De volgende acties hadden betrekking op het aanmaken en toepassen van
     * kleurenthema's. Het dynamische themasysteem is verwijderd uit de
     * live versie, daarom worden deze acties genegeerd. Mocht er toch
     * per ongeluk een verzoek voor deze acties binnenkomen, dan wordt
     * hier geen verdere verwerking gedaan en valt de applicatie terug op
     * de standaard afhandeling na de switch.
     */
    case 'apply_theme':
    case 'update_theme':
    case 'create_theme':
    case 'delete_color_theme':
        // Niet langer van toepassing
        break;

    case 'download_gallery_selection':
        $slug = $_GET['gallery_slug'] ?? '';
        if ($slug !== '') {
            $file = GALLERIES_DIR . '/' . $slug . '/gallery.json';
            $galleryData = loadJsonFile($file);
            if ($galleryData) {
                $lines = ["Selectie voor: " . $galleryData['title'] . "\n"];
                $counter = 1;
                $foundSelection = false;
                foreach ($galleryData['photos'] as $photo) {
                    if (!empty($photo['favorite'])) {
                        $foundSelection = true;
                        $orig = $photo['original_name'] ?? basename($photo['path']);
                        $comment = trim($photo['comment'] ?? '');
                        $line = $counter . '. ' . $orig . ($comment !== '' ? ' - Opmerking: ' . $comment : '');
                        $lines[] = $line;
                        $counter++;
                    }
                }
                if (!$foundSelection) $lines[] = "Er zijn nog geen foto's geselecteerd.";
                header('Content-Type: text/plain; charset=utf-8');
                header('Content-Disposition: attachment; filename="selectie_' . $slug . '.txt"');
                echo implode("\n", $lines);
                exit;
            }
        }
        break;
}

// Fallback redirect to admin with the right tab and detail view.
// This ensures that non-AJAX actions preserve context using the URL hash.
$hash = '#tab-homepage';
$detailParam = '';

if (in_array($action, ['add_theme', 'delete_theme', 'rename_theme', 'update_photo_details', 'delete_photo', 'update_photo_order', 'update_portfolio_order', 'update_theme_intro'])) {
    $hash = '#tab-portfolio';
    $themeName = $_POST['theme_name'] ?? $_POST['theme'] ?? $_POST['old_theme_name'] ?? null;
    if ($action === 'rename_theme') {
        $themeName = trim($_POST['new_theme_name'] ?? '');
    }
    if ($themeName) {
        $detailParam = '&theme=' . urlencode($themeName);
    }
} elseif (in_array($action, ['create_gallery', 'delete_gallery', 'toggle_gallery_active', 'rename_gallery', 'update_gallery_max', 'delete_gallery_photo', 'reset_gallery_password'])) {
    $hash = '#tab-galleries';
    $slug = $_POST['gallery_slug'] ?? null;
    if ($slug) {
        $detailParam = '&slug=' . urlencode($slug);
    }
}

header('Location: admin.php' . $hash . $detailParam);
exit;
