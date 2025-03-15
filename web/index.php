<?php

require dirname(__DIR__) . '/vendor/autoload.php';

// Point d'entrée principal de l'interface web
session_start();

// Inclure la configuration
require_once 'config.php';
require_once 'includes/functions.php';

// Vérifier l'authentification
require_once 'auth/check.php';

// Définir la page par défaut
$page = 'dashboard';

// Vérifier si une page est spécifiée
if (isset($_GET['page']) && !empty($_GET['page'])) {
    $requested_page = $_GET['page'];

    // Liste des pages autorisées
    $allowed_pages = ['dashboard', 'positions', 'backtest', 'strategies', 'settings', 'logs'];

    if (in_array($requested_page, $allowed_pages)) {
        $page = $requested_page;
    }
}

// Inclure l'en-tête
include 'includes/header.php';

// Inclure la barre latérale
include 'includes/sidebar.php';

// Inclure le contenu de la page
$file_path = "pages/{$page}.php";
if (file_exists($file_path)) {
    include $file_path;
} else {
    echo '<div class="container-fluid mt-4"><div class="alert alert-danger">Page non trouvée</div></div>';
}

// Inclure le pied de page
include 'includes/footer.php';