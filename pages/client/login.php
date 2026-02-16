<?php
/**
 * Page de Connexion Client
 * Fichier: pages/client/login.php
 */

session_start(); // IMPORTANT: Démarrer la session
require_once '../../config/database.php';

// Rediriger si déjà connecté
if (isset($_SESSION['user_id']) && $_SESSION['user_role'] === 'client') {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$success = '';

// Traitement du formulaire de connexion
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = 'Veuillez remplir tous les champs.';
    } else {
        try {
            $db = getDB();
            $stmt = $db->prepare("SELECT * FROM utilisateurs WHERE email = ? AND role = 'client' AND actif = 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if (!$user) {
                $error = 'Aucun compte client actif trouvé avec cet email.';
            } else {
                if (password_verify($password, $user['mot_de_passe'])) {
                    // Connexion réussie
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_nom'] = $user['nom'];
                    $_SESSION['user_prenom'] = $user['prenom'];
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['user_role'] = $user['role'];
                    
                    header('Location: dashboard.php');
                    exit;
                } else {
                    $error = 'Mot de passe incorrect.';
                }
            }
        } catch(PDOException $e) {
            $error = 'Erreur de connexion : ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion Client - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/fontawesome/css/all.min.css">
</head>
<body>
    <nav class="navbar">
        <div class="container">
            <a class="navbar-brand" href="../../index.php" style="display: flex; align-items: center; gap: 10px;">
                <img src="../../assets/images/logo.png" alt="Logo SCPC" style="height: 40px; width: auto;">
                <div style="display: flex; flex-direction: column; line-height: 1.2;">
                    <span style="font-weight: 700; font-size: 1.1rem;">SCPC Comptoir Hammami</span>
                    <span style="font-size: 0.8rem; opacity: 0.9; font-weight: normal;"><?php echo SITE_NAME; ?></span>
                </div>
            </a>
            <div class="navbar-menu">
                <a href="../../index.php"><i class="fas fa-home"></i> <span>Accueil</span></a>
                <a href="nouvelle_reclamation.php"><i class="fas fa-plus-circle"></i> <span>Nouvelle Réclamation</span></a>
                <a href="suivi_reclamation.php"><i class="fas fa-search"></i> <span>Suivi</span></a>
                <a href="login.php" class="active"><i class="fas fa-sign-in-alt"></i> <span>Connexion</span></a>
            </div>
            <button id="navbarToggler" class="navbar-toggler"><i class="fas fa-bars"></i></button>
        </div>
    </nav>

    <div class="mobile-menu" id="mobileMenu">
        <div class="mobile-menu-header">
            <i class="fas fa-user-circle"></i>
            <strong>Client</strong>
            <p class="mobile-menu-subtitle">Connexion</p>
        </div>
        <a href="../../index.php" class="mobile-menu-link"><i class="fas fa-home"></i> <span>Accueil</span></a>
        <a href="nouvelle_reclamation.php" class="mobile-menu-link"><i class="fas fa-plus-circle"></i> <span>Nouvelle</span></a>
        <a href="suivi_reclamation.php" class="mobile-menu-link"><i class="fas fa-search"></i> <span>Suivi</span></a>
        <a href="login.php" class="mobile-menu-link active"><i class="fas fa-sign-in-alt"></i> <span>Connexion</span></a>
    </div>

    <div class="hero-section" style="min-height: 100vh; display: flex; align-items: center; padding: 2rem 0;">
        <div class="container">
            <div class="card fade-in" style="max-width: 500px; margin: 0 auto;">
                <div class="card-body p-5">
                    <!-- En-tête -->
                    <div class="text-center mb-4">
                        <div class="option-icon icon-info" style="width: 80px; height: 80px; margin: 0 auto 1rem;">
                            <i class="fas fa-user-circle"></i>
                        </div>
                        <h2 class="mb-2">Connexion Client</h2>
                        <p style="color: var(--gray-600);">Connectez-vous à votre compte</p>
                    </div>

                    <!-- Messages d'erreur/succès -->
                    <?php if ($error): ?>
                        <div class="alert alert-danger" style="margin-bottom: 1.5rem;">
                            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($success): ?>
                        <div class="alert alert-success" style="margin-bottom: 1.5rem;">
                            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                        </div>
                    <?php endif; ?>

                    <!-- Formulaire de connexion -->
                    <form method="POST" action="" style="margin-bottom: 2rem;">
                        <div style="margin-bottom: 1.5rem;">
                            <label for="email" style="display: block; font-weight: 500; margin-bottom: 0.5rem;">Email</label>
                            <input type="email" id="email" name="email" required 
                                   style="width: 100%; padding: 0.75rem; border: 1px solid var(--gray-300); border-radius: var(--radius-md); font-size: 1rem;"
                                   placeholder="votre@email.com">
                        </div>

                        <div style="margin-bottom: 2rem;">
                            <label for="password" style="display: block; font-weight: 500; margin-bottom: 0.5rem;">Mot de passe</label>
                            <input type="password" id="password" name="password" required 
                                   style="width: 100%; padding: 0.75rem; border: 1px solid var(--gray-300); border-radius: var(--radius-md); font-size: 1rem;"
                                   placeholder="Votre mot de passe">
                        </div>

                        <button type="submit" class="btn btn-primary" style="width: 100%; padding: 0.75rem;">
                            <i class="fas fa-sign-in-alt"></i> Se Connecter
                        </button>
                    </form>

                    <!-- Informations utiles -->
                    <div style="margin-top: 2rem; padding: 1rem; background: var(--gray-50); border-radius: var(--radius-lg); border-left: 4px solid var(--info-color);">
                        <p style="font-size: 0.875rem; color: var(--gray-700); margin-bottom: 0.5rem;">
                            <strong>ℹ️ Vous êtes nouveau client ?</strong>
                        </p>
                        <p style="font-size: 0.875rem; color: var(--gray-600); margin-bottom: 0.5rem;">
                            Vous avez reçu un email avec vos identifiants de connexion ? C'est normal ! Utilisez l'email et le mot de passe envoyé dans ce mail pour vous connecter.
                        </p>
                        <p style="font-size: 0.875rem; color: var(--gray-600);">
                            Vous n'avez pas de compte encore ? Créez une nouvelle réclamation et votre compte sera généré automatiquement !
                        </p>
                    </div>

                    <!-- Lien retour -->
                    <div class="text-center mt-4">
                        <a href="../../index.php" style="color: var(--primary-color); text-decoration: none; font-weight: 500;">
                            <i class="fas fa-arrow-left"></i> Retour à l'accueil
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div style="text-align: center; padding: 2rem 0;">
                <p>&copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. Tous droits réservés.</p>
            </div>
        </div>
    </footer>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const mobileMenu = document.getElementById('mobileMenu');
        const toggler = document.getElementById('navbarToggler');
        const togglerIcon = toggler ? toggler.querySelector('i') : null;

        // Function to toggle menu state
        function setMenuState(isOpen) {
            if (isOpen) {
                mobileMenu.classList.add('show');
                if (togglerIcon) {
                    togglerIcon.classList.remove('fa-bars');
                    togglerIcon.classList.add('fa-times');
                }
            } else {
                mobileMenu.classList.remove('show');
                if (togglerIcon) {
                    togglerIcon.classList.remove('fa-times');
                    togglerIcon.classList.add('fa-bars');
                }
            }
        }

        // Toggle button click handler
        if (toggler) {
            toggler.addEventListener('click', function(e) {
                e.stopPropagation(); // Prevent document click from immediately closing it
                const isCurrentlyOpen = mobileMenu.classList.contains('show');
                setMenuState(!isCurrentlyOpen);
            });
        }

        // Document click handler (Close when clicking outside or on the overlay)
        document.addEventListener('click', function(event) {
            if (mobileMenu && mobileMenu.classList.contains('show')) {
                const isClickOnOverlay = (event.target === mobileMenu);
                const isClickOutside = !mobileMenu.contains(event.target);
                const isClickOnToggler = (toggler && toggler.contains(event.target));

                if ((isClickOutside || isClickOnOverlay) && !isClickOnToggler) {
                    setMenuState(false);
                }
            }
        });
    });
    </script>
</body>
</html>
