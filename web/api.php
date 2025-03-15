<?php
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
            $daemon_script = BOT_PATH . '/daemon.sh';

            // Si le script de démarrage n'existe pas, le créer
            if (!file_exists($daemon_script)) {
                $script_content = "#!/bin/bash\ncd " . BOT_PATH . "\nphp run.php --daemon > /dev/null 2>&1 &\necho \$! > " . BOT_PATH . "/bot.pid\n";
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
            $pid_file = BOT_PATH . '/bot.pid';
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
            $params = [];

            // Récupérer les paramètres
            foreach ($_POST as $key => $value) {
                if (strpos($key, 'param_') === 0) {
                    $param_name = substr($key, 6);
                    $params[$param_name] = is_numeric($value) ? (float) $value : $value;
                }
            }

            // Construire la commande
            $command = 'php ' . BOT_PATH . '/backtest.php ' . escapeshellarg($strategy);

            if (!empty($params)) {
                $command .= ' --params';
                foreach ($params as $key => $value) {
                    $command .= ' ' . escapeshellarg($key . '=' . $value);
                }
            }

            // Exécuter la commande
            $output = [];
            $return_var = 0;
            exec($command . ' 2>&1', $output, $return_var);

            if ($return_var !== 0) {
                send_json_response([
                    'success' => false,
                    'message' => 'Erreur lors de l\'exécution du backtest: ' . implode("\n", $output)
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

            // Mettre à jour la configuration
            foreach ($_POST as $key => $value) {
                // Traiter les clés imbriquées (ex: trading[base_currency])
                if (strpos($key, '[') !== false && strpos($key, ']') !== false) {
                    preg_match('/^([^\[]+)\[([^\]]+)\]$/', $key, $matches);
                    if (count($matches) === 3) {
                        $section = $matches[1];
                        $option = $matches[2];

                        if (isset($current_config[$section])) {
                            $current_config[$section][$option] = is_numeric($value) ? (float) $value : $value;
                        }
                    }
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
