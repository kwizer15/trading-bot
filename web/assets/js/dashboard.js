// Fonctions principales du dashboard
$(document).ready(function() {
    // Initialisation des tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl)
    });

    // Gestion de l'actualisation automatique
    let refreshInterval = null;

    // Fonction pour actualiser la page
    function refreshPage() {
        location.reload();
    }

    // Changer l'intervalle d'actualisation
    $('#refresh-interval').on('change', function() {
        const interval = parseInt($(this).val());

        // Arrêter l'actualisation existante
        if (refreshInterval) {
            clearInterval(refreshInterval);
            refreshInterval = null;
        }

        // Configurer une nouvelle actualisation si nécessaire
        if (interval > 0) {
            refreshInterval = setInterval(refreshPage, interval * 1000);
        }

        // Sauvegarder la préférence
        localStorage.setItem('refreshInterval', interval);
    });

    // Charger la préférence d'actualisation
    const savedInterval = localStorage.getItem('refreshInterval');
    if (savedInterval) {
        $('#refresh-interval').val(savedInterval).trigger('change');
    } else {
        $('#refresh-interval').trigger('change');
    }

    // Vérifier l'état du bot
    function checkBotStatus() {
        $.ajax({
            url: 'api.php',
            data: { action: 'check_bot_status' },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // Mettre à jour l'indicateur d'état
                    $('#bot-status-indicator').removeClass('running stopped error')
                        .addClass(response.status);

                    // Mettre à jour le texte
                    let statusText = 'Inconnu';
                    switch (response.status) {
                        case 'running':
                            statusText = 'En marche';
                            break;
                        case 'stopped':
                            statusText = 'Arrêté';
                            break;
                        case 'error':
                            statusText = 'Erreur';
                            break;
                    }
                    $('#bot-status-text').text(statusText);

                    // Activer/désactiver les boutons
                    if (response.status === 'running') {
                        $('#start-bot-btn').prop('disabled', true);
                        $('#stop-bot-btn').prop('disabled', false);
                    } else {
                        $('#start-bot-btn').prop('disabled', false);
                        $('#stop-bot-btn').prop('disabled', true);
                    }
                }
            },
            error: function() {
                $('#bot-status-indicator').removeClass('running stopped')
                    .addClass('error');
                $('#bot-status-text').text('Erreur de connexion');
            }
        });
    }

    // Vérifier l'état du bot au chargement
    checkBotStatus();

    // Vérifier périodiquement l'état du bot
    setInterval(checkBotStatus, 10000);

    // Démarrer le bot
    $('#start-bot-btn').on('click', function() {
        $(this).prop('disabled', true).html('<span class="loading-spinner"></span> Démarrage...');

        $.ajax({
            url: 'api.php',
            data: { action: 'start_bot' },
            method: 'POST',
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    checkBotStatus();
                } else {
                    alert('Erreur lors du démarrage du bot: ' + response.message);
                    $('#start-bot-btn').prop('disabled', false).text('Démarrer');
                }
            },
            error: function() {
                alert('Erreur de connexion');
                $('#start-bot-btn').prop('disabled', false).text('Démarrer');
            }
        });
    });

    // Arrêter le bot
    $('#stop-bot-btn').on('click', function() {
        $(this).prop('disabled', true).html('<span class="loading-spinner"></span> Arrêt...');

        $.ajax({
            url: 'api.php',
            data: { action: 'stop_bot' },
            method: 'POST',
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    checkBotStatus();
                } else {
                    alert('Erreur lors de l\'arrêt du bot: ' + response.message);
                    $('#stop-bot-btn').prop('disabled', false).text('Arrêter');
                }
            },
            error: function() {
                alert('Erreur de connexion');
                $('#stop-bot-btn').prop('disabled', false).text('Arrêter');
            }
        });
    });

    // Gestion du backtest
    $('#backtest-form').on('submit', function(e) {
        e.preventDefault();

        const $submitBtn = $(this).find('button[type="submit"]');
        $submitBtn.prop('disabled', true).html('<span class="loading-spinner"></span> Exécution...');

        $.ajax({
            url: 'api.php',
            data: $(this).serialize(),
            method: 'POST',
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    alert('Backtest terminé avec succès.');
                    location.reload();
                } else {
                    alert('Erreur: ' + response.message);
                    $submitBtn.prop('disabled', false).text('Exécuter le backtest');
                }
            },
            error: function() {
                alert('Erreur de connexion');
                $submitBtn.prop('disabled', false).text('Exécuter le backtest');
            }
        });
    });

    // Formulaire de configuration
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
                    $submitBtn.prop('disabled', false).text('Enregistrer');
                }
            },
            error: function() {
                alert('Erreur de connexion');
                $submitBtn.prop('disabled', false).text('Enregistrer');
            }
        });
    });

    // Initialiser les graphiques
    initCharts();
});

// Fonction pour initialiser les graphiques
function initCharts() {
    // Graphique d'équité (si présent)
    const equityChartElem = document.getElementById('equity-chart');
    if (equityChartElem) {
        $.ajax({
            url: 'api.php',
            data: { action: 'get_equity_data' },
            dataType: 'json',
            success: function(response) {
                if (response.success && response.data) {
                    renderEquityChart(equityChartElem, response.data);
                }
            }
        });
    }

    // Graphique de backtest (si présent)
    const backtestChartElem = document.getElementById('backtest-chart');
    if (backtestChartElem && typeof backtestData !== 'undefined') {
        renderBacktestChart(backtestChartElem, backtestData);
    }
}

// Fonction pour afficher le graphique d'équité
function renderEquityChart(canvas, data) {
    const ctx = canvas.getContext('2d');

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: data.labels,
            datasets: [{
                label: 'Équité totale',
                data: data.equity,
                borderColor: 'rgb(75, 192, 192)',
                backgroundColor: 'rgba(75, 192, 192, 0.1)',
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
                        label: function(context) {
                            return context.parsed.y.toFixed(2) + ' ' + data.currency;
                        }
                    }
                }
            }
        }
    });
}

// Fonction pour afficher le graphique de backtest
function renderBacktestChart(canvas, data) {
    const ctx = canvas.getContext('2d');

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: data.labels,
            datasets: [{
                label: 'Équité',
                data: data.equity,
                borderColor: 'rgb(75, 192, 192)',
                backgroundColor: 'rgba(75, 192, 192, 0.1)',
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
                        label: function(context) {
                            return context.parsed.y.toFixed(2) + ' USDT';
                        }
                    }
                }
            }
        }
    });
}
