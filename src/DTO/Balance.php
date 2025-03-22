<?php

namespace Kwizer15\TradingBot\DTO;

final class Balance
{
    public function __construct(
        public float $free = 0,
        public float $locked = 0,
    ) {
    }

    public function add(float $amount): self
    {
        $this->free += $amount;
        return $this;
    }

    public function sub(float $amount): self
    {
        $this->free -= $amount;
        return $this;
    }
}