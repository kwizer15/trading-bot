<?php

namespace Kwizer15\TradingBot;

use Kwizer15\TradingBot\DTO\KlineHistory;
use Kwizer15\TradingBot\Strategy\PositionAction;
use Kwizer15\TradingBot\Strategy\PositionActionStrategyInterface;
use Kwizer15\TradingBot\Strategy\StrategyInterface;
use Psr\Log\LoggerInterface;

class TradingBot {
    private array $positions = [];

    public function __construct(
        private readonly BinanceAPI $binanceAPI,
        private readonly StrategyInterface $strategy,
        private readonly array $config,
        private readonly LoggerInterface $logger,
        private readonly ?string $positionsFile = null,
    ) {
    }

    /**
     * Lance le bot de trading
     */
    public function run(): void {
        $this->logger->info('Démarrage du bot de trading avec la stratégie: ' . $this->strategy->getName());

        $this->loadPositions();

        $this->managePositions();

        // Chercher de nouvelles opportunités d'achat
        $this->findBuyOpportunities();

        $this->logger->info('Cycle de trading terminé');
    }

    /**
     * Augmente une position existante
     * @param string $symbol Symbole de la paire
     * @param float $additionalInvestment Montant supplémentaire à investir
     * @return bool Succès de l'opération
     */
    public function increasPosition(string $symbol, float $additionalInvestment) {
        try {
            // Vérifier si nous avons cette position
            if (!isset($this->positions[$symbol])) {
                $this->logger->warning("Impossible d'augmenter la position, aucune position trouvée pour {$symbol}");
                return false;
            }

            $position = $this->positions[$symbol];

            // Vérifier le solde disponible
            $balance = $this->binanceAPI->getBalance($this->config['trading']['base_currency']);

            if ($balance['free'] < $additionalInvestment) {
                $this->logger->warning("Solde insuffisant pour augmenter la position {$symbol}");
                return false;
            }

            // Obtenir le prix actuel
            $currentPrice = $this->binanceAPI->getCurrentPrice($symbol);

            if (!$currentPrice) {
                $this->logger->error("Impossible d'obtenir le prix actuel pour {$symbol}");
                return false;
            }

            // Calculer la quantité à acheter
            $additionalQuantity = $additionalInvestment / $currentPrice;

            // Arrondir la quantité selon les règles de Binance
            $additionalQuantity = floor($additionalQuantity * 100000) / 100000;

            // Exécuter l'ordre d'achat
            $order = $this->binanceAPI->buyMarket($symbol, $additionalQuantity);

            if (!$order || !isset($order['orderId'])) {
                $this->logger->error("Erreur lors de l'augmentation de la position {$symbol}: " . json_encode($order));
                return false;
            }

            // Mettre à jour la position
            $newQuantity = $position['quantity'] + $additionalQuantity;
            $newCost = $position['cost'] + $additionalInvestment;
            $newEntryPrice = $newCost / $newQuantity;

            $this->positions[$symbol] = [
                'symbol' => $symbol,
                'entry_price' => $newEntryPrice,
                'quantity' => $newQuantity,
                'timestamp' => $position['timestamp'],
                'cost' => $newCost,
                'current_price' => $currentPrice,
                'current_value' => $newQuantity * $currentPrice,
                'profit_loss' => ($newQuantity * $currentPrice) - $newCost,
                'profit_loss_pct' => ((($newQuantity * $currentPrice) - $newCost) / $newCost) * 100,
                'order_id' => $position['order_id']
            ];

            // Sauvegarder les positions
            $this->savePositions();

            $this->logger->info("Position augmentée pour {$symbol}: +{$additionalQuantity} au prix de {$currentPrice}");

            return true;

        } catch (\Exception $e) {
            $this->logger->error( "Erreur lors de l'augmentation de la position {$symbol}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Exécute une sortie partielle de position
     * @param string $symbol Symbole de la paire
     * @param float $exitPercentage Pourcentage de la position à vendre
     * @return bool Succès de l'opération
     */
    public function partialExit(string $symbol, float $exitPercentage): bool {
        try {
            // Vérifier si nous avons cette position
            if (!isset($this->positions[$symbol])) {
                $this->logger->warning( "Impossible de réaliser une sortie partielle, aucune position trouvée pour {$symbol}");
                return false;
            }

            $position = $this->positions[$symbol];

            // Calculer la quantité à vendre
            $quantityToSell = $position['quantity'] * ($exitPercentage / 100);

            // Arrondir la quantité selon les règles de Binance
            $quantityToSell = floor($quantityToSell * 100000) / 100000;

            // Vérifier que la quantité à vendre est suffisante
            if ($quantityToSell <= 0) {
                $this->logger->warning( "Quantité de sortie trop faible pour {$symbol}");
                return false;
            }

            // Exécuter l'ordre de vente partielle
            $order = $this->binanceAPI->sellMarket($symbol, $quantityToSell);

            if (!$order || !isset($order['orderId'])) {
                $this->logger->error( "Erreur lors de la vente partielle de {$symbol}: " . json_encode($order));
                return false;
            }

            // Mettre à jour la position
            $remainingQuantity = $position['quantity'] - $quantityToSell;
            $soldValue = $quantityToSell * $position['current_price'];
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

            // Sauvegarder les positions
            $this->savePositions();

            $this->logger->info( "Sortie partielle réussie pour {$symbol}: {$quantityToSell} vendus au prix de {$position['current_price']}");

            return true;

        } catch (\Exception $e) {
            $this->logger->error( "Erreur lors de la sortie partielle de {$symbol}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Gère les positions ouvertes avec support pour actions spéciales
     * Cette méthode remplace ou complète la méthode managePositions() existante
     */
    private function managePositions(): void {
        foreach ($this->positions as $symbol => $position) {
            $this->managePosition($symbol, $position);
        }

        // Sauvegarder les positions mises à jour
        $this->savePositions();
    }

    private function managePosition(string $symbol, array $position): void
    {
        $this->logger->info("Vérification de la position: {$symbol}");

        try {
            // Obtenir le prix actuel
            $currentPrice = $this->binanceAPI->getCurrentPrice($symbol);

            if (!$currentPrice) {
                $this->logger->error("Impossible d'obtenir le prix actuel pour {$symbol}");
                return;
            }

            // Mettre à jour la position avec le prix actuel
            $position['current_price'] = $currentPrice;
            $position['current_value'] = $position['quantity'] * $currentPrice;
            $position['profit_loss'] = $position['current_value'] - $position['cost'];
            $position['profit_loss_pct'] = ($position['profit_loss'] / $position['cost']) * 100;

            $this->positions[$symbol] = $position;

            // Vérifier le stop loss général
            if ($position['profit_loss_pct'] <= -$this->config['trading']['stop_loss_percentage']) {
                $this->logger->info( "Stop loss déclenché pour {$symbol} (perte: {$position['profit_loss_pct']}%)");
                $this->sell($symbol);
                return;
            }

            // Vérifier le take profit général
            if ($position['profit_loss_pct'] >= $this->config['trading']['take_profit_percentage']) {
                $this->logger->info( "Take profit déclenché pour {$symbol} (gain: {$position['profit_loss_pct']}%)");
                $this->sell($symbol);
                return;
            }

            // Obtenir les données récentes du marché
            $klines = $this->binanceAPI->getKlines($symbol, '1h', 100);
            $dtoKlines = KlineHistory::create($klines);

            // Vérifier si la stratégie supporte les actions spéciales
            if ($this->strategy instanceof PositionActionStrategyInterface) {
                $action = $this->strategy->getPositionAction($dtoKlines, $position);

                switch ($action) {
                    case PositionAction::SELL:
                        $this->logger->info( "Signal de vente détecté pour {$symbol}");
                        $this->sell($symbol);
                        break;

                    case PositionAction::INCREASE_POSITION:
                        $percentIncrease = $this->strategy->calculateIncreasePercentage($dtoKlines, $position);
                        $additionalInvestment = $this->calculateAdditionalInvestment($percentIncrease);
                        $this->logger->info( "Signal d'augmentation de position pour {$symbol}");
                        $this->increasPosition($symbol, $additionalInvestment);
                        break;

                    case PositionAction::PARTIAL_EXIT:
                        $exitPercentage = $this->strategy->calculateExitPercentage($dtoKlines, $position);
                        $this->logger->info( "Signal de sortie partielle pour {$symbol}");
                        $this->partialExit($symbol, $exitPercentage);
                        break;

                    case PositionAction::HOLD:
                    default:
                        $this->logger->info( "Position maintenue pour {$symbol} (P/L: {$position['profit_loss_pct']}%)");
                        break;
                }
                return;
            }
                // Utiliser l'approche classique shouldSell
            if ($this->strategy->shouldSell($dtoKlines, $position)) {
                $this->logger->info( "Signal de vente détecté pour {$symbol}");
                $this->sell($symbol);
                return;
            }

            $this->logger->info( "Position maintenue pour {$symbol} (P/L: {$position['profit_loss_pct']}%)");
        } catch (\Exception $e) {
            $this->logger->error( "Erreur lors de la gestion de la position {$symbol}: " . $e->getMessage());
        }
    }

    /**
     * Calcule le montant supplémentaire à investir pour une position existante
     * @param float $percentIncrease Pourcentage supplémentaire à investir
     * @return float Montant à investir
     */
    private function calculateAdditionalInvestment(float $percentIncrease = 50.0): float
    {
        // Par défaut, on augmente de 50% de l'investissement initial
        $baseAmount = $this->config['trading']['investment_per_trade'];

        return $baseAmount * ($percentIncrease / 100);
    }

    /**
     * Charge les positions actuelles depuis un fichier
     */
    private function loadPositions(): void{
        if (file_exists($this->positionsFile)) {
            $this->positions = json_decode(file_get_contents($this->positionsFile), true);
            $this->logger->info( 'Positions chargées: ' . count($this->positions));
        } else {
            $this->logger->info( 'Aucune position existante trouvée');
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
     * Cherche de nouvelles opportunités d'achat
     */
    private function findBuyOpportunities() {
        // Vérifier si nous avons déjà atteint le nombre maximum de positions
        if (count($this->positions) >= $this->config['trading']['max_open_positions']) {
            $this->logger->info( 'Nombre maximum de positions atteint, aucun nouvel achat possible');
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
                $history = KlineHistory::create($klines);

                // Vérifier le signal d'achat
                if ($this->strategy->shouldBuy($history, $symbol)) {
                    $this->logger->info( "Signal d'achat détecté pour {$pairSymbol}");
                    $this->buy($pairSymbol);

                    // Si nous avons atteint le nombre maximum de positions après cet achat, on arrête
                    if (count($this->positions) >= $this->config['trading']['max_open_positions']) {
                        break;
                    }
                }
            } catch (\Exception $e) {
                $this->logger->error( "Erreur lors de la recherche d'opportunités pour {$pairSymbol}: " . $e->getMessage());
            }
        }
    }

    /**
     * Exécute un achat
     */
    private function buy($symbol): void {
        try {
            // Vérifier le solde disponible
            $balance = $this->binanceAPI->getBalance($this->config['trading']['base_currency']);

            if ($balance['free'] < $this->config['trading']['investment_per_trade']) {
                $this->logger->warning("Solde insuffisant pour acheter {$symbol}");
                return;
            }

            // Obtenir le prix actuel
            $currentPrice = $this->binanceAPI->getCurrentPrice($symbol);

            if (!$currentPrice) {
                $this->logger->error("Impossible d'obtenir le prix actuel pour {$symbol}");
                return;
            }

            // Calculer la quantité à acheter
            $quantity = $this->config['trading']['investment_per_trade'] / $currentPrice;

            // Arrondir la quantité selon les règles de Binance (à adapter selon les paires)
            $quantity = floor($quantity * 100000) / 100000;

            // Exécuter l'ordre d'achat
            $order = $this->binanceAPI->buyMarket($symbol, $quantity);

            if (!$order || !isset($order['orderId'])) {
                $this->logger->error("Erreur lors de l'achat de {$symbol}: " . json_encode($order));
                return;
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

            $this->logger->info("Achat réussi de {$quantity} {$symbol} au prix de {$currentPrice}");
        } catch (\Exception $e) {
            $this->logger->error("Erreur lors de l'achat de {$symbol}: " . $e->getMessage());
        }
    }

    /**
     * Exécute une vente
     */
    private function sell($symbol) {
        try {
            // Vérifier si nous avons cette position
            if (!isset($this->positions[$symbol])) {
                $this->logger->warning( "Aucune position trouvée pour {$symbol}");
                return false;
            }

            $position = $this->positions[$symbol];

            // Exécuter l'ordre de vente
            $order = $this->binanceAPI->sellMarket($symbol, $position['quantity']);

            if (!$order || !isset($order['orderId'])) {
                $this->logger->error( "Erreur lors de la vente de {$symbol}: " . json_encode($order));
                return false;
            }

            // Journaliser la vente
            $this->logger->info( "Vente réussie de {$position['quantity']} {$symbol} au prix de {$position['current_price']} (P/L: {$position['profit_loss_pct']}%)");

            // Supprimer la position
            unset($this->positions[$symbol]);

            // Sauvegarder les positions
            $this->savePositions();

            return true;

        } catch (\Exception $e) {
            $this->logger->error( "Erreur lors de la vente de {$symbol}: " . $e->getMessage());
            return false;
        }
    }
}