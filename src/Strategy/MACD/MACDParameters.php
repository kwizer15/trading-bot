<?php

namespace Kwizer15\TradingBot\Strategy\MACD;

final readonly class MACDParameters
{
    public function __construct(
        public int $macd_fast = 12,
        public int $macd_slow = 26,
        public int $macd_signal = 9,
    ) {
    }
}