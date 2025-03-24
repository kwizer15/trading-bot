<?php

namespace Kwizer15\TradingBot\Strategy\MACD;

use Kwizer15\TradingBot\DTO\KlineHistory;
use Kwizer15\TradingBot\DTO\Position;
use Kwizer15\TradingBot\Strategy\StrategyInterface;

final class MACDStrategy implements StrategyInterface
{

    public function getMinimumKlines(): int
    {
        // TODO: Implement getMinimumKlines() method.
    }

    public function shouldBuy(KlineHistory $history, string $currentSymbol): bool
    {
        // TODO: Implement shouldBuy() method.
    }

    public function shouldSell(KlineHistory $history): bool
    {
        // TODO: Implement shouldSell() method.
    }

    public function getName(): string
    {
        // TODO: Implement getName() method.
    }

    public function getDescription(): string
    {
        // TODO: Implement getDescription() method.
    }

    public function setParameters(array $params): void
    {
        // TODO: Implement setParameters() method.
    }

    public function getParameters(): array
    {
        // TODO: Implement getParameters() method.
    }

    public function getParameter(string $key, mixed $default = null): mixed
    {
        // TODO: Implement getParameter() method.
    }

    public function onSell(string $symbol, float $currentPrice): void
    {
        // TODO: Implement onSell() method.
    }

    public function onBuy(Position $position): void
    {
        // TODO: Implement onBuy() method.
    }

    public function getInvestment(string $symbol, float $currentPrice): ?float
    {
        // TODO: Implement getInvestment() method.
    }
}