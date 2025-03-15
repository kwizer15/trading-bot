<nav id="sidebarMenu" class="col-md-3 col-lg-2 d-md-block bg-light sidebar collapse">
    <div class="position-sticky pt-3">
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?php echo $page === 'dashboard' ? 'active' : ''; ?>" href="index.php?page=dashboard">
                    <i class="fas fa-tachometer-alt me-2"></i>
                    Tableau de bord
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $page === 'positions' ? 'active' : ''; ?>" href="index.php?page=positions">
                    <i class="fas fa-chart-line me-2"></i>
                    Positions
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $page === 'backtest' ? 'active' : ''; ?>" href="index.php?page=backtest">
                    <i class="fas fa-flask me-2"></i>
                    Backtesting
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $page === 'strategies' ? 'active' : ''; ?>" href="index.php?page=strategies">
                    <i class="fas fa-lightbulb me-2"></i>
                    Stratégies
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $page === 'logs' ? 'active' : ''; ?>" href="index.php?page=logs">
                    <i class="fas fa-list me-2"></i>
                    Logs
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $page === 'settings' ? 'active' : ''; ?>" href="index.php?page=settings">
                    <i class="fas fa-cog me-2"></i>
                    Paramètres
                </a>
            </li>
        </ul>

        <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted">
            <span>État du Bot</span>
        </h6>
        <div class="px-3 py-2">
            <div class="d-flex align-items-center mb-2">
                <div id="bot-status-indicator" class="status-indicator"></div>
                <span id="bot-status-text" class="ms-2">Chargement...</span>
            </div>
            <div class="d-grid gap-2">
                <button id="start-bot-btn" class="btn btn-sm btn-success" type="button">Démarrer</button>
                <button id="stop-bot-btn" class="btn btn-sm btn-danger" type="button">Arrêter</button>
            </div>
        </div>
    </div>
</nav>
