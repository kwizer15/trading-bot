<?php

namespace Kwizer15\TradingBot\Utils;

use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;

final class Logger implements LoggerInterface
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
        private readonly string $logFile,
        string $level = 'info'
    ) {
        $this->logLevel = self::LEVELS[$level] ?? 1;

        // Créer le dossier de logs si nécessaire
        $dir = dirname($logFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
    }

    public function log($level, string|\Stringable $message, array $context = []): void
    {
        if (self::LEVELS[$level] < $this->logLevel) {
            return;
        }

        $logMessage = $this->clock->now()->format('Y-m-d H:i:s') . " [{$level}] {$message}" . PHP_EOL;

        file_put_contents($this->logFile, $logMessage, FILE_APPEND);

        // Afficher également dans la console
        echo $logMessage;
    }
}
