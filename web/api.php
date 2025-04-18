<?php

use Kwizer15\TradingBot\Clock\RealClock;
use Kwizer15\TradingBot\Configuration\ApiConfiguration;
use Kwizer15\TradingBot\Configuration\BacktestConfiguration;
use Kwizer15\TradingBot\DTO\PositionList;
use Kwizer15\TradingBot\Strategy\StrategyFactory;
use Kwizer15\TradingBot\TradingBot;
use Kwizer15\TradingBot\Utils\Logger;

require dirname(__DIR__) . '/vendor/autoload.php';

// API pour les requêtes AJAX
session_start();

// Vérifier l'authentification
require_once 'config.php';
require_once 'includes/functions.php';
require_once 'auth/check.php';

// Vérifier qu'une action est spécifiée
if (!isset($_REQUEST['action'])) {
    send_json_response(['success' => false, 'message' => 'Action non spécifiée']);
}

$action = $_REQUEST['action'];

$config = get_config();
$api = new Kwizer15\TradingBot\BinanceAPI(new ApiConfiguration($config));

// Traiter l'action demandée
switch ($action) {
    // Vérifier l'état du bot
    case 'check_bot_status':
        $status = is_bot_running() ? 'running' : 'stopped';
        send_json_response([
            'success' => true,
            'status' => $status
        ]);
        break;

    // Démarrer le bot
    case 'start_bot':
        try {
            // Vérifier si le bot est déjà en cours d'exécution
            if (is_bot_running()) {
                send_json_response(['success' => false, 'message' => 'Le bot est déjà en cours d\'exécution']);
            }

            // Chemin du script de démarrage
            $daemon_script = BOT_PATH . '/bin/daemon.sh';

            // Si le script de démarrage n'existe pas, le créer
            if (!file_exists($daemon_script)) {
                $script_content = "#!/bin/bash\ncd " . BOT_PATH . "\nphp run.php --daemon > /dev/null 2>&1 &\necho \$! > " . BOT_PATH . "/bin/bot.pid\n";
                file_put_contents($daemon_script, $script_content);
                chmod($daemon_script, 0755);
            }

            // Exécuter le script
            $output = [];
            $return_var = 0;
            exec($daemon_script, $output, $return_var);

            if ($return_var !== 0) {
                send_json_response(['success' => false, 'message' => 'Erreur lors du démarrage du bot: ' . implode("\n", $output)]);
            }

            send_json_response(['success' => true]);
        } catch (Exception $e) {
            send_json_response(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
        }
        break;

    // Arrêter le bot
    case 'stop_bot':
        try {
            // Vérifier si le bot est en cours d'exécution
            if (!is_bot_running()) {
                send_json_response(['success' => false, 'message' => 'Le bot n\'est pas en cours d\'exécution']);
            }

            // Lire le PID
            $pid_file = BOT_PATH . '/bin/bot.pid';
            if (!file_exists($pid_file)) {
                send_json_response(['success' => false, 'message' => 'Fichier PID non trouvé']);
            }

            $pid = trim(file_get_contents($pid_file));

            // Arrêter le processus
            if (PHP_OS_FAMILY === 'Windows') {
                exec("taskkill /F /PID $pid");
            } else {
                exec("kill $pid");
            }

            // Supprimer le fichier PID
            unlink($pid_file);

            send_json_response(['success' => true]);
        } catch (Exception $e) {
            send_json_response(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
        }
        break;

    // Exécuter un backtest
    case 'run_backtest':
        try {
            // Vérifier les paramètres requis
            if (!isset($_POST['strategy']) || empty($_POST['strategy'])) {
                send_json_response(['success' => false, 'message' => 'Stratégie non spécifiée']);
            }

            $strategy = $_POST['strategy'];
            $symbol = $_POST['symbol'] ?? 'BTCUSDT';
            $period_start = $_POST['period_start'] ?? date('Y-m-d', strtotime('-1 year'));
            $period_end = $_POST['period_end'] ?? date('Y-m-d');
            $initial_balance = isset($_POST['initial_balance']) ? floatval($_POST['initial_balance']) : 1000;
            $download_data = isset($_POST['download_data']) && $_POST['download_data'] == '1';

            // Extraire tous les paramètres de stratégie du formulaire
            $strategy_params = [];
            foreach ($_POST as $key => $value) {
                if (strpos($key, 'param_') === 0) {
                    // Extraire le nom du paramètre (enlever le préfixe param_)
                    $param_name = substr($key, 6);
                    // Convertir en nombre si c'est numérique
                    $strategy_params[$param_name] = is_numeric($value) ? (float) $value : $value;
                }
            }

            // Debug des paramètres
            error_log("Executing backtest with strategy: " . $strategy);
            error_log("Parameters: " . json_encode($strategy_params));
            error_log("Symbol: " . $symbol);
            error_log("Period: " . $period_start . " to " . $period_end);

            // Modifier temporairement la configuration pour le backtest
            $config_file = BOT_PATH . '/config/config.php';
            $current_config = require $config_file;
            $backtest_config = $current_config;
            $backtestConfig = (new BacktestConfiguration($current_config))
                ->withInitialBalance($initial_balance)
                ->withPeriod($period_start, $period_end)
            ;

            $tradingConfiguration = $backtestConfig->tradingConfiguration;
            // Ajouter les paramètres spécifiques au trading si présents
            if (isset($strategy_params['investment_per_trade'])) {
                $tradingConfiguration = $tradingConfiguration->withInvestmentPerTrade($strategy_params['investment_per_trade']);
                // Ne pas passer ce paramètre à la stratégie
                unset($strategy_params['investment_per_trade']);
            }

            if (isset($strategy_params['stop_loss_percentage'])) {
                $tradingConfiguration = $tradingConfiguration->withStopLossPercentage($strategy_params['stop_loss_percentage']);
                // Ne pas passer ce paramètre à la stratégie
                unset($strategy_params['stop_loss_percentage']);
            }

            if (isset($strategy_params['take_profit_percentage'])) {
                $tradingConfiguration = $tradingConfiguration->withTakeProfitPercentage($strategy_params['take_profit_percentage']);
                // Ne pas passer ce paramètre à la stratégie
                unset($strategy_params['take_profit_percentage']);
            }

            $backtestConfig->withTradingConfiguration($tradingConfiguration)->export();

            // Préparer le dossier pour les données historiques
            $data_dir = BOT_PATH . '/data/historical';
            if (!is_dir($data_dir)) {
                mkdir($data_dir, 0777, true);
            }

            // Préparer les paramètres pour la commande en ligne
            $params_string = '';
            foreach ($strategy_params as $key => $value) {
                $params_string .= ' ' . escapeshellarg($key . '=' . $value);
            }

            // Construire la commande
            $command = 'cd ' . BOT_PATH . ' && php backtest.php --strategy=' . escapeshellarg($strategy);

            // Ajouter le flag de téléchargement si nécessaire
            if ($download_data) {
                $command .= ' --download';
            }

            // Ajouter le symbole
            $command .= ' --symbol=' . escapeshellarg($symbol);

            // Ajouter les paramètres si présents
            if (!empty($strategy_params)) {
                $command .= ' --params' . $params_string;
            }

            // Ajouter le chemin de la configuration temporaire
            $command .= ' --config=' . escapeshellarg($backtestConfig->exportPath());

            // Journaliser la commande complète pour le débogage
            error_log("Executing command: " . $command);

            // Exécuter la commande
            $output = [];
            $return_var = 0;
            exec($command . ' 2>&1', $output, $return_var);

            $backtestConfig->clearExport();

            if ($return_var !== 0) {
                send_json_response([
                    'success' => false,
                    'message' => 'Erreur lors de l\'exécution du backtest: ' . implode("\n", $output),
                    'command' => $command,
                    'output' => $output
                ]);
            }

            send_json_response(['success' => true]);
        } catch (Exception $e) {
            send_json_response(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
        }
        break;
    // Obtenir les données d'équité
    case 'get_equity_data':
        try {
            $equity_data = [];
            $labels = [];
            $currency = get_config('trading')['base_currency'];

            // Récupérer l'historique des trades
            $trades = get_trade_history();

            // Si nous avons des trades, calculer l'équité au fil du temps
            if (!empty($trades)) {
                // Trier par date
                usort($trades, function($a, $b) {
                    return $a['exit_time'] - $b['exit_time'];
                });

                $initial_balance = get_config('backtest')['initial_balance'];
                $current_balance = $initial_balance;

                // Ajouter le point initial
                $equity_data[] = $current_balance;
                $labels[] = date('Y-m-d', $trades[0]['entry_time'] / 1000);

                // Calculer l'équité après chaque trade
                foreach ($trades as $trade) {
                    $current_balance += $trade['profit'];
                    $equity_data[] = $current_balance;
                    $labels[] = date('Y-m-d', $trade['exit_time'] / 1000);
                }
            }

            send_json_response([
                'success' => true,
                'data' => [
                    'equity' => $equity_data,
                    'labels' => $labels,
                    'currency' => $currency
                ]
            ]);
        } catch (Exception $e) {
            send_json_response(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
        }
        break;

    // Sauvegarder les paramètres
    case 'save_settings':
        try {
            // Vérifier que nous avons des données
            if (empty($_POST)) {
                send_json_response(['success' => false, 'message' => 'Aucune donnée reçue']);
            }

            // Récupérer la configuration actuelle
            $config_file = BOT_PATH . '/config/config.php';
            $current_config = require $config_file;

            foreach ($_POST as $section => $values) {
                // Ignorer l'action du formulaire
                if ($section === 'action') {
                    continue;
                }

                // S'assurer que la section existe dans la configuration
                if (!isset($current_config[$section])) {
                    $current_config[$section] = [];
                }

                // Si c'est un tableau, fusionner avec la configuration existante
                if (is_array($values)) {
                    foreach ($values as $key => $value) {
                        // Convertir les valeurs numériques
                        if (is_numeric($value)) {
                            $value = strpos($value, '.') !== false ? (float)$value : (int)$value;
                        } else if ($value === '1') {
                            $value = true;
                        } else if ($value === '0') {
                            $value = false;
                        }

                        // Traitement spécial pour les tableaux (comme trading[symbols])
                        if (is_array($value)) {
                            $current_config[$section][$key] = $value;
                        } else {
                            $current_config[$section][$key] = $value;
                        }
                    }
                } else {
                    // Clé simple
                    $current_config[$section] = $values;
                }
            }

            // Générer le contenu du fichier de configuration
            $config_content = "<?php\n\nreturn " . var_export($current_config, true) . ";\n";

            // Sauvegarder la configuration
            file_put_contents($config_file, $config_content);

            send_json_response(['success' => true]);
        } catch (Exception $e) {
            send_json_response(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
        }
        break;

        case 'get_base_currencies':
            try {
                $baseCurrencies = $api->getBaseCurrencies();

                send_json_response([
                    'success' => true,
                    'data' => $baseCurrencies
                ]);
            } catch (Exception $e) {
                send_json_response([
                    'success' => false,
                    'message' => $e->getMessage()
                ]);
            }
            break;

        case 'get_symbols':
                try {
                    $baseCurrency = $_GET['base_currency'] ?? 'USDT';
                    $symbols = $api->getAvailableSymbols($baseCurrency);

                    // Filtrer les stablecoins si demandé
                    if (isset($_GET['filter_stablecoins']) && $_GET['filter_stablecoins'] === 'true') {
                        $stablecoins = ['USDT', 'USDC', 'BUSD', 'DAI', 'TUSD', 'UST', 'USDP', 'USDD', 'GUSD'];
                        $symbols = array_filter($symbols, function($symbol) use ($stablecoins) {
                            return !in_array($symbol, $stablecoins);
                        });
                    }

                    // Si top volume demandé, on pourrait ajouter une logique pour récupérer les symboles
                    // par volume, mais cela nécessiterait un autre appel API
                    // Pour l'instant, on retourne simplement les symboles triés
                    sort($symbols);

                    send_json_response([
                        'success' => true,
                        'data' => array_values($symbols)
                    ]);
                } catch (Exception $e) {
                    send_json_response([
                        'success' => false,
                        'message' => $e->getMessage()
                    ]);
                }
                break;

    case 'sell_position':
        ob_start();
        $apiConfig = new Kwizer15\TradingBot\Configuration\ApiConfiguration($config);
        $tradingConfig = new Kwizer15\TradingBot\Configuration\TradingConfiguration($config);
        $strategyFactory = new StrategyFactory();
        $strategy = $strategyFactory->create('DynamicPositionStrategy', [], true);
        $clock = new RealClock();
        $logger = new Logger($clock, dirname(__DIR__) . '/logs/trading.log');
        $tradingBot = new TradingBot($api, $strategy, $tradingConfig, $logger, dirname(__DIR__).'/data/positions.json', dirname(__DIR__).'/data/trades.json', $clock);

        $symbol = $_POST['symbol'] ?? null;
        try {
            $tradingBot->closePosition($symbol);
            $logger->notice('Vente manuelle de '.$symbol.' effectuée');
            ob_end_clean();
            send_json_response(['success' => true, 'message' => 'Position vendue avec succès']);
        } catch (Exception $e) {
            ob_end_clean();
            send_json_response(['success' => false, 'message' => $e->getMessage()]);
        }

        break;

    // Action non reconnue
    default:
        send_json_response(['success' => false, 'message' => 'Action non reconnue: ' . $action]);
        break;
}

/**
 * Envoie une réponse JSON et termine le script
 */
function send_json_response($data) {
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}
