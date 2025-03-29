<?php

namespace Kwizer15\TradingBot\Strategy\DynamicPosition;

final readonly class DynamicPositionParameters
{
    public function __construct(
        public float $secure_stop_loss_pct = 8.0,        // Stop loss en pourcentage
        public float $profit_stop_loss_pct = 15.0,        // Stop loss en pourcentage
        public float $buy_stop_loss_pct = 5.0,     // Stop loss initial en pourcentage
        public float $max_buy_stop_loss_pct = 10.0,        // Stop loss en pourcentage
        public array $entry_indicators = [              // Indicateurs pour lentrée (option)
            'rsi_period' => 14,
            'rsi_oversold' => 40,
            'macd_fast' => 12,
            'macd_slow' => 26,
            'macd_signal' => 9
        ],
    ) {
    }

    public function toArray(): array
    {
        return [
            'buy_stop_loss_pct' => $this->buy_stop_loss_pct,
            'profit_stop_loss_pct' => $this->profit_stop_loss_pct,
            'secure_stop_loss_pct' => $this->secure_stop_loss_pct,
            'max_buy_stop_loss_pct' => $this->max_buy_stop_loss_pct,
            'entry_indicators' => $this->entry_indicators,
        ];
    }
}
