<?php

require __DIR__ . '/vendor/autoload.php';

use Kwizer15\TradingBot\Backtest\BacktestBinanceAPI;
use Kwizer15\TradingBot\Backtest\BacktestEngine;
use Kwizer15\TradingBot\Backtest\DataLoader;
use Kwizer15\TradingBot\BinanceAPI;
use Kwizer15\TradingBot\Clock\FixedClock;
use Kwizer15\TradingBot\Configuration\ApiConfiguration;
use Kwizer15\TradingBot\Configuration\BacktestConfiguration;
use Kwizer15\TradingBot\Configuration\TradingConfiguration;
use Kwizer15\TradingBot\DTO\KlineHistory;
use Kwizer15\TradingBot\Strategy\StrategyFactory;
use Kwizer15\TradingBot\TradingBot;

// Options pour getopt
$short_options = "";
$long_options = [
    "strategy:",       // --download
    "download",       // --download
    "symbol:",        // --symbol=value
//    "symbols:",       // --symbols=value1,value2
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
//    echo "  --symbols=SYMBOL             Liste des symboles à tester (ex: BTCUSDT,ETHUSDT)\n";
    echo "  --params key1=value1 key2=value2  Paramètres de stratégie\n";
    echo "  --config=FILE                Utiliser un fichier de configuration alternatif\n";
    echo "  --start-date=DATE            Date de début (format: YYYY-MM-DD)\n";
    echo "  --end-date=DATE              Date de fin (format: YYYY-MM-DD)\n";
    exit(0);
}

// Vérifier si un fichier de configuration alternatif est spécifié
$options['config'] ??= __DIR__ . '/config/config.php';
$config_file = $options['config'];
if (!is_readable($config_file)) {
    die("Erreur: Fichier de configuration spécifié introuvable: {$config_file}\n");
}

$config = require $config_file;

// Écraser les dates si spécifiées en ligne de commande
$config['backtest']['start_date'] = $options['start-date'] ?? $config['backtest']['start_date'];
$config['backtest']['end_date'] = $options['end-date'] ?? $config['backtest']['end_date'];

// Créer l'instance de l'API Binance
$binanceAPI = new BinanceAPI(new ApiConfiguration($config));

// Créer le chargeur de données
$dataLoader = new DataLoader($binanceAPI);

// Symbole à tester (par défaut BTCUSDT ou spécifié en argument)
$symbol = $options['symbol'] ?? 'BTCUSDT';

// Vérifier si les données historiques existent déjà
$dataFile = __DIR__ . '/data/historical/' . $symbol . '_1h.csv';
$download = isset($options['download']);

if ($download || !file_exists($dataFile)) {
    echo "Téléchargement des données historiques pour {$symbol}...\n";

    // Télécharger les données historiques
    $startDate = $config['backtest']['start_date'];
    $endDate = $config['backtest']['end_date'];

    echo "Période: de {$startDate} à {$endDate}\n";

    $downloadedHistoricalData = $dataLoader->downloadHistoricalData($symbol, '1h', $startDate, $endDate);

    if ($downloadedHistoricalData === []) {
        die("Erreur: Aucune donnée historique n'a pu être téléchargée. Vérifiez les dates et le symbole.\n");
    }

    // Sauvegarder les données dans un fichier CSV
    $dataLoader->saveToCSV($downloadedHistoricalData, $dataFile);

    echo "Données historiques sauvegardées dans {$dataFile}\n";
} else {
    echo "Chargement des données historiques depuis {$dataFile}...\n";
}

// Charger les données historiques
$dtoHistoricalData = KlineHistory::create($dataLoader->loadFromCSV($dataFile));

echo "Données chargées : " . $dtoHistoricalData->count() . " points, du " .
    $dtoHistoricalData->from() . " au " .
    $dtoHistoricalData->to() . "\n";

// Déterminer la stratégie à tester

$clock = new FixedClock();

// Configurer les paramètres de la stratégie s'ils sont fournis
$options['params'] ??= '';
$params = [];

$listParams = explode(' ', $options['params']);
// Récupérer les paramètres à partir des arguments de ligne de commande
foreach ($listParams as $param) {
    if (str_contains($param, '=')) {
        [$key, $value] = explode('=', $param);
        $params[$key] = is_numeric($value) ? (float) $value : $value;
    }
}

$strategyName = $options['strategy'] ?? 'MovingAverageStrategy';
try {
    $strategy = (new StrategyFactory())->create($strategyName, $params);

    echo "Utilisation de la stratégie {$strategyName}\n";
} catch (Exception $e) {
    echo "Erreur lors de la création de la stratégie : " . $e->getMessage() . "\n";
    exit(1);
}

if (!empty($params)) {
    $strategy->setParameters($params);
    echo "Paramètres personnalisés: " . json_encode($params) . "\n";
}

$tradingConfiguration = new TradingConfiguration($config);

// Créer le moteur de backtest
$backtester = new BacktestEngine(
    $strategy,
    $dtoHistoricalData,
    $tradingConfiguration,
    new BacktestConfiguration($config),
    $symbol,
);

// Exécuter le backtest
echo "Exécution du backtest...\n";
$results = $backtester->run();

// Afficher les résultats
echo "\nRésultats du backtest :\n";
echo "------------------------\n";
echo "Balance initiale : " . $results['initial_balance'] . " " . $tradingConfiguration->baseCurrency . "\n";
echo "Balance finale : " . $results['final_balance'] . " " . $tradingConfiguration->baseCurrency . "\n";
echo "Profit : " . $results['profit'] . " " . $tradingConfiguration->baseCurrency . " (" . $results['profit_pct'] . "%)\n";
echo "Nombre total de trades : " . $results['total_trades'] . "\n";
echo "Trades gagnants : " . $results['winning_trades'] . "\n";
echo "Trades perdants : " . $results['losing_trades'] . "\n";
echo "Taux de réussite : " . $results['win_rate'] . "%\n";
echo "Facteur de profit : " . $results['profit_factor'] . "\n";
echo "Drawdown maximum : " . $results['max_drawdown'] . "%\n";
echo "Frais payés : " . $results['fees_paid'] . " " . $tradingConfiguration->baseCurrency . "\n";
echo "Durée du backtest : " . $results['duration'] . " secondes\n";

// Sauvegarder les résultats dans un fichier
$resultFileName = str_replace(' ', '_', $strategy->getName());
$paramHash = substr(md5(uniqid('', true)), 0, 8);
$resultFileName .= "_" . $paramHash;
$resultsFile = __DIR__ . '/data/results_' .  $resultFileName . '.json';

file_put_contents($resultsFile, json_encode($results, JSON_PRETTY_PRINT));

echo "Résultats détaillés sauvegardés dans {$resultsFile}\n";

echo "\nBacktest terminé.\n";