<?php

namespace Kwizer15\TradingBot\Configuration;

class ApiConfiguration
{
    public string $key;
    public string $secret;
    public bool $testMode;

    public function __construct(array $config) {
        $this->key = $config['api']['key'];
        $this->secret = $config['api']['secret'];
        $this->testMode = $config['api']['test_mode'];
    }
}