<?php

// Inclure les fichiers nécessaires
require_once __DIR__ . '/src/BinanceAPI.php';
require_once __DIR__ . '/src/Strategy/StrategyInterface.php';
require_once __DIR__ . '/src/Strategy/MovingAverageStrategy.php';
require_once __DIR__ . '/src/Strategy/RSIStrategy.php';
require_once __DIR__ . '/src/Backtest/DataLoader.php';
require_once __DIR__ . '/src/Backtest/BacktestEngine.php';

// Analyser les arguments de ligne de commande
$options = getopt('', ['download', 'symbol:', 'params', 'config:']);

// Vérifier si un fichier de configuration alternatif est spécifié
if (isset($options['config'])) {
    $config_file = $options['config'];
    if (file_exists($config_file)) {
        $config = require $config_file;
    } else {
        die("Erreur: Fichier de configuration spécifié introuvable: {$config_file}\n");
    }
} else {
    // Charger la configuration par défaut
    $config = require_once __DIR__ . '/config/config.php';
}

// Créer l'instance de l'API Binance
$binanceAPI = new BinanceAPI($config);

// Créer le chargeur de données
$dataLoader = new DataLoader($binanceAPI);

// Symbole à tester (par défaut BTCUSDT ou spécifié en argument)
$symbol = isset($options['symbol']) ? $options['symbol'] : 'BTCUSDT';

// Vérifier si les données historiques existent déjà
$dataFile = __DIR__ . '/data/historical/' . $symbol . '_1h.csv';

if (!file_exists($dataFile) || isset($options['download'])) {
    echo "Téléchargement des données historiques pour {$symbol}...\n";

    // Télécharger les données historiques
    $startDate = $config['backtest']['start_date'];
    $endDate = $config['backtest']['end_date'];

    $historicalData = $dataLoader->downloadHistoricalData($symbol, '1h', $startDate, $endDate);

    // Sauvegarder les données dans un fichier CSV
    $dataLoader->saveToCSV($historicalData, $dataFile);

    echo "Données historiques sauvegardées dans {$dataFile}\n";
} else {
    echo "Chargement des données historiques depuis {$dataFile}...\n";
}

// Charger les données historiques
$historicalData = $dataLoader->loadFromCSV($dataFile);

echo "Données chargées : " . count($historicalData) . " points\n";

// Déterminer la stratégie à tester
$strategyName = isset($argv[1]) ? $argv[1] : 'MovingAverageStrategy';

// Instancier la stratégie
switch ($strategyName) {
    case 'RSI':
    case 'RSIStrategy':
        echo "Utilisation de la stratégie RSI\n";
        $strategy = new RSIStrategy();
        break;

    case 'MA':
    case 'MovingAverage':
    case 'MovingAverageStrategy':
    default:
        echo "Utilisation de la stratégie Moving Average Crossover\n";
        $strategy = new MovingAverageStrategy();
        break;
}

// Configurer les paramètres de la stratégie s'ils sont fournis
if (isset($options['params'])) {
    $params = [];

    // Récupérer les paramètres à partir des arguments de ligne de commande
    for ($i = 2; $i < $argc; $i++) {
        if (strpos($argv[$i], '=') !== false) {
            list($key, $value) = explode('=', $argv[$i]);
            $params[$key] = is_numeric($value) ? (float) $value : $value;
        }
    }

    if (!empty($params)) {
        $strategy->setParameters($params);
        echo "Paramètres personnalisés: " . json_encode($params) . "\n";
    }
}

// Créer le moteur de backtest
$backtester = new BacktestEngine($strategy, $historicalData, $config);

// Exécuter le backtest
$results = $backtester->run();

// Afficher les résultats
echo "\nRésultats du backtest :\n";
echo "------------------------\n";
echo "Balance initiale : " . $results['initial_balance'] . " " . $config['trading']['base_currency'] . "\n";
echo "Balance finale : " . $results['final_balance'] . " " . $config['trading']['base_currency'] . "\n";
echo "Profit : " . $results['profit'] . " " . $config['trading']['base_currency'] . " (" . $results['profit_pct'] . "%)\n";
echo "Nombre total de trades : " . $results['total_trades'] . "\n";
echo "Trades gagnants : " . $results['winning_trades'] . "\n";
echo "Trades perdants : " . $results['losing_trades'] . "\n";
echo "Taux de réussite : " . $results['win_rate'] . "%\n";
echo "Facteur de profit : " . $results['profit_factor'] . "\n";
echo "Drawdown maximum : " . $results['max_drawdown'] . "%\n";
echo "Frais payés : " . $results['fees_paid'] . " " . $config['trading']['base_currency'] . "\n";
echo "Durée du backtest : " . $results['duration'] . " secondes\n";

// Sauvegarder les résultats dans un fichier
$resultsFile = __DIR__ . '/data/results_' . str_replace(' ', '_', $strategy->getName()) . '.json';
file_put_contents($resultsFile, json_encode($results, JSON_PRETTY_PRINT));

echo "Résultats détaillés sauvegardés dans {$resultsFile}\n";

echo "\nBacktest terminé.\n";