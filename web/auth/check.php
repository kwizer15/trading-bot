<?php
// Vérification de l'authentification

// Si l'authentification est désactivée, on considère que l'utilisateur est connecté
if (!get_config('auth')['enabled']) {
    $_SESSION['logged_in'] = true;
    return;
}

// Si nous sommes sur la page de login, ne pas rediriger
if (basename($_SERVER['PHP_SELF']) === 'login.php') {
    return;
}

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    // Rediriger vers la page de login
    header('Location: auth/login.php');
    exit;
}