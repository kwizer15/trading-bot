<?php

namespace Kwizer15\TradingBot\DTO;

use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;

class PositionList
{
    /**
     * @var array <string, Position>
     */
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
            $positions = json_decode(file_get_contents($this->positionsFile), true);
            foreach ($positions as $position) {
                $positionObject = Position::fromArray($position);
                $this->positions[$positionObject->symbol] = $positionObject;
            }
            $this->logger->info('Positions chargées: ' . count($this->positions));
        } else {
            $this->logger->info('Aucune position existante trouvée');
        }

        $this->loaded = true;

    }

    public function hasPositionForSymbol(string $symbol): bool
    {
        $this->load();

        return isset($this->positions[$symbol]);
    }

    public function getPositionForSymbol(string $symbol): Position
    {
        $this->load();
        if ($this->hasPositionForSymbol($symbol)) {
            return $this->positions[$symbol];
        }

        throw new \InvalidArgumentException("Aucune position trouvée pour {$symbol}");
    }

    public function getPositionQuantity(string $symbol): float
    {
        return $this->getPositionForSymbol($symbol)->quantity;
    }

    public function increasePosition(
        string $symbol,
        Order $order,
    ): Position {
        $this->load();

        $this->positions[$symbol] = $this->getPositionForSymbol($symbol)->increase($order);

        $this->save();

        $this->logger->info("Position augmentée pour {$symbol}: +{$order->quantity} au prix de {$order->price}");

        return $this->positions[$symbol];
    }

    public function partialExit(string $symbol, float $quantityToSell, float $fees): Position
    {
        $this->load();

        $this->positions[$symbol] = $this->getPositionForSymbol($symbol)->partialExit($quantityToSell, $fees);

        $this->save();

        $this->logger->info("Sortie partielle réussie pour {$symbol}: {$quantityToSell} vendus au prix de {$this->positions[$symbol]->current_price}");

        return $this->positions[$symbol];
    }

    public function buy(string $symbol, float $currentPrice, float $quantity, float $cost, int $orderId, float $fees): Position
    {
        $this->load();

        $this->positions[$symbol] = (new Position(
            $symbol,
            $currentPrice,
            $quantity,
            $this->clock->now()->getTimestamp() * 1000,
            $cost,
            $currentPrice,
            $quantity * $currentPrice,
            0,
            0,
            $fees,
            0,
            $orderId
        ));

        $this->save();

        $this->logger->info("Achat réussi de {$quantity} {$symbol} au prix de {$currentPrice}");

        return $this->positions[$symbol];
    }

    public function sell(string $symbol): void
    {
        $this->load();

        $positionObject = $this->getPositionForSymbol($symbol);

        $this->logger->info("Vente réussie de {$positionObject->quantity} {$symbol} au prix de {$positionObject->current_price} (P/L: {$positionObject->profit_loss_pct}%)");

        unset($this->positions[$symbol]);

        $this->save();
    }

    private function save(): void
    {
        $this->load();

        $positions = [];
        foreach ($this->positions as $symbol => $position) {
            $positions[$symbol] = $position->toArray();
        }
        file_put_contents($this->positionsFile, json_encode($positions, JSON_PRETTY_PRINT));
    }

    public function iterateSymbols(): \Generator
    {
        $this->load();

        foreach ($this->positions as $symbol => $position) {
            yield $symbol;
        }
    }

    public function updatePosition(string $symbol, float $currentPrice): Position
    {
        $this->load();

        $this->positions[$symbol] = $this->getPositionForSymbol($symbol)->update($currentPrice);

        $this->save();

        return $this->positions[$symbol];
    }

    public function isStopLossTriggered(string $symbol, float $stopLossPercentage): bool
    {
        $positionObject = $this->getPositionForSymbol($symbol);

        return $positionObject->profit_loss_pct <= -$stopLossPercentage;
    }

    public function isTakeProfitTriggered(string $symbol, float $takeProfitPercentage): bool
    {
        $positionObject = $this->getPositionForSymbol($symbol);

        return $positionObject->profit_loss_pct >= $takeProfitPercentage;
    }

    public function count(): int
    {
        return \count($this->positions);
    }
}
