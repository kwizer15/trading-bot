<?php

namespace Kwizer15\TradingBot\DTO;

final class Position
{
    public function __construct(
        public string $symbol,
        public float $entry_price,
        public float $quantity,
        public int $timestamp,
        public float $cost,
        public float $current_price,
        public float $current_value,
        public float $profit_loss,
        public float $profit_loss_pct,
        public float $total_buy_fees,
        public float $total_sell_fees,
        public int $order_id
    ) {
        $this->total_sell_fees ??= 0;
        $this->order_id ??= 0;
    }

    public static function fromArray(array $position): self
    {
        return new self(
            $position['symbol'],
            $position['entry_price'],
            $position['quantity'],
            $position['timestamp'],
            $position['cost'],
            $position['current_price'],
            $position['current_value'],
            $position['profit_loss'],
            $position['profit_loss_pct'],
            $position['total_buy_fees'],
            $position['total_sell_fees'],
            $position['order_id'],
        );
    }

    public function update(float $currentPrice): self
    {
        return new self(
            $this->symbol,
            $this->entry_price,
            $this->quantity,
            $this->timestamp,
            $this->cost,
            $currentPrice,
            $this->quantity * $currentPrice,
            $this->current_value - $this->cost,
            ($this->current_value - $this->cost) / $this->cost * 100,
            $this->total_buy_fees,
            $this->total_sell_fees,
            $this->order_id,
        );
    }

    public function toArray(): array
    {
        return [
            'symbol' => $this->symbol,
            'entry_price' => $this->entry_price,
            'quantity' => $this->quantity,
            'timestamp' => $this->timestamp,
            'cost' => $this->cost,
            'current_price' => $this->current_price,
            'current_value' => $this->current_value,
            'profit_loss' => $this->profit_loss,
            'profit_loss_pct' => $this->profit_loss_pct,
            'total_buy_fees' => $this->total_buy_fees,
            'total_sell_fees' => $this->total_sell_fees,
            'order_id' => $this->order_id,
        ];
    }

    public function partialExit(float $quantityToSell, float $fees): self
    {
        $remainingQuantity = $this->quantity - $quantityToSell;
        $remainingCost = $this->cost * ($remainingQuantity / $this->quantity);

        $currentPrice = $this->current_price;
        $currentValue = $remainingQuantity * $currentPrice;
        $profitLoss = $currentValue - $remainingCost;

        return new self(
            $this->symbol,
            $this->entry_price,
            $remainingQuantity,
            $this->timestamp,
            $remainingCost,
            $currentPrice,
            $currentValue,
            $profitLoss,
            ($profitLoss / $remainingCost) * 100,
            $this->total_buy_fees,
            $this->total_sell_fees + $fees,
            $this->order_id,
        );
    }

    public function increase(Order $order): Position
    {
        $currentPrice = $order->price;
        $totalQuantity = $this->quantity + $order->quantity;
        $cost = $this->cost + ($order->quantity * $order->price);
        $entryPrice = $cost / $totalQuantity;

        $currentValue = $totalQuantity * $currentPrice;
        $profitLoss = $currentValue - $cost;

        return new self(
            $this->symbol,
            $entryPrice,
            $totalQuantity,
            $this->timestamp,
            $cost,
            $currentPrice,
            $currentValue,
            $profitLoss,
            ($profitLoss / $cost) * 100,
            $this->total_buy_fees + $order->fee,
            $this->total_sell_fees,
            $this->order_id,
        );
    }

}
