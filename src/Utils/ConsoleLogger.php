<?php

namespace Kwizer15\TradingBot\Utils;

use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;

final class ConsoleLogger implements LoggerInterface
{
    use LoggerTrait;

    private int $logLevel;
    private const LEVELS = [
        'debug' => 0,
        'info' => 1,
        'notice' => 2,
        'warning' => 3,
        'error' => 4,
    ];

    public function __construct(
        private readonly ClockInterface $clock,
        string $level = 'info'
    ) {
        $this->logLevel = self::LEVELS[$level] ?? 1;
    }

    public function log($level, string|\Stringable $message, array $context = []): void
    {
        return;
        if (self::LEVELS[$level] < $this->logLevel) {
            return;
        }

        $logMessage = $this->clock->now()->format('Y-m-d H:i:s') . " [{$level}] {$message}" . PHP_EOL;

        echo $logMessage;
    }
}
