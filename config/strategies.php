<?php

/**
 * Fichier de configuration des stratégies de trading
 * Vous pouvez définir ici plusieurs configurations pour chaque stratégie
 */

return [
    'moving_average' => [
        'default' => [
            'short_period' => 9,
            'long_period' => 21,
            'price_index' => 4,
        ],
        'aggressive' => [
            'short_period' => 5,
            'long_period' => 15,
            'price_index' => 4,
        ],
        'conservative' => [
            'short_period' => 12,
            'long_period' => 26,
            'price_index' => 4,
        ],
    ],

    'rsi' => [
        'default' => [
            'period' => 14,
            'overbought' => 70,
            'oversold' => 30,
            'price_index' => 4,
        ],
        'aggressive' => [
            'period' => 10,
            'overbought' => 75,
            'oversold' => 25,
            'price_index' => 4,
        ],
        'conservative' => [
            'period' => 21,
            'overbought' => 65,
            'oversold' => 35,
            'price_index' => 4,
        ],
    ],

    'dynamic_position_strategy' => [
        'initial_stop_loss_pct' => 5.0,
        'analysis_period' => 24,
        'partial_take_profit' => true,
        'position_increase_pct' => 5.0,
        'max_investment_multiplier' => 2.0,
        'partial_exit_pct' => 30.0,
        'entry_indicators' => [
            'rsi_period' => 14,
            'rsi_oversold' => 30,
            'macd_fast' => 12,
            'macd_slow' => 26,
            'macd_signal' => 9
        ]
    ],

    // Vous pouvez ajouter d'autres stratégies ici
    'bollinger_bands' => [
        'default' => [
            'period' => 20,
            'deviation' => 2,
            'price_index' => 4,
        ],
    ],

    'macd' => [
        'default' => [
            'fast_period' => 12,
            'slow_period' => 26,
            'signal_period' => 9,
            'price_index' => 4,
        ],
    ],
];