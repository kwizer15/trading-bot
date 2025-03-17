<?php

namespace Kwizer15\TradingBot\DTO;

final readonly class Balance
{
    public function __construct(
        public float $free = 0,
        public float $locked = 0,
    ) {
    }
}