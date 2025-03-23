<?php

namespace Kwizer15\TradingBot\DTO;

final readonly class Order
{
    public function __construct(
        public int   $orderId,
        public float $price,
        public float $quantity,
        public float $fee,
        public int $timestamp,
    ) {

    }
}
