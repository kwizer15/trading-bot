<?php

namespace Kwizer15\TradingBot\Strategy\MovingAverage;

use Kwizer15\TradingBot\DTO\KlineHistory;
use Kwizer15\TradingBot\DTO\Position;
use Kwizer15\TradingBot\Strategy\StrategyInterface;

final class MovingAverageStrategy implements StrategyInterface
{
    private $params = [
        'short_period' => 9,   // Période courte (en bougies)
        'long_period' => 21,   // Période longue (en bougies)
        'price_index' => 4,    // Indice du prix de clôture dans les klines (4 = close)
    ];

    /**
     * Calcule la moyenne mobile simple pour une période donnée
     */
    private function calculateSMA(KlineHistory $history, int $period, int $priceIndex): float
    {
        $count = $history->count();

        $priceIndexMap = [
            1 => 'open',
            2 => 'high',
            3 => 'low',
            4 => 'close',
        ];

        if ($count < $period) {
            throw new \Exception("Pas assez de données pour calculer la SMA de période {$period}");
        }

        $sum = 0;

        $priceName = $priceIndexMap[$priceIndex];
        // Prendre les dernières bougies selon la période
        for ($i = $count - $period; $i < $count; $i++) {
            $sum += $history->get($i)->$priceName;
        }

        return $sum / $period;
    }

    /**
     * Vérifie si un croisement haussier (Golden Cross) s'est produit
     * (SMA courte croise au-dessus de SMA longue)
     */
    public function shouldBuy(KlineHistory $history, string $currentSymbol): bool
    {
        try {
            // Calculer la SMA courte et longue actuelles
            $shortSMA_current = $this->calculateSMA($history, $this->params['short_period'], $this->params['price_index']);
            $longSMA_current = $this->calculateSMA($history, $this->params['long_period'], $this->params['price_index']);

            // Si nous n'avons pas assez de données pour les calculs précédents, on ne peut pas détecter un croisement
            if ($history->count() <= $this->params['long_period']) {
                return false;
            }

            // Créer un ensemble de données sans la dernière bougie pour calculer les SMA précédentes
            $dtoPreviousData = $history->slice(-1);
            // Calculer la SMA courte et longue précédentes
            $shortSMA_previous = $this->calculateSMA($dtoPreviousData, $this->params['short_period'], $this->params['price_index']);
            $longSMA_previous = $this->calculateSMA($dtoPreviousData, $this->params['long_period'], $this->params['price_index']);

            // Détecter le croisement haussier (Golden Cross)
            // SMA courte était sous la SMA longue et est maintenant au-dessus
            return ($shortSMA_previous < $longSMA_previous) && ($shortSMA_current > $longSMA_current);

        } catch (\Exception $e) {
            // En cas d'erreur, ne pas générer de signal d'achat
            return false;
        }
    }

    /**
     * Vérifie si un croisement baissier (Death Cross) s'est produit
     * (SMA courte croise en-dessous de SMA longue)
     * @param KlineHistory $history
     * @param Position $position
     */
    public function shouldSell(KlineHistory $history, Position $position): bool
    {
        try {
            // Calculer la SMA courte et longue actuelles
            $shortSMA_current = $this->calculateSMA($history, $this->params['short_period'], $this->params['price_index']);
            $longSMA_current = $this->calculateSMA($history, $this->params['long_period'], $this->params['price_index']);

            // Si nous n'avons pas assez de données pour les calculs précédents, on ne peut pas détecter un croisement
            if ($history->count() <= $this->params['long_period']) {
                return false;
            }

            // Créer un ensemble de données sans la dernière bougie pour calculer les SMA précédentes
            $previousData = $history->slice(-1);
            // Calculer la SMA courte et longue précédentes
            $shortSMA_previous = $this->calculateSMA($previousData, $this->params['short_period'], $this->params['price_index']);
            $longSMA_previous = $this->calculateSMA($previousData, $this->params['long_period'], $this->params['price_index']);

            // Détecter le croisement baissier (Death Cross)
            // SMA courte était au-dessus de la SMA longue et est maintenant en-dessous
            return ($shortSMA_previous > $longSMA_previous) && ($shortSMA_current < $longSMA_current);

        } catch (\Exception $e) {
            // En cas d'erreur, ne pas générer de signal de vente
            return false;
        }
    }

    public function getName(): string
    {
        return 'Moving Average Crossover';
    }

    public function getDescription(): string
    {
        return 'Stratégie basée sur le croisement de deux moyennes mobiles simples (SMA). ' .
            'Achète lors d\'un Golden Cross (SMA courte croise au-dessus de SMA longue) et ' .
            'vend lors d\'un Death Cross (SMA courte croise en-dessous de SMA longue).';
    }

    public function setParameters(array $params): void
    {
        foreach ($params as $key => $value) {
            if (array_key_exists($key, $this->params)) {
                $this->params[$key] = $value;
            }
        }
    }

    public function getParameters(): array
    {
        return $this->params;
    }

    public function getParameter(string $key, mixed $default = null): mixed
    {
        return $this->params[$key] ?? $default;
    }
    public function onSell(string $symbol, float $currentPrice): void
    {
    }

    public function onBuy(Position $position): void
    {
    }

    public function getInvestment(string $symbol, float $currentPrice): ?float
    {
        return null;
    }

    public function getMinimumKlines(): int
    {
        return $this->params['long_period'];
    }

    public function calculateStopLoss(string $symbol, float $currentPrice): ?float
    {
        return null;
    }

    public function onPreCycle(): void
    {
        // TODO: Implement onPreCycle() method.
    }

    public function onPostCycle(): void
    {
        // TODO: Implement onPostCycle() method.
    }
}
