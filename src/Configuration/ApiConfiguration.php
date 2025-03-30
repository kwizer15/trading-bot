<?php

namespace Kwizer15\TradingBot\Configuration;

final readonly class ApiConfiguration
{
    public string $key;
    public string $secret;
    public bool $testMode;

    public function __construct(array $config)
    {
        $this->key = $config['api']['key'] ?? 'API_KEY';
        $this->secret = $config['api']['secret'] ?? 'SECRET_KEY';
        $this->testMode = $config['api']['test_mode'] ?? true;
    }
}
