<?php
/**
 * Navbar RMQ réutilisable
 * Utilisation: <?php $pageTitle = "Dashboard"; include 'includes/navbar_rmq.php'; ?>
 */

$pageTitle = $pageTitle ?? 'Espace RMQ';
?>

<!-- Navbar RMQ -->
<nav class="navbar" style="background: white; border-bottom: 2px solid #f0f0f0; box-shadow: 0 2px 8px rgba(0,0,0,0.05);">
    <div class="container" style="display: flex; align-items: center; justify-content: space-between; padding: 0 1rem;">
        <!-- Brand -->
        <a class="navbar-brand" href="../../index.php" style="display: flex; align-items: center; gap: 10px; text-decoration: none;">
            <img src="../../assets/images/logo.png" alt="Logo SCPC" style="height: 40px; width: auto;">
            <div style="display: flex; flex-direction: column; line-height: 1.2;">
                <span style="font-weight: 700; font-size: 1.1rem; color: #1A1A1A;">SCPC Comptoir Hammami</span>
                <span style="font-size: 0.75rem; color: #666; font-weight: 500;"><?php echo SITE_NAME; ?></span>
            </div>
            <span style="background: linear-gradient(135deg, #FF6B35 0%, #FFA500 100%); color: white; padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.7rem; font-weight: 600; margin-left: auto;">
                <?php echo htmlspecialchars($pageTitle); ?>
            </span>
        </a>

        <!-- Menu Desktop -->
        <div class="navbar-menu" id="mainMenu" style="display: flex; gap: 0.5rem; list-style: none; margin: 0; padding: 0;">
            <a href="dashboard.php" class="navbar-link" style="display: flex; align-items: center; gap: 0.5rem; padding: 0.75rem 1rem; text-decoration: none; color: #4b5563; font-weight: 500; border-radius: 8px; transition: all 0.2s;">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </a>
            <a href="statistiques.php" class="navbar-link" style="display: flex; align-items: center; gap: 0.5rem; padding: 0.75rem 1rem; text-decoration: none; color: #4b5563; font-weight: 500; border-radius: 8px; transition: all 0.2s;">
                <i class="fas fa-chart-bar"></i>
                <span>Statistiques</span>
            </a>
            <a href="create_claim.php" class="navbar-link" style="display: flex; align-items: center; gap: 0.5rem; padding: 0.75rem 1rem; text-decoration: none; color: #4b5563; font-weight: 500; border-radius: 8px; transition: all 0.2s;">
                <i class="fas fa-plus-circle"></i>
                <span>Nouvelle Réclamation</span>
            </a>
            <a href="../../pages/logout.php" class="navbar-link" style="display: flex; align-items: center; gap: 0.5rem; padding: 0.75rem 1rem; text-decoration: none; color: #dc2626; font-weight: 500; border-radius: 8px; transition: all 0.2s;">
                <i class="fas fa-sign-out-alt"></i>
                <span>Déconnexion</span>
            </a>
        </div>

        <!-- Mobile Menu Toggle -->
        <button id="navbarToggler" class="navbar-toggler" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #333; display: none;">
            <i class="fas fa-bars"></i>
        </button>
    </div>
</nav>

<!-- Mobile Menu -->
<div class="mobile-menu" id="mobileMenu">
    <div class="mobile-menu-header">
        <i class="fas fa-tasks"></i>
        <strong>RMQ</strong>
        <p class="mobile-menu-subtitle">Espace Responsable</p>
    </div>
    <a href="../../index.php" class="mobile-menu-link">
        <i class="fas fa-home"></i> <span>Accueil Site</span>
    </a>
    <a href="dashboard.php" class="mobile-menu-link <?php echo basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : ''; ?>">
        <i class="fas fa-tachometer-alt"></i> <span>Dashboard</span>
    </a>
    <a href="statistiques.php" class="mobile-menu-link <?php echo basename($_SERVER['PHP_SELF']) === 'statistiques.php' ? 'active' : ''; ?>">
        <i class="fas fa-chart-bar"></i> <span>Statistiques</span>
    </a>
    <a href="create_claim.php" class="mobile-menu-link <?php echo basename($_SERVER['PHP_SELF']) === 'create_claim.php' ? 'active' : ''; ?>">
        <i class="fas fa-plus-circle"></i> <span>Nouvelle Réclamation</span>
    </a>
    <a href="../../pages/logout.php" class="mobile-menu-link" style="color: var(--danger-color) !important;">
        <i class="fas fa-sign-out-alt"></i> <span>Déconnexion</span>
    </a>
</div>


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
            e.stopPropagation(); 
            const isCurrentlyOpen = mobileMenu.classList.contains('show');
            setMenuState(!isCurrentlyOpen);
        });
    }

    // Document click handler
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
