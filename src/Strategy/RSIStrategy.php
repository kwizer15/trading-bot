<?php

namespace Kwizer15\TradingBot\Strategy;

use Kwizer15\TradingBot\DTO\KlineHistory;

class RSIStrategy implements StrategyInterface {
    private $params = [
        'period' => 14,           // Période pour le calcul du RSI
        'overbought' => 70,       // Niveau de surachat
        'oversold' => 30,         // Niveau de survente
        'price_index' => 4,       // Indice du prix de clôture dans les klines (4 = close)
    ];

    /**
     * Calcule l'indice de force relative (RSI)
     */
    private function calculateRSI(KlineHistory $history, int $period, int $priceIndex): float {
        $count = $history->count();

        $priceIndexMap = [
            1 => 'open',
            2 => 'high',
            3 => 'low',
            4 => 'close',
        ];

        if ($count < $period + 1) {
            throw new \Exception("Pas assez de données pour calculer le RSI de période {$period}");
        }

        $gains = 0;
        $losses = 0;

        $priceName = $priceIndexMap[$priceIndex];

        // Calculer les gains et pertes sur la période
        for ($i = $count - $period; $i < $count; $i++) {
            $currentPrice = $history->get($i)->$priceName;
            $previousPrice = $history->get($i - 1)->$priceName;
            $change = $currentPrice - $previousPrice;

            if ($change >= 0) {
                $gains += $change;
            } else {
                $losses -= $change;  // Convertir en valeur positive
            }
        }

        // Éviter la division par zéro
        if ($losses == 0) {
            return 100;
        }

        // Moyenne des gains et des pertes
        $avgGain = $gains / $period;
        $avgLoss = $losses / $period;

        // Calculer le RSI
        $rs = $avgGain / $avgLoss;
        $rsi = 100 - (100 / (1 + $rs));

        return $rsi;
    }

    public function shouldBuy(KlineHistory $history, string $currentSymbol): bool {
        try {
            // Calculer le RSI actuel
            $currentRSI = $this->calculateRSI($history, $this->params['period'], $this->params['price_index']);

            // Si nous n'avons pas assez de données pour le calcul précédent, on ne peut pas détecter un croisement
            if ($history->count() <= $this->params['period'] + 1) {
                return false;
            }

            // Créer un ensemble de données sans la dernière bougie pour calculer le RSI précédent
            $dtoPreviousData = $history->slice(-1);

            // Calculer le RSI précédent
            $previousRSI = $this->calculateRSI($dtoPreviousData, $this->params['period'], $this->params['price_index']);

            // Signal d'achat : RSI était en dessous du niveau de survente et remonte maintenant
            return ($previousRSI < $this->params['oversold']) && ($currentRSI > $this->params['oversold']);

        } catch (\Exception $e) {
            // En cas d'erreur, ne pas générer de signal d'achat
            return false;
        }
    }

    public function shouldSell(KlineHistory $history, array $position): bool {
        try {
            // Calculer le RSI actuel
            $currentRSI = $this->calculateRSI($history, $this->params['period'], $this->params['price_index']);

            // Si nous n'avons pas assez de données pour le calcul précédent, on ne peut pas détecter un croisement
            if ($history->count() <= $this->params['period'] + 1) {
                return false;
            }

            // Créer un ensemble de données sans la dernière bougie pour calculer le RSI précédent
            $dtoPreviousData = $history->slice(-1);

            // Calculer le RSI précédent
            $previousRSI = $this->calculateRSI($dtoPreviousData, $this->params['period'], $this->params['price_index']);

            // Signal de vente : RSI était au-dessus du niveau de surachat et redescend maintenant
            return ($previousRSI > $this->params['overbought']) && ($currentRSI < $this->params['overbought']);

        } catch (\Exception $e) {
            // En cas d'erreur, ne pas générer de signal de vente
            return false;
        }
    }

    public function getName(): string {
        return 'RSI Strategy';
    }

    public function getDescription(): string {
        return 'Stratégie basée sur l\'indice de force relative (RSI). ' .
            'Achète lorsque le RSI sort d\'une zone de survente et ' .
            'vend lorsque le RSI sort d\'une zone de surachat.';
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

    public function onSell(string $symbol, float $currentPrice): void
    {
    }

    public function onBuy(array $position): void
    {
    }
}
