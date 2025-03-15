<?php

namespace Kwizer15\TradingBot\Strategy;

class MovingAverageStrategy implements StrategyInterface {
    private $params = [
        'short_period' => 9,   // Période courte (en bougies)
        'long_period' => 21,   // Période longue (en bougies)
        'price_index' => 4,    // Indice du prix de clôture dans les klines (4 = close)
    ];

    /**
     * Calcule la moyenne mobile simple pour une période donnée
     */
    private function calculateSMA(array $data, int $period, int $priceIndex): float {
        $count = count($data);

        if ($count < $period) {
            throw new \Exception("Pas assez de données pour calculer la SMA de période {$period}");
        }

        $sum = 0;

        // Prendre les dernières bougies selon la période
        for ($i = $count - $period; $i < $count; $i++) {
            $sum += floatval($data[$i][$priceIndex]);
        }

        return $sum / $period;
    }

    /**
     * Vérifie si un croisement haussier (Golden Cross) s'est produit
     * (SMA courte croise au-dessus de SMA longue)
     */
    public function shouldBuy(array $marketData): bool {
        try {
            // Calculer la SMA courte et longue actuelles
            $shortSMA_current = $this->calculateSMA($marketData, $this->params['short_period'], $this->params['price_index']);
            $longSMA_current = $this->calculateSMA($marketData, $this->params['long_period'], $this->params['price_index']);

            // Si nous n'avons pas assez de données pour les calculs précédents, on ne peut pas détecter un croisement
            if (count($marketData) <= $this->params['long_period']) {
                return false;
            }

            // Créer un ensemble de données sans la dernière bougie pour calculer les SMA précédentes
            $previousData = array_slice($marketData, 0, -1);

            // Calculer la SMA courte et longue précédentes
            $shortSMA_previous = $this->calculateSMA($previousData, $this->params['short_period'], $this->params['price_index']);
            $longSMA_previous = $this->calculateSMA($previousData, $this->params['long_period'], $this->params['price_index']);

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
     */
    public function shouldSell(array $marketData, array $position): bool {
        try {
            // Calculer la SMA courte et longue actuelles
            $shortSMA_current = $this->calculateSMA($marketData, $this->params['short_period'], $this->params['price_index']);
            $longSMA_current = $this->calculateSMA($marketData, $this->params['long_period'], $this->params['price_index']);

            // Si nous n'avons pas assez de données pour les calculs précédents, on ne peut pas détecter un croisement
            if (count($marketData) <= $this->params['long_period']) {
                return false;
            }

            // Créer un ensemble de données sans la dernière bougie pour calculer les SMA précédentes
            $previousData = array_slice($marketData, 0, -1);

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

    public function getName(): string {
        return 'Moving Average Crossover';
    }

    public function getDescription(): string {
        return 'Stratégie basée sur le croisement de deux moyennes mobiles simples (SMA). ' .
            'Achète lors d\'un Golden Cross (SMA courte croise au-dessus de SMA longue) et ' .
            'vend lors d\'un Death Cross (SMA courte croise en-dessous de SMA longue).';
    }

    public function setParameters(array $params): void {
        foreach ($params as $key => $value) {
            if (array_key_exists($key, $this->params)) {
                $this->params[$key] = $value;
            }
        }
    }

    public function getParameters(): array {
        return $this->params;
    }
}
