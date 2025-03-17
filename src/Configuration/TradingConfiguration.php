<?php

namespace Kwizer15\TradingBot\Configuration;

final class TradingConfiguration
{

    /** @var string[] */
    public readonly array $symbols;
    public readonly float $investmentPerTrade;
    public readonly int $maxOpenPositions;
    public readonly string $baseCurrency;
    public readonly float $stopLossPercentage;
    public readonly float $takeProfitPercentage;


    public function __construct(array $config) {
        $this->symbols = $config['trading']['symbols'];
        $this->baseCurrency = $config['trading']['base_currency'];
        $this->stopLossPercentage = $config['trading']['stop_loss_percentage'];
        $this->takeProfitPercentage = $config['trading']['take_profit_percentage'];
        $this->investmentPerTrade = $config['trading']['investment_per_trade'];
        $this->maxOpenPositions = $config['trading']['max_open_positions'] ?? 1;
    }
}