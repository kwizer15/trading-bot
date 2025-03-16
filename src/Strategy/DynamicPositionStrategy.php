<?php

namespace Kwizer15\TradingBot\Strategy;

class DynamicPositionStrategy implements StrategyInterface {
    // Paramètres par défaut de la stratégie
    private $params = [
        'initial_stop_loss_pct' => 5.0,      // Stop loss initial en pourcentage
        'analysis_period' => 24,             // Période d'analyse en heures
        'partial_take_profit' => true,       // Activer la prise de profit partielle
        'position_increase_pct' => 5.0,      // Pourcentage d'augmentation de position si baisse
        'max_investment_multiplier' => 2.0,  // Multiplicateur max de l'investissement initial
        'partial_exit_pct' => 30.0,          // Pourcentage de la position à sortir lors d'un profit
        'entry_indicators' => [              // Indicateurs pour l'entrée (option)
            'rsi_period' => 14,
            'rsi_oversold' => 30,
            'macd_fast' => 12,
            'macd_slow' => 26,
            'macd_signal' => 9
        ]
    ];

    // Stockage des positions avec leurs données
    private $positionData = [];
    private $currentSymbol = null; // Pour stocker temporairement le symbole en cours d'analyse

    public function __construct() {
        // Initialiser les données de position
        $this->loadPositionData();
    }

    /**
     * Analyse les données du marché et détermine si un signal d'achat est présent
     */
    public function shouldBuy(array $marketData): bool {
        // Extraire les klines
        $klines = $this->formatKlines($marketData);

        // Stocker le symbole courant à partir du premier kline si disponible
        if (isset($marketData[0]) && isset($marketData[0][0])) {
            // Utiliser une méthode pour extraire le symbole ou le passer en paramètre externe
            $this->currentSymbol = "CURRENT_SYMBOL"; // À remplacer par la méthode appropriée
        }

        // Vérifier si nous avons déjà eu une position sur ce symbole qui a été stoppée
        if ($this->currentSymbol &&
            isset($this->positionData[$this->currentSymbol]) &&
            $this->positionData[$this->currentSymbol]['exit_reason'] === 'stop_loss' &&
            $this->positionData[$this->currentSymbol]['last_exit_price'] > 0) {

            // Obtenir le prix actuel
            $currentPrice = $klines[count($klines) - 1]['close'];
            $lastExitPrice = $this->positionData[$this->currentSymbol]['last_exit_price'];

            // Vérifier si le prix est inférieur au prix de sortie (condition de réentrée)
            if ($currentPrice >= $lastExitPrice) {
                return false; // Ne pas rentrer si le prix n'est pas plus bas
            }
        }

        // Vérifier si nous avons des conditions d'entrée spécifiques
        if (empty($this->params['entry_indicators'])) {
            // Sans conditions spécifiques, on peut utiliser une règle simple
            // comme une tendance haussière récente
            return $this->detectUptrend($klines);
        }

        // Sinon utiliser des indicateurs techniques
        return $this->analyzeEntryIndicators($klines);
    }

    /**
     * Analyse les données du marché et détermine si un signal de vente est présent
     */
    public function shouldSell(array $marketData, array $position): bool {
        // Version simplifiée pour le backtest
        static $candles_count = 0;

        // Extraire les klines
        $klines = $this->formatKlines($marketData);
        $symbol = $position['symbol'];

        // Si nous n'avons pas de données pour cette position, l'initialiser
        if (!isset($this->positionData[$symbol])) {
            $this->initializePositionData($symbol, $position);
            $candles_count = 0;
        }

        // Mettre à jour les données de position
        $this->updatePositionData($symbol, $position, $klines);

        // Vérifier le stop loss en premier
        $stopLossPrice = $this->positionData[$symbol]['stop_loss_price'];
        $currentPrice = $position['current_price'];

        if ($currentPrice <= $stopLossPrice) {
            // Stop loss atteint
            // Marquer cette position pour ne pas la reprendre sauf si le prix baisse encore
            $this->positionData[$symbol]['last_exit_price'] = $currentPrice;
            $this->positionData[$symbol]['exit_reason'] = 'stop_loss';
            $this->savePositionData();
            return true;
        }

        // Pour le backtest, incrémenter un compteur de bougies au lieu d'utiliser le temps réel
        $candles_count++;

        // Ne faire l'analyse que tous les N bougies (où N correspond à analysis_period en heures)
        if ($candles_count >= $this->params['analysis_period']) {
            $candles_count = 0; // Réinitialiser le compteur

            // Récupérer le prix d'entrée moyen et le prix actuel
            $entryPrice = $this->positionData[$symbol]['avg_entry_price'];
            $currentPrice = $position['current_price'];

            // Le reste de votre logique d'analyse ici...
            // ...

            // Sauvegarder les données de position
            $this->savePositionData();
        }

        // Par défaut, ne pas vendre
        return false;
    }

    /**
     * Détermine l'action à effectuer sur une position
     */
    public function getPositionAction(array $marketData, array $position): string {
        // Version adaptée pour le backtest
        static $candles_count = [];

        // Extraire les klines
        $klines = $this->formatKlines($marketData);
        $symbol = $position['symbol'];

        // Initialiser le compteur pour ce symbole si nécessaire
        if (!isset($candles_count[$symbol])) {
            $candles_count[$symbol] = 0;
        }

        // Si nous n'avons pas de données pour cette position, l'initialiser
        if (!isset($this->positionData[$symbol])) {
            $this->initializePositionData($symbol, $position);
        }

        // Mettre à jour les données de position
        $this->updatePositionData($symbol, $position, $klines);

        // Vérifier le stop loss en premier
        $stopLossPrice = $this->positionData[$symbol]['stop_loss_price'];
        $currentPrice = $position['current_price'];

        if ($currentPrice <= $stopLossPrice) {
            // Stop loss atteint
            $this->positionData[$symbol]['last_exit_price'] = $currentPrice;
            $this->positionData[$symbol]['exit_reason'] = 'stop_loss';
            $this->savePositionData();
            return 'SELL';
        }

        // Incrémenter le compteur de bougies pour ce symbole
        $candles_count[$symbol]++;

        // Ne faire l'analyse que tous les N bougies
        if ($candles_count[$symbol] >= $this->params['analysis_period']) {
            $candles_count[$symbol] = 0; // Réinitialiser le compteur

            // Ici, insérez votre logique d'analyse de position
            // ...

            // Sauvegarder les données de position
            $this->savePositionData();
        }

        // Par défaut, maintenir la position
        return 'HOLD';
    }

    /**
     * Met à jour le stop loss en fonction de la position actuelle
     */
    private function updateStopLoss(string $symbol, array $position, float $avgEntryPrice): void {
        // Calculer le nouveau stop loss
        $stopLossPct = $this->params['initial_stop_loss_pct'];
        $stopLossPrice = $avgEntryPrice * (1 - ($stopLossPct / 100));

        // Mettre à jour les données de position
        $this->positionData[$symbol]['stop_loss_price'] = $stopLossPrice;
    }

    /**
     * Initialise les données d'une nouvelle position
     */
    private function initializePositionData(string $symbol, array $position): void {
        $entryPrice = $position['entry_price'];
        $initialInvestment = $position['cost'];

        $this->positionData[$symbol] = [
            'symbol' => $symbol,
            'initial_entry_price' => $entryPrice,
            'avg_entry_price' => $entryPrice,
            'initial_investment' => $initialInvestment,
            'total_investment' => $initialInvestment,
            'initial_quantity' => $position['quantity'],
            'total_quantity' => $position['quantity'],
            'entry_time' => $position['timestamp'],
            'last_analysis_time' => $position['timestamp'],
            'stop_loss_price' => $entryPrice * (1 - ($this->params['initial_stop_loss_pct'] / 100)),
            'partial_exits' => [],
            'additional_entries' => [],
            'last_exit_price' => 0,
            'exit_reason' => ''
        ];

        $this->savePositionData();
    }

    /**
     * Met à jour les données d'une position existante
     */
    private function updatePositionData(string $symbol, array $position, array $klines): void {
        // Mettre à jour les valeurs actuelles
        $this->positionData[$symbol]['current_price'] = $position['current_price'];
        $this->positionData[$symbol]['current_value'] = $position['current_value'];

        // S'il y a eu des opérations d'augmentation ou de sortie partielle,
        // nous devons mettre à jour certaines valeurs en conséquence
        // (cela serait normalement fait dans les méthodes d'augmentation/sortie partielle)

        $this->savePositionData();
    }

    /**
     * Sauvegarde les données de position dans un fichier
     */
    private function savePositionData(): void {
        $dataFile = __DIR__ . '/../../data/strategy_position_data.json';
        file_put_contents($dataFile, json_encode($this->positionData, JSON_PRETTY_PRINT));
    }

    /**
     * Charge les données de position depuis un fichier
     */
    private function loadPositionData(): void {
        $dataFile = __DIR__ . '/../../data/strategy_position_data.json';

        if (file_exists($dataFile)) {
            $this->positionData = json_decode(file_get_contents($dataFile), true);
        }
    }

    /**
     * Formate les klines pour faciliter l'analyse
     */
    private function formatKlines(array $marketData): array {
        $formattedKlines = [];

        foreach ($marketData as $kline) {
            $formattedKlines[] = [
                'open_time' => $kline[0],
                'open' => floatval($kline[1]),
                'high' => floatval($kline[2]),
                'low' => floatval($kline[3]),
                'close' => floatval($kline[4]),
                'volume' => floatval($kline[5]),
                'close_time' => $kline[6]
            ];
        }

        return $formattedKlines;
    }

    /**
     * Détecte si il y a une tendance haussière récente
     */
    private function detectUptrend(array $klines): bool {
        // Exemple simple: vérifier si les 3 dernières bougies sont haussières
        $count = count($klines);
        if ($count < 3) return false;

        $bullishCount = 0;
        for ($i = $count - 3; $i < $count; $i++) {
            if ($klines[$i]['close'] > $klines[$i]['open']) {
                $bullishCount++;
            }
        }

        return $bullishCount >= 2;
    }

    /**
     * Analyse les indicateurs techniques pour l'entrée
     */
    private function analyzeEntryIndicators(array $klines): bool {
        // Calculer le RSI
        $rsiPeriod = $this->params['entry_indicators']['rsi_period'];
        $rsiValue = $this->calculateRSI($klines, $rsiPeriod);

        // Vérifier la condition de surachat
        if ($rsiValue <= $this->params['entry_indicators']['rsi_oversold']) {
            // Calculer le MACD
            $fastPeriod = $this->params['entry_indicators']['macd_fast'];
            $slowPeriod = $this->params['entry_indicators']['macd_slow'];
            $signalPeriod = $this->params['entry_indicators']['macd_signal'];

            $macd = $this->calculateMACD($klines, $fastPeriod, $slowPeriod, $signalPeriod);

            // Vérifier si le MACD croise au-dessus de la ligne de signal
            $count = count($macd);
            if ($count >= 2) {
                $currentMACD = $macd[$count - 1]['macd'];
                $currentSignal = $macd[$count - 1]['signal'];
                $prevMACD = $macd[$count - 2]['macd'];
                $prevSignal = $macd[$count - 2]['signal'];

                if ($prevMACD < $prevSignal && $currentMACD > $currentSignal) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Calcule l'indicateur RSI
     */
    private function calculateRSI(array $klines, int $period): float {
        // Liste des variations de prix
        $changes = [];
        $count = count($klines);

        for ($i = 1; $i < $count; $i++) {
            $changes[] = $klines[$i]['close'] - $klines[$i - 1]['close'];
        }

        if (count($changes) < $period) {
            return 50.0; // Valeur par défaut si pas assez de données
        }

        // Calculer les gains et pertes
        $gains = [];
        $losses = [];

        foreach ($changes as $change) {
            if ($change > 0) {
                $gains[] = $change;
                $losses[] = 0;
            } else {
                $gains[] = 0;
                $losses[] = abs($change);
            }
        }

        // Limiter aux dernières périodes
        $gains = array_slice($gains, -$period);
        $losses = array_slice($losses, -$period);

        // Calculer les moyennes
        $avgGain = array_sum($gains) / $period;
        $avgLoss = array_sum($losses) / $period;

        // Éviter la division par zéro
        if ($avgLoss == 0) {
            return 100;
        }

        // Calculer le RS et le RSI
        $rs = $avgGain / $avgLoss;
        $rsi = 100 - (100 / (1 + $rs));

        return $rsi;
    }

    /**
     * Calcule l'indicateur MACD
     */
    private function calculateMACD(array $klines, int $fastPeriod, int $slowPeriod, int $signalPeriod): array {
        // Extraire les prix de clôture
        $closes = array_column($klines, 'close');

        // Calculer les EMA
        $fastEMA = $this->calculateEMA($closes, $fastPeriod);
        $slowEMA = $this->calculateEMA($closes, $slowPeriod);

        // Calculer la ligne MACD
        $macdLine = [];
        $count = count($closes);

        for ($i = 0; $i < $count; $i++) {
            if (isset($fastEMA[$i]) && isset($slowEMA[$i])) {
                $macdLine[$i] = $fastEMA[$i] - $slowEMA[$i];
            }
        }

        // Calculer la ligne de signal (EMA du MACD)
        $signalLine = $this->calculateEMA(array_values($macdLine), $signalPeriod);

        // Créer le résultat
        $result = [];

        foreach ($macdLine as $i => $macd) {
            if (isset($signalLine[$i - (count($macdLine) - count($signalLine))])) {
                $signal = $signalLine[$i - (count($macdLine) - count($signalLine))];
                $histogram = $macd - $signal;

                $result[] = [
                    'macd' => $macd,
                    'signal' => $signal,
                    'histogram' => $histogram
                ];
            }
        }

        return $result;
    }

    /**
     * Calcule la moyenne mobile exponentielle (EMA)
     */
    private function calculateEMA(array $prices, int $period): array {
        $count = count($prices);
        if ($count < $period) {
            return [];
        }

        // Calculer la SMA initiale
        $sma = array_sum(array_slice($prices, 0, $period)) / $period;

        // Calculer le facteur de lissage
        $multiplier = 2 / ($period + 1);

        // Calculer l'EMA
        $ema = [];
        $ema[$period - 1] = $sma;

        for ($i = $period; $i < $count; $i++) {
            $ema[$i] = ($prices[$i] - $ema[$i - 1]) * $multiplier + $ema[$i - 1];
        }

        return $ema;
    }

    // Méthodes requises par l'interface

    public function getName(): string {
        return 'DynamicPositionStrategy';
    }

    public function getDescription(): string {
        return 'Stratégie avec gestion dynamique des positions, arrêts ajustables et entrées/sorties partielles.';
    }

    public function setParameters(array $params): void {
        // Fusionner avec les paramètres par défaut
        $this->params = array_merge($this->params, $params);
        // Charger les données de position
        $this->loadPositionData();
    }

    public function getParameters(): array {
        return $this->params;
    }
}