<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo get_config('site_title'); ?></title>
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <script src="assets/js/jquery.min.js"></script>
    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/chart.min.js"></script>
</head>
<body>
<header class="navbar navbar-dark sticky-top bg-dark flex-md-nowrap p-0 shadow">
    <a class="navbar-brand col-md-3 col-lg-2 me-0 px-3" href="index.php">
        <?php echo get_config('site_title'); ?>
    </a>
    <button class="navbar-toggler position-absolute d-md-none collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#sidebarMenu" aria-controls="sidebarMenu" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
    </button>
    <div class="w-100"></div>
    <div class="navbar-nav">
        <div class="nav-item text-nowrap d-none d-md-block">
            <span class="nav-link px-3 text-light"><?php echo date('d/m/Y H:i:s'); ?></span>
        </div>
        <div class="nav-item text-nowrap">
            <a class="nav-link px-3" href="auth/logout.php">Déconnexion</a>
        </div>
    </div>
</header>
<div class="container-fluid">
    <div class="row">
