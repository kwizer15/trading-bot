</div>
</div>

<footer class="footer mt-auto py-3 bg-light">
    <div class="container-fluid">
        <div class="d-flex justify-content-between">
            <span class="text-muted">Bot de Trading Binance © <?php echo date('Y'); ?></span>
            <span class="text-muted">
                    Actualisation auto:
                    <select id="refresh-interval" class="form-select form-select-sm d-inline-block" style="width: auto;">
                        <option value="0">Désactivée</option>
                        <option value="30" <?php echo get_config('refresh_interval') == 30 ? 'selected' : ''; ?>>30s</option>
                        <option value="60" <?php echo get_config('refresh_interval') == 60 ? 'selected' : ''; ?>>1m</option>
                        <option value="300" <?php echo get_config('refresh_interval') == 300 ? 'selected' : ''; ?>>5m</option>
                    </select>
                </span>
        </div>
    </div>
</footer>

<!-- Script principal du dashboard -->
<script src="assets/js/dashboard.js"></script>
</body>
</html>