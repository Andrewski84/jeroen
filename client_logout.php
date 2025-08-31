<?php
// Client gallery logout: clears the client context and gallery session flag
session_start();
require_once 'config.php';

// If we recorded client gallery context, also clear the specific gallery login flag
if (!empty($_SESSION['client_gallery'])) {
    $slug = $_SESSION['client_gallery']['slug'] ?? '';
    if ($slug !== '') {
        $flag = 'gallery_' . $slug . '_logged_in';
        if (isset($_SESSION[$flag])) unset($_SESSION[$flag]);
    }
}

unset($_SESSION['client_gallery']);

// Redirect home (or back if referer present)
$target = 'index.php';
if (!empty($_SERVER['HTTP_REFERER'])) {
    $ref = $_SERVER['HTTP_REFERER'];
    // Avoid redirecting back to proof page with an authenticated session cleared — home is safer
}
header('Location: ' . $target);
exit;
?>
