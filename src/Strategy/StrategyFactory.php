<?php

namespace Kwizer15\TradingBot\Strategy;

final class StrategyFactory
{
    public function create(string $strategyName, array $params, bool $backtest = false): StrategyInterface {

        $strategy = match ($strategyName) {
            'RSI',
            'RSIStrategy' => new RSIStrategy(),
            'MovingAverageStrategy' => new MovingAverageStrategy(),
            'DynamicPositionStrategy' => new DynamicPositionStrategy($backtest),
            default => throw new \Exception('Stratégie non supportée'),
        };

        $strategy->setParameters($params);

        return $strategy;
    }
}