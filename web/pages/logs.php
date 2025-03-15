<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Logs</h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <div class="btn-group me-2">
                <a href="index.php?page=logs" class="btn btn-sm btn-outline-secondary">
                    <i class="fas fa-sync-alt"></i> Actualiser
                </a>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="clear-logs-btn">
                    <i class="fas fa-trash"></i> Effacer
                </button>
            </div>
            <div class="dropdown">
                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="logLevelDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                    Niveau
                </button>
                <ul class="dropdown-menu" aria-labelledby="logLevelDropdown">
                    <li><a class="dropdown-item log-level-filter active" href="#" data-level="all">Tous</a></li>
                    <li><a class="dropdown-item log-level-filter" href="#" data-level="info">Info</a></li>
                    <li><a class="dropdown-item log-level-filter" href="#" data-level="warning">Warning</a></li>
                    <li><a class="dropdown-item log-level-filter" href="#" data-level="error">Error</a></li>
                    <li><a class="dropdown-item log-level-filter" href="#" data-level="debug">Debug</a></li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Recherche dans les logs -->
    <div class="row mb-3">
        <div class="col-md-12">
            <div class="input-group">
                <span class="input-group-text">
                    <i class="fas fa-search"></i>
                </span>
                <input type="text" class="form-control" id="log-search" placeholder="Rechercher dans les logs...">
                <button class="btn btn-outline-secondary" type="button" id="clear-search-btn">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
    </div>

    <!-- Logs -->
    <div class="card">
        <div class="card-body log-container">
            <?php
            $logs = get_logs();

            if (empty($logs)):
                ?>
                <div class="alert alert-info">Aucun log disponible.</div>
            <?php else: ?>
                <?php foreach ($logs as $log): ?>
                    <div class="log-line log-line-<?php echo strtolower($log['level']); ?>" data-level="<?php echo strtolower($log['level']); ?>">
                        <small class="text-muted">[<?php echo $log['timestamp']; ?>]</small>
                        <span class="badge bg-<?php
                        echo strtolower($log['level']) === 'info' ? 'primary' :
                            (strtolower($log['level']) === 'warning' ? 'warning' :
                                (strtolower($log['level']) === 'error' ? 'danger' : 'secondary'));
                        ?>">
                            <?php echo strtoupper($log['level']); ?>
                        </span>
                        <?php echo htmlspecialchars($log['message']); ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</main>

<script>
    $(document).ready(function() {
        // Filtrer les logs par niveau
        $('.log-level-filter').on('click', function(e) {
            e.preventDefault();

            // Mettre à jour le bouton actif
            $('.log-level-filter').removeClass('active');
            $(this).addClass('active');

            const level = $(this).data('level');
            filterLogs();
        });

        // Recherche dans les logs
        $('#log-search').on('input', function() {
            filterLogs();
        });

        // Effacer la recherche
        $('#clear-search-btn').on('click', function() {
            $('#log-search').val('').trigger('input');
        });

        // Effacer les logs
        $('#clear-logs-btn').on('click', function() {
            if (confirm('Êtes-vous sûr de vouloir effacer tous les logs ?')) {
                $.ajax({
                    url: 'api.php',
                    data: { action: 'clear_logs' },
                    method: 'POST',
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
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

        // Fonction pour filtrer les logs
        function filterLogs() {
            const level = $('.log-level-filter.active').data('level');
            const search = $('#log-search').val().toLowerCase();

            $('.log-line').each(function() {
                const $line = $(this);
                const lineLevel = $line.data('level');
                const lineText = $line.text().toLowerCase();

                // Vérifier le niveau
                let showLine = level === 'all' || lineLevel === level;

                // Vérifier la recherche
                if (showLine && search) {
                    showLine = lineText.includes(search);
                }

                $line.toggle(showLine);
            });
        }
    });
</script>