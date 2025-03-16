<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Positions & Trades</h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <div class="btn-group me-2">
                <a href="index.php?page=positions" class="btn btn-sm btn-outline-secondary">
                    <i class="fas fa-sync-alt"></i> Actualiser
                </a>
            </div>
        </div>
    </div>

    <!-- Onglets -->
    <ul class="nav nav-tabs mb-4" id="positionsTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="open-positions-tab" data-bs-toggle="tab" data-bs-target="#open-positions" type="button" role="tab" aria-controls="open-positions" aria-selected="true">
                Positions ouvertes
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="trade-history-tab" data-bs-toggle="tab" data-bs-target="#trade-history" type="button" role="tab" aria-controls="trade-history" aria-selected="false">
                Historique des trades
            </button>
        </li>
    </ul>

    <!-- Contenu des onglets -->
    <div class="tab-content" id="positionsTabsContent">
        <!-- Positions ouvertes -->
        <div class="tab-pane fade show active" id="open-positions" role="tabpanel" aria-labelledby="open-positions-tab">
            <div class="card">
                <div class="card-body">
                    <?php
                    $positions = get_positions();

                    if (empty($positions)):
                        ?>
                        <div class="alert alert-info">Aucune position ouverte actuellement.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-positions">
                                <thead>
                                <tr>
                                    <th>Symbole</th>
                                    <th>Date d'entrée</th>
                                    <th>Prix d'entrée</th>
                                    <th>Prix actuel</th>
                                    <th>Quantité</th>
                                    <th>Coût</th>
                                    <th>Valeur actuelle</th>
                                    <th>P/L</th>
                                    <th>Actions</th>
                                    <th>Entrée initiale</th>
                                    <th>Entrées additionnelles</th>
                                    <th>Sorties partielles</th>
                                    <th>Stop-Loss actuel</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($positions as $symbol => $position): ?>
                                    <tr>
                                        <td><strong><?php echo $symbol; ?></strong></td>
                                        <td><?php echo date('d/m/Y H:i:s', $position['timestamp'] / 1000); ?></td>
                                        <td><?php echo format_number($position['entry_price'], 2); ?></td>
                                        <td><?php echo format_number($position['current_price'], 2); ?></td>
                                        <td><?php echo format_number($position['quantity'], 5); ?></td>
                                        <td><?php echo format_currency($position['cost']); ?></td>
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
                                        <td>
                                            <?php
                                            if (isset($position['strategy_data']) && $position['strategy_data']['initial_entry_price']) {
                                                echo format_currency($position['strategy_data']['initial_investment']);
                                            } else {
                                                echo format_currency($position['cost']);
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <?php
                                            if (isset($position['strategy_data']) && !empty($position['strategy_data']['additional_entries'])) {
                                                foreach ($position['strategy_data']['additional_entries'] as $entry) {
                                                    echo date('d/m H:i', $entry['timestamp'] / 1000) . ': ';
                                                    echo format_currency($entry['amount']) . '<br>';
                                                }
                                            } else {
                                                echo '-';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <?php
                                            if (isset($position['strategy_data']) && !empty($position['strategy_data']['partial_exits'])) {
                                                foreach ($position['strategy_data']['partial_exits'] as $exit) {
                                                    echo date('d/m H:i', $exit['timestamp'] / 1000) . ': ';
                                                    echo format_currency($exit['amount']) . '<br>';
                                                }
                                            } else {
                                                echo '-';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <?php
                                            if (isset($position['strategy_data']) && isset($position['strategy_data']['stop_loss_price'])) {
                                                echo format_number($position['strategy_data']['stop_loss_price'], 2);

                                                // Calculer le pourcentage par rapport au prix actuel
                                                $stopLossPercent = (($position['current_price'] - $position['strategy_data']['stop_loss_price']) / $position['current_price']) * 100;
                                                echo '<br><small>' . format_percent($stopLossPercent) . ' du prix actuel</small>';
                                            } else {
                                                echo '-';
                                            }
                                            ?>
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

        <!-- Historique des trades -->
        <div class="tab-pane fade" id="trade-history" role="tabpanel" aria-labelledby="trade-history-tab">
            <div class="card">
                <div class="card-body">
                    <?php
                    $trade_history = get_trade_history();

                    if (empty($trade_history)):
                        ?>
                        <div class="alert alert-info">Aucun trade dans l'historique.</div>
                    <?php else:
                        // Trier par date (plus récent en premier)
                        usort($trade_history, function($a, $b) {
                            return $b['exit_time'] - $a['exit_time'];
                        });
                        ?>
                        <div class="mb-3">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="input-group">
                                        <span class="input-group-text">Filtrer par symbole</span>
                                        <select id="symbol-filter" class="form-select">
                                            <option value="">Tous</option>
                                            <?php
                                            // Récupérer les symboles uniques
                                            $symbols = [];
                                            foreach ($trade_history as $trade) {
                                                $symbols[$trade['symbol']] = true;
                                            }

                                            foreach (array_keys($symbols) as $symbol):
                                                ?>
                                                <option value="<?php echo $symbol; ?>"><?php echo $symbol; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="input-group">
                                        <span class="input-group-text">Résultat</span>
                                        <select id="result-filter" class="form-select">
                                            <option value="">Tous</option>
                                            <option value="win">Gains</option>
                                            <option value="loss">Pertes</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table id="trade-history-table" class="table table-striped table-trades">
                                <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Symbole</th>
                                    <th>Type</th>
                                    <th>Prix entrée</th>
                                    <th>Prix sortie</th>
                                    <th>Quantité</th>
                                    <th>Coût</th>
                                    <th>Valeur vente</th>
                                    <th>P/L</th>
                                    <th>Durée</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($trade_history as $trade): ?>
                                    <tr data-symbol="<?php echo $trade['symbol']; ?>" data-result="<?php echo $trade['profit'] > 0 ? 'win' : 'loss'; ?>">
                                        <td>
                                            <strong><?php echo date('d/m/Y', $trade['exit_time'] / 1000); ?></strong>
                                            <br>
                                            <small class="text-muted">
                                                <?php echo date('H:i:s', $trade['exit_time'] / 1000); ?>
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
                                        <td><?php echo format_currency($trade['cost']); ?></td>
                                        <td><?php echo format_currency($trade['sale_value']); ?></td>
                                        <td class="<?php echo get_value_class($trade['profit']); ?>">
                                            <?php echo format_percent($trade['profit_pct']); ?>
                                            <br>
                                            <small><?php echo format_currency($trade['profit']); ?></small>
                                        </td>
                                        <td>
                                            <?php
                                            $hours = floor($trade['duration']);
                                            $minutes = round(($trade['duration'] - $hours) * 60);
                                            echo "{$hours}h {$minutes}m";
                                            ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Statistiques des trades -->
                        <div class="row mt-4">
                            <div class="col-md-12">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="card-title mb-0">Statistiques</h5>
                                    </div>
                                    <div class="card-body">
                                        <?php
                                        // Calculer les statistiques
                                        $total_trades = count($trade_history);
                                        $winning_trades = 0;
                                        $losing_trades = 0;
                                        $total_profit = 0;
                                        $total_loss = 0;
                                        $total_duration = 0;

                                        foreach ($trade_history as $trade) {
                                            if ($trade['profit'] > 0) {
                                                $winning_trades++;
                                                $total_profit += $trade['profit'];
                                            } else {
                                                $losing_trades++;
                                                $total_loss += abs($trade['profit']);
                                            }

                                            $total_duration += $trade['duration'];
                                        }

                                        $win_rate = $total_trades > 0 ? ($winning_trades / $total_trades) * 100 : 0;
                                        $profit_factor = $total_loss > 0 ? $total_profit / $total_loss : 0;
                                        $avg_duration = $total_trades > 0 ? $total_duration / $total_trades : 0;
                                        $net_profit = $total_profit - $total_loss;
                                        ?>

                                        <div class="row">
                                            <div class="col-md-3">
                                                <div class="card-title">Trades totaux</div>
                                                <div class="card-value"><?php echo $total_trades; ?></div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="card-title">Taux de réussite</div>
                                                <div class="card-value"><?php echo format_percent($win_rate); ?></div>
                                                <div class="small">
                                                    <?php echo $winning_trades; ?> gains / <?php echo $losing_trades; ?> pertes
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="card-title">Profit net</div>
                                                <div class="card-value <?php echo get_value_class($net_profit); ?>">
                                                    <?php echo format_currency($net_profit); ?>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="card-title">Facteur de profit</div>
                                                <div class="card-value"><?php echo format_number($profit_factor, 2); ?></div>
                                                <div class="small">Durée moyenne: <?php echo round($avg_duration, 1); ?>h</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
    $(document).ready(function() {
        // Gestion de la vente manuelle
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

        // Filtrage de la table d'historique
        $('#symbol-filter, #result-filter').on('change', function() {
            filterTradeHistory();
        });

        function filterTradeHistory() {
            const symbolFilter = $('#symbol-filter').val();
            const resultFilter = $('#result-filter').val();

            $('#trade-history-table tbody tr').each(function() {
                const $row = $(this);
                const rowSymbol = $row.data('symbol');
                const rowResult = $row.data('result');

                let showRow = true;

                if (symbolFilter && rowSymbol !== symbolFilter) {
                    showRow = false;
                }

                if (resultFilter && rowResult !== resultFilter) {
                    showRow = false;
                }

                $row.toggle(showRow);
            });
        }

        // Mémoriser l'onglet actif
        $('a[data-bs-toggle="tab"]').on('shown.bs.tab', function (e) {
            localStorage.setItem('activePositionsTab', $(e.target).attr('id'));
        });

        // Restaurer l'onglet actif
        const activeTab = localStorage.getItem('activePositionsTab');
        if (activeTab) {
            $('#' + activeTab).tab('show');
        }
    });
</script>