<?php

namespace Kwizer15\TradingBot\Strategy;

use Kwizer15\TradingBot\DTO\KlineHistory;

interface PositionActionStrategyInterface extends StrategyInterface
{
    /**
     * Détermine l'action à effectuer sur une position
     *
     * @param array $marketData Données du marché (klines)
     * @param array $position Informations sur la position ouverte
     *
     * @return PositionAction Action à effectuer
     */
    public function getPositionAction(KlineHistory $marketData, array $position): PositionAction;

    public function calculateIncreasePercentage(KlineHistory $marketData, array $position): float;

    public function calculateExitPercentage(KlineHistory $marketData, array $position): float;
}