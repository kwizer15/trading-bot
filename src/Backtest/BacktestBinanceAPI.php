<?php

namespace Kwizer15\TradingBot\Backtest;

use Kwizer15\TradingBot\BinanceAPIInterface;
use Kwizer15\TradingBot\DTO\Balance;
use Kwizer15\TradingBot\DTO\KlineHistory;

class BacktestBinanceAPI implements BinanceAPIInterface
{
    public function __construct(private readonly KlineHistory $klineHistory) {

    }

    public function getKlines(string $symbol, string $interval = '1h', int $limit = 100, ?int $startTime = null, ?int $endTime = null): array
    {
        return (array) $this->klineHistory;
    }

    public function getBalance(string $currency): Balance
    {
        return new Balance(10000);
    }

    public function buyMarket($symbol, $quantity)
    {
        // TODO: Implement buyMarket() method.
    }

    public function sellMarket($symbol, $quantity)
    {
        // TODO: Implement sellMarket() method.
    }

    public function getCurrentPrice($symbol)
    {
        return $this->klineHistory->last()->close;
    }

}