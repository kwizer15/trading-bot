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