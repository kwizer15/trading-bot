<?php

namespace Kwizer15\TradingBot\Strategy;

final class StrategyFactory
{
    public function create(string $strategyName, array $params): StrategyInterface {

        $strategy = match ($strategyName) {
            'RSI',
            'RSIStrategy' => new RSIStrategy(),
            'MovingAverageStrategy' => new MovingAverageStrategy(),
            'DynamicPositionStrategy' => new DynamicPositionStrategy(),
            default => throw new \Exception('Stratégie non supportée'),
        };

        $strategy->setParameters($params);

        return $strategy;
    }
}