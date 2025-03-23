<?php

namespace Kwizer15\TradingBot\Configuration;

final readonly class BacktestConfiguration
{
    public float $initialBalance;
    public string $startDate;
    public string $endDate;
    public float $fees;
    public TradingConfiguration $tradingConfiguration;

    public function __construct(private array $config)
    {
        $this->initialBalance = $config['backtest']['initial_balance'] ?? 1000.0;
        $this->startDate = $config['backtest']['start_date'] ?? '2023-01-01';
        $this->endDate = $config['backtest']['end_date'] ?? '2023-12-31';
        $this->fees = $config['backtest']['fees'] ?? 0.1;
        $this->tradingConfiguration = new TradingConfiguration($config);
    }

    public function withPeriod(string $startDate, string $endDate): self
    {
        return new self([
           'backtest' => [
               'start_date' => $startDate,
               'end_date' => $endDate,
           ] + $this->config['backtest']
        ] + $this->config);
    }

    public function withInitialBalance(float $initialBalance): self
    {
        return new self([
            'backtest' => [
                'initial_balance' => $initialBalance,
            ] + $this->config['backtest']
        ] + $this->config);
    }

    public function withTradingConfiguration(TradingConfiguration $tradingConfiguration): self
    {
        return new self([
            'trading' => $tradingConfiguration->toArray() + $this->config['trading']
        ] + $this->config);
    }

    public function export(): void
    {
        file_put_contents($this->exportPath(), '<?php return ' . var_export($this->config, true) . ';');
    }

    public function clearExport(): void
    {
        // Supprimer la configuration temporaire
        if (file_exists($this->exportPath())) {
            unlink($this->exportPath());
        }
    }

    public function exportPath(): string
    {
        return dirname(__DIR__, 2) . '/config/temp_backtest_config.php';
    }
}
