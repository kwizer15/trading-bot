<?php

namespace Kwizer15\TradingBot\DTO;

use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;

class PositionList
{
    private array $positions = [];
    private bool $loaded = false;

    public function __construct(
        private readonly ?string $positionsFile,
        private readonly LoggerInterface $logger,
        private readonly ClockInterface $clock,
    ) {
    }

    private function load(): void
    {
        if ($this->loaded) {
            return;
        }

            if (file_exists($this->positionsFile)) {
                $this->positions = json_decode(file_get_contents($this->positionsFile), true);
                $this->logger->info( 'Positions chargées: ' . count($this->positions));
            } else {
                $this->logger->info( 'Aucune position existante trouvée');
            }

            $this->loaded = true;

    }

    public function hasPositionForSymbol(string $symbol): bool
    {
        $this->load();

        return isset($this->positions[$symbol]);
    }

    public function getPositionForSymbol(string $symbol): array
    {
        $this->load();

        return $this->positions[$symbol] ?? throw new \InvalidArgumentException("Aucune position trouvée pour {$symbol}");
    }

    public function getPositionQuantity(string $symbol): float
    {
        return $this->getPositionForSymbol($symbol)['quantity'];
    }

    public function increasePosition(
        string $symbol,
        float  $currentPrice,
        float  $additionalQuantity,
        float  $additionalInvestment,
    ): array
    {
        $this->load();

        $position = $this->positions[$symbol];
        $quantity = $position['quantity'] + $additionalQuantity;
        $cost = $position['cost'] + $additionalInvestment;
        $entryPrice = $cost / $quantity;

        $this->positions[$symbol] = [
            'symbol' => $symbol,
            'entry_price' => $entryPrice,
            'quantity' => $quantity,
            'timestamp' => $position['timestamp'],
            'cost' => $cost,
            'current_price' => $currentPrice,
            'current_value' => $quantity * $currentPrice,
            'profit_loss' => ($quantity * $currentPrice) - $cost,
            'profit_loss_pct' => ((($quantity * $currentPrice) - $cost) / $cost) * 100,
            'order_id' => $position['order_id']
        ];

        $this->save();

        $this->logger->info("Position augmentée pour {$symbol}: +{$additionalQuantity} au prix de {$currentPrice}");

        return $this->positions[$symbol];
    }

    public function partialExit(string $symbol, float $quantityToSell): array
    {
        $this->load();

        $position = $this->positions[$symbol];

        $remainingQuantity = $position['quantity'] - $quantityToSell;
        $remainingCost = $position['cost'] * ($remainingQuantity / $position['quantity']);

        $this->positions[$symbol] = [
            'symbol' => $symbol,
            'entry_price' => $position['entry_price'],
            'quantity' => $remainingQuantity,
            'timestamp' => $position['timestamp'],
            'cost' => $remainingCost,
            'current_price' => $position['current_price'],
            'current_value' => $remainingQuantity * $position['current_price'],
            'profit_loss' => ($remainingQuantity * $position['current_price']) - $remainingCost,
            'profit_loss_pct' => ((($remainingQuantity * $position['current_price']) - $remainingCost) / $remainingCost) * 100,
            'order_id' => $position['order_id']
        ];

        $this->save();

        $this->logger->info( "Sortie partielle réussie pour {$symbol}: {$quantityToSell} vendus au prix de {$position['current_price']}");

        return $this->positions[$symbol];
    }

    public function buy(string $symbol, float $currentPrice, float $quantity, float $cost, int $orderId, int $timestamp = null): array
    {
        $this->load();

        $timestamp ??= $this->clock->now()->getTimestamp() * 1000;
        $this->positions[$symbol] = [
            'symbol' => $symbol,
            'entry_price' => $currentPrice,
            'quantity' => $quantity,
            'timestamp' => $timestamp,
            'cost' => $cost,
            'current_price' => $currentPrice,
            'current_value' => $quantity * $currentPrice,
            'profit_loss' => 0,
            'profit_loss_pct' => 0,
            'order_id' => $orderId
        ];

        $this->save();

        $this->logger->info("Achat réussi de {$quantity} {$symbol} au prix de {$currentPrice}");

        return $this->positions[$symbol];
    }

    public function sell(string $symbol): void
    {
        $this->load();

        $position = $this->getPositionForSymbol($symbol);

        $this->logger->info( "Vente réussie de {$position['quantity']} {$symbol} au prix de {$position['current_price']} (P/L: {$position['profit_loss_pct']}%)");

        unset($this->positions[$symbol]);

        $this->save();
    }

    private function save(): void
    {
        $this->load();

        file_put_contents($this->positionsFile, json_encode($this->positions, JSON_PRETTY_PRINT));
    }

    public function iterateSymbols(): \Generator
    {
        $this->load();

        foreach ($this->positions as $symbol => $position) {
            yield $symbol;
        }
    }

    public function updatePosition(string $symbol, float $currentPrice): array
    {
        $this->load();

        $position = $this->getPositionForSymbol($symbol);
        // Mettre à jour la position avec le prix actuel
        $position['current_price'] = $currentPrice;
        $position['current_value'] = $position['quantity'] * $currentPrice;
        $position['profit_loss'] = $position['current_value'] - $position['cost'];
        $position['profit_loss_pct'] = ($position['profit_loss'] / $position['cost']) * 100;

        $this->positions[$symbol] = $position;

        $this->save();

        return $position;
    }

    public function isStopLossTriggered(string $symbol, float $stopLossPercentage): bool
    {
        $position = $this->getPositionForSymbol($symbol);

        return $position['profit_loss_pct'] <= -$stopLossPercentage;
    }

    public function isTakeProfitTriggered(string $symbol, float $takeProfitPercentage): bool
    {
        $position = $this->getPositionForSymbol($symbol);

        return $position['profit_loss_pct'] >= $takeProfitPercentage;
    }

    public function count(): int
    {
        return \count($this->positions);
    }
}