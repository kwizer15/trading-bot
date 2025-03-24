<?php

namespace Kwizer15\TradingBot\Strategy\DynamicPosition;

final readonly class DynamicPositionParameters
{
    public function __construct(
        public float $initial_stop_loss_pct = 10.0,     // Stop loss initial en pourcentage
        public float $profit_stop_loss_pct = 15.0,        // Stop loss en pourcentage
        public float $rebuy_stop_loss_pct = 10.0,        // Stop loss en pourcentage
        public int $analysis_period = 24,            // Période d'analyse en heures
        public bool $partial_take_profit = true,      // Activer la prise de profit partielle
        public float $max_investment_multiplier = 20.0, // Multiplicateur max de l'investissement initial
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
            'initial_stop_loss_pct' => $this->initial_stop_loss_pct,
            'analysis_period' => $this->analysis_period,
            'partial_take_profit' => $this->partial_take_profit,
            'max_investment_multiplier' => $this->max_investment_multiplier,
            'entry_indicators' => $this->entry_indicators,
        ];
    }
}
