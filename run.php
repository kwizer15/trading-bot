<?php

use Kwizer15\TradingBot\BinanceAPI;
use Kwizer15\TradingBot\Clock\RealClock;
use Kwizer15\TradingBot\Configuration\ApiConfiguration;
use Kwizer15\TradingBot\Configuration\LoggingConfiguration;
use Kwizer15\TradingBot\Configuration\TradingConfiguration;
use Kwizer15\TradingBot\Strategy\StrategyFactory;
use Kwizer15\TradingBot\TradingBot;
use Kwizer15\TradingBot\Utils\Logger;
use Psr\Log\LoggerInterface;

require __DIR__ . '/vendor/autoload.php';

// Charger la configuration
$config = require __DIR__ . '/config/config.php';

// Créer le dossier de logs s'il n'existe pas
if (!is_dir(__DIR__ . '/logs')) {
    mkdir(__DIR__ . '/logs', 0777, true);
}

$loggingConfig = new LoggingConfiguration($config);

$clock = new RealClock();
// Créer le logger
$logger = new Logger($clock, $loggingConfig->file, $loggingConfig->level);

$logger->info('Démarrage du bot de trading');

// Créer l'instance de l'API Binance
$binanceAPI = new BinanceAPI(new ApiConfiguration($config));

$options = getopt('', [
    'strategy:',
    'daemon',
    'params:',
]);

// Vérifier si des paramètres de stratégie sont fournis
$params = [];
if ($options['params'] ?? []) {
    $queryString = explode(' ', $options['params']);
    foreach ($queryString as $param) {
        list($key, $value) = explode('=', $param);
        $params[$key] = is_numeric($value) ? (float) $value : $value;
    }

    if (!empty($params)) {
        $logger->info('Paramètres personnalisés: ' . json_encode($params));
    }
}

// Créer la stratégie
$strategyName = $options['strategy'] ?? 'DynamicPositionStrategy';
try {
    $strategy = (new StrategyFactory())->create($strategyName, $params);

    echo "Utilisation de la stratégie {$strategyName}\n";
} catch (Exception $e) {
    echo "Erreur lors de la création de la stratégie : " . $e->getMessage() . "\n";
    exit(1);
}

// Créer le bot de trading
$tradingBot = new TradingBot(
    $binanceAPI,
    $strategy,
    new TradingConfiguration($config),
    $logger,
    __DIR__ . '/data/positions.json',
    $clock,
);

// Fonction pour gérer le signal de fin
function handleShutdown($bot, LoggerInterface $logger) {
    $logger->info('Signal de fin reçu, arrêt du bot');
    // Sauvegarde de l'état si nécessaire
    exit(0);
}

// Enregistrer le gestionnaire de signal
if (function_exists('pcntl_signal')) {
    declare(ticks = 1);
    pcntl_signal(SIGTERM, function() use ($tradingBot, $logger) {
        handleShutdown($tradingBot, $logger);
    });
    pcntl_signal(SIGINT, function() use ($tradingBot, $logger) {
        handleShutdown($tradingBot, $logger);
    });
}

// Mode de fonctionnement
$mode = isset($options['daemon']) ? 'daemon' : 'single';

if ($mode === 'daemon') {
    $logger->info('Démarrage en mode daemon (continu)');

    // Boucle infinie avec intervalle de vérification
    while (true) {
        try {
            // Exécuter le bot
            $tradingBot->run();

            // Attendre l'intervalle configuré
            $logger->info('En attente pour ' . $config['schedule']['check_interval'] . ' secondes');
            sleep($config['schedule']['check_interval']);
        } catch (Exception $e) {
            $logger->error('Erreur: ' . $e->getMessage());
            // Attendre un peu avant de réessayer en cas d'erreur
            sleep(60);
        }
    }
} else {
    $logger->info( 'Exécution unique');

    // Exécuter le bot une seule fois
    try {
        $tradingBot->run();
        $logger->info( 'Exécution terminée');
    } catch (Exception $e) {
        $logger->error( 'Erreur: ' . $e->getMessage());
    }
}
