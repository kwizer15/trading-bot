<?php

namespace Kwizer15\TradingBot\Strategy;

use Kwizer15\TradingBot\DTO\KlineHistory;

class DynamicPositionStrategy implements PositionActionStrategyInterface {
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

    public function __construct() {
        // Initialiser les données de position
        $this->loadPositionData();
    }

    /**
     * Analyse les données du marché et détermine si un signal d'achat est présent
     */
    public function shouldBuy(KlineHistory $history, string $currentSymbol): bool {
        // Vérifier si nous avons déjà eu une position sur ce symbole qui a été stoppée
        if (isset($this->positionData[$currentSymbol]) &&
            $this->positionData[$currentSymbol]['exit_reason'] === 'stop_loss' &&
            $this->positionData[$currentSymbol]['last_exit_price'] > 0) {

            // Obtenir le prix actuel
            $currentPrice = $history->last()->close;
            $lastExitPrice = $this->positionData[$currentSymbol]['last_exit_price'];

            // Vérifier si le prix est inférieur au prix de sortie (condition de réentrée)
            if ($currentPrice >= $lastExitPrice) {
                return false; // Ne pas rentrer si le prix n'est pas plus bas
            }
        }

        // Vérifier si nous avons des conditions d'entrée spécifiques
        if (empty($this->params['entry_indicators'])) {
            // Sans conditions spécifiques, on peut utiliser une règle simple
            // comme une tendance haussière récente
            return $this->detectUptrend($history);
        }

        // Sinon utiliser des indicateurs techniques
        return $this->analyzeEntryIndicators($history);
    }

    /**
     * Analyse les données du marché et détermine si un signal de vente est présent
     */
    public function shouldSell(KlineHistory $history, array $position): bool {
        return false;
    }

    /**
     * Détermine l'action à effectuer sur une position
     */
    public function getPositionAction(KlineHistory $history, array $position): PositionAction {
        // Extraire les klines et le symbole
        $symbol = $position['symbol'];

        // Obtenir le timestamp actuel depuis les données de marché (pour le backtest)
        $currentTime = $history->last()->openTime;

        // Si nous n'avons pas de données pour cette position, l'initialiser
        if (!isset($this->positionData[$symbol])) {
            $this->onBuy($position);
        }

        // Mettre à jour les données de position
        $this->updatePositionData($symbol, $position, $history);

        // Vérifier le stop loss d'abord
        $stopLossPrice = $this->positionData[$symbol]['stop_loss_price'];
        $currentPrice = $position['current_price'];

        if ($currentPrice <= $stopLossPrice) {
            // Stop loss atteint
            $this->positionData[$symbol]['exit_reason'] = 'try_stop_loss';
            $this->savePositionData();
            return PositionAction::SELL;
        }

        // Vérifier si c'est le moment d'une analyse périodique
        $lastAnalysisTime = $this->positionData[$symbol]['last_analysis_time'];
        $analysisPeriodMs = $this->params['analysis_period'] * 3600 * 1000;

        if ($currentTime - $lastAnalysisTime >= $analysisPeriodMs) {
            // Mettre à jour le timestamp de la dernière analyse
            $this->positionData[$symbol]['last_analysis_time'] = $currentTime;

            // Récupérer le prix d'entrée moyen et le prix actuel
            $entryPrice = ($this->positionData[$symbol]['total_investment'] / $this->positionData[$symbol]['quantity']);

            // Calculer la performance actuelle
            $performancePct = (($currentPrice - $entryPrice) / $entryPrice) * 100;

            if ($performancePct > 0) {
                // Le prix a augmenté, envisager une sortie partielle
                if ($this->params['partial_take_profit']) {
                    $initialInvestment = $this->positionData[$symbol]['initial_investment'];
                    $currentValue = $position['current_value'];

                    // Calculer le profit en montant
                    $profit = $currentValue - $initialInvestment;

                    if ($profit > 0) {
                        $this->savePositionData();

                        return PositionAction::PARTIAL_EXIT;
                    }
                }
            } else {
                // Le prix a baissé, envisager d'augmenter la position
                $decreasePct = abs($performancePct);

                // Vérifier si la baisse justifie une augmentation de position
                if ($decreasePct >= $this->params['position_increase_pct']) {
                    // Calculer le multiplicateur actuel
                    $initialInvestment = $this->positionData[$symbol]['initial_investment'];
                    $currentInvestment = $this->positionData[$symbol]['total_investment'];
                    $currentMultiplier = $currentInvestment / $initialInvestment;

                    // Vérifier si nous pouvons encore augmenter la position
                    if ($currentMultiplier < $this->params['max_investment_multiplier']) {
                        $this->savePositionData();

                        return PositionAction::INCREASE_POSITION;
                    }
                }
            }

            // Sauvegarder les données de position
            $this->savePositionData();
        }

        // Par défaut, maintenir la position
        return PositionAction::HOLD;
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
    public function onBuy(array $position): void {
        $symbol = $position['symbol'];
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
    private function updatePositionData(string $symbol, array $position, KlineHistory $klines): void {
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
     * Détecte si il y a une tendance haussière récente
     */
    private function detectUptrend(KlineHistory $history): bool {
        // Exemple simple: vérifier si les 3 dernières bougies sont haussières
        $count = $history->count();
        if ($count < 3) {
            return false;
        }

        $bullishCount = 0;
        for ($i = $count - 3; $i < $count; $i++) {
            $kline = $history->get($i);
            if ($kline->close > $kline->open) {
                $bullishCount++;
            }
        }

        return $bullishCount >= 2;
    }

    /**
     * Analyse les indicateurs techniques pour l'entrée
     */
    private function analyzeEntryIndicators(KlineHistory $history): bool {
        // Calculer le RSI
        $rsiPeriod = $this->params['entry_indicators']['rsi_period'];
        $rsiValue = $this->calculateRSI($history, $rsiPeriod);

        // Vérifier la condition de surachat
        if ($rsiValue <= $this->params['entry_indicators']['rsi_oversold']) {
            // Calculer le MACD
            $fastPeriod = $this->params['entry_indicators']['macd_fast'];
            $slowPeriod = $this->params['entry_indicators']['macd_slow'];
            $signalPeriod = $this->params['entry_indicators']['macd_signal'];

            $macd = $this->calculateMACD($history, $fastPeriod, $slowPeriod, $signalPeriod);

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
    private function calculateRSI(KlineHistory $history, int $period): float {
        // Liste des variations de prix
        $changes = [];
        $count = $history->count();

        for ($i = 1; $i < $count; $i++) {
            $changes[] = $history->get($i)->close - $history->get($i - 1)->close;
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
    private function calculateMACD(KlineHistory $history, int $fastPeriod, int $slowPeriod, int $signalPeriod): array {
        // Extraire les prix de clôture
        $count = $history->count();
        $closes = array_column($history->getData(), 'close');

        // Calculer les EMA
        $fastEMA = $this->calculateEMA($closes, $fastPeriod);
        $slowEMA = $this->calculateEMA($closes, $slowPeriod);

        // Calculer la ligne MACD
        $macdLine = [];

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

    public function calculateIncreasePercentage(KlineHistory $marketData, array $position): float
    {
        $symbol = $position['symbol'];
        $currentPrice = $marketData->last()->close;
        $investment = $this->positionData[$symbol]['total_investment'];
        $equity = $currentPrice * $this->positionData[$symbol]['quantity'];

        return abs((($equity - $investment) / $investment) * 100);
    }

    /**
     * Calcule le pourcentage de la position à sortir lors d'une sortie partielle
     * @param array $position Données de la position
     * @param array $marketData Données du marché (klines)
     * @return float Pourcentage à sortir
     */
    public function calculateExitPercentage(KlineHistory $marketData, array $position): float
    {
        $symbol = $position['symbol'];
        $currentPrice = $marketData->last()->close;
        $investment = $this->positionData[$symbol]['total_investment'];
        $equity = $currentPrice * $this->positionData[$symbol]['quantity'];

        return (($equity - $investment) / $investment) * 100;
    }

    public function onPartialExit(array $position, float $sellQuantity): void
    {
        $symbol = $position['symbol'];

        $this->positionData[$symbol]['quantity'] -= $sellQuantity;
        $entryPrice = ($this->positionData[$symbol]['total_investment'] / $this->positionData[$symbol]['quantity']);
        $this->updateStopLoss($symbol, $position, $entryPrice);
        $this->savePositionData();
    }

    public function onIncreasePosition(array $position, float $additionalInvestment, float $additionalQuantity): void
    {
        $symbol = $position['symbol'];

        $this->positionData[$symbol]['total_investment'] += $additionalInvestment;
        $this->positionData[$symbol]['quantity'] += $additionalQuantity;
        $entryPrice = ($this->positionData[$symbol]['total_investment'] / $this->positionData[$symbol]['quantity']);

        $this->updateStopLoss($symbol, $position, $entryPrice);
        $this->savePositionData();
    }

    public function onSell(string $symbol, float $currentPrice): void
    {

        $this->positionData[$symbol]['last_exit_price'] = $currentPrice;
        switch ($this->positionData[$symbol]['exit_reason']) {
            case 'try_stop_loss':
                $this->positionData[$symbol]['exit_reason'] = 'stop_loss';
                break;
        }
    }
}