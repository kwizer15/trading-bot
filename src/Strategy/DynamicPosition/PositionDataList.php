<?php

namespace Kwizer15\TradingBot\Strategy\DynamicPosition;

use Kwizer15\TradingBot\DTO\Position;

final class PositionDataList
{
    public function __construct(
        private readonly DynamicPositionParameters $parameters,
        private array $positionData = [],
    ) {
    }

    public function buy(Position $position): void
    {
        $symbol = $position->symbol;
        $entryPrice = $position->entry_price;
        $initialInvestment = $position->cost;
        $this->positionData[$symbol] = [
            'open' => true,
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
            'stop_loss_price' => $position->stop_loss,
            'partial_exits' => [],
            'additional_entries' => [],
            'last_exit_price' => 0,
            'new_buy_price' => 0,
        ];
    }

    public function sell(string $symbol, float $currentPrice): void
    {
        $this->positionData[$symbol]['open'] = false;
        $this->positionData[$symbol]['quantity'] = 0;
        $this->positionData[$symbol]['last_exit_price'] = $currentPrice;
        $this->positionData[$symbol]['new_buy_price'] = $currentPrice * (1 + ($this->parameters->buy_stop_loss_pct / 100));
    }

    public function getPositionData(): array
    {
        return $this->positionData;
    }

    public function update(string $symbol, Position $positionObject): void
    {
        $this->positionData[$symbol] ??= $this->initialize($symbol);
        $this->positionData[$symbol]['open'] ??= (($this->positionData[$symbol]['quantity'] ?? 0.0) > 0.0);
        $this->positionData[$symbol]['current_price'] = $positionObject->current_price;
        $this->positionData[$symbol]['current_value'] = $positionObject->current_value;

        if ($this->positionData[$symbol]['open']) {
            $this->updateStopLoss($symbol);
            return;
        }
        $this->updateMinimumBuyPrice($symbol, $this->positionData[$symbol]['current_price']);

    }

    public function updateMinimumBuyPrice(string $symbol, float $currentPrice): void
    {
        $this->positionData[$symbol] ??= $this->initialize($symbol);
        $this->positionData[$symbol]['open'] ??= (($this->positionData[$symbol]['quantity'] ?? 0.0) > 0.0);
        $this->positionData[$symbol]['last_exit_price'] ??= $currentPrice;
        $this->positionData[$symbol]['new_buy_price'] = $this->calculateMinimumBuyPrice($symbol, $currentPrice);
    }

    private function updateStopLoss(string $symbol): void
    {
        $this->positionData[$symbol]['stop_loss_price'] = $this->calculateStopLoss($symbol, $this->positionData[$symbol]['current_price']);
    }

    public function getPosition(string $pairSymbol): array
    {
        return $this->positionData[$pairSymbol] ?? $this->initialize($pairSymbol);
    }

    private function initialize(string $symbol): array
    {
        return [
            'open' => false,
            'symbol' => $symbol,
            'initial_entry_price' => null,
            'avg_entry_price' => 0,
            'initial_investment' => 0,
            'total_investment' => 0,
            'initial_quantity' => 0,
            'quantity' => 0,
            'total_quantity' => 0,
            'entry_time' => null,
            'last_analysis_time' => null,
            'stop_loss_price' => null,
            'partial_exits' => [],
            'additional_entries' => [],
            'last_exit_price' => null,
            'new_buy_price' => 0,
        ];
    }

    public function calculateStopLoss(string $symbol, float $currentPrice): float
    {
        $position = $this->getPosition($symbol);
        if ($position['open'] === false) {
            return $currentPrice * (1 - ($this->parameters->secure_stop_loss_pct / 100));
        }

        $stopLossPrice = $currentPrice * (1 - ($this->parameters->profit_stop_loss_pct / 100));

        return max($stopLossPrice, $position['stop_loss_price']);
    }

    private function calculateMinimumBuyPrice(string $symbol, float $currentPrice): float
    {

        $newBuyPrices = array_filter([
            $this->positionData[$symbol]['last_exit_price'] * (1 + ($this->parameters->max_buy_stop_loss_pct / 100)),
            $currentPrice * (1 + ($this->parameters->buy_stop_loss_pct / 100)),
            $this->positionData[$symbol]['new_buy_price'] ?? 0.0,
        ], function ($price) {
            return $price > 0.0;
        });

        return min($newBuyPrices);
    }


}