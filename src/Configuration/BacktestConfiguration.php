<?php

namespace Kwizer15\TradingBot\Configuration;

class BacktestConfiguration
{

    public readonly float $initialBalance;

    public function __construct(array $config) {
        $this->initialBalance = $config['backtest']['initial_balance'] ?? 1000.0;
    }
}