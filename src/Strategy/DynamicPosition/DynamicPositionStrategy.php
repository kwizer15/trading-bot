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
        private readonly bool      $isBacktest = false,
    )
    {
        $this->parameters = $parameters ?? new DynamicPositionParameters();
        $this->positionDataListStorage = $this->isBacktest
            ? new BacktestPositionDataListStorage($this->parameters)
            : new PositionDataListStorage($this->parameters);
    }

    /**
     * Analyse les données du marché et détermine si un signal d'achat est présent
     */
    public function shouldBuy(KlineHistory $history, string $pairSymbol): bool
    {
        $currentPrice = $history->last()->close;
        $this->positionDataList->updateMinimumBuyPrice($pairSymbol, $currentPrice);
        $positionData = $this->positionDataList->getPosition($pairSymbol);
        $newBuyPrice = $positionData['new_buy_price'] ?? 0;

        $maxBuyPrice = $positionData['last_exit_price'] * (1 + ($this->parameters->max_buy_stop_loss_pct / 100));


        return $newBuyPrice <= $currentPrice && $currentPrice <= $maxBuyPrice;
    }

    public function onBuy(Position $position): void
    {
        $this->positionDataList->buy($position);
    }

    /**
     * Analyse les données du marché et détermine si un signal de vente est présent
     */
    public function shouldSell(KlineHistory $history, Position $position): bool
    {
        $symbol = $position->symbol;

        $this->positionDataList->update($symbol, $position);

        $positionData = $this->positionDataList->getPosition($symbol);
        // Vérifier le stop loss d'abord
        $stopLossPrice = $positionData['stop_loss_price'];
        $currentPrice = $position->current_price;

        return $currentPrice <= $stopLossPrice;
    }

    public function onSell(string $symbol, float $currentPrice): void
    {
        $this->positionDataList->sell($symbol, $currentPrice);
    }


    public function calculateStopLoss(string $symbol, float $currentPrice): ?float
    {
        return $this->positionDataList->calculateStopLoss($symbol, $currentPrice);
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

    public function getInvestment(string $symbol, float $currentPrice): ?float
    {
        return null;
    }

    public function getMinimumKlines(): int
    {
        return 1;
    }

    public function onPreCycle(): void
    {
        $this->positionDataList = $this->positionDataListStorage->load();
    }

    public function onPostCycle(): void
    {
        $this->positionDataListStorage->save($this->positionDataList);
    }
}
