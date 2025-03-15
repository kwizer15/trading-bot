            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Stratégies de Trading</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <a href="index.php?page=strategies" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-sync-alt"></i> Actualiser
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Stratégies disponibles -->
                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Stratégies disponibles</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <!-- Moving Average Crossover -->
                                    <div class="col-md-6 mb-4">
                                        <div class="card h-100">
                                            <div class="card-header">
                                                <h5 class="card-title mb-0">Moving Average Crossover</h5>
                                            </div>
                                            <div class="card-body">
                                                <p>Cette stratégie est basée sur le croisement de deux moyennes mobiles simples (SMA).</p>

                                                <ul>
                                                    <li><strong>Signal d'achat :</strong> Quand la moyenne mobile courte croise au-dessus de la moyenne mobile longue (Golden Cross)</li>
                                                    <li><strong>Signal de vente :</strong> Quand la moyenne mobile courte croise en-dessous de la moyenne mobile longue (Death Cross)</li>
                                                </ul>

                                                <h6 class="mt-3">Paramètres configurables :</h6>
                                                <ul>
                                                    <li><strong>Période courte :</strong> Nombre de bougies pour la moyenne mobile courte</li>
                                                    <li><strong>Période longue :</strong> Nombre de bougies pour la moyenne mobile longue</li>
                                                    <li><strong>Indice de prix :</strong> Quel prix utiliser (Open, High, Low, Close)</li>
                                                </ul>

                                                <div class="mt-3">
                                                    <button class="btn btn-primary configure-strategy-btn" data-strategy="MovingAverageStrategy">
                                                        Configurer
                                                    </button>
                                                    <button class="btn btn-success use-strategy-btn" data-strategy="MovingAverageStrategy">
                                                        Utiliser
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- RSI Strategy -->
                                    <div class="col-md-6 mb-4">
                                        <div class="card h-100">
                                            <div class="card-header">
                                                <h5 class="card-title mb-0">RSI Strategy</h5>
                                            </div>
                                            <div class="card-body">
                                                <p>Cette stratégie est basée sur l'indice de force relative (RSI) qui mesure la vitesse et le changement des mouvements de prix.</p>

                                                <ul>
                                                    <li><strong>Signal d'achat :</strong> Quand le RSI sort de la zone de survente (remonte au-dessus du niveau oversold)</li>
                                                    <li><strong>Signal de vente :</strong> Quand le RSI sort de la zone de surachat (redescend en-dessous du niveau overbought)</li>
                                                </ul>

                                                <h6 class="mt-3">Paramètres configurables :</h6>
                                                <ul>
                                                    <li><strong>Période :</strong> Nombre de bougies pour le calcul du RSI</li>
                                                    <li><strong>Niveau de surachat :</strong> Seuil au-dessus duquel le marché est considéré en surachat</li>
                                                    <li><strong>Niveau de survente :</strong> Seuil en-dessous duquel le marché est considéré en survente</li>
                                                    <li><strong>Indice de prix :</strong> Quel prix utiliser (Open, High, Low, Close)</li>
                                                </ul>

                                                <div class="mt-3">
                                                    <button class="btn btn-primary configure-strategy-btn" data-strategy="RSIStrategy">
                                                        Configurer
                                                    </button>
                                                    <button class="btn btn-success use-strategy-btn" data-strategy="RSIStrategy">
                                                        Utiliser
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Stratégie actuelle -->
                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Stratégie actuelle</h5>
                            </div>
                            <div class="card-body">
                                <?php
                                // Récupérer la stratégie actuelle
                                $current_strategy = null;
                                $strategy_config_file = BOT_PATH . '/data/current_strategy.json';

                                if (file_exists($strategy_config_file)) {
                                    $current_strategy = json_decode(file_get_contents($strategy_config_file), true);
                                }

                                if ($current_strategy):
                                    ?>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <h5><?php echo $current_strategy['name']; ?></h5>
                                            <p class="text-muted">Configurée le <?php echo date('d/m/Y H:i:s', $current_strategy['timestamp']); ?></p>

                                            <h6 class="mt-3">Paramètres :</h6>
                                            <ul>
                                                <?php foreach ($current_strategy['parameters'] as $key => $value): ?>
                                                    <li><strong><?php echo $key; ?> :</strong> <?php echo $value; ?></li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="alert alert-info">
                                                <strong>Statut :</strong>
                                                <?php echo is_bot_running() ? status_badge('running') : status_badge('stopped'); ?>
                                            </div>

                                            <div class="mt-3">
                                                <button class="btn btn-primary edit-current-strategy-btn">
                                                    Modifier
                                                </button>
                                                <?php if (is_bot_running()): ?>
                                                    <button class="btn btn-danger" id="stop-bot-btn-strategy">
                                                        Arrêter le bot
                                                    </button>
                                                <?php else: ?>
                                                    <button class="btn btn-success" id="start-bot-btn-strategy">
                                                        Démarrer le bot
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-warning">
                                        Aucune stratégie n'est actuellement configurée. Utilisez une des stratégies disponibles pour commencer.
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Variantes de stratégies -->
                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Variantes de stratégies</h5>
                            </div>
                            <div class="card-body">
                                <?php
                                $strategies = get_strategies();

                                if (empty($strategies)):
                                    ?>
                                    <div class="alert alert-info">
                                        Aucune variante de stratégie n'est définie. Vous pouvez les configurer dans le fichier config/strategies.php.
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-striped">
                                            <thead>
                                            <tr>
                                                <th>Stratégie</th>
                                                <th>Variante</th>
                                                <th>Paramètres</th>
                                                <th>Actions</th>
                                            </tr>
                                            </thead>
                                            <tbody>
                                            <?php
                                            // Pour chaque type de stratégie
                                            foreach ($strategies as $strategy_type => $variants):
                                                // Pour chaque variante
                                                foreach ($variants as $variant_name => $params):
                                                    // Déterminer le nom de la classe de stratégie
                                                    $strategy_class = '';
                                                    switch ($strategy_type) {
                                                        case 'moving_average':
                                                            $strategy_class = 'MovingAverageStrategy';
                                                            break;
                                                        case 'rsi':
                                                            $strategy_class = 'RSIStrategy';
                                                            break;
                                                        // Ajouter d'autres cas si nécessaire
                                                    }
                                                    ?>
                                                    <tr>
                                                        <td><?php echo ucfirst(str_replace('_', ' ', $strategy_type)); ?></td>
                                                        <td><?php echo ucfirst($variant_name); ?></td>
                                                        <td>
                                                            <small>
                                                                <?php
                                                                $param_list = [];
                                                                foreach ($params as $key => $value) {
                                                                    $param_list[] = "{$key}: {$value}";
                                                                }
                                                                echo implode('<br>', $param_list);
                                                                ?>
                                                            </small>
                                                        </td>
                                                        <td>
                                                            <button class="btn btn-sm btn-success use-variant-btn"
                                                                    data-strategy="<?php echo $strategy_class; ?>"
                                                                    data-params="<?php echo htmlspecialchars(json_encode($params), ENT_QUOTES, 'UTF-8'); ?>">
                                                                Utiliser
                                                            </button>
                                                        </td>
                                                    </tr>
                                                <?php
                                                endforeach;
                                            endforeach;
                                            ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Modal pour configurer une stratégie -->
                <div class="modal fade" id="configureStrategyModal" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Configurer la stratégie</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <form id="configure-strategy-form">
                                    <input type="hidden" name="action" value="configure_strategy">
                                    <input type="hidden" name="strategy" id="strategy-name" value="">

                                    <!-- Les champs seront ajoutés dynamiquement ici -->
                                    <div id="strategy-params-container"></div>
                                </form>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                                <button type="button" class="btn btn-primary" id="save-strategy-btn">Enregistrer</button>
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
                    }
                };

                $(document).ready(function() {
                    // Ouvrir le modal de configuration
                    $('.configure-strategy-btn').on('click', function() {
                        const strategy = $(this).data('strategy');
                        configureStrategy(strategy);
                    });

                    // Modifier la stratégie actuelle
                    $('.edit-current-strategy-btn').on('click', function() {
                        const strategy = <?php echo json_encode($current_strategy ? $current_strategy['class'] : ''); ?>;

                        if (strategy) {
                            const params = <?php echo json_encode($current_strategy ? $current_strategy['parameters'] : []); ?>;
                            configureStrategy(strategy, params);
                        } else {
                            alert('Aucune stratégie actuelle à modifier');
                        }
                    });

                    // Utiliser une stratégie
                    $('.use-strategy-btn').on('click', function() {
                        const strategy = $(this).data('strategy');

                        if (confirm('Voulez-vous utiliser cette stratégie avec les paramètres par défaut ?')) {
                            useStrategy(strategy);
                        }
                    });

                    // Utiliser une variante de stratégie
                    $('.use-variant-btn').on('click', function() {
                        const strategy = $(this).data('strategy');
                        const params = $(this).data('params');

                        if (confirm('Voulez-vous utiliser cette variante de stratégie ?')) {
                            useStrategy(strategy, params);
                        }
                    });

                    // Enregistrer la configuration de la stratégie
                    $('#save-strategy-btn').on('click', function() {
                        const strategy = $('#strategy-name').val();
                        const params = {};

                        // Récupérer les valeurs des champs
                        $('#configure-strategy-form input, #configure-strategy-form select').each(function() {
                            const name = $(this).attr('name');

                            if (name && name !== 'action' && name !== 'strategy') {
                                const value = $(this).val();
                                params[name] = isNaN(value) ? value : parseFloat(value);
                            }
                        });

                        // Enregistrer la stratégie
                        useStrategy(strategy, params);

                        // Fermer le modal
                        $('#configureStrategyModal').modal('hide');
                    });

                    // Démarrer le bot
                    $('#start-bot-btn-strategy').on('click', function() {
                        $(this).prop('disabled', true).html('<span class="loading-spinner"></span> Démarrage...');

                        $.ajax({
                            url: 'api.php',
                            data: { action: 'start_bot' },
                            method: 'POST',
                            dataType: 'json',
                            success: function(response) {
                                if (response.success) {
                                    location.reload();
                                } else {
                                    alert('Erreur lors du démarrage du bot: ' + response.message);
                                    $('#start-bot-btn-strategy').prop('disabled', false).text('Démarrer le bot');
                                }
                            },
                            error: function() {
                                alert('Erreur de connexion');
                                $('#start-bot-btn-strategy').prop('disabled', false).text('Démarrer le bot');
                            }
                        });
                    });

                    // Arrêter le bot
                    $('#stop-bot-btn-strategy').on('click', function() {
                        $(this).prop('disabled', true).html('<span class="loading-spinner"></span> Arrêt...');

                        $.ajax({
                            url: 'api.php',
                            data: { action: 'stop_bot' },
                            method: 'POST',
                            dataType: 'json',
                            success: function(response) {
                                if (response.success) {
                                    location.reload();
                                } else {
                                    alert('Erreur lors de l\'arrêt du bot: ' + response.message);
                                    $('#stop-bot-btn-strategy').prop('disabled', false).text('Arrêter le bot');
                                }
                            },
                            error: function() {
                                alert('Erreur de connexion');
                                $('#stop-bot-btn-strategy').prop('disabled', false).text('Arrêter le bot');
                            }
                        });
                    });
                });

                // Configurer une stratégie
                function configureStrategy(strategy, params = null) {
                    if (!strategy || !strategyParams[strategy]) {
                        alert('Stratégie non reconnue');
                        return;
                    }

                    // Définir le nom de la stratégie
                    $('#strategy-name').val(strategy);

                    // Construire le formulaire
                    const $container = $('#strategy-params-container');
                    $container.empty();

                    const strategyParamsConfig = strategyParams[strategy];

                    Object.keys(strategyParamsConfig).forEach(key => {
                        const param = strategyParamsConfig[key];
                        const value = params && params[key] !== undefined ? params[key] : param.default;

                        // Créer la ligne du formulaire
                        let html = '<div class="mb-3">';
                        html += `<label for="param-${key}" class="form-label">${param.label}</label>`;

                        if (param.type === 'number') {
                            html += `<input type="number" class="form-control" id="param-${key}" name="${key}"
                     value="${value}" min="${param.min}" max="${param.max}" step="${param.step}">`;
                        } else if (param.type === 'select') {
                            html += `<select class="form-select" id="param-${key}" name="${key}">`;

                            Object.keys(param.options).forEach(optionKey => {
                                const selected = optionKey == value ? 'selected' : '';
                                html += `<option value="${optionKey}" ${selected}>${param.options[optionKey]}</option>`;
                            });

                            html += '</select>';
                        }

                        html += '</div>';

                        $container.append(html);
                    });

                    // Afficher le modal
                    $('#configureStrategyModal').modal('show');
                }

                // Utiliser une stratégie
                function useStrategy(strategy, params = null) {
                    $.ajax({
                        url: 'api.php',
                        data: {
                            action: 'use_strategy',
                            strategy: strategy,
                            params: params ? JSON.stringify(params) : null
                        },
                        method: 'POST',
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                alert('Stratégie configurée avec succès');
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
            </script>