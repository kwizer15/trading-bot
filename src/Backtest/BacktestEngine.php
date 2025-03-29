<?php

namespace Kwizer15\TradingBot\Backtest;

use Kwizer15\TradingBot\Clock\FixedClock;
use Kwizer15\TradingBot\Configuration\BacktestConfiguration;
use Kwizer15\TradingBot\Configuration\TradingConfiguration;
use Kwizer15\TradingBot\DTO\Balance;
use Kwizer15\TradingBot\DTO\KlineHistory;
use Kwizer15\TradingBot\Strategy\StrategyInterface;
use Kwizer15\TradingBot\TradingBot;
use Kwizer15\TradingBot\Utils\ConsoleLogger;

class BacktestEngine
{
    private Balance $balance;
    private array $equity = [];
    private KlineHistory $history;

    public function __construct(
        private readonly StrategyInterface $strategy,
        array $history,
        private readonly TradingConfiguration $tradingConfiguration,
        private readonly BacktestConfiguration $backtestConfiguration,
    ) {
        $this->history = current($history);
        $this->balance = new Balance($this->backtestConfiguration->initialBalance);
    }

    public function run(): array
    {
        $initialBalance = $this->balance->free;
        $startTime = microtime(true);

        $this->equity[] = [
            'timestamp' => $this->history->first()->openTime, // Timestamp de la première bougie
            'equity' => $this->balance->free,
            'balance' => $this->balance->free,
            'price' => $this->history->first()->open,
        ];

        // Parcourir les données historiques
        $countData = $this->history->count();
        for ($i = $this->strategy->getMinimumKlines() + 1; $i < $countData; $i++) {
            $currentData = $this->history->slice($i + 1);
            $kline = $currentData->last();
            $tradingBot = $this->buildTradingBot($currentData);

            $tradingBot->run();

            $positions = $tradingBot->getPositions();
            // Enregistrer l'équité à chaque étape
            $totalEquity = $this->balance->free;
            foreach ($positions->iterateSymbols() as $symbol) {
                $positionObject = $positions->getPositionForSymbol($symbol);
                $totalEquity += $positionObject->current_value;
            }

            $this->equity[] = [
                'timestamp' => $kline->closeTime,
                'equity' => $totalEquity,
                'balance' => $this->balance->free,
                'price' => $this->history->first()->close,
            ];
        }

        $tradingBot = $this->buildTradingBot($this->history);
        foreach ($tradingBot->getPositions()->iterateSymbols() as $symbol) {
            $tradingBot->closePosition($symbol);
        }

        $trades = $tradingBot->getTrades();
        $endTime = microtime(true);
        $duration = $endTime - $startTime;

        // Calculer les statistiques de performance
        $finalBalance = $this->balance->free;
        $totalProfit = $finalBalance - $initialBalance;
        $totalProfitPct = ($totalProfit / $initialBalance) * 100;
        $totalTrades = \count($trades);

        $winningTrades = array_filter($trades, function ($trade) {
            return $trade['profit'] > 0;
        });

        $losingTrades = array_filter($trades, function ($trade) {
            return $trade['profit'] <= 0;
        });

        $winRate = $totalTrades > 0 ? (count($winningTrades) / $totalTrades) * 100 : 0;

        $profitFactor = 0;
        $totalGain = 0;
        $totalLoss = 0;

        foreach ($winningTrades as $trade) {
            $totalGain += $trade['profit'];
        }

        foreach ($losingTrades as $trade) {
            $totalLoss += abs($trade['profit']);
        }

        if ($totalLoss > 0) {
            $profitFactor = $totalGain / $totalLoss;
        }

        // Calculer le drawdown maximum
        $maxEquity = $initialBalance;
        $maxDrawdown = 0;
        $drawdownEnd = null;

        foreach ($this->equity as $equityPoint) {
            $currentEquity = $equityPoint['equity'];

            if ($currentEquity > $maxEquity) {
                $maxEquity = $currentEquity;
            }

            $drawdown = (($maxEquity - $currentEquity) / $maxEquity) * 100;

            if ($drawdown > $maxDrawdown) {
                $maxDrawdown = $drawdown;
                $drawdownEnd = $equityPoint['timestamp'];
            }
        }

        // Calculer les frais payés
        $feesPaid = 0;
        foreach ($trades as $trade) {
            $feesPaid += $trade['fees'] ?? 0;
        }

        unlink($this->getPositionFile());
        unlink($this->getTradeFile());

        // Résultats du backtest
        return [
            'strategy' => $this->strategy->getName(),
            'parameters' => $this->strategy->getParameters(),
            'initial_balance' => $initialBalance,
            'final_balance' => $finalBalance,
            'profit' => $totalProfit,
            'profit_pct' => $totalProfitPct,
            'total_trades' => $totalTrades,
            'winning_trades' => count($winningTrades),
            'losing_trades' => count($losingTrades),
            'win_rate' => $winRate,
            'profit_factor' => $profitFactor,
            'max_drawdown' => $maxDrawdown,
            'drawdown_end' => $drawdownEnd,
            'fees_paid' => $feesPaid,
            'duration' => $duration,
            'trades' => $trades,
            'equity_curve' => $this->equity,
        ];
    }

    /**
     * @param KlineHistory $currentData
     * @return TradingBot
     */
    private function buildTradingBot(KlineHistory $currentData): TradingBot
    {
        $kline = $currentData->last();
        $clock = new FixedClock(\DateTimeImmutable::createFromFormat('U', (int) ($kline->closeTime / 1000), new \DateTimeZone('UTC')));
        $binanceAPI = new BacktestBinanceAPI($currentData, $this->balance, $this->backtestConfiguration->fees ?? 0.1);

        return new TradingBot(
            $binanceAPI,
            $this->strategy,
            $this->tradingConfiguration,
            new ConsoleLogger($clock, 'notice'),
            $this->getPositionFile(),
            $this->getTradeFile(),
            $clock,
        );
    }

    /**
     * @return string
     */
    private function getPositionFile(): string
    {
        return dirname(__DIR__, 2) . '/data/backtest_positions.json';
    }

    private function getTradeFile(): string
    {
        return dirname(__DIR__, 2) . '/data/backtest_trades.json';
    }

}
