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

}
