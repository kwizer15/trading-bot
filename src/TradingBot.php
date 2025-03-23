<?php

namespace Kwizer15\TradingBot;

use Kwizer15\TradingBot\Clock\RealClock;
use Kwizer15\TradingBot\Configuration\TradingConfiguration;
use Kwizer15\TradingBot\DTO\KlineHistory;
use Kwizer15\TradingBot\DTO\PositionList;
use Kwizer15\TradingBot\Strategy\PositionAction;
use Kwizer15\TradingBot\Strategy\PositionActionStrategyInterface;
use Kwizer15\TradingBot\Strategy\StrategyInterface;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;

class TradingBot {
    private PositionList $positionList;
    private ClockInterface $clock;

    private array $trades = [];

    public function __construct(
        private readonly BinanceAPIInterface $binanceAPI,
        private readonly StrategyInterface $strategy,
        private readonly TradingConfiguration $tradingConfiguration,
        private readonly LoggerInterface $logger,
        private readonly ?string $positionsFile = null,
        ?ClockInterface $clock = null
    ) {
        $this->clock = $clock ?? new RealClock();
        $this->positionList = new PositionList($this->positionsFile, $this->logger, $this->clock);
    }

    public function getPositions(): PositionList
    {
        return $this->positionList;
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

            $currentPrice = $this->binanceAPI->getCurrentPrice($symbol);
            $additionalQuantity = $additionalInvestment / $currentPrice;
            $order = $this->binanceAPI->buyMarket($symbol, $additionalQuantity);

            $position = $this->positionList->increasePosition($symbol, $order);
            $this->strategy->onIncreasePosition($position, $order);
            $this->logger->notice("Position {$symbol} augmentée de {$additionalQuantity} {$symbol}");
        } catch (\Exception $e) {
            $this->logger->error( "Erreur lors de l'augmentation de la position {$symbol}: " . $e->getMessage());
        }
    }

    /**
     * Exécute une sortie partielle de position
     *
     * @param string $symbol Symbole de la paire
     * @param float $exitPercentage Pourcentage de la position à vendre
     *
     * @return bool Succès de l'opération
     */
    public function partialExit(string $symbol, float $exitPercentage): void {
        try {
            // Vérifier si nous avons cette position
            if (!$this->positionList->hasPositionForSymbol($symbol)) {
                $this->logger->warning( "Impossible de réaliser une sortie partielle, aucune position trouvée pour {$symbol}");
                return;
            }

            $quantity = $this->positionList->getPositionQuantity($symbol);

            // Calculer la quantité à vendre
            $quantityToSell = $quantity * ($exitPercentage / 100);

            // Arrondir la quantité selon les règles de Binance
            $quantityToSell = floor($quantityToSell * 100000) / 100000;

            // Vérifier que la quantité à vendre est suffisante
            if ($quantityToSell <= 0) {
                $this->logger->warning( "Quantité de sortie trop faible pour {$symbol}");
                return;
            }

            // Exécuter l'ordre de vente partielle
            $order = $this->binanceAPI->sellMarket($symbol, $quantityToSell);

            $position = $this->positionList->partialExit($symbol, $order->quantity, $order->fee);
            $this->strategy->onPartialExit($position, $order);
            $this->logger->notice("Sortie partielle de {$symbol} de {$order->quantity} {$symbol}");
        } catch (\Exception $e) {
            $this->logger->error( "Erreur lors de la sortie partielle de {$symbol}: " . $e->getMessage());
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
            $position = $this->positionList->updatePosition($symbol, $currentPrice);

            // Vérifier le stop loss général
            if ($this->positionList->isStopLossTriggered($symbol, $this->tradingConfiguration->stopLossPercentage)) {
                $this->logger->notice( "Stop loss déclenché pour {$symbol} (perte: {$position['profit_loss_pct']}%)");
                $this->sell($symbol);
                return;
            }

            // Vérifier le take profit général
            if ($this->positionList->isTakeProfitTriggered($symbol, $this->tradingConfiguration->takeProfitPercentage)) {
                $this->logger->notice( "Take profit déclenché pour {$symbol} (gain: {$position['profit_loss_pct']}%)");
                $this->sell($symbol);
                return;
            }

            // Obtenir les données récentes du marché
            $klines = $this->binanceAPI->getKlines($symbol, '1h', 100);
            $dtoKlines = KlineHistory::create($symbol, $klines);

            // Vérifier si la stratégie supporte les actions spéciales
            if ($this->strategy instanceof PositionActionStrategyInterface) {
                $action = $this->strategy->getPositionAction($dtoKlines, $position);

                switch ($action) {
                    case PositionAction::SELL:
                        $this->logger->notice( "Signal de vente détecté pour {$symbol}");
                        $this->sell($symbol);
                        break;

                    case PositionAction::INCREASE_POSITION:
                        $percentIncrease = $this->strategy->calculateIncreasePercentage($dtoKlines, $position);
                        $additionalInvestment = $this->calculateAdditionalInvestment($percentIncrease);
                        $this->logger->notice( "Signal d'augmentation de position pour {$symbol}");
                        $this->increasePosition($symbol, $additionalInvestment);
                        break;

                    case PositionAction::PARTIAL_EXIT:
                        $exitPercentage = $this->strategy->calculateExitPercentage($dtoKlines, $position);
                        $this->logger->notice( "Signal de sortie partielle pour {$symbol}");
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
    private function findBuyOpportunities(): void {
        // Vérifier si nous avons déjà atteint le nombre maximum de positions
        if ($this->positionList->count() >= $this->tradingConfiguration->maxOpenPositions) {
            $this->logger->info('Nombre maximum de positions atteint, aucun nouvel achat possible');
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
                $history = KlineHistory::create($pairSymbol, $klines);

                // Vérifier le signal d'achat
                if ($this->strategy->shouldBuy($history, $pairSymbol)) {
                    $this->logger->notice( "Signal d'achat détecté pour {$pairSymbol}");
                    $this->buy($pairSymbol, $this->strategy->getInvestment($pairSymbol, $history->last()->close));

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
    private function buy(string $symbol, float $investment = null): void {
        try {
            // Vérifier le solde disponible
            $balance = $this->binanceAPI->getBalance($this->tradingConfiguration->baseCurrency);

            $cost = $investment ?? $this->tradingConfiguration->investmentPerTrade;
            if ($balance->free < $cost) {
                $this->logger->warning("Solde insuffisant pour acheter {$symbol}");
                return;
            }

            $currentPrice = $this->binanceAPI->getCurrentPrice($symbol);
            $quantity = $cost / $currentPrice;
            $order = $this->binanceAPI->buyMarket($symbol, $quantity);

            $position = $this->positionList->buy(
                $symbol,
                $currentPrice,
                $quantity,
                $cost,
                $order->orderId,
                $order->fee,
            );
            $this->strategy->onBuy($position);
        } catch (\Exception $e) {
            $this->logger->error("Erreur lors de l'achat de {$symbol}: " . $e->getMessage());
        }
    }

    public function closePosition(string $symbol): void {
        $this->sell($symbol);
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

            $position = $this->positionList->getPositionForSymbol($symbol);
            $saleValue = $order->price * $order->quantity;
            $totalFee = $order->fee + $position['total_buy_fees'] + $position['total_sell_fees'];
            $profit = $saleValue - $position['cost'] - $totalFee;
            $profitPct = ($profit / $position['cost']) * 100;

            $this->trades[] = [
                'symbol' => $symbol,
                'entry_price' => $position['entry_price'],
                'exit_price' => $order->price,
                'entry_time' => $position['timestamp'],
                'exit_time' => $order->timestamp,
                'quantity' => $order->quantity,
                'cost' => $position['cost'],
                'sale_value' => $saleValue,
                'fees' => $totalFee,
                'profit' => $profit,
                'profit_pct' => $profitPct,
                'duration' => ($order->timestamp - $position['timestamp']) / (60 * 60 * 1000) // Durée en heures
            ];

            $this->positionList->sell($symbol);
            $this->strategy->onSell($symbol, $order->price);

            return;

        } catch (\Exception $e) {
            $this->logger->error( "Erreur lors de la vente de {$symbol}: " . $e->getMessage());
            return;
        }
    }

    public function getTrades(): array {
        return $this->trades;
    }
}