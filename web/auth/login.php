<?php

require dirname(__DIR__, 2) . '/vendor/autoload.php';

// Page de connexion
session_start();

// Inclure la configuration
require_once '../config.php';

// Si l'authentification est désactivée, rediriger vers l'accueil
if (!get_config('auth')['enabled']) {
    $_SESSION['logged_in'] = true;
    header('Location: ../index.php');
    exit;
}

// Si l'utilisateur est déjà connecté, rediriger vers l'accueil
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    header('Location: ../index.php');
    exit;
}

$error = '';

// Traitement du formulaire de connexion
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = isset($_POST['username']) ? $_POST['username'] : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';

    $auth_config = get_config('auth');

    if ($username === $auth_config['username'] && $password === $auth_config['password']) {
        // Authentification réussie
        $_SESSION['logged_in'] = true;
        $_SESSION['username'] = $username;

        // Rediriger vers l'accueil
        header('Location: ../index.php');
        exit;
    } else {
        // Authentification échouée
        $error = 'Nom d\'utilisateur ou mot de passe incorrect';
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - <?php echo get_config('site_title'); ?></title>
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            background-color: #f5f5f5;
        }
        .login-container {
            width: 100%;
            max-width: 400px;
            padding: 20px;
            background-color: white;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        .logo {
            text-align: center;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
<div class="login-container">
    <div class="logo">
        <h1><?php echo get_config('site_title'); ?></h1>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger" role="alert">
            <?php echo $error; ?>
        </div>
    <?php endif; ?>

    <form method="post" action="">
        <div class="mb-3">
            <label for="username" class="form-label">Nom d'utilisateur</label>
            <input type="text" class="form-control" id="username" name="username" required>
        </div>
        <div class="mb-3">
            <label for="password" class="form-label">Mot de passe</label>
            <input type="password" class="form-control" id="password" name="password" required>
        </div>
        <button type="submit" class="btn btn-primary w-100">Se connecter</button>
    </form>
</div>

<script src="../assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>
