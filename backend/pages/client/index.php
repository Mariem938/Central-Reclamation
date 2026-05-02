<?php
/**
 * Accès Rapide aux Formulaires
 * Fichier: pages/client/index.php
 */

require_once '../../config/database.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Formulaires de Réclamation - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/fontawesome/css/all.min.css">
    <style>
        .card-button { text-decoration: none; color: inherit; }
        .card-button:hover { transform: translateY(-5px); box-shadow: 0 20px 40px rgba(0,0,0,0.2); }
        .card-icon { font-size: 3em; margin-bottom: 1rem; }
        .badge-new { display: inline-block; background: #10B981; color: white; padding: 4px 12px; border-radius: 20px; font-size: 0.8em; font-weight: 600; margin-left: 10px; }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="container">
            <a class="navbar-brand" href="../../index.php">
                <i class="fas fa-headset"></i>
                <span><?php echo SITE_NAME; ?></span>
            </a>
            <div class="navbar-menu">
                <a href="../../index.php"><i class="fas fa-home"></i> <span>Accueil</span></a>
                <a href="index.php" class="active"><i class="fas fa-plus-circle"></i> <span>Réclamations</span></a>
                <a href="suivi_reclamation.php"><i class="fas fa-search"></i> <span>Suivi</span></a>
                <a href="../agent/login.php"><i class="fas fa-user-shield"></i> <span>Agent</span></a>
            </div>
            <button id="navbarToggler" class="navbar-toggler">
                <i class="fas fa-bars"></i>
            </button>
        </div>
    </nav>

    <div class="section" style="padding: 3rem 0; background: var(--gradient-hero);">
        <div class="container">
            <div style="text-align: center; margin-bottom: 3rem;">
                <h1 style="font-size: 2.5rem; margin-bottom: 1rem; color: white;">
                    <i class="fas fa-clipboard-list"></i> Formulaires de Réclamation
                </h1>
                <p style="font-size: 1.1rem; color: rgba(255,255,255,0.9); margin-bottom: 2rem;">
                    Choisissez le formulaire adapté à votre besoin
                </p>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 2rem; margin-bottom: 3rem;">
                
                <!-- Card 1: Formulaire Simple -->
                <a href="nouvelle_reclamation.php" class="card-button">
                    <div class="card glass-card" style="height: 100%; text-align: center; padding: 2rem;">
                        <div class="card-icon" style="color: #EF4444;">
                            <i class="fas fa-pencil-alt"></i>
                        </div>
                        <h3 style="margin-bottom: 0.5rem; color: #333;">Formulaire Simple</h3>
                        <p style="color: #666; margin-bottom: 1rem; font-size: 0.95rem;">
                            Pour les réclamations standard avec informations de base
                        </p>
                        <div style="background: #ffebee; color: #c62828; padding: 0.75rem; border-radius: 6px; margin-top: 1rem; font-size: 0.85rem;">
                            <strong>Champs:</strong> Nom, Email, Catégorie, Sujet, Description
                        </div>
                    </div>
                </a>

                <!-- Card 2: Formulaire Complet (NEW) -->
                <a href="formulaire_nc.php" class="card-button">
                    <div class="card glass-card" style="height: 100%; text-align: center; padding: 2rem; border: 2px solid #10B981; position: relative;">
                        <span class="badge-new">NOUVEAU</span>
                        <div class="card-icon" style="color: #10B981;">
                            <i class="fas fa-file-contract"></i>
                        </div>
                        <h3 style="margin-bottom: 0.5rem; color: #333;">
                            Registre Non-Conformités
                        </h3>
                        <p style="color: #666; margin-bottom: 1rem; font-size: 0.95rem;">
                            Formulaire complet PL-FR05-01 avec tous les détails
                        </p>
                        <div style="background: #e8f5e9; color: #1b5e20; padding: 0.75rem; border-radius: 6px; margin-top: 1rem; font-size: 0.85rem;">
                            <strong>Sites:</strong> 5 sites SCPC Comptoir Hammami
                        </div>
                        <div style="background: #e3f2fd; color: #0d47a1; padding: 0.75rem; border-radius: 6px; margin-top: 0.5rem; font-size: 0.85rem;">
                            <strong>Champs:</strong> +19 colonnes de données
                        </div>
                    </div>
                </a>

            </div>

            <!-- Info Section -->
            <div class="card glass-card" style="max-width: 800px; margin: 0 auto;">
                <div class="card-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                    <h3 style="margin: 0; display: flex; align-items: center; gap: 0.75rem;">
                        <i class="fas fa-lightbulb"></i> Comment choisir?
                    </h3>
                </div>
                <div class="card-body p-4">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 2rem;">
                        <div>
                            <h4 style="color: #667eea; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                                <i class="fas fa-arrow-right" style="font-size: 0.8em;"></i> Formulaire Simple
                            </h4>
                            <ul style="list-style: none; padding: 0;">
                                <li style="padding: 0.5rem 0; color: #666;"><i class="fas fa-check" style="color: #10B981; margin-right: 0.5rem;"></i>Réclamation client rapide</li>
                                <li style="padding: 0.5rem 0; color: #666;"><i class="fas fa-check" style="color: #10B981; margin-right: 0.5rem;"></i>Peu d'informations requises</li>
                                <li style="padding: 0.5rem 0; color: #666;"><i class="fas fa-check" style="color: #10B981; margin-right: 0.5rem;"></i>Parfait pour une demande simple</li>
                            </ul>
                        </div>
                        <div>
                            <h4 style="color: #10B981; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                                <i class="fas fa-arrow-right" style="font-size: 0.8em;"></i> Registre Non-Conformités
                            </h4>
                            <ul style="list-style: none; padding: 0;">
                                <li style="padding: 0.5rem 0; color: #666;"><i class="fas fa-check" style="color: #667eea; margin-right: 0.5rem;"></i>Documentation complète requise</li>
                                <li style="padding: 0.5rem 0; color: #666;"><i class="fas fa-check" style="color: #667eea; margin-right: 0.5rem;"></i>Traçabilité et analyse détaillée</li>
                                <li style="padding: 0.5rem 0; color: #666;"><i class="fas fa-check" style="color: #667eea; margin-right: 0.5rem;"></i>Conforme au registre PL-FR05-01</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Suivi Section -->
            <div style="text-align: center; margin-top: 3rem;">
                <h3 style="color: white; margin-bottom: 1rem;">
                    <i class="fas fa-search"></i> Vous avez un numéro de ticket?
                </h3>
                <a href="suivi_reclamation.php" class="btn btn-primary" style="display: inline-block; padding: 12px 30px;">
                    Suivre ma réclamation
                </a>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div style="text-align: center; padding: 2rem 0;">
                <p style="margin: 0; color: var(--text-gray);">
                    © <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. Tous droits réservés.
                </p>
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
</body>
</html>
