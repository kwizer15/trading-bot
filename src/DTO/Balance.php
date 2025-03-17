<?php

namespace Kwizer15\TradingBot\DTO;

final class Balance
{
    public function __construct(
        public readonly float $free = 0,
        public readonly float $locked = 0,
    ) {
    }
}