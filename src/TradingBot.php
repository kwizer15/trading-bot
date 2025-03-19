<?php

namespace Kwizer15\TradingBot;

use Kwizer15\TradingBot\Configuration\TradingConfiguration;
use Kwizer15\TradingBot\DTO\KlineHistory;
use Kwizer15\TradingBot\DTO\PositionList;
use Kwizer15\TradingBot\Strategy\PositionAction;
use Kwizer15\TradingBot\Strategy\PositionActionStrategyInterface;
use Kwizer15\TradingBot\Strategy\StrategyInterface;
use Psr\Log\LoggerInterface;

class TradingBot {
    private PositionList $positionList;

    public function __construct(
        private readonly BinanceAPI $binanceAPI,
        private readonly StrategyInterface $strategy,
        private readonly TradingConfiguration $tradingConfiguration,
        private readonly LoggerInterface $logger,
        private readonly ?string $positionsFile = null,
    ) {
        $this->positionList = new PositionList($this->positionsFile, $this->logger);
    }

    /**
     * Lance le bot de trading
     */
    public function run(): void {
        $this->logger->info('Démarrage du bot de trading avec la stratégie: ' . $this->strategy->getName());

        $this->managePositions();

        // Chercher de nouvelles opportunités d'achat
        $this->findBuyOpportunities();

        $this->logger->info('Cycle de trading terminé');
    }

    /**
     * Augmente une position existante
     * @param string $symbol Symbole de la paire
     * @param float $additionalInvestment Montant supplémentaire à investir
     */
    public function increasePosition(string $symbol, float $additionalInvestment): void {
        try {
            // Vérifier si nous avons cette position
            if (!$this->positionList->hasPositionForSymbol($symbol)) {
                $this->logger->warning("Impossible d'augmenter la position, aucune position trouvée pour {$symbol}");
                return;
            }

            // Vérifier le solde disponible
            $balance = $this->binanceAPI->getBalance($this->tradingConfiguration->baseCurrency);

            if ($balance->free < $additionalInvestment) {
                $this->logger->warning("Solde insuffisant pour augmenter la position {$symbol}");
                return;
            }

            // Obtenir le prix actuel
            $currentPrice = $this->binanceAPI->getCurrentPrice($symbol);

            if (!$currentPrice) {
                $this->logger->error("Impossible d'obtenir le prix actuel pour {$symbol}");
                return;
            }

            // Calculer la quantité à acheter
            $additionalQuantity = $additionalInvestment / $currentPrice;

            // Arrondir la quantité selon les règles de Binance
            $additionalQuantity = floor($additionalQuantity * 100000) / 100000;

            // Exécuter l'ordre d'achat
            $order = $this->binanceAPI->buyMarket($symbol, $additionalQuantity);

            if (!$order || !isset($order['orderId'])) {
                $this->logger->error("Erreur lors de l'augmentation de la position {$symbol}: " . json_encode($order));
                return;
            }

            $position = $this->positionList->increasePosition($symbol, $currentPrice, $additionalQuantity, $additionalInvestment);
            $this->strategy->onIncreasePosition($position, $additionalQuantity, $additionalInvestment);
        } catch (\Exception $e) {
            $this->logger->error( "Erreur lors de l'augmentation de la position {$symbol}: " . $e->getMessage());
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
            if (!$this->positionList->hasPositionForSymbol($symbol)) {
                $this->logger->warning( "Impossible de réaliser une sortie partielle, aucune position trouvée pour {$symbol}");
                return false;
            }

            $quantity = $this->positionList->getPositionQuantity($symbol);

            // Calculer la quantité à vendre
            $quantityToSell = $quantity * ($exitPercentage / 100);

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

            $position = $this->positionList->partialExit($symbol, $quantityToSell);
            $this->strategy->onPartialExit($position, $quantityToSell);

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
        foreach ($this->positionList->iterateSymbols() as $symbol) {
            $this->managePosition($symbol);
        }
    }

    private function managePosition(string $symbol): void
    {
        $this->logger->info("Vérification de la position: {$symbol}");

        try {
            // Obtenir le prix actuel
            $currentPrice = $this->binanceAPI->getCurrentPrice($symbol);

            if (!$currentPrice) {
                $this->logger->error("Impossible d'obtenir le prix actuel pour {$symbol}");
                return;
            }

            $position = $this->positionList->updatePosition($symbol, $currentPrice);

            // Vérifier le stop loss général
            if ($this->positionList->isStopLossTriggered($symbol, $this->tradingConfiguration->stopLossPercentage)) {
                $this->logger->info( "Stop loss déclenché pour {$symbol} (perte: {$position['profit_loss_pct']}%)");
                $this->sell($symbol);
                return;
            }

            // Vérifier le take profit général
            if ($this->positionList->isTakeProfitTriggered($symbol, $this->tradingConfiguration->takeProfitPercentage)) {
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
                        $this->increasePosition($symbol, $additionalInvestment);
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
        $baseAmount = $this->tradingConfiguration->investmentPerTrade;

        return $baseAmount * ($percentIncrease / 100);
    }

    /**
     * Cherche de nouvelles opportunités d'achat
     */
    private function findBuyOpportunities() {
        // Vérifier si nous avons déjà atteint le nombre maximum de positions
        if ($this->positionList->count() >= $this->tradingConfiguration->maxOpenPositions) {
            $this->logger->info( 'Nombre maximum de positions atteint, aucun nouvel achat possible');
            return;
        }

        // Parcourir les symboles configurés
        foreach ($this->tradingConfiguration->symbols as $symbol) {
            $pairSymbol = $symbol . $this->tradingConfiguration->baseCurrency;

            // Vérifier si nous avons déjà une position sur ce symbole
            if ($this->positionList->hasPositionForSymbol($pairSymbol)) {
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
                    if ($this->positionList->count() >= $this->tradingConfiguration->maxOpenPositions) {
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
            $balance = $this->binanceAPI->getBalance($this->tradingConfiguration->baseCurrency);

            $cost = $this->tradingConfiguration->investmentPerTrade;
            if ($balance->free < $cost) {
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
            $quantity = $cost / $currentPrice;

            // Arrondir la quantité selon les règles de Binance (à adapter selon les paires)
            $quantity = floor($quantity * 100000) / 100000;

            // Exécuter l'ordre d'achat
            $order = $this->binanceAPI->buyMarket($symbol, $quantity);

            if (!$order || !isset($order['orderId'])) {
                $this->logger->error("Erreur lors de l'achat de {$symbol}: " . json_encode($order));
                return;
            }

            $position = $this->positionList->buy(
                $symbol,
                $currentPrice,
                $quantity,
                $cost,
                $order['orderId']
            );
            $this->strategy->onBuy($position);
        } catch (\Exception $e) {
            $this->logger->error("Erreur lors de l'achat de {$symbol}: " . $e->getMessage());
        }
    }

    /**
     * Exécute une vente
     */
    private function sell($symbol): void {
        try {
            // Vérifier si nous avons cette position
            if (!$this->positionList->hasPositionForSymbol($symbol)) {
                $this->logger->warning( "Aucune position trouvée pour {$symbol}");
                return;
            }

            $quantityToSell = $this->positionList->getPositionQuantity($symbol);

            // Exécuter l'ordre de vente
            $order = $this->binanceAPI->sellMarket($symbol, $quantityToSell);

            if (!$order || !isset($order['orderId'])) {
                $this->logger->error( "Erreur lors de la vente de {$symbol}: " . json_encode($order));
                return;
            }

            $this->positionList->sell($symbol);
            $this->strategy->onSell($symbol, $order['avgPrice']);

            return;

        } catch (\Exception $e) {
            $this->logger->error( "Erreur lors de la vente de {$symbol}: " . $e->getMessage());
            return;
        }
    }
}