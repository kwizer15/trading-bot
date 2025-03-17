<?php

namespace Kwizer15\TradingBot\Configuration;

class LoggingConfiguration
{
    public string $file;
    public string $level;

    public function __construct(array $config) {
        $this->file = $config['logging']['file'];
        $this->level = $config['logging']['level'];
    }
}