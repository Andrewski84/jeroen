<?php
session_start();
require_once 'config.php';

// Controleer of het formulier is verzonden
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';

    // Vergelijk het ingevoerde wachtwoord met de opgeslagen hash
    if (defined('ADMIN_PASSWORD_HASH') && password_verify($password, ADMIN_PASSWORD_HASH)) {
        // Wachtwoord is correct, zet de sessie variabele
        $_SESSION['loggedin'] = true;
        header('Location: ' . (defined('ADMIN_PANEL_FILE') ? ADMIN_PANEL_FILE : 'beheer-gpe-a4x7.php'));
        exit;
    } else {
        // Wachtwoord is incorrect, stuur terug naar login met een foutmelding
        header('Location: login.php?error=1');
        exit;
    }
} else {
    // Als iemand direct naar auth.php navigeert, stuur ze naar de login pagina
    header('Location: login.php');
    exit;
}
?>
