
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Backtesting</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <a href="index.php?page=backtest" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-sync-alt"></i> Actualiser
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Onglets -->
                <ul class="nav nav-tabs mb-4" id="backtestTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="run-backtest-tab" data-bs-toggle="tab" data-bs-target="#run-backtest" type="button" role="tab" aria-controls="run-backtest" aria-selected="true">
                            Exécuter un backtest
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="results-tab" data-bs-toggle="tab" data-bs-target="#results" type="button" role="tab" aria-controls="results" aria-selected="false">
                            Résultats
                        </button>
                    </li>
                </ul>

                <!-- Contenu des onglets -->
                <div class="tab-content" id="backtestTabsContent">
                    <!-- Exécuter un backtest -->
                    <div class="tab-pane fade show active" id="run-backtest" role="tabpanel" aria-labelledby="run-backtest-tab">
                        <div class="card">
                            <div class="card-body">
                                <form id="backtest-form" method="post">
                                    <input type="hidden" name="action" value="run_backtest">

                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label for="strategy" class="form-label">Stratégie</label>
                                            <select class="form-select" id="strategy" name="strategy" required>
                                                <option value="">Sélectionnez une stratégie</option>
                                                <option value="MovingAverageStrategy">Moving Average Crossover</option>
                                                <option value="RSIStrategy">RSI Strategy</option>
                                                <option value="DynamicPositionStrategy">Dynamic Position Strategy</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="symbol" class="form-label">Symbole</label>
                                            <select class="form-select" id="symbol" name="symbol" required>
                                                <?php
                                                $symbols = get_config('trading')['symbols'];
                                                $base = get_config('trading')['base_currency'];

                                                foreach ($symbols as $symbol) {
                                                    $pair = $symbol . $base;
                                                    echo "<option value=\"{$symbol}\">{$pair}</option>";
                                                }
                                                ?>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label for="period-start" class="form-label">Période</label>
                                            <div class="input-group">
                                                <input type="date" class="form-control" id="period-start" name="period_start"
                                                       value="<?php echo date('Y-m-d', strtotime('-1 year')); ?>">
                                                <span class="input-group-text">à</span>
                                                <input type="date" class="form-control" id="period-end" name="period_end"
                                                       value="<?php echo date('Y-m-d'); ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="initial-balance" class="form-label">Balance initiale</label>
                                            <div class="input-group">
                                                <input type="number" class="form-control" id="initial-balance" name="initial_balance"
                                                       value="<?php echo get_config('backtest')['initial_balance']; ?>" min="100" step="100">
                                                <span class="input-group-text"><?php echo get_config('trading')['base_currency']; ?></span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Paramètres de stratégie (dynamique) -->
                                    <div id="strategy-params">
                                        <div class="alert alert-info">
                                            Sélectionnez une stratégie pour configurer ses paramètres.
                                        </div>
                                    </div>

                                    <hr>

                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label for="investment-per-trade" class="form-label">Investissement par trade</label>
                                            <div class="input-group">
                                                <input type="number" class="form-control" id="investment-per-trade" name="param_investment_per_trade"
                                                       value="<?php echo get_config('trading')['investment_per_trade']; ?>" min="10" step="10">
                                                <span class="input-group-text"><?php echo get_config('trading')['base_currency']; ?></span>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <label for="stop-loss" class="form-label">Stop Loss</label>
                                                    <div class="input-group">
                                                        <input type="number" class="form-control" id="stop-loss" name="param_stop_loss_percentage"
                                                               value="<?php echo get_config('trading')['stop_loss_percentage']; ?>" min="0.1" step="0.1">
                                                        <span class="input-group-text">%</span>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <label for="take-profit" class="form-label">Take Profit</label>
                                                    <div class="input-group">
                                                        <input type="number" class="form-control" id="take-profit" name="param_take_profit_percentage"
                                                               value="<?php echo get_config('trading')['take_profit_percentage']; ?>" min="0.1" step="0.1">
                                                        <span class="input-group-text">%</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mb-3 form-check">
                                        <input type="checkbox" class="form-check-input" id="download-data" name="download_data" value="1">
                                        <label class="form-check-label" for="download-data">
                                            Télécharger de nouvelles données historiques
                                        </label>
                                        <div class="form-text">
                                            Cochez cette option si vous souhaitez télécharger de nouvelles données historiques depuis Binance.
                                        </div>
                                    </div>

                                    <div class="d-grid">
                                        <button type="submit" class="btn btn-primary">Exécuter le backtest</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Résultats -->
                    <div class="tab-pane fade" id="results" role="tabpanel" aria-labelledby="results-tab">
                        <div class="card">
                            <div class="card-body">
                                <?php
                                $backtest_results = get_backtest_results();

                                if (empty($backtest_results)):
                                    ?>
                                    <div class="alert alert-info">
                                        Aucun résultat de backtest disponible. Exécutez un backtest pour voir les résultats.
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-striped table-hover">
                                            <thead>
                                            <tr>
                                                <th>Stratégie</th>
                                                <th>Paramètres</th>
                                                <th>Profit</th>
                                                <th>Trades</th>
                                                <th>Win Rate</th>
                                                <th>Drawdown Max</th>
                                                <th>Actions</th>
                                            </tr>
                                            </thead>
                                            <tbody>
                                            <?php foreach ($backtest_results as $result): ?>
                                                <tr>
                                                    <td><?php echo $result['strategy']; ?></td>
                                                    <td>
                                                        <small>
                                                            <?php
                                                            $params = [];
                                                            foreach ($result['parameters'] as $key => $value) {
                                                                if (is_array($value)) {
                                                                    $value = json_encode($value);
                                                                }
                                                                $params[] = "{$key}: {$value}";
                                                            }
                                                            echo implode('<br>', $params);
                                                            ?>
                                                        </small>
                                                    </td>
                                                    <td class="<?php echo get_value_class($result['profit']); ?>">
                                                        <?php echo format_percent($result['profit_pct']); ?>
                                                        <br>
                                                        <small><?php echo format_currency($result['profit']); ?></small>
                                                    </td>
                                                    <td>
                                                        <?php echo $result['total_trades']; ?>
                                                        <br>
                                                        <small><?php echo $result['winning_trades']; ?> / <?php echo $result['losing_trades']; ?></small>
                                                    </td>
                                                    <td><?php echo format_percent($result['win_rate']); ?></td>
                                                    <td><?php echo format_percent($result['max_drawdown']); ?></td>
                                                    <td>
                                                        <button class="btn btn-sm btn-primary view-result-btn"
                                                                data-bs-toggle="modal" data-bs-target="#resultModal"
                                                                data-result="<?php echo htmlspecialchars(json_encode($result), ENT_QUOTES, 'UTF-8'); ?>">
                                                            Détails
                                                        </button>
                                                        <button class="btn btn-sm btn-success use-strategy-btn"
                                                                data-strategy="<?php echo $result['strategy']; ?>"
                                                                data-params="<?php echo htmlspecialchars(json_encode($result['parameters']), ENT_QUOTES, 'UTF-8'); ?>">
                                                            Utiliser
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

                <!-- Modal pour les détails des résultats -->
                <div class="modal fade" id="resultModal" tabindex="-1" aria-labelledby="resultModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-xl">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="resultModalLabel">Détails du backtest</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <div class="row mb-4">
                                    <div class="col-md-6">
                                        <div class="card">
                                            <div class="card-header">
                                                <h5 class="card-title mb-0">Informations</h5>
                                            </div>
                                            <div class="card-body">
                                                <table class="table table-sm">
                                                    <tbody>
                                                    <tr>
                                                        <th>Stratégie:</th>
                                                        <td id="result-strategy"></td>
                                                    </tr>
                                                    <tr>
                                                        <th>Balance initiale:</th>
                                                        <td id="result-initial-balance"></td>
                                                    </tr>
                                                    <tr>
                                                        <th>Balance finale:</th>
                                                        <td id="result-final-balance"></td>
                                                    </tr>
                                                    <tr>
                                                        <th>Profit:</th>
                                                        <td id="result-profit"></td>
                                                    </tr>
                                                    <tr>
                                                        <th>Trades:</th>
                                                        <td id="result-trades"></td>
                                                    </tr>
                                                    <tr>
                                                        <th>Win Rate:</th>
                                                        <td id="result-win-rate"></td>
                                                    </tr>
                                                    <tr>
                                                        <th>Facteur de profit:</th>
                                                        <td id="result-profit-factor"></td>
                                                    </tr>
                                                    <tr>
                                                        <th>Drawdown maximum:</th>
                                                        <td id="result-max-drawdown"></td>
                                                    </tr>
                                                    <tr>
                                                        <th>Frais payés:</th>
                                                        <td id="result-fees"></td>
                                                    </tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="card">
                                            <div class="card-header">
                                                <h5 class="card-title mb-0">Paramètres</h5>
                                            </div>
                                            <div class="card-body">
                                                <table class="table table-sm" id="result-parameters-table">
                                                    <tbody>
                                                    <!-- Les paramètres seront ajoutés ici dynamiquement -->
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="row mb-4">
                                    <div class="col-md-12">
                                        <div class="card">
                                            <div class="card-header">
                                                <h5 class="card-title mb-0">Courbe d'équité</h5>
                                            </div>
                                            <div class="card-body">
                                                <div class="chart-container">
                                                    <canvas id="backtest-chart"></canvas>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="card">
                                            <div class="card-header">
                                                <h5 class="card-title mb-0">Trades</h5>
                                            </div>
                                            <div class="card-body">
                                                <div class="table-responsive">
                                                    <table class="table table-sm table-striped" id="result-trades-table">
                                                        <thead>
                                                        <tr>
                                                            <th>Entrée</th>
                                                            <th>Sortie</th>
                                                            <th>Type</th>
                                                            <th>Prix entrée</th>
                                                            <th>Prix sortie</th>
                                                            <th>P/L</th>
                                                            <th>Durée</th>
                                                        </tr>
                                                        </thead>
                                                        <tbody>
                                                        <!-- Les trades seront ajoutés ici dynamiquement -->
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                                <button type="button" class="btn btn-success" id="use-strategy-modal-btn">Utiliser cette stratégie</button>
                            </div>
                        </div>
                    </div>
                </div>
            </main>

            <script>
                // Paramètres des stratégies
                const strategyParams = {
                    MovingAverageStrategy: {
                        short_period: {
                            type: 'number',
                            label: 'Période courte',
                            default: 9,
                            min: 2,
                            max: 50,
                            step: 1
                        },
                        long_period: {
                            type: 'number',
                            label: 'Période longue',
                            default: 21,
                            min: 5,
                            max: 200,
                            step: 1
                        },
                        price_index: {
                            type: 'select',
                            label: 'Indice de prix',
                            default: 4,
                            options: {
                                1: 'Open',
                                2: 'High',
                                3: 'Low',
                                4: 'Close'
                            }
                        }
                    },
                    RSIStrategy: {
                        period: {
                            type: 'number',
                            label: 'Période RSI',
                            default: 14,
                            min: 2,
                            max: 50,
                            step: 1
                        },
                        overbought: {
                            type: 'number',
                            label: 'Niveau de surachat',
                            default: 70,
                            min: 50,
                            max: 90,
                            step: 1
                        },
                        oversold: {
                            type: 'number',
                            label: 'Niveau de survente',
                            default: 30,
                            min: 10,
                            max: 50,
                            step: 1
                        },
                        price_index: {
                            type: 'select',
                            label: 'Indice de prix',
                            default: 4,
                            options: {
                                1: 'Open',
                                2: 'High',
                                3: 'Low',
                                4: 'Close'
                            }
                        }
                    },
                    // Ajouter ce code au strategyParams dans le script de backtest.php
                    DynamicPositionStrategy: {
                        initial_stop_loss_pct: {
                            type: 'number',
                            label: 'Stop-Loss initial',
                            default: 5.0,
                            min: 0.5,
                            max: 20.0,
                            step: 0.5
                        },
                        analysis_period: {
                            type: 'number',
                            label: 'Période d\'analyse (heures)',
                            default: 24,
                            min: 1,
                            max: 72,
                            step: 1
                        },
                        partial_take_profit: {
                            type: 'select',
                            label: 'Prise de profit partielle',
                            default: 'true',
                            options: {
                                'true': 'Activé',
                                'false': 'Désactivé'
                            }
                        },
                        position_increase_pct: {
                            type: 'number',
                            label: 'Seuil d\'augmentation',
                            default: 5.0,
                            min: 1.0,
                            max: 15.0,
                            step: 0.5
                        },
                        max_investment_multiplier: {
                            type: 'number',
                            label: 'Multiplicateur maximal',
                            default: 2.0,
                            min: 1.1,
                            max: 5.0,
                            step: 0.1
                        },
                        partial_exit_pct: {
                            type: 'number',
                            label: 'Pourcentage de sortie',
                            default: 30.0,
                            min: 5.0,
                            max: 90.0,
                            step: 5.0
                        }
                    }
                };

                // Données pour le graphique
                let backtestData = null;

                $(document).ready(function() {
                    // Changer les paramètres en fonction de la stratégie sélectionnée
                    $('#strategy').on('change', function() {
                        const strategy = $(this).val();
                        updateStrategyParams(strategy);
                    });

// Afficher les détails du backtest - Version améliorée avec gestion des erreurs
                    $('.view-result-btn').on('click', function() {
                        try {
                            // Récupérer la chaîne JSON brute
                            const resultDataString = $(this).attr('data-result');

                            // Prétraiter les timestamps pour s'assurer qu'ils sont traités comme des nombres
                            const processedDataString = resultDataString.replace(/"timestamp":"(\d+)"/g, '"timestamp":$1');

                            // Parser la chaîne JSON
                            const resultData = JSON.parse(processedDataString);

                            // Afficher les détails
                            displayBacktestResult(resultData);
                        } catch (error) {
                            console.error("Erreur lors du parsing des données de backtest:", error);
                            console.log("Données brutes:", $(this).attr('data-result'));
                            alert("Une erreur s'est produite lors du chargement des détails. Consultez la console pour plus d'informations.");
                        }
                    });
                                        // Utiliser une stratégie depuis la liste des résultats
                    $('.use-strategy-btn').on('click', function() {
                        const strategy = $(this).data('strategy');
                        const params = JSON.parse($(this).data('params'));

                        if (confirm('Voulez-vous utiliser cette stratégie pour le bot de trading ?')) {
                            // Envoyer la requête pour configurer le bot
                            $.ajax({
                                url: 'api.php',
                                data: {
                                    action: 'use_strategy',
                                    strategy: strategy,
                                    params: JSON.stringify(params)
                                },
                                method: 'POST',
                                dataType: 'json',
                                success: function(response) {
                                    if (response.success) {
                                        alert('Stratégie configurée avec succès');
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

                    // Utiliser la stratégie depuis le modal de détails
                    $('#use-strategy-modal-btn').on('click', function() {
                        const strategy = $('#result-strategy').text();
                        const params = {};

                        $('#result-parameters-table tr').each(function() {
                            const key = $(this).find('th').text().replace(':', '');
                            const value = $(this).find('td').text();

                            if (key && value) {
                                params[key] = isNaN(value) ? value : parseFloat(value);
                            }
                        });

                        if (confirm('Voulez-vous utiliser cette stratégie pour le bot de trading ?')) {
                            // Envoyer la requête pour configurer le bot
                            $.ajax({
                                url: 'api.php',
                                data: {
                                    action: 'use_strategy',
                                    strategy: strategy,
                                    params: JSON.stringify(params)
                                },
                                method: 'POST',
                                dataType: 'json',
                                success: function(response) {
                                    if (response.success) {
                                        alert('Stratégie configurée avec succès');
                                        $('#resultModal').modal('hide');
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

                    // Mémoriser l'onglet actif
                    $('a[data-bs-toggle="tab"]').on('shown.bs.tab', function (e) {
                        localStorage.setItem('activeBacktestTab', $(e.target).attr('id'));
                    });

                    // Restaurer l'onglet actif
                    const activeTab = localStorage.getItem('activeBacktestTab');
                    if (activeTab) {
                        $('#' + activeTab).tab('show');
                    }
                });

                // Mettre à jour les paramètres de stratégie
                function updateStrategyParams(strategy) {
                    const $container = $('#strategy-params');
                    $container.empty();

                    if (!strategy || !strategyParams[strategy]) {
                        $container.html('<div class="alert alert-info">Sélectionnez une stratégie pour configurer ses paramètres.</div>');
                        return;
                    }

                    const params = strategyParams[strategy];

                    // Créer les champs pour chaque paramètre
                    let html = '<div class="row mb-3">';

                    Object.keys(params).forEach((key, index) => {
                        const param = params[key];

                        html += '<div class="col-md-6 mb-3">';
                        html += `<label for="param-${key}" class="form-label">${param.label}</label>`;

                        if (param.type === 'number') {
                            html += `<input type="number" class="form-control" id="param-${key}" name="param_${key}"
                     value="${param.default}" min="${param.min}" max="${param.max}" step="${param.step}">`;
                        } else if (param.type === 'select') {
                            html += `<select class="form-select" id="param-${key}" name="param_${key}">`;

                            Object.keys(param.options).forEach(optionKey => {
                                const selected = optionKey == param.default ? 'selected' : '';
                                html += `<option value="${optionKey}" ${selected}>${param.options[optionKey]}</option>`;
                            });

                            html += '</select>';
                        }

                        html += '</div>';

                        // Ajouter une nouvelle ligne tous les 2 paramètres
                        if ((index + 1) % 2 === 0 && index < Object.keys(params).length - 1) {
                            html += '</div><div class="row mb-3">';
                        }
                    });

                    html += '</div>';

                    $container.html(html);
                }

                // Fonction d'affichage des détails du backtest
                function displayBacktestResult(data) {
                    console.log("Données du backtest (après parsing):", data);

                    // Vérifier si les données sont valides
                    if (!data || typeof data !== 'object') {
                        console.error("Format de données invalide:", data);
                        alert("Format de données invalide. Consultez la console pour plus d'informations.");
                        return;
                    }

                    // Remplir les informations
                    $('#result-strategy').text(data.strategy || "");
                    $('#result-initial-balance').text((data.initial_balance || 0) + ' USDT');
                    $('#result-final-balance').text((data.final_balance || 0) + ' USDT');
                    $('#result-profit').text(((data.profit_pct || 0).toFixed(2)) + '% (' + ((data.profit || 0).toFixed(2)) + ' USDT)');
                    $('#result-trades').text((data.total_trades || 0) + ' (' + (data.winning_trades || 0) + ' gagnants, ' + (data.losing_trades || 0) + ' perdants)');
                    $('#result-win-rate').text(((data.win_rate || 0).toFixed(2)) + '%');
                    $('#result-profit-factor').text((data.profit_factor || 0).toFixed(2));
                    $('#result-max-drawdown').text(((data.max_drawdown || 0).toFixed(2)) + '%');
                    $('#result-fees').text(((data.fees_paid || 0).toFixed(2)) + ' USDT');

                    // Remplir les paramètres
                    const $paramsTable = $('#result-parameters-table');
                    $paramsTable.empty();

                    if (data.parameters) {
                        Object.keys(data.parameters).forEach(key => {
                            $paramsTable.append(`
                <tr>
                    <th>${key}:</th>
                    <td>${data.parameters[key]}</td>
                </tr>
            `);
                        });
                    } else {
                        $paramsTable.append('<tr><td colspan="2" class="text-center">Aucun paramètre disponible</td></tr>');
                    }

                    // Remplir les trades
                    const $tradesTable = $('#result-trades-table tbody');
                    $tradesTable.empty();

                    if (data.trades && data.trades.length > 0) {
                        data.trades.forEach(trade => {
                            const profitClass = trade.profit > 0 ? 'text-success' : 'text-danger';
                            const tradeType = trade.profit > 0 ? '<span class="badge bg-success">GAIN</span>' : '<span class="badge bg-danger">PERTE</span>';

                            // Convertir les timestamps en nombre si nécessaire
                            const entryTime = typeof trade.entry_time === 'string' ? parseInt(trade.entry_time) : trade.entry_time;
                            const exitTime = typeof trade.exit_time === 'string' ? parseInt(trade.exit_time) : trade.exit_time;

                            $tradesTable.append(`
                <tr>
                    <td>${new Date(entryTime).toLocaleString()}</td>
                    <td>${new Date(exitTime).toLocaleString()}</td>
                    <td>${tradeType}</td>
                    <td>${parseFloat(trade.entry_price).toFixed(2)}</td>
                    <td>${parseFloat(trade.exit_price).toFixed(2)}</td>
                    <td class="${profitClass}">${parseFloat(trade.profit_pct).toFixed(2)}% (${parseFloat(trade.profit).toFixed(2)} USDT)</td>
                    <td>${parseFloat(trade.duration).toFixed(1)}h</td>
                </tr>
            `);
                        });
                    } else {
                        $tradesTable.append('<tr><td colspan="7" class="text-center">Aucun trade disponible</td></tr>');
                    }

                    // Préparer les données pour le graphique
                    if (data.equity_curve && data.equity_curve.length > 0) {
                        const labels = [];
                        const equityData = [];
                        const balanceData = [];

                        data.equity_curve.forEach(point => {
                            // S'assurer que les timestamps sont traités comme des nombres
                            const timestamp = typeof point.timestamp === 'string' ? parseInt(point.timestamp) : point.timestamp;
                            labels.push(new Date(timestamp).toLocaleDateString());
                            equityData.push(parseFloat(point.equity));
                            balanceData.push(parseFloat(point.balance));
                        });

                        backtestData = {
                            labels: labels,
                            equity: equityData,
                            balance: balanceData
                        };

                        // Initialiser le graphique
                        const ctx = document.getElementById('backtest-chart').getContext('2d');

                        if (window.backtestChart) {
                            window.backtestChart.destroy();
                        }

                        window.backtestChart = new Chart(ctx, {
                            type: 'line',
                            data: {
                                labels: backtestData.labels,
                                datasets: [{
                                    label: 'Équité',
                                    data: backtestData.equity,
                                    borderColor: 'rgb(75, 192, 192)',
                                    backgroundColor: 'rgba(75, 192, 192, 0.1)',
                                    tension: 0.1,
                                    fill: true
                                },{
                                    label: 'Balance',
                                    data: backtestData.balance,
                                    borderColor: 'rgb(192, 75, 192)',
                                    backgroundColor: 'rgba(192, 75, 192, 0.1)',
                                    tension: 0.1,
                                    fill: true
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                scales: {
                                    x: {
                                        grid: {
                                            display: false
                                        }
                                    },
                                    y: {
                                        beginAtZero: false
                                    }
                                },
                                plugins: {
                                    tooltip: {
                                        callbacks: {
                                            label: function (context) {
                                                return context.parsed.y.toFixed(2) + ' USDT';
                                            }
                                        }
                                    }
                                }
                            }
                        });
                    } else {
                        // Pas de données d'équité disponibles
                        $('#backtest-chart').parent().html('<div class="alert alert-info">Données d\'équité non disponibles</div>');
                    }

                    if (data.strategy === 'DynamicPositionStrategy') {
                        // Calculer des statistiques supplémentaires à partir des trades
                        let partialExits = 0;
                        let positionIncreases = 0;
                        let stopLossExits = 0;
                        let averageDurationBefore1stAdjustment = 0;

                        if (data.trades && data.trades.length > 0) {
                            data.trades.forEach(trade => {
                                if (trade.metadata) {
                                    if (trade.metadata.partial_exits && trade.metadata.partial_exits.length > 0) {
                                        partialExits += trade.metadata.partial_exits.length;
                                    }
                                    if (trade.metadata.position_increases && trade.metadata.position_increases.length > 0) {
                                        positionIncreases += trade.metadata.position_increases.length;
                                    }
                                    if (trade.metadata.exit_reason === 'stop_loss') {
                                        stopLossExits++;
                                    }
                                    if (trade.metadata.time_to_1st_adjustment) {
                                        averageDurationBefore1stAdjustment += trade.metadata.time_to_1st_adjustment;
                                    }
                                }
                            });

                            // Calculer la moyenne
                            if (data.trades.length > 0) {
                                averageDurationBefore1stAdjustment = averageDurationBefore1stAdjustment / data.trades.length;
                            }
                        }
                        // Ajouter un tableau de statistiques spécifiques
                        $('#backtest-chart').parent().parent().after(`
        <div class="card mt-3">
            <div class="card-header bg-light">
                <h6 class="card-title mb-0">Statistiques Dynamic Position Strategy</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3">
                        <div class="small text-muted">Sorties partielles</div>
                        <div class="fs-5">${partialExits}</div>
                    </div>
                    <div class="col-md-3">
                        <div class="small text-muted">Augmentations de position</div>
                        <div class="fs-5">${positionIncreases}</div>
                    </div>
                    <div class="col-md-3">
                        <div class="small text-muted">Sorties par stop-loss</div>
                        <div class="fs-5">${stopLossExits}</div>
                    </div>
                    <div class="col-md-3">
                        <div class="small text-muted">Temps moyen avant 1ère modification</div>
                        <div class="fs-5">${averageDurationBefore1stAdjustment.toFixed(1)}h</div>
                    </div>
                </div>
            </div>
        </div>
    `);
                    }
                }
            </script>