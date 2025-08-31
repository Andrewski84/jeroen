<?php
session_start();
require_once 'config.php';

$tokensFile = DATA_DIR . '/reset_tokens.json';

function loadTokens($file) {
    return file_exists($file) ? (json_decode(file_get_contents($file), true) ?: []) : [];
}
function saveTokens($file, $tokens) {
    @file_put_contents($file, json_encode($tokens, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
}

$token = $_GET['token'] ?? '';
$valid = false;
if ($token !== '') {
    $hash = hash('sha256', $token);
    $tokens = loadTokens($tokensFile);
    foreach ($tokens as $idx => $t) {
        if (($t['hash'] ?? '') === $hash && empty($t['used']) && ($t['expires'] ?? 0) > time()) {
            $valid = true;
            $tokenIndex = $idx;
            break;
        }
    }
}

// Handle form submit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['token'])) {
    $token = $_POST['token'];
    $hash = hash('sha256', $token);
    $tokens = loadTokens($tokensFile);
    $idxFound = null;
    foreach ($tokens as $idx => $t) {
        if (($t['hash'] ?? '') === $hash && empty($t['used']) && ($t['expires'] ?? 0) > time()) { $idxFound = $idx; break; }
    }
    if ($idxFound === null) {
        header('Location: reset.php?invalid=1');
        exit;
    }
    $p1 = $_POST['new_password'] ?? '';
    $p2 = $_POST['confirm_password'] ?? '';
    if ($p1 === '' || $p1 !== $p2) {
        header('Location: reset.php?token='.urlencode($token).'&mismatch=1');
        exit;
    }
    // Update password hash inside config.php
    $newHash = password_hash($p1, PASSWORD_DEFAULT);
    $configLines = file('config.php');
    $out = '';
    foreach ($configLines as $line) {
        if (strpos($line, "ADMIN_PASSWORD_HASH") !== false) {
            $out .= "define('ADMIN_PASSWORD_HASH', '".$newHash."');\n";
        } else {
            $out .= $line;
        }
    }
    $ok = (file_put_contents('config.php', $out) !== false);
    if ($ok) {
        // Mark token as used
        $tokens[$idxFound]['used'] = true; $tokens[$idxFound]['used_at'] = time();
        saveTokens($tokensFile, $tokens);
        // Notify admin by email
        $subject = 'Wachtwoord gewijzigd';
        $body = 'Je admin-wachtwoord werd succesvol gewijzigd.';
        $sent = false;
        if (defined('SMTP_ENABLED') && SMTP_ENABLED) {
            if (file_exists(__DIR__ . '/vendor/autoload.php')) { require_once __DIR__ . '/vendor/autoload.php'; }
            if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
                $mailer = new PHPMailer\PHPMailer\PHPMailer(true);
                try {
                    $mailer->isSMTP();
                    $mailer->Host = SMTP_HOST; $mailer->Port = SMTP_PORT; $mailer->SMTPAuth = true;
                    if (defined('SMTP_SECURE') && SMTP_SECURE) { $mailer->SMTPSecure = SMTP_SECURE; }
                    $mailer->Username = SMTP_USERNAME; $mailer->Password = SMTP_PASSWORD; $mailer->CharSet = 'UTF-8';
                    $mailer->setFrom(MAIL_FROM, MAIL_FROM_NAME); $mailer->addAddress(MAIL_TO);
                    $mailer->Subject = $subject; $mailer->Body = $body; $sent = $mailer->send();
                } catch (\Throwable $e) { $sent = false; }
            }
        }
        if (!$sent) {
            $headers = "From: " . (defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : 'Website') . " <" . (defined('MAIL_FROM') ? MAIL_FROM : 'no-reply@localhost') . ">\r\n";
            $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
            @mail(defined('MAIL_TO') ? MAIL_TO : (defined('MAIL_FROM') ? MAIL_FROM : ''), $subject, $body, $headers);
        }
        header('Location: login.php?reset=success');
        exit;
    } else {
        header('Location: reset.php?token='.urlencode($token).'&error=1');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nieuw wachtwoord instellen</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <style> body{font-family: 'Inter', sans-serif;} </style>
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen">
    <div class="w-full max-w-sm p-8 bg-white rounded-lg shadow-md">
        <h1 class="text-xl font-semibold text-center mb-4">Nieuw wachtwoord instellen</h1>
        <?php if (!$valid && !isset($_GET['invalid'])): ?>
            <p class="text-sm text-red-600 text-center">Ongeldige of verlopen link.</p>
        <?php endif; ?>
        <?php if (isset($_GET['invalid'])): ?><p class="text-sm text-red-600 text-center mb-4">Ongeldige of verlopen link.</p><?php endif; ?>
        <?php if ($valid): ?>
        <form method="POST" class="space-y-4">
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
            <?php if (isset($_GET['mismatch'])): ?><div class="text-sm text-red-600">Nieuwe wachtwoorden komen niet overeen.</div><?php endif; ?>
            <?php if (isset($_GET['error'])): ?><div class="text-sm text-red-600">Er ging iets mis. Probeer later opnieuw.</div><?php endif; ?>
            <div>
                <label for="new_password" class="block text-sm mb-1">Nieuw wachtwoord</label>
                <input id="new_password" name="new_password" type="password" required class="w-full px-3 py-2 border border-gray-300 rounded-md">
            </div>
            <div>
                <label for="confirm_password" class="block text-sm mb-1">Bevestig nieuw wachtwoord</label>
                <input id="confirm_password" name="confirm_password" type="password" required class="w-full px-3 py-2 border border-gray-300 rounded-md">
            </div>
            <button type="submit" class="w-full bg-gray-700 text-white py-2 rounded-md">Opslaan</button>
        </form>
        <?php endif; ?>
    </div>
</body>
</html>

