<?php

namespace Kwizer15\TradingBot\DTO;

class Kline
{

    public function __construct(
        public readonly int   $openTime,
        public readonly float $open,
        public readonly float $high,
        public readonly float $low,
        public readonly float $close,
        public readonly float $volume,
        public readonly int   $closeTime
    )
    {

    }
}