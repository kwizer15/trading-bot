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
    ) {

    }

    public function openDate(): string
    {
        return date('Y-m-d', round($this->openTime / 1000));
    }

    public function closeDate(): string
    {
        return date('Y-m-d', round($this->closeTime / 1000));
    }
}
