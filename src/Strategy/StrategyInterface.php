<?php

namespace Kwizer15\TradingBot\Strategy;

use Kwizer15\TradingBot\DTO\KlineHistory;

interface StrategyInterface {
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
     * @param array $position Informations sur la position ouverte
     * @return bool True si un signal de vente est détecté, sinon False
     */
    public function shouldSell(KlineHistory $history, array $position): bool;

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
}