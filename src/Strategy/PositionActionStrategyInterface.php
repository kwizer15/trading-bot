<?php

namespace Kwizer15\TradingBot\Strategy;

use Kwizer15\TradingBot\DTO\KlineHistory;
use Kwizer15\TradingBot\DTO\Order;
use Kwizer15\TradingBot\DTO\Position;

interface PositionActionStrategyInterface extends StrategyInterface
{
    /**
     * Détermine l'action à effectuer sur une position
     *
     * @param KlineHistory $history Données du marché (klines)
     * @param Position $position Informations sur la position ouverte
     *
     * @return PositionAction Action à effectuer
     */
    public function getPositionAction(KlineHistory $history, Position $position): PositionAction;

    public function calculateIncreasePercentage(KlineHistory $marketData, Position $position): float;

    public function calculateExitPercentage(KlineHistory $marketData, Position $position): float;

    public function onIncreasePosition(Position $position, Order $order): void;

    public function onPartialExit(Position $position, Order $order): void;

}
