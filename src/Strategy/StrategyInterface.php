<?php

namespace Kwizer15\TradingBot\Strategy;

use Kwizer15\TradingBot\DTO\KlineHistory;
use Kwizer15\TradingBot\DTO\Position;

interface StrategyInterface extends BacktestableInterface
{
    /**
     * Analyse les données du marché et détermine si un signal d'achat est présent
     *
     * @param KlineHistory $history Données du marché (klines)
     * @return bool True si un signal d'achat est détecté, sinon False
     */
    public function shouldBuy(KlineHistory $history, string $currentSymbol): bool;

    /**
     * Analyse les données du marché et détermine si un signal de vente est présent
     *
     * @param KlineHistory $history Données du marché (klines)
     * @return bool True si un signal de vente est détecté, sinon False
     */
    public function shouldSell(KlineHistory $history): bool;

    /**
     * Obtient le nom de la stratégie
     *
     * @return string Nom de la stratégie
     */
    public function getName(): string;

    /**
     * Obtient la description de la stratégie
     *
     * @return string Description de la stratégie
     */
    public function getDescription(): string;

    /**
     * Définit les paramètres de la stratégie
     *
     * @param array $params Paramètres à définir
     * @return void
     */
    public function setParameters(array $params): void;

    /**
     * Obtient les paramètres actuels de la stratégie
     *
     * @return array Paramètres actuels
     */
    public function getParameters(): array;
    public function getParameter(string $key, mixed $default = null): mixed;


    public function onSell(string $symbol, float $currentPrice): void;
    public function onBuy(Position $position): void;

    public function getInvestment(string $symbol, float $currentPrice): ?float;

}
