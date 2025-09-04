<?php
session_start();

// Verwijder alle sessie variabelen
$_SESSION = array();

// Vernietig de sessie
session_destroy();

// Stuur de gebruiker terug naar de login pagina
header('Location: /login.php');
exit;
?>
