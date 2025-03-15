<?php

namespace Kwizer15\TradingBot;

use Kwizer15\TradingBot\Strategy\StrategyInterface;

class TradingBot {
    private $binanceAPI;
    private $strategy;
    private $config;
    private $logger;
    private $positions = [];

    public function __construct(BinanceAPI $binanceAPI, StrategyInterface $strategy, array $config, $logger = null) {
        $this->binanceAPI = $binanceAPI;
        $this->strategy = $strategy;
        $this->config = $config;
        $this->logger = $logger;
    }

    /**
     * Lance le bot de trading
     */
    public function run() {
        $this->log('info', 'Démarrage du bot de trading avec la stratégie: ' . $this->strategy->getName());

        // Charger les positions actuelles
        $this->loadPositions();

        // Gérer les positions existantes (vérifier si on doit vendre)
        $this->managePositions();

        // Chercher de nouvelles opportunités d'achat
        $this->findBuyOpportunities();

        $this->log('info', 'Cycle de trading terminé');
    }

    /**
     * Charge les positions actuelles depuis un fichier
     */
    private function loadPositions() {
        $positionsFile = __DIR__ . '/../data/positions.json';

        if (file_exists($positionsFile)) {
            $this->positions = json_decode(file_get_contents($positionsFile), true);
            $this->log('info', 'Positions chargées: ' . count($this->positions));
        } else {
            $this->log('info', 'Aucune position existante trouvée');
        }
    }

    /**
     * Sauvegarde les positions actuelles dans un fichier
     */
    private function savePositions() {
        $positionsFile = __DIR__ . '/../data/positions.json';
        file_put_contents($positionsFile, json_encode($this->positions, JSON_PRETTY_PRINT));
    }

    /**
     * Gère les positions ouvertes (vérifie si on doit vendre)
     */
    private function managePositions() {
        foreach ($this->positions as $symbol => $position) {
            $this->log('info', "Vérification de la position: {$symbol}");

            try {
                // Obtenir les données récentes du marché
                $klines = $this->binanceAPI->getKlines($symbol, '1h', 100);

                // Obtenir le prix actuel
                $currentPrice = $this->binanceAPI->getCurrentPrice($symbol);

                if (!$currentPrice) {
                    $this->log('error', "Impossible d'obtenir le prix actuel pour {$symbol}");
                    continue;
                }

                // Mettre à jour la position avec le prix actuel
                $position['current_price'] = $currentPrice;
                $position['current_value'] = $position['quantity'] * $currentPrice;
                $position['profit_loss'] = $position['current_value'] - $position['cost'];
                $position['profit_loss_pct'] = ($position['profit_loss'] / $position['cost']) * 100;

                $this->positions[$symbol] = $position;

                // Vérifier le stop loss
                if ($position['profit_loss_pct'] <= -$this->config['trading']['stop_loss_percentage']) {
                    $this->log('info', "Stop loss déclenché pour {$symbol} (perte: {$position['profit_loss_pct']}%)");
                    $this->sell($symbol);
                    continue;
                }

                // Vérifier le take profit
                if ($position['profit_loss_pct'] >= $this->config['trading']['take_profit_percentage']) {
                    $this->log('info', "Take profit déclenché pour {$symbol} (gain: {$position['profit_loss_pct']}%)");
                    $this->sell($symbol);
                    continue;
                }

                // Vérifier le signal de vente de la stratégie
                if ($this->strategy->shouldSell($klines, $position)) {
                    $this->log('info', "Signal de vente détecté pour {$symbol}");
                    $this->sell($symbol);
                    continue;
                }

                $this->log('info', "Position maintenue pour {$symbol} (P/L: {$position['profit_loss_pct']}%)");

            } catch (\Exception $e) {
                $this->log('error', "Erreur lors de la gestion de la position {$symbol}: " . $e->getMessage());
            }
        }

        // Sauvegarder les positions mises à jour
        $this->savePositions();
    }

    /**
     * Cherche de nouvelles opportunités d'achat
     */
    private function findBuyOpportunities() {
        // Vérifier si nous avons déjà atteint le nombre maximum de positions
        if (count($this->positions) >= $this->config['trading']['max_open_positions']) {
            $this->log('info', 'Nombre maximum de positions atteint, aucun nouvel achat possible');
            return;
        }

        // Parcourir les symboles configurés
        foreach ($this->config['trading']['symbols'] as $symbol) {
            $pairSymbol = $symbol . $this->config['trading']['base_currency'];

            // Vérifier si nous avons déjà une position sur ce symbole
            if (isset($this->positions[$pairSymbol])) {
                continue;
            }

            try {
                // Obtenir les données récentes du marché
                $klines = $this->binanceAPI->getKlines($pairSymbol, '1h', 100);

                // Vérifier le signal d'achat
                if ($this->strategy->shouldBuy($klines)) {
                    $this->log('info', "Signal d'achat détecté pour {$pairSymbol}");
                    $this->buy($pairSymbol);

                    // Si nous avons atteint le nombre maximum de positions après cet achat, on arrête
                    if (count($this->positions) >= $this->config['trading']['max_open_positions']) {
                        break;
                    }
                }
            } catch (\Exception $e) {
                $this->log('error', "Erreur lors de la recherche d'opportunités pour {$pairSymbol}: " . $e->getMessage());
            }
        }
    }

    /**
     * Exécute un achat
     */
    private function buy($symbol) {
        try {
            // Vérifier le solde disponible
            $balance = $this->binanceAPI->getBalance($this->config['trading']['base_currency']);

            if ($balance['free'] < $this->config['trading']['investment_per_trade']) {
                $this->log('warning', "Solde insuffisant pour acheter {$symbol}");
                return false;
            }

            // Obtenir le prix actuel
            $currentPrice = $this->binanceAPI->getCurrentPrice($symbol);

            if (!$currentPrice) {
                $this->log('error', "Impossible d'obtenir le prix actuel pour {$symbol}");
                return false;
            }

            // Calculer la quantité à acheter
            $quantity = $this->config['trading']['investment_per_trade'] / $currentPrice;

            // Arrondir la quantité selon les règles de Binance (à adapter selon les paires)
            $quantity = floor($quantity * 100000) / 100000;

            // Exécuter l'ordre d'achat
            $order = $this->binanceAPI->buyMarket($symbol, $quantity);

            if (!$order || !isset($order['orderId'])) {
                $this->log('error', "Erreur lors de l'achat de {$symbol}: " . json_encode($order));
                return false;
            }

            // Enregistrer la position
            $this->positions[$symbol] = [
                'symbol' => $symbol,
                'entry_price' => $currentPrice,
                'quantity' => $quantity,
                'timestamp' => time() * 1000,
                'cost' => $this->config['trading']['investment_per_trade'],
                'current_price' => $currentPrice,
                'current_value' => $quantity * $currentPrice,
                'profit_loss' => 0,
                'profit_loss_pct' => 0,
                'order_id' => $order['orderId']
            ];

            // Sauvegarder les positions
            $this->savePositions();

            $this->log('info', "Achat réussi de {$quantity} {$symbol} au prix de {$currentPrice}");

            return true;

        } catch (\Exception $e) {
            $this->log('error', "Erreur lors de l'achat de {$symbol}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Exécute une vente
     */
    private function sell($symbol) {
        try {
            // Vérifier si nous avons cette position
            if (!isset($this->positions[$symbol])) {
                $this->log('warning', "Aucune position trouvée pour {$symbol}");
                return false;
            }

            $position = $this->positions[$symbol];

            // Exécuter l'ordre de vente
            $order = $this->binanceAPI->sellMarket($symbol, $position['quantity']);

            if (!$order || !isset($order['orderId'])) {
                $this->log('error', "Erreur lors de la vente de {$symbol}: " . json_encode($order));
                return false;
            }

            // Journaliser la vente
            $this->log('info', "Vente réussie de {$position['quantity']} {$symbol} au prix de {$position['current_price']} (P/L: {$position['profit_loss_pct']}%)");

            // Supprimer la position
            unset($this->positions[$symbol]);

            // Sauvegarder les positions
            $this->savePositions();

            return true;

        } catch (\Exception $e) {
            $this->log('error', "Erreur lors de la vente de {$symbol}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Journalise un message
     */
    private function log($level, $message) {
        if ($this->logger) {
            $this->logger->log($level, $message);
        } else {
            echo date('Y-m-d H:i:s') . " [{$level}] {$message}" . PHP_EOL;
        }
    }
}