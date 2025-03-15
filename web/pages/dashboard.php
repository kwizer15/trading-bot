<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Tableau de bord</h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <div class="btn-group me-2">
                <a href="index.php?page=dashboard" class="btn btn-sm btn-outline-secondary">
                    <i class="fas fa-sync-alt"></i> Actualiser
                </a>
            </div>
        </div>
    </div>

    <!-- Cartes d'informations -->
    <div class="row mb-4">
        <?php
        // Calculer les statistiques
        use Kwizer15\TradingBot\BinanceAPI;

        $positions = get_positions();
        $trade_history = get_trade_history();

        // Solde total
        $balance = 0;
        $positions_value = 0;

        // Récupérer le solde depuis Binance si possible
        try {
            $config = get_config();
            $binanceAPI = new BinanceAPI($config);

            $base_currency = $config['trading']['base_currency'];
            $balance_data = $binanceAPI->getBalance($base_currency);
            $balance = $balance_data['free'] + $balance_data['locked'];
        } catch (Exception $e) {
            // Utiliser une valeur par défaut
            $balance = $config['backtest']['initial_balance'];
        }

        // Calculer la valeur des positions ouvertes
        foreach ($positions as $position) {
            $positions_value += $position['current_value'];
        }

        $total_equity = $balance + $positions_value;

        // Calculer le profit total des trades
        $total_profit = 0;
        $winning_trades = 0;
        $losing_trades = 0;

        foreach ($trade_history as $trade) {
            $total_profit += $trade['profit'];

            if ($trade['profit'] > 0) {
                $winning_trades++;
            } elseif ($trade['profit'] < 0) {
                $losing_trades++;
            }
        }

        $total_trades = count($trade_history);
        $win_rate = $total_trades > 0 ? ($winning_trades / $total_trades) * 100 : 0;
        ?>

        <!-- Solde -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card card-stats h-100">
                <div class="card-body">
                    <div class="row">
                        <div class="col-4 text-center">
                            <div class="card-icon">
                                <i class="fas fa-wallet"></i>
                            </div>
                        </div>
                        <div class="col-8">
                            <div class="card-title">Solde</div>
                            <div class="card-value"><?php echo format_currency($balance); ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Équité totale -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card card-stats h-100 <?php echo $total_equity > $config['backtest']['initial_balance'] ? 'card-profit' : 'card-loss'; ?>">
                <div class="card-body">
                    <div class="row">
                        <div class="col-4 text-center">
                            <div class="card-icon">
                                <i class="fas fa-chart-line"></i>
                            </div>
                        </div>
                        <div class="col-8">
                            <div class="card-title">Équité totale</div>
                            <div class="card-value"><?php echo format_currency($total_equity); ?></div>
                            <div class="small <?php echo get_value_class($total_equity - $config['backtest']['initial_balance']); ?>">
                                <?php
                                $profit_pct = (($total_equity / $config['backtest']['initial_balance']) - 1) * 100;
                                echo ($total_equity >= $config['backtest']['initial_balance'] ? '+' : '') . format_percent($profit_pct);
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Positions ouvertes -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card card-stats h-100">
                <div class="card-body">
                    <div class="row">
                        <div class="col-4 text-center">
                            <div class="card-icon">
                                <i class="fas fa-briefcase"></i>
                            </div>
                        </div>
                        <div class="col-8">
                            <div class="card-title">Positions ouvertes</div>
                            <div class="card-value"><?php echo count($positions); ?></div>
                            <div class="small">Valeur: <?php echo format_currency($positions_value); ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Performance -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card card-stats h-100 <?php echo $win_rate >= 50 ? 'card-profit' : 'card-loss'; ?>">
                <div class="card-body">
                    <div class="row">
                        <div class="col-4 text-center">
                            <div class="card-icon">
                                <i class="fas fa-trophy"></i>
                            </div>
                        </div>
                        <div class="col-8">
                            <div class="card-title">Performance</div>
                            <div class="card-value"><?php echo format_percent($win_rate); ?></div>
                            <div class="small">
                                <?php echo $winning_trades; ?> gains / <?php echo $losing_trades; ?> pertes
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Graphique d'équité -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Évolution de l'équité</h5>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="equity-chart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Positions ouvertes -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Positions ouvertes</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($positions)): ?>
                        <div class="alert alert-info">Aucune position ouverte actuellement.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-positions">
                                <thead>
                                <tr>
                                    <th>Symbole</th>
                                    <th>Prix d'entrée</th>
                                    <th>Prix actuel</th>
                                    <th>Quantité</th>
                                    <th>Valeur</th>
                                    <th>P/L</th>
                                    <th>Actions</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($positions as $symbol => $position): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo $symbol; ?></strong>
                                            <br>
                                            <small class="text-muted">
                                                <?php echo date('d/m/Y H:i', $position['timestamp'] / 1000); ?>
                                            </small>
                                        </td>
                                        <td><?php echo format_number($position['entry_price'], 2); ?></td>
                                        <td><?php echo format_number($position['current_price'], 2); ?></td>
                                        <td><?php echo format_number($position['quantity'], 5); ?></td>
                                        <td><?php echo format_currency($position['current_value']); ?></td>
                                        <td class="<?php echo get_value_class($position['profit_loss_pct']); ?>">
                                            <?php echo format_percent($position['profit_loss_pct']); ?>
                                            <br>
                                            <small><?php echo format_currency($position['profit_loss']); ?></small>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-danger sell-btn" data-symbol="<?php echo $symbol; ?>">
                                                Vendre
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Derniers trades -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Derniers trades</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($trade_history)): ?>
                        <div class="alert alert-info">Aucun trade effectué.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-trades">
                                <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Symbole</th>
                                    <th>Type</th>
                                    <th>Prix entrée</th>
                                    <th>Prix sortie</th>
                                    <th>Quantité</th>
                                    <th>P/L</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php
                                // Récupérer les 10 derniers trades
                                $recent_trades = array_slice($trade_history, -10);
                                // Trier par date (plus récent en premier)
                                usort($recent_trades, function($a, $b) {
                                    return $b['exit_time'] - $a['exit_time'];
                                });

                                foreach ($recent_trades as $trade):
                                    ?>
                                    <tr>
                                        <td>
                                            <?php echo date('d/m/Y H:i', $trade['exit_time'] / 1000); ?>
                                            <br>
                                            <small class="text-muted">
                                                Durée: <?php echo round($trade['duration'], 1); ?>h
                                            </small>
                                        </td>
                                        <td><?php echo $trade['symbol']; ?></td>
                                        <td>
                                            <?php if ($trade['profit'] > 0): ?>
                                                <span class="badge bg-success">GAIN</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">PERTE</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo format_number($trade['entry_price'], 2); ?></td>
                                        <td><?php echo format_number($trade['exit_price'], 2); ?></td>
                                        <td><?php echo format_number($trade['quantity'], 5); ?></td>
                                        <td class="<?php echo get_value_class($trade['profit']); ?>">
                                            <?php echo format_percent($trade['profit_pct']); ?>
                                            <br>
                                            <small><?php echo format_currency($trade['profit']); ?></small>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <?php if (count($trade_history) > 10): ?>
                            <div class="text-center mt-3">
                                <a href="index.php?page=positions" class="btn btn-sm btn-outline-primary">
                                    Voir tous les trades (<?php echo count($trade_history); ?>)
                                </a>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
    // Gestion de la vente manuelle
    $(document).ready(function() {
        $('.sell-btn').on('click', function() {
            if (confirm('Êtes-vous sûr de vouloir vendre cette position ?')) {
                const symbol = $(this).data('symbol');

                $.ajax({
                    url: 'api.php',
                    data: {
                        action: 'sell_position',
                        symbol: symbol
                    },
                    method: 'POST',
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            alert('Position vendue avec succès');
                            location.reload();
                        } else {
                            alert('Erreur: ' + response.message);
                        }
                    },
                    error: function() {
                        alert('Erreur de connexion');
                    }
                });
            }
        });
    });
</script>