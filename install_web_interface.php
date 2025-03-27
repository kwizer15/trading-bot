<?php
/**
 * Script d'installation de l'interface web pour le bot de trading Binance
 *
 * Ce script doit être exécuté après l'installation du bot principal.
 * Il crée les dossiers nécessaires et télécharge les bibliothèques requises.
 */

// Définir le chemin du bot (adapté si nécessaire)
$bot_path = __DIR__;
$web_path = $bot_path . '/web';

echo "=== Installation de l'interface web pour le Bot de Trading Binance ===\n\n";

// 1. Créer le dossier web s'il n'existe pas
echo "Création du dossier web...\n";
if (!is_dir($web_path)) {
    mkdir($web_path, 0755, true);
} else {
    echo "Le dossier web existe déjà.\n";
}

// 2. Créer les sous-dossiers
echo "Création des sous-dossiers...\n";
$subdirs = [
    'assets/css',
    'assets/js',
    'assets/img',
    'includes',
    'pages',
    'auth'
];

foreach ($subdirs as $dir) {
    $path = $web_path . '/' . $dir;
    if (!is_dir($path)) {
        mkdir($path, 0755, true);
    }
}

// 3. Télécharger les bibliothèques externes
echo "Téléchargement des bibliothèques externes...\n";

$libraries = [
    // Bootstrap
    'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' => $web_path . '/assets/css/bootstrap.min.css',
    'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js' => $web_path . '/assets/js/bootstrap.bundle.min.js',

    // jQuery
    'https://code.jquery.com/jquery-3.6.0.min.js' => $web_path . '/assets/js/jquery.min.js',

    // Chart.js
    'https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.js' => $web_path . '/assets/js/chart.min.js',
];

foreach ($libraries as $url => $path) {
    echo "Téléchargement de : " . basename($path) . "...\n";

    if (!file_exists($path)) {
        $content = file_get_contents($url);
        if ($content) {
            file_put_contents($path, $content);
        } else {
            echo "ERREUR : Impossible de télécharger " . basename($path) . "\n";
        }
    } else {
        echo "Le fichier " . basename($path) . " existe déjà.\n";
    }
}

// 4. Créer le fichier .htaccess pour sécuriser l'accès
echo "Création du fichier .htaccess...\n";
$htaccess_content = <<<EOT
# Protéger les fichiers sensibles
<FilesMatch "\.(json|log|txt|md|sh)$">
    Order Allow,Deny
    Deny from all
</FilesMatch>

# Autoriser l'accès à l'interface web
<FilesMatch "\.(php|css|js|jpg|jpeg|png|gif|ico|svg)$">
    Order Allow,Deny
    Allow from all
</FilesMatch>

# Rediriger vers index.php
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteBase /
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule . /index.php [L]
</IfModule>
EOT;

file_put_contents($web_path . '/.htaccess', $htaccess_content);

// 5. Créer un fichier data/current_strategy.json si nécessaire
echo "Vérification de la configuration de stratégie actuelle...\n";
$strategy_file = $bot_path . '/data/current_strategy.json';

if (!file_exists($strategy_file)) {
    $default_strategy = [
        'class' => 'MovingAverageStrategy',
        'name' => 'Moving Average Crossover',
        'parameters' => [
            'short_period' => 9,
            'long_period' => 21,
            'price_index' => 4
        ],
        'timestamp' => time()
    ];

    file_put_contents($strategy_file, json_encode($default_strategy, JSON_PRETTY_PRINT));
    echo "Fichier de stratégie par défaut créé.\n";
} else {
    echo "Le fichier de stratégie existe déjà.\n";
}

// 6. Créer un fichier d'historique de trades vide si nécessaire
echo "Vérification de l'historique des trades...\n";
$history_file = $bot_path . '/data/trades.json';

if (!file_exists($history_file)) {
    file_put_contents($history_file, '[]');
    echo "Fichier d'historique des trades créé.\n";
} else {
    echo "Le fichier d'historique des trades existe déjà.\n";
}

// 7. Finalisation
echo "\n=== Installation terminée ===\n";
echo "L'interface web a été installée dans le dossier : " . $web_path . "\n";
echo "Vous pouvez y accéder en configurant un serveur web pour pointer vers ce dossier.\n";
echo "Sur un NAS Synology, vous pouvez utiliser Web Station pour créer un hôte virtuel.\n\n";