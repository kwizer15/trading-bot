<?php

namespace Kwizer15\TradingBot\Configuration;

final readonly class TradingConfiguration
{
    /** @var string[] */
    public array $symbols;
    public float $investmentPerTrade;
    public int $maxOpenPositions;
    public string $baseCurrency;
    public float $stopLossPercentage;
    public float $takeProfitPercentage;


    public function __construct(private array $config)
    {
        $this->symbols = $config['trading']['symbols'] ?? ['BTC', 'ETH', 'BNB'];
        $this->baseCurrency = $config['trading']['base_currency'] ?? 'USDT';
        $this->stopLossPercentage = $config['trading']['stop_loss_percentage'] ?? 2.5;
        $this->takeProfitPercentage = $config['trading']['take_profit_percentage'] ?? 5;
        $this->investmentPerTrade = $config['trading']['investment_per_trade'] ?? 100;
        $this->maxOpenPositions = $config['trading']['max_open_positions'] ?? 1;
    }

    public function withInvestmentPerTrade(mixed $investmentPerTrade): self
    {
        return new self([
            'trading' => [
                'investment_per_trade' => $investmentPerTrade,
            ] + $this->config['trading']
        ] + $this->config);
    }

    public function withStopLossPercentage(mixed $stopLossPercentage): self
    {
        return new self([
                'trading' => [
                        'stop_loss_percentage' => $stopLossPercentage,
                    ] + $this->config['trading']
            ] + $this->config);
    }

    public function withTakeProfitPercentage(mixed $takeProfitPercentage): self
    {
        return new self([
                'trading' => [
                        'take_profit_percentage' => $takeProfitPercentage,
                    ] + $this->config['trading']
            ] + $this->config);
    }

    public function toArray(): array
    {
        return $this->config['trading'];
    }
}
