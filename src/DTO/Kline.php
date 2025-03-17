<?php

namespace Kwizer15\TradingBot\DTO;

final readonly class Kline
{
    public function __construct(
        public int   $openTime,
        public float $open,
        public float $high,
        public float $low,
        public float $close,
        public float $volume,
        public int   $closeTime
    )
    {

    }
}