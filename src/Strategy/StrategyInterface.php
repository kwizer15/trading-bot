<?php

namespace Kwizer15\TradingBot\Strategy;

interface StrategyInterface {
    /**
     * Analyse les données du marché et détermine si un signal d'achat est présent
     *
     * @param array $marketData Données du marché (klines)
     * @return bool True si un signal d'achat est détecté, sinon False
     */
    public function shouldBuy(array $marketData): bool;

    /**
     * Analyse les données du marché et détermine si un signal de vente est présent
     *
     * @param array $marketData Données du marché (klines)
     * @param array $position Informations sur la position ouverte
     * @return bool True si un signal de vente est détecté, sinon False
     */
    public function shouldSell(array $marketData, array $position): bool;

    /**
     * Détermine l'action à effectuer sur une position
     *
     * @param array $marketData Données du marché (klines)
     * @param array $position Informations sur la position ouverte
     * @return string Action à effectuer ('SELL', 'INCREASE_POSITION', 'PARTIAL_EXIT', ou 'HOLD')
     */
    public function getPositionAction(array $marketData, array $position): string;

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