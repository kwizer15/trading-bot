<?php

namespace Kwizer15\TradingBot\Strategy;

final class StrategyFactory
{
    public function create(string $strategyName): StrategyInterface {

        switch ($strategyName) {
            case 'RSI':
            case 'RSIStrategy':
                echo "Utilisation de la stratégie RSI\n";
                return new RSIStrategy();
            case 'MovingAverageStrategy':
                return new MovingAverageStrategy();
            default:
                throw new \Exception('Stratégie non supportée');
        }
    }
}