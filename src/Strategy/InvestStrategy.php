<?php

namespace Kwizer15\TradingBot\Strategy;

use Kwizer15\TradingBot\DTO\KlineHistory;
use Kwizer15\TradingBot\DTO\Order;

final class InvestStrategy implements StrategyInterface, PositionActionStrategyInterface
{
    private array $params = [
        'period' => 48,
    ];
    private int $lastAnalysisTime = 0;

    public function shouldBuy(KlineHistory $history, string $currentSymbol): bool
    {
        return true;
    }

    public function shouldSell(KlineHistory $history, array $position): bool
    {
        return false;
    }

    public function getName(): string
    {
        return 'InvestStrategy';
    }

    public function getDescription(): string
    {
        return 'Stratégie d’investissement simple';
    }

    public function setParameters(array $params): void
    {
        $this->params = $params;
    }

    public function getParameters(): array
    {
        return $this->params;
    }

    public function getParameter(string $key, mixed $default = null): mixed
    {
        return $this->params[$key] ?? $default;
    }

    public function onSell(string $symbol, float $currentPrice): void
    {
    }

    public function onBuy(array $position): void
    {
    }

    public function getInvestment(string $symbol, float $currentPrice): ?float
    {
        return null;
    }

    public function getPositionAction(KlineHistory $history, array $position): PositionAction
    {
        $symbol = $position['symbol'];

        $currentTime = $history->last()->closeTime;

        if (!isset($this->positionData[$symbol])) {
            $this->onBuy($position);
        }

        $lastAnalysisTime = $this->lastAnalysisTime;
        $analysisPeriodMs = $this->params['period'] * 3600 * 1000;

        if ($currentTime - $lastAnalysisTime >= $analysisPeriodMs) {
            $this->lastAnalysisTime = $currentTime;

            return PositionAction::INCREASE_POSITION;
        }

        return PositionAction::HOLD;
    }

    public function calculateIncreasePercentage(KlineHistory $marketData, array $position): float
    {
    }

    public function calculateExitPercentage(KlineHistory $marketData, array $position): float
    {
    }

    public function onIncreasePosition(array $position, Order $order): void
    {
    }

    public function onPartialExit(array $position, Order $order): void
    {
    }
}