<?php
/**
 * ═══════════════════════════════════════════════════════════════
 * EN-TÊTE COMMUN POUR TOUTES LES PAGES
 * Fichier: includes/header.php
 * 🎨 Design Premium avec Navigation Moderne & Responsive
 * ═══════════════════════════════════════════════════════════════
 */

// ═══ INITIALISATION ═══
if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!defined('SITE_NAME')) {
    require_once BASE_PATH . '/config/database.php';
}

$current_page = basename($_SERVER['PHP_SELF'], '.php');
$is_authenticated = isset($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <!-- ═══ MÉTADONNÉES ═══ -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title><?php echo isset($page_title) ? $page_title . ' - ' . SITE_NAME : SITE_NAME; ?></title>
    <meta name="description" content="<?php echo isset($page_description) ? $page_description : 'Système de gestion des réclamations clients'; ?>">
    <meta name="theme-color" content="#FF6B35">
    
    <!-- ═══ RESSOURCES CSS ═══ -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">
    <link rel="stylesheet" href="../assets/fontawesome/css/all.min.css">
    <link rel="icon" type="image/png" href="<?php echo BASE_URL; ?>/assets/images/favicon.png">
</head>
<body>

<!-- ═══════════════════════════════════════════════════════════════
     BARRE DE NAVIGATION PRINCIPALE
     ═══════════════════════════════════════════════════════════════ -->
<nav class="navbar" role="navigation" aria-label="Navigation principale">
    <div class="container">
        
        <!-- ══════════════ LOGO & MARQUE ══════════════ -->
        <a href="<?php echo BASE_URL; ?>/index.php" class="navbar-brand" title="Retour à l'accueil">
            <div class="navbar-brand-logo">
                <img src="<?php echo BASE_URL; ?>/assets/images/logo.png" alt="Logo SCPC" class="navbar-logo-img">
            </div>
            <div class="navbar-brand-text">
                <span class="navbar-company">SCPC Hammami</span>
                <span class="navbar-subtitle">Réclamations</span>
            </div>
            <!-- BADGE ANNÉE ACTIVE GLOBAL -->
            <?php 
            // Inclusion légère pour récupérer l'année sans bloquer si erreur
            $yearBadge = date('Y');
            if (file_exists(BASE_PATH . '/api/year_manager.php')) {
                require_once BASE_PATH . '/api/year_manager.php';
                $yearBadge = getActiveYear();
            }
            ?>
            <span style="background: #F3F4F6; color: #4B5563; padding: 2px 8px; border-radius: 6px; font-size: 0.75rem; font-weight: 700; margin-left: 10px; border: 1px solid #E5E7EB;">
                <?php echo $yearBadge; ?>
            </span>
        </a>

        <!-- ══════════════ MENU NAVIGATION (DESKTOP) ══════════════ -->
        <div class="navbar-menu" id="mainMenu">
            
            <!-- SECTION 1: Navigation Publique -->
            <div class="navbar-section">
                <a href="<?php echo BASE_URL; ?>/index.php" 
                   class="navbar-link <?php echo $current_page == 'index' ? 'active' : ''; ?>"
                   title="Page d'accueil">
                    <i class="fas fa-home" aria-hidden="true"></i>
                    <span>Accueil</span>
                </a>

                <a href="<?php echo BASE_URL; ?>/pages/client/nouvelle_reclamation.php"
                   class="navbar-link <?php echo $current_page == 'nouvelle_reclamation' ? 'active' : ''; ?>"
                   title="Créer une nouvelle réclamation">
                    <i class="fas fa-plus-circle" aria-hidden="true"></i>
                    <span>Nouvelle Réclamation</span>
                </a>

                <a href="<?php echo BASE_URL; ?>/pages/client/suivi_reclamation.php"
                   class="navbar-link <?php echo ($current_page == 'suivi_reclamation') ? 'active' : ''; ?>"
                   title="Suivre ma réclamation">
                    <i class="fas fa-search" aria-hidden="true"></i>
                    <span>Suivi</span>
                </a>

                <a href="<?php echo BASE_URL; ?>/pages/client/satisfaction.php"
                   class="navbar-link <?php echo ($current_page == 'satisfaction') ? 'active' : ''; ?>"
                   title="Donner votre avis de satisfaction">
                    <i class="fas fa-smile-wink" aria-hidden="true"></i>
                    <span>Satisfaction</span>
                </a>

                <a href="<?php echo BASE_URL; ?>/pages/agent/dashboard.php"
                   class="navbar-link <?php echo ($current_page == 'dashboard') ? 'active' : ''; ?>"
                   title="Accéder au dashboard">
                    <i class="fas fa-chart-line" aria-hidden="true"></i>
                    <span>Dashboard</span>
                </a>

                <a href="<?php echo BASE_URL; ?>/pages/agent/logout.php"
                   class="navbar-link navbar-logout"
                   title="Se déconnecter"
                   onclick="return confirm('Êtes-vous sûr de vouloir vous déconnecter ?');">
                    <i class="fas fa-sign-out-alt" aria-hidden="true"></i>
                    <span>Déconnexion</span>
                </a>
            <?php else: ?>
                <div class="navbar-divider" aria-hidden="true"></div>
                
                <div class="navbar-section">
                    <a href="<?php echo BASE_URL; ?>/pages/client/login.php"
                       class="navbar-link <?php echo ($current_page == 'login_client') ? 'active' : ''; ?>"
                       title="Connexion Client">
                        <i class="fas fa-user-circle" aria-hidden="true"></i>
                        <span>Connexion Client</span>
                    </a>

                    <a href="<?php echo BASE_URL; ?>/pages/agent/login.php"
                       class="navbar-link navbar-login <?php echo ($current_page == 'login') ? 'active' : ''; ?>"
                       title="Espace Agent">
                        <i class="fas fa-user-tie" aria-hidden="true"></i>
                        <span>Espace Agent</span>
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <!-- ══════════════ BOUTON MENU MOBILE ══════════════ -->
        <button id="navbarToggler" 
                class="navbar-toggler"
                onclick="toggleMobileMenu()"
                aria-controls="mobileMenu"
                aria-expanded="false"
                aria-label="Ouvrir le menu de navigation">
            <i class="fas fa-bars" aria-hidden="true"></i>
        </button>
    </div>
</nav>

<!-- ═══════════════════════════════════════════════════════════════
     MENU MOBILE (HIDDEN PAR DÉFAUT)
     ═══════════════════════════════════════════════════════════════ -->
<aside class="mobile-menu" id="mobileMenu" role="navigation" aria-label="Navigation mobile">
    
    <!-- SECTION 1: Navigation Publique Mobile -->
    <div class="mobile-menu-section">
        <span class="mobile-menu-title">Navigation</span>
        
        <a href="<?php echo BASE_URL; ?>/index.php"
           class="mobile-menu-link <?php echo $current_page == 'index' ? 'active' : ''; ?>"
           title="Page d'accueil">
            <i class="fas fa-home" aria-hidden="true"></i>
            <span>Accueil</span>
        </a>

        <a href="<?php echo BASE_URL; ?>/pages/client/nouvelle_reclamation.php"
           class="mobile-menu-link <?php echo $current_page == 'nouvelle_reclamation' ? 'active' : ''; ?>"
           title="Créer une nouvelle réclamation">
            <i class="fas fa-plus-circle" aria-hidden="true"></i>
            <span>Nouvelle Réclamation</span>
        </a>

        <a href="<?php echo BASE_URL; ?>/pages/client/suivi_reclamation.php"
           class="mobile-menu-link <?php echo $current_page == 'suivi_reclamation' ? 'active' : ''; ?>"
           title="Suivre ma réclamation">
            <i class="fas fa-search" aria-hidden="true"></i>
            <span>Suivi</span>
        </a>

        <a href="<?php echo BASE_URL; ?>/pages/client/satisfaction.php"
           class="mobile-menu-link <?php echo $current_page == 'satisfaction' ? 'active' : ''; ?>"
           title="Donner votre avis de satisfaction">
            <i class="fas fa-smile-wink" aria-hidden="true"></i>
            <span>Satisfaction</span>
        </a>
    </div>

    <!-- SECTION 2: Navigation Authentifiée Mobile -->
    <?php if ($is_authenticated): ?>
        <div class="mobile-menu-divider" aria-hidden="true"></div>
        
        <div class="mobile-menu-section">
            <span class="mobile-menu-title">Mon Compte</span>
            
            <a href="<?php echo BASE_URL; ?>/pages/agent/dashboard.php"
               class="mobile-menu-link <?php echo $current_page == 'dashboard' ? 'active' : ''; ?>"
               title="Accéder au dashboard">
                <i class="fas fa-chart-line" aria-hidden="true"></i>
                <span>Dashboard</span>
            </a>

            <a href="<?php echo BASE_URL; ?>/pages/agent/logout.php"
               class="mobile-menu-link mobile-menu-logout"
               title="Se déconnecter">
                <i class="fas fa-sign-out-alt" aria-hidden="true"></i>
                <span>Déconnexion</span>
            </a>
        </div>
    <?php else: ?>
        <div class="mobile-menu-divider" aria-hidden="true"></div>
        
        <div class="mobile-menu-section">
            <span class="mobile-menu-title">Authentification</span>
            
            <a href="<?php echo BASE_URL; ?>/pages/client/login.php"
               class="mobile-menu-link <?php echo $current_page == 'login_client' ? 'active' : ''; ?>"
               title="Connexion Client">
                <i class="fas fa-user-circle" aria-hidden="true"></i>
                <span>Connexion Client</span>
            </a>

            <a href="<?php echo BASE_URL; ?>/pages/agent/login.php"
               class="mobile-menu-link mobile-menu-login <?php echo $current_page == 'login' ? 'active' : ''; ?>"
               title="Espace Agent">
                <i class="fas fa-user-tie" aria-hidden="true"></i>
                <span>Espace Agent</span>
            </a>
        </div>
    <?php endif; ?>
</aside>

<!-- ═══════════════════════════════════════════════════════════════
     MESSAGES D'ALERTE (SUCCESS / ERROR / INFO)
     ═══════════════════════════════════════════════════════════════ -->
<?php if (isset($_SESSION['success_message'])): ?>
    <div class="alert alert-success alert-dismissible fade-in" role="alert">
        <i class="fas fa-check-circle" aria-hidden="true"></i>
        <span class="alert-message"><?php echo $_SESSION['success_message']; ?></span>
        <button type="button" class="alert-close" aria-label="Fermer l'alerte" onclick="this.parentElement.style.display='none';">
            <i class="fas fa-times" aria-hidden="true"></i>
        </button>
        <?php unset($_SESSION['success_message']); ?>
    </div>
<?php endif; ?>

<?php if (isset($_SESSION['error_message'])): ?>
    <div class="alert alert-danger alert-dismissible fade-in" role="alert">
        <i class="fas fa-exclamation-circle" aria-hidden="true"></i>
        <span class="alert-message"><?php echo $_SESSION['error_message']; ?></span>
        <button type="button" class="alert-close" aria-label="Fermer l'alerte" onclick="this.parentElement.style.display='none';">
            <i class="fas fa-times" aria-hidden="true"></i>
        </button>
        <?php unset($_SESSION['error_message']); ?>
    </div>
<?php endif; ?>

<?php if (isset($_SESSION['info_message'])): ?>
    <div class="alert alert-info alert-dismissible fade-in" role="alert">
        <i class="fas fa-info-circle" aria-hidden="true"></i>
        <span class="alert-message"><?php echo $_SESSION['info_message']; ?></span>
        <button type="button" class="alert-close" aria-label="Fermer l'alerte" onclick="this.parentElement.style.display='none';">
            <i class="fas fa-times" aria-hidden="true"></i>
        </button>
        <?php unset($_SESSION['info_message']); ?>
    </div>
<?php endif; ?>

<!-- ═══════════════════════════════════════════════════════════════
     CONTENU PRINCIPAL
     ═══════════════════════════════════════════════════════════════ -->
<main class="main-content">
    <div class="container">