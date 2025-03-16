<?php

return [
    'api' => [
        'key' => 'VOTRE_CLE_API_BINANCE',
        'secret' => 'VOTRE_SECRET_API_BINANCE',
        'test_mode' => true,  // true pour utiliser le mode testnet
    ],
    'trading' => [
        'base_currency' => 'USDT',
        'symbols' => ['BTC', 'ETH', 'BNB'], // Paires à trader
        'investment_per_trade' => 100,  // Montant à investir par trade (en USDT)
        'stop_loss_percentage' => 2.5,  // Pourcentage de stop loss
        'take_profit_percentage' => 5,  // Pourcentage de prise de profit
        'max_open_positions' => 3,      // Nombre maximum de positions ouvertes
    ],
    'schedule' => [
        'check_interval' => 300,  // Intervalle de vérification en secondes (5 minutes)
    ],
    'backtest' => [
        'start_date' => '2023-01-01',
        'end_date' => '2023-12-31',
        'initial_balance' => 1000,  // En USDT
    ],
    'notifications' => [
        'email' => [
            'enabled' => false,
            'address' => 'votre-email@exemple.com',
        ],
        'telegram' => [
            'enabled' => false,
            'bot_token' => '',
            'chat_id' => '',
        ],
    ],
    'logging' => [
        'level' => 'info',  // debug, info, warning, error
        'file' => __DIR__ . '/../logs/trading.log',
    ],
];
