<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Détruire toutes les variables de session
$_SESSION = array();


if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Finalement, détruire la session.
session_destroy();

// Rediriger vers la page d'accueil du frontend
redirect('index.php');
?>
