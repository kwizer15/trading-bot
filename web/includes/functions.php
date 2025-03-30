<?php
// Fonctions utilitaires pour l'interface web

/**
 * Récupère les positions ouvertes
 */
function get_positions() {
    $positions_file = BOT_PATH . '/data/positions.json';

    if (file_exists($positions_file)) {
        $positions = json_decode(file_get_contents($positions_file), true) ?: [];
        uasort($positions, function($a, $b) {
           return ($b['profit_loss_pct'] * 100) - ($a['profit_loss_pct'] * 100);
        });
        return $positions;
    }

    return [];
}

/**
 * Récupère l'historique des trades
 */
function get_trade_history() {
    $history_file = dirname(__DIR__, 2) . '/data/trades.json';

    if (file_exists($history_file)) {
        $history = json_decode(file_get_contents($history_file), true);
        return $history ?: [];
    }

    return [];
}

/**
 * Récupère les résultats de backtest
 */
function get_backtest_results($strategy = null) {
    $results = [];
    $results_dir = BOT_PATH . '/data';

    // Scanner le dossier des résultats
    $files = glob($results_dir . '/results_*.json');

    foreach ($files as $file) {
        $content = json_decode(file_get_contents($file), true);

        // Vérifier que le fichier a été correctement décodé
        if ($content === null) {
            continue;
        }

        // Si une stratégie est spécifiée, filtrer les résultats
        if ($strategy !== null && isset($content['strategy']) && $content['strategy'] != $strategy) {
            continue;
        }

        // S'assurer que les champs nécessaires existent pour éviter les erreurs
        if (!isset($content['equity_curve'])) {
            $content['equity_curve'] = [];
        }

        if (!isset($content['trades'])) {
            $content['trades'] = [];
        }

        // Autres vérifications de sécurité
        $content['profit'] = isset($content['profit']) ? $content['profit'] : 0;
        $content['profit_pct'] = isset($content['profit_pct']) ? $content['profit_pct'] : 0;
        $content['win_rate'] = isset($content['win_rate']) ? $content['win_rate'] : 0;
        $content['max_drawdown'] = isset($content['max_drawdown']) ? $content['max_drawdown'] : 0;

        $results[] = $content;
    }

    // Trier par profit
    usort($results, function($a, $b) {
        $a_profit = isset($a['profit_pct']) ? $a['profit_pct'] : 0;
        $b_profit = isset($b['profit_pct']) ? $b['profit_pct'] : 0;
        return $b_profit - $a_profit;
    });

    return $results;
}

/**
 * Récupère les stratégies disponibles
 */
function get_strategies() {
    $strategies_file = BOT_PATH . '/config/strategies.php';

    if (file_exists($strategies_file)) {
        $strategies = require $strategies_file;
        return $strategies;
    }

    return [];
}

/**
 * Récupère les logs du bot
 */
function get_logs($max_lines = null): iterable {
    $log_file = get_config('logging')['file'];

    if (!file_exists($log_file)) {
        return [];
    }

    $max_lines ??= get_config('max_log_lines');
    $logs = [];
    $handle = fopen($log_file, 'r');

    if ($handle) {
        // Si le fichier est trop grand, on lit seulement les dernières lignes
        $filesize = filesize($log_file);
        if ($filesize > 1024 * 1024 && $max_lines > 0) { // 1 MB
            // Position approximative pour lire les dernières lignes
            $seekPosition = max(0, $filesize - 100 * $max_lines);
            fseek($handle, $seekPosition);

            // Ignorer la première ligne partielle
            fgets($handle);
        }

        while (($line = fgets($handle)) !== false) {
            // Parser la ligne de log
            if (!preg_match('/^(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}) \[(\w+)\] (.+)$/', $line, $matches)) {
                continue;
            }
            $logs[] = [
                'timestamp' => $matches[1],
                'level' => $matches[2],
                'message' => $matches[3]
            ];
        }

        fclose($handle);
    }

    $count = $max_lines;
    while ($count-- > 0 && $logs !== []) {
        yield array_pop($logs);
    }

    return $logs;
}

/**
 * Vérifie si le bot est en cours d'exécution
 */
function is_bot_running() {
    $pid_file = BOT_PATH . '/bin/bot.pid';

    if (file_exists($pid_file)) {
        $pid = trim(file_get_contents($pid_file));

        // Vérifier si le processus existe
        if (function_exists('posix_kill')) {
            return posix_kill($pid, 0);
        } else {
            // Alternative pour Windows ou sans posix_kill
            if (PHP_OS_FAMILY === 'Windows') {
                $output = [];
                exec("tasklist /FI \"PID eq $pid\" 2>&1", $output);
                return count($output) > 1 && strpos($output[1], $pid) !== false;
            } else {
                $output = [];
                exec("ps -p $pid", $output);
                return count($output) > 1;
            }
        }
    }

    return false;
}

/**
 * Formate un nombre avec séparateur de milliers et précision
 */
function format_number($number, $decimals = 2) {
    return number_format($number, $decimals, '.', ' ');
}

/**
 * Formate un montant en devise
 */
function format_currency($amount, $currency = 'USDT', $decimals = 2) {
    return format_number($amount, $decimals) . ' ' . $currency;
}

/**
 * Formate un pourcentage
 */
function format_percent($value, $decimals = 2) {
    return format_number($value, $decimals) . '%';
}

/**
 * Génère une classe CSS en fonction d'une valeur (positive/négative)
 */
function get_value_class($value) {
    if ($value > 0) {
        return 'text-success';
    } elseif ($value < 0) {
        return 'text-danger';
    } else {
        return 'text-muted';
    }
}

/**
 * Génère un badge coloré pour le statut
 */
function status_badge($status) {
    $classes = [
        'running' => 'bg-success',
        'stopped' => 'bg-danger',
        'idle' => 'bg-warning',
        'error' => 'bg-danger',
        'backtest' => 'bg-info',
    ];

    $labels = [
        'running' => 'En cours',
        'stopped' => 'Arrêté',
        'idle' => 'En attente',
        'error' => 'Erreur',
        'backtest' => 'Backtest',
    ];

    $class = isset($classes[$status]) ? $classes[$status] : 'bg-secondary';
    $label = isset($labels[$status]) ? $labels[$status] : $status;

    return '<span class="badge ' . $class . '">' . $label . '</span>';
}
