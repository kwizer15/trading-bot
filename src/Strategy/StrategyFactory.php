<?php

namespace Kwizer15\TradingBot\Strategy;

use Kwizer15\TradingBot\Strategy\DynamicPosition\DynamicPositionStrategy;
use Kwizer15\TradingBot\Strategy\MovingAverage\MovingAverageStrategy;
use Kwizer15\TradingBot\Strategy\RSI\RSIStrategy;

final class StrategyFactory
{
    public function create(string $strategyName, array $params, bool $backtest = false): StrategyInterface
    {

        $strategy = match ($strategyName) {
            'RSI',
            'RSIStrategy' => new RSIStrategy(),
            'MovingAverageStrategy' => new MovingAverageStrategy(),
            'DynamicPositionStrategy' => new DynamicPositionStrategy(isBacktest: $backtest),
            default => throw new \Exception('Stratégie non supportée'),
        };

        $strategy->setParameters($params);

        return $strategy;
    }
}
