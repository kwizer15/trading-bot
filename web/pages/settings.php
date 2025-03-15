<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Paramètres</h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <div class="btn-group me-2">
                <a href="index.php?page=settings" class="btn btn-sm btn-outline-secondary">
                    <i class="fas fa-sync-alt"></i> Actualiser
                </a>
            </div>
        </div>
    </div>

    <form id="settings-form" class="form-settings" method="post">
        <input type="hidden" name="action" value="save_settings">

        <!-- API Binance -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">API Binance</h5>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="api-key" class="form-label">Clé API</label>
                        <input type="text" class="form-control" id="api-key" name="api[key]"
                               value="<?php echo get_config('api')['key']; ?>" required>
                        <div class="form-text">Votre clé API Binance</div>
                    </div>
                    <div class="col-md-6">
                        <label for="api-secret" class="form-label">Secret API</label>
                        <input type="password" class="form-control" id="api-secret" name="api[secret]"
                               value="<?php echo get_config('api')['secret']; ?>" required>
                        <div class="form-text">Votre clé secrète Binance</div>
                    </div>
                </div>
                <div class="mb-3 form-check">
                    <input type="checkbox" class="form-check-input" id="test-mode" name="api[test_mode]"
                           value="1" <?php echo get_config('api')['test_mode'] ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="test-mode">Mode test</label>
                    <div class="form-text">
                        Activez cette option pour utiliser le testnet de Binance (trading avec des fonds fictifs).
                    </div>
                </div>
                <div class="alert alert-warning mt-3">
                    <i class="fas fa-exclamation-triangle"></i>
                    Assurez-vous que vos clés API ont les permissions de lecture et de trading, mais pas de retrait.
                </div>
            </div>
        </div>

        <!-- Paramètres de trading -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Paramètres de trading</h5>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="base-currency" class="form-label">Devise de base</label>
                        <input type="text" class="form-control" id="base-currency" name="trading[base_currency]"
                               value="<?php echo get_config('trading')['base_currency']; ?>" required>
                        <div class="form-text">Devise utilisée pour les trades (ex: USDT)</div>
                    </div>
                    <div class="col-md-6">
                        <label for="investment-per-trade" class="form-label">Investissement par trade</label>
                        <div class="input-group">
                            <input type="number" class="form-control" id="investment-per-trade" name="trading[investment_per_trade]"
                                   value="<?php echo get_config('trading')['investment_per_trade']; ?>" min="1" step="1" required>
                            <span class="input-group-text"><?php echo get_config('trading')['base_currency']; ?></span>
                        </div>
                        <div class="form-text">Montant à investir à chaque trade</div>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="symbols" class="form-label">Symboles à trader</label>
                        <select class="form-select" id="symbols" name="trading[symbols][]" multiple size="5" required>
                            <?php
                            $popular_symbols = ['BTC', 'ETH', 'BNB', 'ADA', 'XRP', 'SOL', 'DOT', 'DOGE', 'AVAX', 'MATIC'];
                            $selected_symbols = get_config('trading')['symbols'];

                            foreach ($popular_symbols as $symbol) {
                                $selected = in_array($symbol, $selected_symbols) ? 'selected' : '';
                                echo "<option value=\"{$symbol}\" {$selected}>{$symbol}</option>";
                            }
                            ?>
                        </select>
                        <div class="form-text">Sélectionnez les crypto-monnaies à trader (Ctrl+clic pour sélection multiple)</div>
                    </div>
                    <div class="col-md-6">
                        <label for="max-positions" class="form-label">Positions maximum</label>
                        <input type="number" class="form-control" id="max-positions" name="trading[max_open_positions]"
                               value="<?php echo get_config('trading')['max_open_positions']; ?>" min="1" max="10" required>
                        <div class="form-text">Nombre maximum de positions ouvertes simultanément</div>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="stop-loss" class="form-label">Stop Loss</label>
                        <div class="input-group">
                            <input type="number" class="form-control" id="stop-loss" name="trading[stop_loss_percentage]"
                                   value="<?php echo get_config('trading')['stop_loss_percentage']; ?>" min="0.1" step="0.1" required>
                            <span class="input-group-text">%</span>
                        </div>
                        <div class="form-text">Pourcentage de perte à partir duquel vendre automatiquement</div>
                    </div>
                    <div class="col-md-6">
                        <label for="take-profit" class="form-label">Take Profit</label>
                        <div class="input-group">
                            <input type="number" class="form-control" id="take-profit" name="trading[take_profit_percentage]"
                                   value="<?php echo get_config('trading')['take_profit_percentage']; ?>" min="0.1" step="0.1" required>
                            <span class="input-group-text">%</span>
                        </div>
                        <div class="form-text">Pourcentage de gain à partir duquel vendre automatiquement</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Planification -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Planification</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label for="check-interval" class="form-label">Intervalle de vérification</label>
                    <div class="input-group">
                        <input type="number" class="form-control" id="check-interval" name="schedule[check_interval]"
                               value="<?php echo get_config('schedule')['check_interval']; ?>" min="30" step="30" required>
                        <span class="input-group-text">secondes</span>
                    </div>
                    <div class="form-text">Intervalle entre chaque vérification du marché (en mode daemon)</div>
                </div>
            </div>
        </div>

        <!-- Backtest -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Backtest</h5>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="backtest-start-date" class="form-label">Date de début</label>
                        <input type="date" class="form-control" id="backtest-start-date" name="backtest[start_date]"
                               value="<?php echo get_config('backtest')['start_date']; ?>" required>
                        <div class="form-text">Date de début pour les backtests par défaut</div>
                    </div>
                    <div class="col-md-6">
                        <label for="backtest-end-date" class="form-label">Date de fin</label>
                        <input type="date" class="form-control" id="backtest-end-date" name="backtest[end_date]"
                               value="<?php echo get_config('backtest')['end_date']; ?>" required>
                        <div class="form-text">Date de fin pour les backtests par défaut</div>
                    </div>
                </div>
                <div class="mb-3">
                    <label for="backtest-balance" class="form-label">Balance initiale</label>
                    <div class="input-group">
                        <input type="number" class="form-control" id="backtest-balance" name="backtest[initial_balance]"
                               value="<?php echo get_config('backtest')['initial_balance']; ?>" min="100" step="100" required>
                        <span class="input-group-text"><?php echo get_config('trading')['base_currency']; ?></span>
                    </div>
                    <div class="form-text">Balance initiale virtuelle pour les backtests</div>
                </div>
            </div>
        </div>

        <!-- Notifications -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Notifications</h5>
            </div>
            <div class="card-body">
                <!-- Email -->
                <div class="mb-3 form-check">
                    <input type="checkbox" class="form-check-input" id="email-notifications" name="notifications[email][enabled]"
                           value="1" <?php echo (get_config('notifications')['email']['enabled'] ?? false) ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="email-notifications">Activer les notifications par email</label>
                </div>
                <div class="mb-3">
                    <label for="email-address" class="form-label">Adresse email</label>
                    <input type="email" class="form-control" id="email-address" name="notifications[email][address]"
                           value="<?php echo get_config('notifications')['email']['address']; ?>">
                </div>

                <!-- Telegram -->
                <div class="mb-3 form-check">
                    <input type="checkbox" class="form-check-input" id="telegram-notifications" name="notifications[telegram][enabled]"
                           value="1" <?php echo (get_config('notifications')['telegram']['enabled'] ?? false) ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="telegram-notifications">Activer les notifications Telegram</label>
                </div>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="telegram-token" class="form-label">Token du bot</label>
                        <input type="text" class="form-control" id="telegram-token" name="notifications[telegram][bot_token]"
                               value="<?php echo get_config('notifications')['telegram']['bot_token']; ?>">
                    </div>
                    <div class="col-md-6">
                        <label for="telegram-chat-id" class="form-label">Chat ID</label>
                        <input type="text" class="form-control" id="telegram-chat-id" name="notifications[telegram][chat_id]"
                               value="<?php echo get_config('notifications')['telegram']['chat_id']; ?>">
                    </div>
                </div>
            </div>
        </div>

        <!-- Journalisation -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Journalisation</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label for="log-level" class="form-label">Niveau de journalisation</label>
                    <select class="form-select" id="log-level" name="logging[level]" required>
                        <option value="debug" <?php echo get_config('logging')['level'] === 'debug' ? 'selected' : ''; ?>>Debug (Tout)</option>
                        <option value="info" <?php echo get_config('logging')['level'] === 'info' ? 'selected' : ''; ?>>Info (Normal)</option>
                        <option value="warning" <?php echo get_config('logging')['level'] === 'warning' ? 'selected' : ''; ?>>Warning (Avertissements et erreurs)</option>
                        <option value="error" <?php echo get_config('logging')['level'] === 'error' ? 'selected' : ''; ?>>Error (Erreurs uniquement)</option>
                    </select>
                    <div class="form-text">Niveau de détail des logs générés par le bot</div>
                </div>
            </div>
        </div>

        <!-- Interface web -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Interface web</h5>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="site-title" class="form-label">Titre du site</label>
                        <input type="text" class="form-control" id="site-title" name="site_title"
                               value="<?php echo get_config('site_title'); ?>">
                    </div>
                    <div class="col-md-6">
                        <label for="refresh-interval-setting" class="form-label">Intervalle d'actualisation par défaut</label>
                        <select class="form-select" id="refresh-interval-setting" name="refresh_interval">
                            <option value="0" <?php echo get_config('refresh_interval') == 0 ? 'selected' : ''; ?>>Désactivé</option>
                            <option value="30" <?php echo get_config('refresh_interval') == 30 ? 'selected' : ''; ?>>30 secondes</option>
                            <option value="60" <?php echo get_config('refresh_interval') == 60 ? 'selected' : ''; ?>>1 minute</option>
                            <option value="300" <?php echo get_config('refresh_interval') == 300 ? 'selected' : ''; ?>>5 minutes</option>
                        </select>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="max-log-lines" class="form-label">Nombre maximum de lignes de log</label>
                        <input type="number" class="form-control" id="max-log-lines" name="max_log_lines"
                               value="<?php echo get_config('max_log_lines'); ?>" min="100" step="100">
                    </div>
                    <div class="col-md-6">
                        <label for="chart-days" class="form-label">Jours affichés dans les graphiques</label>
                        <input type="number" class="form-control" id="chart-days" name="chart_days"
                               value="<?php echo get_config('chart_days'); ?>" min="7" max="365" step="1">
                    </div>
                </div>
                <div class="mb-3 form-check">
                    <input type="checkbox" class="form-check-input" id="auth-enabled" name="auth[enabled]"
                           value="1" <?php echo get_config('auth')['enabled'] ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="auth-enabled">Activer l'authentification</label>
                </div>
                <div class="row mb-3" id="auth-fields" <?php echo !get_config('auth')['enabled'] ? 'style="display: none;"' : ''; ?>>
                    <div class="col-md-6">
                        <label for="auth-username" class="form-label">Nom d'utilisateur</label>
                        <input type="text" class="form-control" id="auth-username" name="auth[username]"
                               value="<?php echo get_config('auth')['username']; ?>">
                    </div>
                    <div class="col-md-6">
                        <label for="auth-password" class="form-label">Mot de passe</label>
                        <input type="password" class="form-control" id="auth-password" name="auth[password]"
                               value="<?php echo get_config('auth')['password']; ?>">
                        <div class="form-text">Laissez vide pour conserver le mot de passe actuel</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bouton de soumission -->
        <div class="d-grid gap-2 mb-4">
            <button type="submit" class="btn btn-primary">Enregistrer les paramètres</button>
        </div>
    </form>
</main>

<script>
    $(document).ready(function() {
        // Gérer l'affichage des champs d'authentification
        $('#auth-enabled').on('change', function() {
            if ($(this).is(':checked')) {
                $('#auth-fields').show();
            } else {
                $('#auth-fields').hide();
            }
        });

        // Soumettre le formulaire
        $('#settings-form').on('submit', function(e) {
            e.preventDefault();

            const $submitBtn = $(this).find('button[type="submit"]');
            $submitBtn.prop('disabled', true).html('<span class="loading-spinner"></span> Enregistrement...');

            $.ajax({
                url: 'api.php',
                data: $(this).serialize(),
                method: 'POST',
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        alert('Configuration enregistrée avec succès.');
                        location.reload();
                    } else {
                        alert('Erreur: ' + response.message);
                        $submitBtn.prop('disabled', false).text('Enregistrer les paramètres');
                    }
                },
                error: function() {
                    alert('Erreur de connexion');
                    $submitBtn.prop('disabled', false).text('Enregistrer les paramètres');
                }
            });
        });
    });
</script>