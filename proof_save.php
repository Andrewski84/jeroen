<?php
// proof_save.php
// Endpoint voor AJAX-aanroepen vanuit proof.php om favorieten en opmerkingen bij te werken.
session_start();
require_once 'helpers.php';
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

$action = $_POST['action'] ?? '';
$slug = $_POST['gallery'] ?? '';
$index = isset($_POST['index']) ? intval($_POST['index']) : -1;

if ($slug === '') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid parameters']);
    exit;
}

$galleryDir = GALLERY_ASSETS_DIR . '/' . $slug;
$galleryFile = GALLERIES_DIR . '/' . $slug . '/gallery.json';
if (!file_exists($galleryFile)) {
    echo json_encode(['status' => 'error', 'message' => 'Gallery not found']);
    exit;
}

// Controleer of de klant is ingelogd voor deze galerij
if (!isset($_SESSION['gallery_' . $slug . '_logged_in']) || $_SESSION['gallery_' . $slug . '_logged_in'] !== true) {
    echo json_encode(['status' => 'error', 'message' => 'Not authenticated']);
    exit;
}

$galleryData = loadJsonFile($galleryFile);

if ($action === 'update_photo') {
    if ($index < 0 || !isset($galleryData['photos'][$index])) {
        echo json_encode(['status' => 'error', 'message' => 'Photo not found']);
        exit;
    }
    // Bijwerken van favoriete status en/of commentaar
    if (isset($_POST['favorite'])) {
        $galleryData['photos'][$index]['favorite'] = $_POST['favorite'] === '1' ? true : false;
    }
    if (isset($_POST['comment'])) {
        $galleryData['photos'][$index]['comment'] = $_POST['comment'];
    }
    saveJsonFile($galleryFile, $galleryData);
    echo json_encode(['status' => 'success']);
    exit;
} elseif ($action === 'finalize') {
    // Finalize client selection: mark gallery confirmed, persist timestamp
    // and email the admin with the selection file attached.
    $galleryTitle = $galleryData['title'] ?? $slug;

    // Count selected photos
    $photos = $galleryData['photos'] ?? [];
    $selected = array_values(array_filter($photos, function($p){ return !empty($p['favorite']); }));
    $selectedCount = count($selected);

    // Build selection text, same format as admin download
    $lines = ["Selectie voor: " . $galleryTitle . "\n"];
    if ($selectedCount === 0) {
        $lines[] = "Er zijn nog geen foto's geselecteerd.";
    } else {
        $counter = 1;
        foreach ($selected as $photo) {
            $orig = $photo['original_name'] ?? basename($photo['path'] ?? '');
            $comment = trim($photo['comment'] ?? '');
            $line = $counter . '. ' . $orig . ($comment !== '' ? ' - Opmerking: ' . $comment : '');
            $lines[] = $line;
            $counter++;
        }
    }
    $selectionText = implode("\n", $lines);

    // Save selection to a file in the gallery data directory for attachment
    $attachPath = GALLERIES_DIR . '/' . $slug . '/selectie_' . $slug . '.txt';
    @file_put_contents($attachPath, $selectionText);

    // Compose email
    $subject = 'Keuze bevestigd - ' . $galleryTitle;
    $body = "De keuze voor klantengalerij {$galleryTitle} werd bevestigd.\nEr werden {$selectedCount} foto's geselecteerd.";

    $sent = false;
    // Prefer PHPMailer if available (SMTP optional) for attachments
    if (file_exists(__DIR__ . '/vendor/autoload.php')) { require_once __DIR__ . '/vendor/autoload.php'; }
    if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
        try {
            $mailer = new PHPMailer\PHPMailer\PHPMailer(true);
            $transport = 'mail';
            if (defined('SMTP_ENABLED') && SMTP_ENABLED) {
                $mailer->isSMTP();
                $mailer->Host = SMTP_HOST;
                $mailer->Port = SMTP_PORT;
                $mailer->SMTPAuth = true;
                if (defined('SMTP_SECURE') && SMTP_SECURE) { $mailer->SMTPSecure = SMTP_SECURE; }
                $mailer->Username = SMTP_USERNAME;
                $mailer->Password = SMTP_PASSWORD;
                $transport = 'smtp-phpmailer';
            }
            $mailer->CharSet = 'UTF-8';
            $mailer->setFrom(defined('MAIL_FROM') ? MAIL_FROM : (defined('SMTP_USERNAME') ? SMTP_USERNAME : 'no-reply@localhost'), defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : 'Website');
            $mailer->addAddress(defined('MAIL_TO') ? MAIL_TO : (defined('MAIL_FROM') ? MAIL_FROM : ''));
            $mailer->Subject = $subject;
            $mailer->Body = $body;
            if (file_exists($attachPath)) { $mailer->addAttachment($attachPath, 'selectie_' . $slug . '.txt'); }
            $sent = $mailer->send();
        } catch (\Throwable $e) {
            $sent = false;
        }
    }
    // Fallback: manual MIME email with attachment via mail()
    if (!$sent) {
        $to = defined('MAIL_TO') ? MAIL_TO : (defined('MAIL_FROM') ? MAIL_FROM : '');
        $fromName = defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : 'Website';
        $fromAddr = defined('MAIL_FROM') ? MAIL_FROM : 'no-reply@localhost';
        $boundary = '==Multipart_Boundary_x' . md5(uniqid(time(), true)) . 'x';
        $headers = "From: {$fromName} <{$fromAddr}>\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: multipart/mixed; boundary=\"{$boundary}\"\r\n";
        $message = "--{$boundary}\r\n";
        $message .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n" . $body . "\r\n";
        if (file_exists($attachPath)) {
            $fileData = chunk_split(base64_encode(file_get_contents($attachPath)));
            $message .= "--{$boundary}\r\n";
            $message .= "Content-Type: text/plain; name=\"selectie_{$slug}.txt\"\r\n";
            $message .= "Content-Transfer-Encoding: base64\r\n";
            $message .= "Content-Disposition: attachment; filename=\"selectie_{$slug}.txt\"\r\n\r\n";
            $message .= $fileData . "\r\n";
        }
        $message .= "--{$boundary}--";
        $sent = @mail($to, $subject, $message, $headers);
    }

    // Update gallery status
    $galleryData['last_saved'] = date('Y-m-d H:i:s');
    $galleryData['active'] = false;
    $galleryData['confirmed'] = true;
    saveJsonFile($galleryFile, $galleryData);

    // Log mail attempt
    logMailAttempt([
        'type' => 'selection_finalized',
        'gallery_slug' => $slug,
        'gallery_title' => $galleryTitle,
        'count' => $selectedCount,
        'to' => (defined('MAIL_TO') ? MAIL_TO : (defined('MAIL_FROM') ? MAIL_FROM : '')),
        'subject' => $subject,
        'sent' => $sent,
        'transport' => isset($transport) ? $transport : 'mail'
    ]);
    echo json_encode(['status' => $sent ? 'success' : 'success']); // keep success for UX
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
?>

