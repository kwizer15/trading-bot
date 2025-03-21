<?php

require __DIR__ . '/vendor/autoload.php';

use Kwizer15\TradingBot\Backtest\BacktestEngine;
use Kwizer15\TradingBot\Backtest\DataLoader;
use Kwizer15\TradingBot\BinanceAPI;
use Kwizer15\TradingBot\Strategy\StrategyFactory;

// Options pour getopt
$short_options = "";
$long_options = [
    "strategy:",       // --download
    "download",       // --download
    "symbol:",        // --symbol=value
    "params",         // --params
    "config:",        // --config=value
    "start-date:",    // --start-date=value
    "end-date:",      // --end-date=value
];

// Analyser les arguments de ligne de commande
$options = getopt($short_options, $long_options);

// Afficher l'utilisation
if (isset($argv[1]) && $argv[1] === '--help') {
    echo "Usage: php backtest.php [options]\n";
    echo "Options:\n";
    echo "  --strategy=STRATEGY          Strategie à utiliser\n";
    echo "  --download                   Télécharger de nouvelles données historiques\n";
    echo "  --symbol=SYMBOL              Symbole à tester (ex: BTCUSDT)\n";
    echo "  --params key1=value1 key2=value2  Paramètres de stratégie\n";
    echo "  --config=FILE                Utiliser un fichier de configuration alternatif\n";
    echo "  --start-date=DATE            Date de début (format: YYYY-MM-DD)\n";
    echo "  --end-date=DATE              Date de fin (format: YYYY-MM-DD)\n";
    exit(0);
}

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

// Écraser les dates si spécifiées en ligne de commande
if (isset($options['start-date'])) {
    $config['backtest']['start_date'] = $options['start-date'];
}

if (isset($options['end-date'])) {
    $config['backtest']['end_date'] = $options['end-date'];
}

// Créer l'instance de l'API Binance
$binanceAPI = new BinanceAPI($config);

// Créer le chargeur de données
$dataLoader = new DataLoader($binanceAPI);

// Symbole à tester (par défaut BTCUSDT ou spécifié en argument)
$symbol = isset($options['symbol']) ? $options['symbol'] : 'BTCUSDT';

// Vérifier si les données historiques existent déjà
$dataFile = __DIR__ . '/data/historical/' . $symbol . '_1h.csv';
$download = isset($options['download']);

if (!file_exists($dataFile) || $download) {
    echo "Téléchargement des données historiques pour {$symbol}...\n";

    // Télécharger les données historiques
    $startDate = $config['backtest']['start_date'];
    $endDate = $config['backtest']['end_date'];

    echo "Période: de {$startDate} à {$endDate}\n";

    $historicalData = $dataLoader->downloadHistoricalData($symbol, '1h', $startDate, $endDate);

    if (count($historicalData) === 0) {
        die("Erreur: Aucune donnée historique n'a pu être téléchargée. Vérifiez les dates et le symbole.\n");
    }

    // Sauvegarder les données dans un fichier CSV
    $dataLoader->saveToCSV($historicalData, $dataFile);

    echo "Données historiques sauvegardées dans {$dataFile}\n";
} else {
    echo "Chargement des données historiques depuis {$dataFile}...\n";
}

// Charger les données historiques
$historicalData = $dataLoader->loadFromCSV($dataFile);

echo "Données chargées : " . count($historicalData) . " points, du " .
    date('Y-m-d H:i:s', $historicalData[0][0]/1000) . " au " .
    date('Y-m-d H:i:s', $historicalData[count($historicalData)-1][0]/1000) . "\n";

// Déterminer la stratégie à tester

$strategyName = $options['strategy'] ?? 'MovingAverageStrategy';
try {
    $strategy = (new StrategyFactory())->create($strategyName);

    echo "Utilisation de la stratégie {$strategyName}\n";
} catch (Exception $e) {
    echo "Erreur lors de la création de la stratégie : " . $e->getMessage() . "\n";
    exit(1);
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
echo "Exécution du backtest...\n";
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
$resultFileName = str_replace(' ', '_', $strategy->getName());
$paramHash = substr(md5(uniqid('', true)), 0, 8);
$resultFileName .= "_" . $paramHash;
$resultsFile = __DIR__ . '/data/results_' . $resultFileName . '.json';

file_put_contents($resultsFile, json_encode($results, JSON_PRETTY_PRINT));

echo "Résultats détaillés sauvegardés dans {$resultsFile}\n";

echo "\nBacktest terminé.\n";