<?php

namespace Kwizer15\TradingBot\Strategy;

use Kwizer15\TradingBot\DTO\KlineHistory;
use Kwizer15\TradingBot\DTO\Position;

final readonly class CumulateStrategy implements StrategyInterface
{
    private array $strategies;
    public function __construct(StrategyInterface ...$strategies)
    {
        $this->strategies = $strategies;
    }

    public function getMinimumKlines(): int
    {
        return max(...array_map(static fn (StrategyInterface $strategy) => $strategy->getMinimumKlines(), $this->strategies));
    }

    public function shouldBuy(KlineHistory $history, string $currentSymbol): bool
    {
        foreach ($this->strategies as $strategy) {
            if (!$strategy->shouldBuy($history, $currentSymbol)) {
                return false;
            }
        }

        return true;
    }

    public function shouldSell(KlineHistory $history, Position $position): bool
    {
        foreach ($this->strategies as $strategy) {
            if (!$strategy->shouldSell($history)) {
                return false;
            }
        }

        return true;
    }

    public function getName(): string
    {
        return 'CumulateStrategy';
    }

    public function getDescription(): string
    {
        return '';
    }

    public function setParameters(array $params): void
    {
        foreach ($this->strategies as $strategy) {
            $strategy->setParameters($params[$strategy->getName()]);
        }
    }

    public function getParameters(): array
    {
        $params = [];
        foreach ($this->strategies as $strategy) {
            $params[$strategy->getName()] = $strategy->getParameters();
        }

        return $params;
    }

    public function getParameter(string $key, mixed $default = null): mixed
    {
        [$strategyName, $paramKey] = explode('.', $key);
        foreach ($this->strategies as $strategy) {
            if ($strategy->getName() === $strategyName) {
                return $strategy->getParameter($paramKey, $default);
            }
        }

        return $default;
    }

    public function onSell(string $symbol, float $currentPrice): void
    {
        foreach ($this->strategies as $strategy) {
            $strategy->onSell($symbol, $currentPrice);
        }
    }

    public function onBuy(Position $position): void
    {
        foreach ($this->strategies as $strategy) {
            $strategy->onBuy($position);
        }
    }

    public function getInvestment(string $symbol, float $currentPrice): ?float
    {
        throw new \LogicException('Should not be called.');
    }

    public function calculateStopLoss(string $symbol, float $currentPrice): ?float
    {
        return null;
    }
}
