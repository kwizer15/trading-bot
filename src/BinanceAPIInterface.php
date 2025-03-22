<?php

namespace Kwizer15\TradingBot;

use Kwizer15\TradingBot\DTO\Balance;

interface BinanceAPIInterface
{
    /**
     * Récupère les données de marché pour un symbole
     */
    public function getKlines(string $symbol, string $interval = '1h', int $limit = 100, ?int $startTime = null, ?int $endTime = null): array;

    /**
     * Récupère le solde pour une devise spécifique
     */
    public function getBalance(string $currency): Balance;

    /**
     * Crée un ordre d'achat market
     */
    public function buyMarket($symbol, $quantity);

    /**
     * Crée un ordre de vente market
     */
    public function sellMarket($symbol, $quantity);

    /**
     * Récupère le prix actuel d'un symbole
     */
    public function getCurrentPrice($symbol);
}