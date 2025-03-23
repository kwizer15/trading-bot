<?php

namespace Kwizer15\TradingBot\Backtest;

use Kwizer15\TradingBot\BinanceAPIInterface;
use Kwizer15\TradingBot\DTO\Balance;
use Kwizer15\TradingBot\DTO\KlineHistory;
use Kwizer15\TradingBot\DTO\Order;

final readonly class BacktestBinanceAPI implements BinanceAPIInterface
{
    public function __construct(
        private KlineHistory $klineHistory,
        private Balance $balance,
        private float $fees = 0.1,
    ) {

    }

    public function getKlines(string $symbol, string $interval = '1h', int $limit = 100, ?int $startTime = null, ?int $endTime = null): array
    {
        return $this->klineHistory->getData();
    }

    public function getBalance(string $currency): Balance
    {
        return $this->balance;
    }

    public function buyMarket($symbol, $quantity): Order
    {
        $price = $this->getCurrentPrice($symbol);
        $buyValue = $quantity * $price;
        if ($buyValue < 5) {
            throw new \Exception('Minimum order value is 5');
        }
        $fee = ($buyValue * $this->fees) / 100;

        $this->balance->sub($buyValue - $fee);

        return new Order(random_int(0, PHP_INT_MAX), $price, $quantity, $fee, $this->klineHistory->last()->closeTime);
    }

    public function sellMarket($symbol, $quantity): Order
    {
        $price = $this->getCurrentPrice($symbol);
        $saleValue = $quantity * $price;
        if ($saleValue < 5) {
            throw new \Exception('Minimum order value is 5');
        }
        $fee = ($saleValue * $this->fees) / 100;

        $this->balance->add($saleValue - $fee);

        return new Order(random_int(0, PHP_INT_MAX), $price, $quantity, $fee, $this->klineHistory->last()->closeTime);
    }

    public function getCurrentPrice($symbol): float
    {
        return $this->klineHistory->last()->close;
    }

}