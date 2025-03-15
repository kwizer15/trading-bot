<?php

// Inclure les fichiers nécessaires
require_once __DIR__ . '/src/BinanceAPI.php';
require_once __DIR__ . '/src/Strategy/StrategyInterface.php';
require_once __DIR__ . '/src/Strategy/MovingAverageStrategy.php';
require_once __DIR__ . '/src/Strategy/RSIStrategy.php';
require_once __DIR__ . '/src/Backtest/DataLoader.php';
require_once __DIR__ . '/src/Backtest/BacktestEngine.php';

// Charger la configuration
$config = require_once __DIR__ . '/config/config.php';

// Créer l'instance de l'API Binance
$binanceAPI = new BinanceAPI($config);

// Créer le chargeur de données
$dataLoader = new DataLoader($binanceAPI);

// Symbole à tester
$symbol = 'BTCUSDT';

// Vérifier si les données historiques existent déjà
$dataFile = __DIR__ . '/data/historical/' . $symbol . '_1h.csv';

if (!file_exists($dataFile) || isset($argv[1]) && $argv[1] === '--download') {
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

// Liste des stratégies à tester
$strategies = [
    new MovingAverageStrategy(),
    new RSIStrategy()
];

// Exécuter le backtest pour chaque stratégie
foreach ($strategies as $strategy) {
    echo "\nTest de la stratégie : " . $strategy->getName() . "\n";
    echo "Description : " . $strategy->getDescription() . "\n";

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
}

echo "\nBacktest terminé.\n";