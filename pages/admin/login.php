<?php
session_start();
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - Central Réclamation</title>
    <link rel="stylesheet" href="../../assets/fontawesome/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background-color: #f1f5f9;
        }
        .login-card {
            width: 100%;
            max-width: 400px;
            background: white;
            padding: 2.5rem;
            border-radius: 1rem;
            box-shadow: var(--shadow-lg);
        }
        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        .login-header img {
            height: 48px;
            margin-bottom: 1rem;
        }
        .login-header h2 {
            font-size: 1.5rem;
            font-weight: 700;
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="login-header">
            <img src="assets/images/logo.png" alt="Logo" onerror="this.src='https://via.placeholder.com/48'">
            <h2>Central Réclamation</h2>
            <p style="color: var(--text-muted); margin-top: 0.5rem;">Connectez-vous à votre compte</p>
        </div>
        <form action="index.php" method="POST">
            <div class="form-group">
                <label class="form-label">Email</label>
                <input type="email" class="form-control" placeholder="admin@reclamation.com" required>
            </div>
            <div class="form-group">
                <label class="form-label">Mot de passe</label>
                <input type="password" class="form-control" placeholder="••••••••" required>
            </div>
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.5rem;">
                <label style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.875rem; cursor: pointer;">
                    <input type="checkbox"> Se souvenir de moi
                </label>
                <a href="#" style="font-size: 0.875rem; color: var(--primary-color); text-decoration: none;">Mot de passe oublié ?</a>
            </div>
            <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center; padding: 0.75rem;">
                Se connecter
            </button>
        </form>
    </div>
</body>
</html>
