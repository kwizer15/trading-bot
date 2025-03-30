<?php

namespace Kwizer15\TradingBot;

use Kwizer15\TradingBot\Clock\RealClock;
use Kwizer15\TradingBot\Configuration\TradingConfiguration;
use Kwizer15\TradingBot\DTO\KlineHistory;
use Kwizer15\TradingBot\DTO\PositionList;
use Kwizer15\TradingBot\Strategy\StrategyInterface;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;

class TradingBot
{
    private PositionList $positionList;
    private array $trades;

    public function __construct(
        private readonly BinanceAPIInterface $binanceAPI,
        private readonly StrategyInterface $strategy,
        private readonly TradingConfiguration $tradingConfiguration,
        private readonly LoggerInterface $logger,
        private readonly ?string $positionsFile = null,
        private readonly ?string $tradesFile = null,
        ?ClockInterface $clock = null
    ) {
        $clock ??= new RealClock();
        $this->positionList = new PositionList($this->positionsFile, $this->logger, $clock);
        if (!is_readable($this->tradesFile)) {
            $this->trades = [];
        } else {
            $tradeList = file_get_contents($this->tradesFile);
            $this->trades = $tradeList ? json_decode($tradeList, true) : [];
        }
    }

    public function getPositions(): PositionList
    {
        return $this->positionList;
    }
    /**
     * Lance le bot de trading
     */
    public function run(): void
    {
        $this->strategy->onPreCycle();
        $this->logger->info('Démarrage du bot de trading avec la stratégie: ' . $this->strategy->getName());

        foreach ($this->tradingConfiguration->symbols as $symbol) {
            $pairSymbol = $symbol . $this->tradingConfiguration->baseCurrency;
            $this->binanceAPI->prepareKlines($pairSymbol, '1h', 100);
        }

        $this->managePositions();

        // Chercher de nouvelles opportunités d'achat
        $this->findBuyOpportunities();

        $this->strategy->onPostCycle();
        $this->logger->info('Cycle de trading terminé');
    }

    /**
     * Gère les positions ouvertes avec support pour actions spéciales
     * Cette méthode remplace ou complète la méthode managePositions() existante
     */
    private function managePositions(): void
    {
        foreach ($this->positionList->iterateSymbols() as $symbol) {
            $klines = $this->binanceAPI->getKlines($symbol, '1h', 100);
            $dtoKlines = KlineHistory::create($symbol, $klines);
            $this->managePosition($symbol, $dtoKlines->last()->close, $dtoKlines);
        }
    }

    private function managePosition(string $symbol, float $currentPrice, KlineHistory $dtoKlines): void
    {
        $this->logger->info("Vérification de la position: {$symbol}");

        try {
            $positionObject = $this->positionList->updatePosition($symbol, $currentPrice, $this->strategy->calculateStopLoss($symbol, $currentPrice));

            // Vérifier le stop loss général
            if ($this->positionList->isStopLossTriggered($symbol, $this->tradingConfiguration->stopLossPercentage)) {
                $this->logger->notice("Stop loss déclenché pour {$symbol} (perte: {$positionObject->profit_loss_pct}%)");
                $this->sell($symbol);
                return;
            }

            // Vérifier le take profit général
            if ($this->positionList->isTakeProfitTriggered($symbol, $this->tradingConfiguration->takeProfitPercentage)) {
                $this->logger->notice("Take profit déclenché pour {$symbol} (gain: {$positionObject->profit_loss_pct}%)");
                $this->sell($symbol);
                return;
            }

            if ($this->strategy->shouldSell($dtoKlines, $positionObject)) {
                $this->logger->info("Signal de vente détecté pour {$symbol}");
                $this->sell($symbol);
                return;
            }

            $this->logger->info("Position maintenue pour {$symbol} (P/L: {$positionObject->profit_loss_pct}%)");
        } catch (\Exception $e) {
            $this->logger->error("Erreur lors de la gestion de la position {$symbol}: " . $e->getMessage());
        }
    }

    /**
     * Cherche de nouvelles opportunités d'achat
     */
    private function findBuyOpportunities(): void
    {
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
                    $this->logger->notice("Signal d'achat détecté pour {$pairSymbol}");
                    $this->buy($pairSymbol, $this->strategy->getInvestment($pairSymbol, $history->last()->close));

                    // Si nous avons atteint le nombre maximum de positions après cet achat, on arrête
                    if ($this->positionList->count() >= $this->tradingConfiguration->maxOpenPositions) {
                        break;
                    }
                }
            } catch (\Exception $e) {
                $this->logger->error("Erreur lors de la recherche d'opportunités pour {$pairSymbol}: " . $e->getMessage());
            }
        }
    }

    /**
     * Exécute un achat
     */
    private function buy(string $symbol, float $investment = null): void
    {
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
                $this->strategy->calculateStopLoss($symbol, $currentPrice)
            );
            $this->strategy->onBuy($position);
        } catch (\Exception $e) {
            $this->logger->error("Erreur lors de l'achat de {$symbol}: " . $e->getMessage());
        }
    }

    public function closePosition(string $symbol): void
    {
        $this->strategy->onPreCycle();
        $this->sell($symbol);
        $this->strategy->onPostCycle();
    }

    /**
     * Exécute une vente
     */
    private function sell($symbol): void
    {
        try {
            // Vérifier si nous avons cette position
            if (!$this->positionList->hasPositionForSymbol($symbol)) {
                $this->logger->warning("Aucune position trouvée pour {$symbol}");
                return;
            }

            $quantityToSell = $this->positionList->getPositionQuantity($symbol);

            // Exécuter l'ordre de vente
            $order = $this->binanceAPI->sellMarket($symbol, $quantityToSell);

            $positionObject = $this->positionList->getPositionForSymbol($symbol);
            $saleValue = $order->price * $order->quantity;
            $totalFee = $order->fee + $positionObject->total_buy_fees + $positionObject->total_sell_fees;
            $profit = $saleValue - $positionObject->cost - $totalFee;
            $profitPct = ($profit / $positionObject->cost) * 100;

            $this->trades[] = [
                'symbol' => $symbol,
                'entry_price' => $positionObject->entry_price,
                'exit_price' => $order->price,
                'entry_time' => $positionObject->timestamp,
                'exit_time' => $order->timestamp,
                'quantity' => $order->quantity,
                'cost' => $positionObject->cost,
                'sale_value' => $saleValue,
                'fees' => $totalFee,
                'profit' => $profit,
                'profit_pct' => $profitPct,
                'duration' => ($order->timestamp - $positionObject->timestamp) / (60 * 60 * 1000) // Durée en heures
            ];

            $encodedTrades = json_encode($this->trades, JSON_PRETTY_PRINT);
            $writeFileSuccess = file_put_contents($this->tradesFile, $encodedTrades);
            if (false === $writeFileSuccess) {
                $this->logger->error("Impossible de sauvegarder les trades.");
            } else {
                $this->logger->info("Trades sauvegardées.");
            }

            $this->positionList->sell($symbol);
            $this->strategy->onSell($symbol, $order->price);

            return;

        } catch (\Exception $e) {
            $this->logger->error("Erreur lors de la vente de {$symbol}: " . $e->getMessage());
            return;
        }
    }

    public function getTrades(): array
    {
        return $this->trades;
    }

}
