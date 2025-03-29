<?php

namespace Kwizer15\TradingBot\Strategy\DynamicPosition;

use Kwizer15\TradingBot\DTO\KlineHistory;
use Kwizer15\TradingBot\DTO\Position;
use Kwizer15\TradingBot\Strategy\StrategyInterface;

final class DynamicPositionStrategy implements StrategyInterface
{
    private DynamicPositionParameters $parameters;
    private PositionDataList $positionDataList;
    private PositionDataListStorageInterface $positionDataListStorage;

    public function __construct(
        ?DynamicPositionParameters $parameters = null,
        private readonly bool $isBacktest = false,
    ) {
        $this->parameters = $parameters ?? new DynamicPositionParameters();
        $this->positionDataListStorage = $this->isBacktest
            ? new BacktestPositionDataListStorage($this->parameters)
            : new PositionDataListStorage($this->parameters);
        $this->positionDataList = $this->positionDataListStorage->load();
    }

    /**
     * Analyse les données du marché et détermine si un signal d'achat est présent
     */
    public function shouldBuy(KlineHistory $history, string $pairSymbol): bool
    {
        // Vérifier si nous avons déjà eu une position sur ce symbole qui a été stoppée

//        if (!$this->positionDataList->hasPosition($pairSymbol)) {
//            if (empty($this->parameters->entry_indicators)) {
//                return $this->detectUptrend($history);
//            }
//
//             Sinon utiliser des indicateurs techniques
//            return $this->analyzeEntryIndicators($history);
//        }


        // Obtenir le prix actuel
        $currentPrice = $history->last()->close;
        $this->positionDataList->updateMinimumBuyPrice($pairSymbol, $currentPrice);
        $positionData = $this->positionDataList->getPosition($pairSymbol);
        $newBuyPrice = $positionData['new_buy_price'] ?? 0; // * (1 - ($this->params['initial_stop_loss_pct'] / 100));

        $maxBuyPrice = $positionData['last_exit_price'] * (1 + ($this->parameters->max_buy_stop_loss_pct / 100));

        return $newBuyPrice <= $currentPrice && $currentPrice <= $maxBuyPrice;
    }

    /**
     * Analyse les données du marché et détermine si un signal de vente est présent
     */
    public function shouldSell(KlineHistory $history, Position $position): bool
    {
        // Extraire les klines et le symbole
        $symbol = $position->symbol;

        $this->positionDataList->update($symbol, $position);

        $this->positionDataListStorage->save($this->positionDataList);

        $var = $this->positionDataList->getPosition($symbol);
        // Vérifier le stop loss d'abord
        $stopLossPrice = $var['stop_loss_price'];
        $currentPrice = $position->current_price;

        if ($currentPrice <= $stopLossPrice) {
            $this->positionDataListStorage->save($this->positionDataList);

            return true;
        }

        $this->positionDataListStorage->save($this->positionDataList);

        return false;
    }


    public function calculateStopLoss(string $symbol, float $currentPrice): ?float
    {
        return $this->positionDataList->calculateStopLoss($symbol, $currentPrice);
    }

    /**
     * Initialise les données d'une nouvelle position
     */
    public function onBuy(Position $position): void
    {
        $this->positionDataList->buy($position);
        $this->positionDataListStorage->save($this->positionDataList);

    }

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

    public function onSell(string $symbol, float $currentPrice): void
    {
        $this->positionDataList->sell($symbol, $currentPrice);
        $this->positionDataListStorage->save($this->positionDataList);
    }

    public function getInvestment(string $symbol, float $currentPrice): ?float
    {
        return null;

    }

    public function getMinimumKlines(): int
    {
        return max($this->parameters->entry_indicators['rsi_period'], $this->parameters->entry_indicators['macd_slow']);
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
            $signal = $signalLine[$i - (\count($macdLine) - \count($signalLine))] ?? null;
            if (null === $signal) {
                continue;
            }

            $histogram = $macd - $signal;

            $result[] = [
                'macd' => $macd,
                'signal' => $signal,
                'histogram' => $histogram
            ];
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

    private function getPositionDataFile(): string
    {
        return dirname(__DIR__, 3) . '/data/strategy_position_data.json';
    }
}
