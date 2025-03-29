<?php

namespace Kwizer15\TradingBot;

use Kwizer15\TradingBot\DTO\Balance;
use Kwizer15\TradingBot\DTO\Order;

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
    public function buyMarket($symbol, $quantity): Order;

    /**
     * Crée un ordre de vente market
     */
    public function sellMarket($symbol, $quantity): Order;

    /**
     * Récupère le prix actuel d'un symbole
     */
    public function getCurrentPrice($symbol): float;

    /**
     * @param array<string> $symbols
     *
     * @return iterable<string, float>
     */
    public function getCurrentPrices(array $symbols): iterable;

    public function prepareKlines(string $symbol, string $interval = '1h', int $limit = 100, ?int $startTime = null, ?int $endTime = null): void;
}
