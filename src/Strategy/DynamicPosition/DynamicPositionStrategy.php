<?php

namespace Kwizer15\TradingBot\Strategy\DynamicPosition;

use Kwizer15\TradingBot\DTO\KlineHistory;
use Kwizer15\TradingBot\DTO\Order;
use Kwizer15\TradingBot\DTO\Position;
use Kwizer15\TradingBot\Strategy\PositionAction;
use Kwizer15\TradingBot\Strategy\PositionActionStrategyInterface;

final class DynamicPositionStrategy implements PositionActionStrategyInterface
{
    private DynamicPositionParameters $parameters;
    // Stockage des positions avec leurs données
    private array $positionData = [];

    public function __construct(
        ?DynamicPositionParameters $parameters = null,
        private readonly bool $isBacktest = false
    ) {
        $this->parameters = $parameters ?? new DynamicPositionParameters();
        $this->loadPositionData();
    }

    /**
     * Analyse les données du marché et détermine si un signal d'achat est présent
     */
    public function shouldBuy(KlineHistory $history, string $pairSymbol): bool
    {
        // Vérifier si nous avons déjà eu une position sur ce symbole qui a été stoppée

        if (!isset($this->positionData[$pairSymbol])) {
        // Vérifier si nous avons des conditions d'entrée spécifiques
        if (empty($this->parameters->entry_indicators)) {
            // Sans conditions spécifiques, on peut utiliser une règle simple
            // comme une tendance haussière récente
            return $this->detectUptrend($history);
        }

        // Sinon utiliser des indicateurs techniques
        return $this->analyzeEntryIndicators($history);
        }


    // Obtenir le prix actuel
    $currentPrice = $history->last()->close;
        $this->updateStopLoss($pairSymbol, $currentPrice);
$lastExitPrice = $this->positionData[$pairSymbol]['new_buy_price']; // * (1 - ($this->params['initial_stop_loss_pct'] / 100));

    // Vérifier si le prix est inférieur au prix de sortie (condition de réentrée)
        return $currentPrice >= $lastExitPrice;
    }

    /**
     * Analyse les données du marché et détermine si un signal de vente est présent
     */
    public function shouldSell(KlineHistory $history): bool
    {
        return false;
    }

    /**
     * Détermine l'action à effectuer sur une position
     */
    public function getPositionAction(KlineHistory $history, Position $position): PositionAction
    {
        // Extraire les klines et le symbole
        $symbol = $position->symbol;

        // Obtenir le timestamp actuel depuis les données de marché (pour le backtest)
        $currentTime = $history->last()->closeTime;

        // Si nous n'avons pas de données pour cette position, l'initialiser
        if (!isset($this->positionData[$symbol])) {
            $this->onBuy($position);
        }

        // Mettre à jour les données de position
        $this->updatePositionData($symbol, $position);

        $this->updateStopLoss($symbol);

        // Vérifier le stop loss d'abord
        $stopLossPrice = $this->positionData[$symbol]['stop_loss_price'];
        $currentPrice = $position->current_price;

        if ($currentPrice <= $stopLossPrice) {
            // Stop loss atteint
            $this->positionData[$symbol]['exit_reason'] = 'try_stop_loss';
            $this->savePositionData();
            return PositionAction::SELL;
        }

        // Vérifier si c'est le moment d'une analyse périodique
        $lastAnalysisTime = $this->positionData[$symbol]['last_analysis_time'];
        $analysisPeriodMs = $this->parameters->analysis_period * 3600 * 1000;

        if ($currentTime - $lastAnalysisTime < $analysisPeriodMs) {
            return PositionAction::HOLD;
        }
        // Mettre à jour le timestamp de la dernière analyse
        $this->positionData[$symbol]['last_analysis_time'] = $currentTime;
        $this->savePositionData();

        return PositionAction::HOLD;
    }

    /**
     * Met à jour le stop loss en fonction de la position actuelle
     */
    private function updateStopLoss(string $symbol, float $currentPrice = null): void
    {
        $currentPrice ??= $this->positionData[$symbol]['current_price'] ?? 0;
        // Calculer le nouveau stop loss
        if ($this->positionData[$symbol]['last_exit_price'] === 0) {
        $avgEntryPrice = $this->positionData[$symbol]['total_investment'] / $this->positionData[$symbol]['quantity'];
        $stopLossPct = $this->parameters->initial_stop_loss_pct;
        $stopLossPrice1 = $currentPrice * (1 - ($this->parameters->profit_stop_loss_pct / 100));
        $stopLossPrice2 = $avgEntryPrice * (1 - ($stopLossPct / 100));
        // Mettre à jour les données de position
        $newStopLossPrice = max($stopLossPrice1, $stopLossPrice2, $this->positionData[$symbol]['stop_loss_price']);
        if ($newStopLossPrice !== $this->positionData[$symbol]['stop_loss_price']) {
            echo "New stop loss price for $symbol: $newStopLossPrice - Entry price: $avgEntryPrice\n";
        }
        $this->positionData[$symbol]['stop_loss_price'] = $newStopLossPrice;

            return;
        }
        $newBuyPrices = array_filter([
            $this->positionData[$symbol]['last_exit_price'] * (1+ ($this->parameters->rebuy_stop_loss_pct / 100)),
            $currentPrice * (1 + ($this->parameters->rebuy_stop_loss_pct / 100)),
            $this->positionData[$symbol]['new_buy_price'],
        ], function ($price) {
            return $price > 0.0;
        });
        $min = min(...$newBuyPrices);
        if ($min !== $this->positionData[$symbol]['new_buy_price']) {
            echo "New buy price for $symbol: $min - Last exit price: {$this->positionData[$symbol]['last_exit_price']}\n";
        }
        $this->positionData[$symbol]['new_buy_price'] = $min;
    }

    /**
     * Initialise les données d'une nouvelle position
     */
    public function onBuy(Position $position): void
    {
        $symbol = $position->symbol;
        $entryPrice = $position->entry_price;
        $initialInvestment = $position->cost;
        $this->positionData[$symbol] = [
            'symbol' => $symbol,
            'initial_entry_price' => $entryPrice,
            'avg_entry_price' => $entryPrice,
            'initial_investment' => $initialInvestment,
            'total_investment' => $initialInvestment,
            'initial_quantity' => $position->quantity,
            'quantity' => $position->quantity,
            'total_quantity' => $position->quantity,
            'entry_time' => $position->timestamp,
            'last_analysis_time' => $position->timestamp,
            'stop_loss_price' => $entryPrice * (1 - ($this->parameters->initial_stop_loss_pct / 100)),
            'partial_exits' => [],
            'additional_entries' => [],
            'last_exit_price' => 0,
            'new_buy_price' => 0,
            'exit_reason' => ''
        ];

        $this->updateStopLoss($symbol);
        $this->savePositionData();

        echo 'Nouvelle position : ' . $symbol . ' - Quantité : ' . $position->quantity . ' - Prix d\'entrée : ' . $entryPrice . ' - Stop Loss : ' . $this->positionData[$symbol]['stop_loss_price'] . PHP_EOL;
    }

    /**
     * Met à jour les données d'une position existante
     */
    private function updatePositionData(string $symbol, Position $positionObject): void
    {
        // Mettre à jour les valeurs actuelles
        $this->positionData[$symbol]['current_price'] = $positionObject->current_price;
        $this->positionData[$symbol]['current_value'] = $positionObject->current_value;

        $this->savePositionData();
    }

    /**
     * Sauvegarde les données de position dans un fichier
     */
    private function savePositionData(): void
    {
        if ($this->isBacktest) {
            return;
        }

        $dataFile = $this->getPositionDataFile();
        file_put_contents($dataFile, json_encode($this->positionData, JSON_PRETTY_PRINT));
    }

    /**
     * Charge les données de position depuis un fichier
     */
    private function loadPositionData(): void
    {
        if ($this->isBacktest) {
            return;
        }
        $dataFile = $this->getPositionDataFile();

        if (file_exists($dataFile)) {
            $this->positionData = json_decode(file_get_contents($dataFile), true);
        }
    }

    /**
     * Détecte si il y a une tendance haussière récente
     */
    private function detectUptrend(KlineHistory $history): bool
    {
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
    private function analyzeEntryIndicators(KlineHistory $history): bool
    {
        // Calculer le RSI
        $rsiPeriod = $this->parameters->entry_indicators['rsi_period'];
        $rsiValue = $this->calculateRSI($history, $rsiPeriod);

        // Vérifier la condition de surachat
        if ($rsiValue <= $this->parameters->entry_indicators['rsi_oversold']) {
            // Calculer le MACD
            $fastPeriod = $this->parameters->entry_indicators['macd_fast'];
            $slowPeriod = $this->parameters->entry_indicators['macd_slow'];
            $signalPeriod = $this->parameters->entry_indicators['macd_signal'];

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
    private function calculateRSI(KlineHistory $history, int $period): float
    {
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
    private function calculateMACD(KlineHistory $history, int $fastPeriod, int $slowPeriod, int $signalPeriod): array
    {
        // Extraire les prix de clôture
        $count = $history->count();
        $closes = $history->listCloses();

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
    private function calculateEMA(array $prices, int $period): array
    {
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

    public function getName(): string
    {
        return 'DynamicPositionStrategy';
    }

    public function getDescription(): string
    {
        return 'Stratégie avec gestion dynamique des positions, arrêts ajustables et entrées/sorties partielles.';
    }

    public function setParameters(array $params): void
    {
        $this->parameters = new DynamicPositionParameters(...$params);
    }

    public function getParameters(): array
    {
        return $this->parameters->toArray();
    }

    public function getParameter(string $key, mixed $default = null): mixed
    {
        return $this->parameters->$key ?? $default;
    }


    public function calculateIncreasePercentage(KlineHistory $marketData, Position $position): float
    {
        $symbol = $position->symbol;
        $currentPrice = $marketData->last()->close;
        $investment = $this->positionData[$symbol]['total_investment'];
        $equity = $currentPrice * $this->positionData[$symbol]['quantity'];

        return abs((($equity - $investment) / $investment) * 100);
    }

    /**
     * Calcule le pourcentage de la position à sortir lors d'une sortie partielle
     * @param KlineHistory $marketData Données du marché (klines)
     * @param Position $position Données de la position
     * @return float Pourcentage à sortir
     */
    public function calculateExitPercentage(KlineHistory $marketData, Position $position): float
    {
        return 0;
        $symbol = $position->symbol;
        $currentPrice = $marketData->last()->close;
        $investment = $this->positionData[$symbol]['total_investment'];
        $equity = $currentPrice * $this->positionData[$symbol]['quantity'];

        return (($equity - $investment) / $investment) * 100;
        return ($this->positionData[$symbol]['total_investment'] - $this->positionData[$symbol]['initial_investment']) / ($this->positionData[$symbol]['initial_investment']) * 100;
    }

    public function onPartialExit(Position $position, Order $order): void
    {
        $symbol = $position->symbol;

        $avgEntryPrice = $this->positionData[$symbol]['total_investment'] / $this->positionData[$symbol]['quantity'];
        $this->positionData[$symbol]['quantity'] -= $order->quantity;
        $this->positionData[$symbol]['total_investment'] -= $order->quantity * $avgEntryPrice;
        $this->updateStopLoss($symbol);
        $this->savePositionData();
        echo 'Sortie partielle de la position : ' . $symbol . ' - Quantité : ' . $order->quantity . ' - Prix : ' . $order->price . ' - Stop Loss : ' . $this->positionData[$symbol]['stop_loss_price'] . PHP_EOL;
    }

    public function onIncreasePosition(Position $position, Order $order): void
    {
        $symbol = $position->symbol;

        $this->positionData[$symbol]['total_investment'] += $order->quantity * $order->price;
        $this->positionData[$symbol]['quantity'] += $order->quantity;

        $this->updateStopLoss($symbol);
        $this->savePositionData();
        echo 'Augmentation de la position : ' . $symbol . ' - Quantité : ' . $order->quantity . ' - Prix : ' . $order->price . ' - Stop Loss : ' . $this->positionData[$symbol]['stop_loss_price'] . PHP_EOL;
    }

    public function onSell(string $symbol, float $currentPrice): void
    {

        $this->positionData[$symbol]['last_exit_price'] = $currentPrice;
        switch ($this->positionData[$symbol]['exit_reason']) {
            case 'try_stop_loss':
                $this->positionData[$symbol]['exit_reason'] = 'stop_loss';
                break;
        }

        $this->savePositionData();
        echo 'Sortie de la position : ' . $symbol . ' - Prix : ' . $currentPrice . ' - Raison : ' . $this->positionData[$symbol]['exit_reason'] . PHP_EOL;
    }

    /**
     * @return string
     */
    public function getPositionDataFile(): string
    {
        return dirname(__DIR__, 2) . '/data/strategy_position_data.json';
    }

    public function getInvestment(string $symbol, float $currentPrice): ?float
    {
                if (!isset($this->positionData[$symbol])) {
        return null;
                }

                return $this->positionData[$symbol]['total_investment'];
    }

    public function getMinimumKlines(): int
    {
        return max($this->parameters->entry_indicators['rsi_period'], $this->parameters->entry_indicators['macd_slow']);
    }
}
