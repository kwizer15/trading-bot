<?php

namespace Kwizer15\TradingBot\Backtest;

final class BacktestResult
{
    public function __construct(
        public string $strategy,
        public string $parameters,
        public string $initial_balance,
        public string $final_balance,
        public string $profit,
        public string $profit_pct,
        public string $total_trades,
        public string $winning_trades,
        public string $losing_trades,
        public string $win_rate,
        public string $profit_factor,
        public string $max_drawdown,
        public string $drawdown_end,
        public string $fees_paid,
        public string $duration,
        public string $trades,
        public string $equity_curve,
    ) {
    }
}
