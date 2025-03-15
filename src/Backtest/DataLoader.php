<?php

class DataLoader {
    private $binanceAPI;

    public function __construct(BinanceAPI $binanceAPI) {
        $this->binanceAPI = $binanceAPI;
    }

    /**
     * Télécharge des données historiques depuis Binance
     */
    public function downloadHistoricalData($symbol, $interval = '1h', $startTime = null, $endTime = null) {
        $klines = [];
        $limit = 1000; // Nombre maximum de klines par requête

        if (!$startTime) {
            $startTime = strtotime('-1 month') * 1000; // Par défaut : 1 mois
        } elseif (is_string($startTime)) {
            $startTime = strtotime($startTime) * 1000;
        }

        if (!$endTime) {
            $endTime = time() * 1000;
        } elseif (is_string($endTime)) {
            $endTime = strtotime($endTime) * 1000;
        }

        $currentStartTime = $startTime;

        while ($currentStartTime < $endTime) {
            $params = [
                'symbol' => $symbol,
                'interval' => $interval,
                'limit' => $limit,
                'startTime' => $currentStartTime
            ];

            // Ajouter le paramètre endTime seulement si nous ne sommes pas à la dernière itération
            if ($currentStartTime + ($limit * $this->getIntervalInMilliseconds($interval)) < $endTime) {
                $params['endTime'] = $endTime;
            }

            $response = $this->binanceAPI->getKlines($symbol, $interval, $limit, $currentStartTime, $endTime);

            if (empty($response)) {
                break;
            }

            $klines = array_merge($klines, $response);

            // Mettre à jour le startTime pour la prochaine itération
            $lastKline = end($response);
            $currentStartTime = $lastKline[0] + 1; // +1 pour éviter les doublons

            // Petite pause pour ne pas dépasser les limites de l'API
            usleep(100000); // 100ms
        }

        return $klines;
    }

    /**
     * Enregistre les données historiques dans un fichier CSV
     */
    public function saveToCSV($data, $filePath) {
        $dir = dirname($filePath);

        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $file = fopen($filePath, 'w');

        // Écrire l'en-tête
        fputcsv($file, ['timestamp', 'open', 'high', 'low', 'close', 'volume', 'close_time', 'quote_asset_volume', 'number_of_trades', 'taker_buy_base_asset_volume', 'taker_buy_quote_asset_volume', 'ignore']);

        // Écrire les données
        foreach ($data as $row) {
            fputcsv($file, $row);
        }

        fclose($file);

        return true;
    }

    /**
     * Charge les données historiques depuis un fichier CSV
     */
    public function loadFromCSV($filePath) {
        if (!file_exists($filePath)) {
            throw new Exception("Le fichier {$filePath} n'existe pas");
        }

        $data = [];

        $file = fopen($filePath, 'r');

        // Ignorer l'en-tête
        fgetcsv($file);

        // Lire les données
        while (($row = fgetcsv($file)) !== false) {
            $data[] = $row;
        }

        fclose($file);

        return $data;
    }

    /**
     * Convertit un intervalle en millisecondes
     */
    private function getIntervalInMilliseconds($interval) {
        $unit = substr($interval, -1);
        $value = intval(substr($interval, 0, -1));

        switch ($unit) {
            case 'm': // minute
                return $value * 60 * 1000;
            case 'h': // heure
                return $value * 60 * 60 * 1000;
            case 'd': // jour
                return $value * 24 * 60 * 60 * 1000;
            case 'w': // semaine
                return $value * 7 * 24 * 60 * 60 * 1000;
            case 'M': // mois (approximatif)
                return $value * 30 * 24 * 60 * 60 * 1000;
            default:
                throw new Exception("Intervalle non reconnu: {$interval}");
        }
    }
}