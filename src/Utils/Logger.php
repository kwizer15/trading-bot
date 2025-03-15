<?php

namespace Kwizer15\TradingBot\Utils;

use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;

final class Logger implements LoggerInterface
{

    use LoggerTrait;

    private $logFile;
    private $logLevel;
    private $levels = [
        'debug' => 0,
        'info' => 1,
        'warning' => 2,
        'error' => 3
    ];

    public function __construct($logFile, $level = 'info')
    {
        $this->logFile = $logFile;
        $this->logLevel = $this->levels[$level] ?? 1;

        // Créer le dossier de logs si nécessaire
        $dir = dirname($logFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
    }

    public function log($level, string|\Stringable $message, array $context = []): void
    {
        if ($this->levels[$level] < $this->logLevel) {
            return;
        }

        $logMessage = date('Y-m-d H:i:s') . " [{$level}] {$message}" . PHP_EOL;

        file_put_contents($this->logFile, $logMessage, FILE_APPEND);

        // Afficher également dans la console
        echo $logMessage;
    }
}
