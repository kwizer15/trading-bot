<?php

namespace Kwizer15\TradingBot\Backtest;

use Kwizer15\TradingBot\Strategy\StrategyInterface;

class BacktestEngine {
    private $strategy;
    private $data;
    private $config;
    private $symbol;
    private $balance;
    private $positions = [];
    private $trades = [];
    private $equity = [];
    private $fees = 0.1; // 0.1% de frais par transaction
    public function __construct(StrategyInterface $strategy, array $data, array $config, string $symbol = 'BTCUSDT') {
        $this->strategy = $strategy;
        $this->data = $data;
        $this->config = $config;
        $this->balance = $config['backtest']['initial_balance'];
        $this->symbol = $symbol;
    }

    /**
     * Simule un achat pendant le backtest
     */
    private function buy($symbol, $price, $timestamp) {
        $investment = $this->config['trading']['investment_per_trade'];

        // Vérifier si nous avons assez de fonds
        if ($this->balance < $investment) {
            return false;
        }

        // Calculer les frais
        $fee = ($investment * $this->fees) / 100;

        // Calculer la quantité achetée (après frais)
        $quantity = ($investment - $fee) / $price;

        // Mettre à jour le solde
        $this->balance -= $investment;

        // Enregistrer la position
        $this->positions[$symbol] = [
            'symbol' => $symbol,
            'entry_price' => $price,
            'quantity' => $quantity,
            'timestamp' => $timestamp,
            'cost' => $investment,
            'current_value' => $quantity * $price,
            'profit_loss' => -$fee,
            'profit_loss_pct' => -($fee / $investment) * 100
        ];

        return true;
    }

    /**
     * Simule une vente pendant le backtest
     */
    private function sell($symbol, $price, $timestamp) {
        // Vérifier si nous avons cette position
        if (!isset($this->positions[$symbol])) {
            return false;
        }

        $position = $this->positions[$symbol];

        // Calculer la valeur de la vente
        $saleValue = $position['quantity'] * $price;

        // Calculer les frais
        $fee = ($saleValue * $this->fees) / 100;

        // Calculer le profit/perte
        $profit = $saleValue - $position['cost'] - $fee;
        $profitPct = ($profit / $position['cost']) * 100;

        // Mettre à jour le solde
        $this->balance += $saleValue - $fee;

        // Enregistrer le trade
        $this->trades[] = [
            'symbol' => $symbol,
            'entry_price' => $position['entry_price'],
            'exit_price' => $price,
            'entry_time' => $position['timestamp'],
            'exit_time' => $timestamp,
            'quantity' => $position['quantity'],
            'cost' => $position['cost'],
            'sale_value' => $saleValue,
            'fees' => $fee + (($position['cost'] * $this->fees) / 100),
            'profit' => $profit,
            'profit_pct' => $profitPct,
            'duration' => ($timestamp - $position['timestamp']) / (60 * 60 * 1000) // Durée en heures
        ];

        // Supprimer la position
        unset($this->positions[$symbol]);

        return true;
    }

    public function run() {
        $initialBalance = $this->balance;
        $startTime = microtime(true);

        $this->equity[] = [
            'timestamp' => $this->data[0][0], // Timestamp de la première bougie
            'equity' => $this->balance
        ];

        // Parcourir les données historiques
        for ($i = max($this->strategy->getParameters()['long_period'] ?? 0, $this->strategy->getParameters()['period'] ?? 0) + 1; $i < count($this->data); $i++) {
            $currentData = array_slice($this->data, 0, $i + 1);
            $currentPrice = floatval($this->data[$i][4]); // Prix de clôture
            $timestamp = $this->data[$i][0]; // Timestamp

            // Mettre à jour la valeur des positions ouvertes
            foreach ($this->positions as $symbol => $position) {
                $position['current_price'] = $currentPrice; // Ajout de current_price
                $position['current_value'] = $position['quantity'] * $currentPrice;
                $position['profit_loss'] = $position['current_value'] - $position['cost'];
                $position['profit_loss_pct'] = ($position['profit_loss'] / $position['cost']) * 100;
                $this->positions[$symbol] = $position;
            }

            // Vérifier les signaux de vente pour les positions ouvertes
            foreach ($this->positions as $symbol => $position) {
                // Vérifier si la stratégie supporte getPositionAction
                if (method_exists($this->strategy, 'getPositionAction')) {
                    $action = $this->strategy->getPositionAction($currentData, $position);

                    switch ($action) {
                        case 'SELL':
                            $this->sell($symbol, $currentPrice, $timestamp);
                            break;

                        case 'INCREASE_POSITION':
                            // Simuler une augmentation de position
                            $additionalInvestment = min(
                                $this->config['trading']['investment_per_trade'] * 0.5,
                                $this->balance
                            );

                            if ($additionalInvestment > 0) {
                                // Calculer les frais
                                $fee = ($additionalInvestment * $this->fees) / 100;

                                // Calculer la quantité additionnelle
                                $additionalQuantity = ($additionalInvestment - $fee) / $currentPrice;

                                // Mettre à jour le solde
                                $this->balance -= $additionalInvestment;

                                // Mettre à jour la position
                                $newQuantity = $position['quantity'] + $additionalQuantity;
                                $newCost = $position['cost'] + $additionalInvestment;
                                $newEntryPrice = $newCost / $newQuantity;

                                $this->positions[$symbol]['quantity'] = $newQuantity;
                                $this->positions[$symbol]['cost'] = $newCost;
                                $this->positions[$symbol]['entry_price'] = $newEntryPrice;
                                $this->positions[$symbol]['current_value'] = $newQuantity * $currentPrice;
                                $this->positions[$symbol]['profit_loss'] = ($newQuantity * $currentPrice) - $newCost;
                                $this->positions[$symbol]['profit_loss_pct'] = (($newQuantity * $currentPrice) - $newCost) / $newCost * 100;
                            }
                            break;

                        case 'PARTIAL_EXIT':
                            // Simuler une sortie partielle
                            $exitPercentage = 30; // Pourcentage par défaut
                            $position = $this->positions[$symbol];

                            // Calculer la quantité à vendre
                            $quantityToSell = $position['quantity'] * ($exitPercentage / 100);

                            // Calculer la valeur de la vente partielle
                            $partialSaleValue = $quantityToSell * $currentPrice;

                            // Calculer les frais
                            $fee = ($partialSaleValue * $this->fees) / 100;

                            // Mettre à jour le solde
                            $this->balance += $partialSaleValue - $fee;

                            // Mettre à jour la position
                            $remainingQuantity = $position['quantity'] - $quantityToSell;
                            $remainingCost = $position['cost'] * ($remainingQuantity / $position['quantity']);

                            if ($remainingQuantity > 0) {
                                $this->positions[$symbol]['quantity'] = $remainingQuantity;
                                $this->positions[$symbol]['cost'] = $remainingCost;
                                $this->positions[$symbol]['current_value'] = $remainingQuantity * $currentPrice;
                                $this->positions[$symbol]['profit_loss'] = ($remainingQuantity * $currentPrice) - $remainingCost;
                                $this->positions[$symbol]['profit_loss_pct'] = (($remainingQuantity * $currentPrice) - $remainingCost) / $remainingCost * 100;
                            } else {
                                // Si toute la position est vendue, c'est équivalent à un SELL
                                $this->sell($symbol, $currentPrice, $timestamp);
                            }
                            break;

                        case 'HOLD':
                        default:
                            // Ne rien faire
                            break;
                    }
                } else {
                    // Stratégie classique avec shouldSell
                    if ($this->strategy->shouldSell($currentData, $position)) {
                        $this->sell($symbol, $currentPrice, $timestamp);
                    }
                }
            }

            // Vérifier le signal d'achat si nous avons des fonds disponibles
            if ($this->balance > $this->config['trading']['investment_per_trade'] &&
                count($this->positions) < ($this->config['trading']['max_open_positions'] ?? 1)) {
                if ($this->strategy->shouldBuy($currentData)) {
                    $this->buy($this->symbol, $currentPrice, $timestamp);
                }
            }

            // Enregistrer l'équité à chaque étape
            $totalEquity = $this->balance;
            foreach ($this->positions as $position) {
                $totalEquity += $position['current_value'];
            }

            $this->equity[] = [
                'timestamp' => $timestamp,
                'equity' => $totalEquity
            ];
        }

        // Clôturer toutes les positions à la fin du backtest
        $lastPrice = floatval($this->data[count($this->data) - 1][4]);
        $lastTimestamp = $this->data[count($this->data) - 1][0];

        foreach ($this->positions as $symbol => $position) {
            $this->sell($symbol, $lastPrice, $lastTimestamp);
        }

        $endTime = microtime(true);
        $duration = $endTime - $startTime;

        // Calculer les statistiques de performance
        $finalBalance = $this->balance;
        $totalProfit = $finalBalance - $initialBalance;
        $totalProfitPct = ($totalProfit / $initialBalance) * 100;
        $totalTrades = count($this->trades);

        $winningTrades = array_filter($this->trades, function($trade) {
            return $trade['profit'] > 0;
        });

        $losingTrades = array_filter($this->trades, function($trade) {
            return $trade['profit'] <= 0;
        });

        $winRate = $totalTrades > 0 ? (count($winningTrades) / $totalTrades) * 100 : 0;

        $profitFactor = 0;
        $totalGain = 0;
        $totalLoss = 0;

        foreach ($winningTrades as $trade) {
            $totalGain += $trade['profit'];
        }

        foreach ($losingTrades as $trade) {
            $totalLoss += abs($trade['profit']);
        }

        if ($totalLoss > 0) {
            $profitFactor = $totalGain / $totalLoss;
        }

        // Calculer le drawdown maximum
        $maxEquity = $initialBalance;
        $maxDrawdown = 0;
        $drawdownStart = null;
        $drawdownEnd = null;

        foreach ($this->equity as $equityPoint) {
            $currentEquity = $equityPoint['equity'];

            if ($currentEquity > $maxEquity) {
                $maxEquity = $currentEquity;
            }

            $drawdown = (($maxEquity - $currentEquity) / $maxEquity) * 100;

            if ($drawdown > $maxDrawdown) {
                $maxDrawdown = $drawdown;
                $drawdownEnd = $equityPoint['timestamp'];
            }
        }

        // Calculer les frais payés
        $feesPaid = 0;
        foreach ($this->trades as $trade) {
            $feesPaid += isset($trade['fees']) ? $trade['fees'] : 0;
        }

        // Résultats du backtest
        return [
            'strategy' => $this->strategy->getName(),
            'parameters' => $this->strategy->getParameters(),
            'initial_balance' => $initialBalance,
            'final_balance' => $finalBalance,
            'profit' => $totalProfit,
            'profit_pct' => $totalProfitPct,
            'total_trades' => $totalTrades,
            'winning_trades' => count($winningTrades),
            'losing_trades' => count($losingTrades),
            'win_rate' => $winRate,
            'profit_factor' => $profitFactor,
            'max_drawdown' => $maxDrawdown,
            'fees_paid' => $feesPaid,
            'duration' => $duration,
            'trades' => $this->trades,
            'equity_curve' => $this->equity
        ];
    }
}