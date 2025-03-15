<?php

// Inclure les fichiers nécessaires
require_once __DIR__ . '/src/BinanceAPI.php';
require_once __DIR__ . '/src/TradingBot.php';
require_once __DIR__ . '/src/Strategy/StrategyInterface.php';
require_once __DIR__ . '/src/Strategy/MovingAverageStrategy.php';
require_once __DIR__ . '/src/Strategy/RSIStrategy.php';
require_once __DIR__ . '/src/Utils/Logger.php';

// Charger la configuration
$config = require_once __DIR__ . '/config/config.php';

// Créer le dossier de logs s'il n'existe pas
if (!is_dir(__DIR__ . '/logs')) {
    mkdir(__DIR__ . '/logs', 0777, true);
}

// Créer le logger
$logger = new Logger($config['logging']['file'], $config['logging']['level']);

$logger->log('info', 'Démarrage du bot de trading');

// Créer l'instance de l'API Binance
$binanceAPI = new BinanceAPI($config);

// Créer la stratégie
$strategyName = isset($argv[1]) ? $argv[1] : 'MovingAverageStrategy';

switch ($strategyName) {
    case 'RSI':
    case 'RSIStrategy':
        $logger->log('info', 'Utilisation de la stratégie RSI');
        $strategy = new RSIStrategy();
        break;

    case 'MA':
    case 'MovingAverage':
    case 'MovingAverageStrategy':
    default:
        $logger->log('info', 'Utilisation de la stratégie Moving Average Crossover');
        $strategy = new MovingAverageStrategy();
        break;
}

// Vérifier si des paramètres de stratégie sont fournis
if (isset($argv[2]) && $argv[2] === '--params') {
    $params = [];

    for ($i = 3; $i < $argc; $i++) {
        if (strpos($argv[$i], '=') !== false) {
            list($key, $value) = explode('=', $argv[$i]);
            $params[$key] = is_numeric($value) ? (float) $value : $value;
        }
    }

    if (!empty($params)) {
        $strategy->setParameters($params);
        $logger->log('info', 'Paramètres personnalisés: ' . json_encode($params));
    }
}

// Créer le bot de trading
$tradingBot = new TradingBot($binanceAPI, $strategy, $config, $logger);

// Fonction pour gérer le signal de fin
function handleShutdown($bot, $logger) {
    $logger->log('info', 'Signal de fin reçu, arrêt du bot');
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
$mode = isset($argv[1]) && $argv[1] === '--daemon' ? 'daemon' : 'single';

if ($mode === 'daemon') {
    $logger->log('info', 'Démarrage en mode daemon (continu)');

    // Boucle infinie avec intervalle de vérification
    while (true) {
        try {
            // Exécuter le bot
            $tradingBot->run();

            // Attendre l'intervalle configuré
            $logger->log('info', 'En attente pour ' . $config['schedule']['check_interval'] . ' secondes');
            sleep($config['schedule']['check_interval']);
        } catch (Exception $e) {
            $logger->log('error', 'Erreur: ' . $e->getMessage());
            // Attendre un peu avant de réessayer en cas d'erreur
            sleep(60);
        }
    }
} else {
    $logger->log('info', 'Exécution unique');

    // Exécuter le bot une seule fois
    try {
        $tradingBot->run();
        $logger->log('info', 'Exécution terminée');
    } catch (Exception $e) {
        $logger->log('error', 'Erreur: ' . $e->getMessage());
    }
}

// Classe Logger originale
class Logger {
    private $logFile;
    private $logLevel;
    private $levels = [
        'debug' => 0,
        'info' => 1,
        'warning' => 2,
        'error' => 3
    ];

    public function __construct($logFile, $level = 'info') {
        $this->logFile = $logFile;
        $this->logLevel = $this->levels[$level] ?? 1;

        // Créer le dossier de logs si nécessaire
        $dir = dirname($logFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
    }

    public function log($level, $message) {
        if ($this->levels[$level] < $this->logLevel) {
            return;
        }

        $logMessage = date('Y-m-d H:i:s') . " [{$level}] {$message}" . PHP_EOL;

        file_put_contents($this->logFile, $logMessage, FILE_APPEND);

        // Afficher également dans la console
        echo $logMessage;
    }
}