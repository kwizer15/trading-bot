<?php

namespace Kwizer15\TradingBot\Configuration;

final readonly class LoggingConfiguration
{
    public string $file;
    public string $level;

    public function __construct(array $config)
    {
        $this->file = $config['logging']['file'] ?? dirname(__DIR__, 2) . '/logs/trading.log';
        $this->level = $config['logging']['level'] ?? 'info';
    }
}
