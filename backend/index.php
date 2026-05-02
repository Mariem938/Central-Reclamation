<?php
/**
 * Page d'Accueil - Central de Réclamation
 * Premium Expert Design
 */

require_once 'config/database.php';

// Récupérer les statistiques
$db = getDB();


    // Statistiques removed as per user request
    $stats_total = 0;
    $stats_nouveaux = 0;
    $stats_encours = 0;
    $stats_resolus = 0;
    $stats_satisfaction = 'N/A';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title><?php echo SITE_NAME; ?> - Accueil</title>
    <meta name="description" content="Plateforme premium de gestion et de suivi des réclamations clients">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="./assets/fontawesome/css/all.min.css">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="container">
            <a class="navbar-brand" href="index.php" style="display: flex; align-items: center; gap: 10px;">
                <img src="assets/images/logo.png" alt="Logo SCPC" style="height: 40px; width: auto;">
                <div style="display: flex; flex-direction: column; line-height: 1.2;">
                    <span style="font-weight: 700; font-size: 1.1rem;">SCPC Comptoir Hammami</span>
                    <span style="font-size: 0.8rem; opacity: 0.9; font-weight: normal;"><?php echo SITE_NAME; ?></span>
                </div>
            </a>
            
            <div class="navbar-menu" id="mainMenu">
                <a href="index.php" class="navbar-link active">
                    <i class="fas fa-home"></i>
                    <span>Accueil</span>
                </a>
                <a href="pages/client/nouvelle_reclamation.php" class="navbar-link">
                    <i class="fas fa-plus-circle"></i>
                    <span>Nouvelle Réclamation</span>
                </a>
                <a href="pages/client/suivi_reclamation.php" class="navbar-link">
                    <i class="fas fa-search"></i>
                    <span>Suivi</span>
                </a>
                <a href="pages/client/login.php" class="navbar-link">
                    <i class="fas fa-user-circle"></i>
                    <span>Connexion Client</span>
                </a>
                <div style="display: flex; gap: 15px;">
                    <a href="pages/agent/login.php?role=rmq" class="navbar-link" style="color: #4b5563;">
                        <i class="fas fa-user-tie"></i> Espace RMQ
                    </a>
                    <a href="pages/agent/login.php?role=aqf" class="navbar-link" style="color: #4b5563;">
                        <i class="fas fa-check-double"></i> Espace AQF
                    </a>
                    <a href="pages/agent/login.php?role=admin" class="navbar-link" style="color: #4b5563;">
                        <i class="fas fa-cog"></i> Espace Admin
                    </a>
                </div>
            </div>
            
            <button id="navbarToggler" class="navbar-toggler">
                <i class="fas fa-bars"></i>
            </button>
        </div>
    </nav>

    <!-- Mobile Menu -->
    <div class="mobile-menu" id="mobileMenu">
        <div class="mobile-menu-header">
            <i class="fas fa-home"></i>
            <strong>SCPC Comptoir Hammami</strong>
            <p class="mobile-menu-subtitle">Espace Accueil</p>
        </div>
        <a href="index.php" class="mobile-menu-link active"><i class="fas fa-home"></i> <span>Accueil</span></a>
        <a href="pages/client/nouvelle_reclamation.php" class="mobile-menu-link"><i class="fas fa-plus-circle"></i> <span>Nouvelle Réclamation</span></a>
        <a href="pages/client/suivi_reclamation.php" class="mobile-menu-link"><i class="fas fa-search"></i> <span>Suivi</span></a>
        <a href="pages/client/login.php" class="mobile-menu-link"><i class="fas fa-user-circle"></i> <span>Connexion Client</span></a>
        <div style="border-top: 1px solid #eee; margin-top: 5px; padding-top: 5px;">
            <a href="pages/agent/login.php?role=commercial" class="mobile-menu-link"><i class="fas fa-briefcase"></i> <span>Espace Commercial</span></a>
            <a href="pages/agent/login.php?role=rmq" class="mobile-menu-link"><i class="fas fa-user-tie"></i> <span>Espace RMQ</span></a>
            <a href="pages/agent/login.php?role=aqf" class="mobile-menu-link"><i class="fas fa-check-double"></i> <span>Espace AQF</span></a>
            <a href="pages/agent/login.php?role=admin" class="mobile-menu-link"><i class="fas fa-cog"></i> <span>Espace Admin</span></a>
        </div>
    </div>

    <!-- Hero Section -->
    <section class="hero">
        <div class="container">
            <div class="hero-content">
                <div class="hero-text fade-in">
                    <div class="hero-badge">
                        <i class="fas fa-shield-halved"></i>
                        Plateforme sécurisée et fiable
                    </div>
                    
                    <h1 class="hero-title">
                        Votre <span class="highlight">satisfaction</span> est notre priorité
                    </h1>
                    
                    <p class="hero-description">
                        Soumettez vos réclamations en quelques clics et suivez leur traitement en temps réel. Notre équipe s'engage à vous répondre dans les meilleurs délais.
                    </p>
                    
                    <div class="hero-buttons">
                        <a href="pages/client/nouvelle_reclamation.php" class="btn btn-primary btn-lg">
                            <i class="fas fa-plus-circle"></i>
                            Nouvelle Réclamation
                        </a>
                        <a href="pages/client/suivi_reclamation.php" class="btn btn-secondary btn-lg">
                            <i class="fas fa-search"></i>
                            Suivre ma Réclamation
                        </a>
                    </div>
                </div>
                
                <!-- Visual removed as per user request -->
            </div>
        </div>
    </section>
    
    <!-- Features Section -->
    <section class="features section" id="features">
        <div class="container">
            <div class="text-center mb-3">
                <h2>Pourquoi nous choisir ?</h2>
                <p>Une plateforme moderne conçue pour simplifier vos démarches</p>
            </div>
            
            <div class="features-grid">
                <div class="feature-card glass-card fade-in">
                    <div class="feature-icon">
                        <i class="fas fa-bolt"></i>
                    </div>
                    <h3 class="feature-title">Rapide & Simple</h3>
                    <p class="feature-description">Soumettez votre réclamation en moins de 2 minutes avec notre formulaire intuitif.</p>
                </div>
                
                <div class="feature-card glass-card fade-in" style="animation-delay: 0.1s;">
                    <div class="feature-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <h3 class="feature-title">Suivi en Temps Réel</h3>
                    <p class="feature-description">Recevez des notifications à chaque étape du traitement de votre dossier.</p>
                </div>
                
                <div class="feature-card glass-card fade-in" style="animation-delay: 0.2s;">
                    <div class="feature-icon">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <h3 class="feature-title">100% Sécurisé</h3>
                    <p class="feature-description">Vos données sont protégées et traitées en toute confidentialité.</p>
                </div>
                
                <div class="feature-card glass-card fade-in" style="animation-delay: 0.3s;">
                    <div class="feature-icon">
                        <i class="fas fa-headset"></i>
                    </div>
                    <h3 class="feature-title">Support Dédié</h3>
                    <p class="feature-description">Une équipe d'agents qualifiés à votre écoute pour résoudre vos problèmes.</p>
                </div>
            </div>
        </div>
    </section>
    
    <!-- How It Works -->
    <section class="section">
        <div class="container">
            <div class="how-it-works">
                <div class="text-center">
                    <h2>Comment ça marche ?</h2>
                    <p>Un processus simple en 4 étapes</p>
                </div>
                
                <div class="steps-grid">
                    <div class="step-card">
                        <div class="step-number">1</div>
                        <h4 class="step-title">Soumettez</h4>
                        <p class="step-text">Remplissez le formulaire avec les détails de votre réclamation</p>
                        <div class="step-arrow"><i class="fas fa-arrow-right"></i></div>
                    </div>
                    
                    <div class="step-card">
                        <div class="step-number">2</div>
                        <h4 class="step-title">Confirmation</h4>
                        <p class="step-text">Recevez votre numéro de ticket et un lien de suivi par email</p>
                        <div class="step-arrow"><i class="fas fa-arrow-right"></i></div>
                    </div>
                    
                    <div class="step-card">
                        <div class="step-number">3</div>
                        <h4 class="step-title">Traitement</h4>
                        <p class="step-text">Notre équipe analyse et traite votre réclamation</p>
                        <div class="step-arrow"><i class="fas fa-arrow-right"></i></div>
                    </div>
                    
                    <div class="step-card">
                        <div class="step-number">4</div>
                        <h4 class="step-title">Résolution</h4>
                        <p class="step-text">Vous êtes notifié de la résolution et pouvez évaluer le service</p>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Quick Track CTA -->
    <section class="section cta-section">
        <div class="container">
            <h2 class="cta-title">Vous avez déjà soumis une réclamation ?</h2>
            <p class="cta-text">
                Entrez votre numéro de ticket pour consulter l'état de votre dossier
            </p>
            
            <form class="quick-track-form" action="pages/client/suivi_reclamation.php" method="POST">
                <input type="text" class="form-input" name="numero" placeholder="Ex: TKT-2026-0001" required>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search"></i>
                    Rechercher
                </button>
            </form>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h4><i class="fas fa-info-circle"></i> À propos</h4>
                    <p>
                        <?php echo SITE_NAME; ?> est votre plateforme de confiance pour la gestion 
                        et le suivi des réclamations. Une expérience moderne et efficace.
                    </p>
                </div>

                <div class="footer-section">
                    <h4><i class="fas fa-rocket"></i> Navigation</h4>
                    <ul>
                        <li><a href="index.php"><i class="fas fa-home"></i> Accueil</a></li>
                        <li><a href="pages/client/nouvelle_reclamation.php"><i class="fas fa-plus"></i> Nouvelle Réclamation</a></li>
                        <li><a href="pages/client/suivi_reclamation.php"><i class="fas fa-search"></i> Suivre ma Réclamation</a></li>
                        <li><a href="pages/agent/login.php"><i class="fas fa-user-shield"></i> Espace Agent</a></li>
                    </ul>
                </div>

                <div class="footer-section">
                    <h4><i class="fas fa-envelope"></i> Contact</h4>
                    <ul>
                        <li><i class="fas fa-phone"></i> +216 71 100 801</li>
                        <li><i class="fas fa-envelope"></i> contact@reclamation.tn</li>
                        <li><i class="fas fa-map-marker-alt"></i> Sfax, Tunisie</li>
                        <li><i class="fas fa-clock"></i> Lun - Ven: 8h - 17h</li>
                    </ul>
                </div>

                <div class="footer-section">
                    <h4><i class="fas fa-gem"></i> Nos Engagements</h4>
                    <p>
                        <i class="fas fa-check-circle" style="color: #38ef7d;"></i> Sécurité maximale<br>
                        <i class="fas fa-check-circle" style="color: #38ef7d;"></i> Traitement rapide<br>
                        <i class="fas fa-check-circle" style="color: #38ef7d;"></i> Support 24/7<br>
                        <i class="fas fa-check-circle" style="color: #38ef7d;"></i> Satisfaction garantie
                    </p>
                </div>
            </div>

            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. Tous droits réservés.</p>
                
                <div class="footer-links">
                    <a href="#">Confidentialité</a>
                    <a href="#">Conditions</a>
                    <a href="#">Mentions légales</a>
                </div>

                <div class="social-links">
                    <a href="#" title="Facebook"><i class="fab fa-facebook-f"></i></a>
                    <a href="#" title="Twitter"><i class="fab fa-twitter"></i></a>
                    <a href="#" title="LinkedIn"><i class="fab fa-linkedin-in"></i></a>
                    <a href="#" title="Instagram"><i class="fab fa-instagram"></i></a>
                </div>
            </div>
        </div>
    </footer>

    <!-- Back to Top -->
    <button class="back-to-top" id="backToTop" onclick="scrollToTop()">
        <i class="fas fa-arrow-up"></i>
    </button>

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
                // Close if:
                // 1. Click is completely outside the menu (and not on toggler)
                // 2. Click is ON the menu container itself (which catches the overlay ::before clicks)
                //    but NOT on its children (links, etc.)
                const isClickOnOverlay = (event.target === mobileMenu);
                const isClickOutside = !mobileMenu.contains(event.target);
                const isClickOnToggler = (toggler && toggler.contains(event.target));

                if ((isClickOutside || isClickOnOverlay) && !isClickOnToggler) {
                    setMenuState(false);
                }
            }
        });
        
        // Removed the stopPropagation listener on mobileMenu that was blocking overlay clicks

    });

    window.addEventListener('scroll', function() {
        const backToTop = document.getElementById('backToTop');
        if (backToTop) {
            backToTop.style.display = window.pageYOffset > 300 ? 'flex' : 'none';
        }
    });

    function scrollToTop() {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
    </script>
</body>
</html>