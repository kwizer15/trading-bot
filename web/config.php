<?php
// Configuration de l'interface web

// Chemin de base du bot de trading (en remontant d'un niveau depuis le dossier web)
define('BOT_PATH', dirname(__DIR__));

// Inclure la configuration du bot
$bot_config = require BOT_PATH . '/config/config.php';

// Configuration de l'interface web
$web_config = [
    'site_title' => 'Bot de Trading Binance',
    'auth' => [
        'enabled' => true,  // Activer/désactiver l'authentification
        'username' => 'admin',  // Nom d'utilisateur par défaut
        'password' => 'admin',  // Mot de passe par défaut (à changer !)
    ],
    'refresh_interval' => 60,  // Intervalle de rafraîchissement automatique (en secondes)
    'max_log_lines' => 1000,  // Nombre maximum de lignes de log à afficher
    'chart_days' => 30,  // Nombre de jours à afficher dans les graphiques
];

// Fonction pour accéder à la configuration
function get_config($key = null) {
    global $bot_config, $web_config;

    if ($key === null) {
        return array_merge($bot_config, $web_config);
    }

    // Vérifier d'abord dans la config web
    if (isset($web_config[$key])) {
        return $web_config[$key];
    }

    // Ensuite dans la config du bot
    if (isset($bot_config[$key])) {
        return $bot_config[$key];
    }

    return null;
}

// Fuseau horaire
date_default_timezone_set('Europe/Paris');
