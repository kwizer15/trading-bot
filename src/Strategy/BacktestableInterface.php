<?php

namespace Kwizer15\TradingBot\Strategy;

interface BacktestableInterface
{
    public function getMinimumKlines(): int;
}
