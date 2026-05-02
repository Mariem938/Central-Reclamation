<?php
/**
 * Vue d'un Ticket (Lecture seule) pour Commercial
 * Fichier: pages/commercial/view_ticket.php
 */

require_once '../../config/database.php';
require_once '../../api/functions.php';
require_once '../../api/functions_extended.php';

// Vérifier que l'utilisateur est connecté et est un commercial
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'commercial') {
    header('Location: ../agent/login.php?role=commercial');
    exit;
}

$db = getDB();
$ticketId = intval($_GET['id'] ?? 0);

if ($ticketId === 0) {
    header('Location: dashboard.php');
    exit;
}

// Récupérer le ticket
$stmt = $db->prepare("
    SELECT t.*, 
           c.nom as categorie_nom, c.couleur as categorie_couleur,
           s.nom as statut_nom, s.couleur as statut_couleur,
           u.nom as client_nom_compte, u.prenom as client_prenom_compte, u.email as client_email_compte
    FROM tickets t
    LEFT JOIN categories c ON t.categorie_id = c.id
    LEFT JOIN statuts s ON t.statut_id = s.id
    LEFT JOIN utilisateurs u ON t.client_id = u.id
    WHERE t.id = ?
");
$stmt->execute([$ticketId]);
$ticket = $stmt->fetch();

if (!$ticket) {
    header('Location: dashboard.php');
    exit;
}

// Récupérer l'historique des actions
$stmt = $db->prepare("
    SELECT ha.*, u.nom as user_nom, u.prenom as user_prenom, u.role as user_role,
           s_ancien.nom as ancien_statut_nom, s_nouveau.nom as nouveau_statut_nom
    FROM historique_actions ha
    LEFT JOIN utilisateurs u ON ha.utilisateur_id = u.id
    LEFT JOIN statuts s_ancien ON ha.ancien_statut_id = s_ancien.id
    LEFT JOIN statuts s_nouveau ON ha.nouveau_statut_id = s_nouveau.id
    WHERE ha.ticket_id = ?
    ORDER BY ha.date_action DESC
");
$stmt->execute([$ticketId]);
$historique = $stmt->fetchAll();

// Récupérer les pièces jointes
$stmt = $db->prepare("SELECT * FROM pieces_jointes WHERE ticket_id = ? ORDER BY date_upload DESC");
$stmt->execute([$ticketId]);
$piecesJointes = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Détails Ticket - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/fontawesome/css/all.min.css">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="container">
            <a class="navbar-brand" href="../../index.php" style="display: flex; align-items: center; gap: 10px;">
                <img src="../../assets/images/logo.png" alt="Logo SCPC" style="height: 40px; width: auto;">
                <div style="display: flex; flex-direction: column; line-height: 1.2;">
                    <span style="font-weight: 700; font-size: 1.1rem;">SCPC Comptoir Hammami</span>
                    <span style="font-size: 0.8rem; opacity: 0.9; font-weight: normal;"><?php echo SITE_NAME; ?></span>
                </div>
            </a>
            
            <div class="navbar-menu" id="mainMenu">
                <a href="dashboard.php" class="navbar-link">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
                <a href="create_claim.php" class="navbar-link">
                    <i class="fas fa-plus-circle"></i>
                    <span>Nouvelle Réclamation</span>
                </a>
                <a href="../logout.php" class="navbar-link">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Déconnexion</span>
                </a>
            </div>
            
            <button id="navbarToggler" class="navbar-toggler">
                <i class="fas fa-bars"></i>
            </button>
        </div>
    </nav>

    <!-- Mobile Menu -->
    <!-- Mobile Menu -->
    <div class="mobile-menu" id="mobileMenu">
        <div class="mobile-menu-header">
            <i class="fas fa-briefcase"></i>
            <strong>Commercial</strong>
            <p class="mobile-menu-subtitle">Espace Agent</p>
        </div>
        <a href="dashboard.php" class="mobile-menu-link"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
        <a href="create_claim.php" class="mobile-menu-link"><i class="fas fa-plus-circle"></i> Nouvelle Réclamation</a>
        <a href="../logout.php" class="mobile-menu-link"><i class="fas fa-sign-out-alt"></i> Déconnexion</a>
    </div>

    <div class="container" style="padding: 2rem 1rem;">
        <!-- Bouton retour -->
        <div style="margin-bottom: 1.5rem;">
            <a href="dashboard.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Retour au Dashboard
            </a>
        </div>
        
        <!-- En-tête du ticket -->
        <div class="glass-card" style="padding: 2rem; margin-bottom: 2rem;">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem;">
                <div>
                    <div style="display: flex; align-items: center; gap: 1rem; flex-wrap: wrap;">
                        <h1 style="margin: 0; font-size: 1.5rem;">
                            <i class="fas fa-ticket-alt" style="color: var(--primary);"></i>
                            <?php echo htmlspecialchars($ticket['numero_ticket']); ?>
                        </h1>
                        <span style="background: <?php echo $ticket['statut_couleur']; ?>; color: white; padding: 0.5rem 1rem; border-radius: 20px; font-weight: 600;">
                            <?php echo htmlspecialchars($ticket['statut_nom']); ?>
                        </span>
                        <?php if (!empty($ticket['cree_par_commercial'])): ?>
                        <span class="badge badge-warning">
                            <i class="fas fa-briefcase"></i> Créé par Commercial
                        </span>
                        <?php endif; ?>
                    </div>
                    <h2 style="margin: 1rem 0 0 0; color: var(--text-dark);"><?php echo htmlspecialchars($ticket['sujet']); ?></h2>
                </div>
                <div style="text-align: right;">
                    <span style="background: <?php echo $ticket['categorie_couleur']; ?>; color: white; padding: 0.5rem 1rem; border-radius: 20px;">
                        <?php echo htmlspecialchars($ticket['categorie_nom']); ?>
                    </span>
                    <p style="margin: 0.5rem 0 0 0; color: var(--text-muted); font-size: 0.875rem;">
                        <i class="fas fa-calendar-alt"></i> <?php echo date('d/m/Y H:i', strtotime($ticket['date_creation'])); ?>
                    </p>
                </div>
            </div>
        </div>
        
        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 2rem;">
            <!-- Colonne principale -->
            <div>
                <!-- Informations Client -->
                <div class="glass-card" style="padding: 2rem; margin-bottom: 2rem;">
                    <h3 style="margin: 0 0 1.5rem 0; display: flex; align-items: center; gap: 0.75rem;">
                        <i class="fas fa-user-circle" style="color: var(--primary);"></i>
                        Informations Client
                    </h3>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                        <div>
                            <label style="font-size: 0.875rem; color: var(--text-muted);">Nom</label>
                            <p style="margin: 0.25rem 0 0 0; font-weight: 600;"><?php echo htmlspecialchars($ticket['nom_client']); ?></p>
                        </div>
                        <div>
                            <label style="font-size: 0.875rem; color: var(--text-muted);">Prénom</label>
                            <p style="margin: 0.25rem 0 0 0; font-weight: 600;"><?php echo htmlspecialchars($ticket['prenom_client']); ?></p>
                        </div>
                        <div>
                            <label style="font-size: 0.875rem; color: var(--text-muted);">Email</label>
                            <p style="margin: 0.25rem 0 0 0; font-weight: 600;"><?php echo htmlspecialchars($ticket['email_client']); ?></p>
                        </div>
                        <div>
                            <label style="font-size: 0.875rem; color: var(--text-muted);">Téléphone</label>
                            <p style="margin: 0.25rem 0 0 0; font-weight: 600;"><?php echo htmlspecialchars($ticket['telephone_client'] ?? 'Non renseigné'); ?></p>
                        </div>
                    </div>
                </div>
                
                <!-- Description -->
                <div class="glass-card" style="padding: 2rem; margin-bottom: 2rem;">
                    <h3 style="margin: 0 0 1.5rem 0; display: flex; align-items: center; gap: 0.75rem;">
                        <i class="fas fa-align-left" style="color: var(--primary);"></i>
                        Description
                    </h3>
                    <div style="background: #F5F5F5; padding: 1.5rem; border-radius: var(--radius-md); white-space: pre-wrap; line-height: 1.6;">
                        <?php echo htmlspecialchars($ticket['description']); ?>
                    </div>
                </div>
                
                <!-- NEW: Décision RMQ & Preuve (Read Only) -->
                <?php if (!empty($ticket['decision_rmq'])): ?>
                <div class="glass-card" style="padding: 2rem; margin-bottom: 2rem; border-left: 4px solid var(--primary);">
                    <h3 style="margin: 0 0 1.5rem 0; display: flex; align-items: center; gap: 0.75rem;">
                        <i class="fas fa-gavel" style="color: var(--primary);"></i>
                        Décision RMQ & Résolution
                    </h3>
                    
                    <div style="margin-bottom: 1.5rem;">
                        <label style="font-size: 0.875rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Décision Finale</label>
                        <p style="margin: 0.5rem 0 0 0; line-height: 1.6; color: var(--text-dark);"><?php echo nl2br(htmlspecialchars($ticket['decision_rmq'])); ?></p>
                    </div>

                    <div style="background: #F9FAFB; padding: 1.5rem; border-radius: 8px; border: 1px solid #E5E7EB;">
                        <p style="color: var(--text-muted); font-size: 0.8rem; font-weight: 700; text-transform: uppercase; margin-bottom: 0.5rem;">Preuve & Commentaire de résolution</p>
                        <p style="color: var(--text-dark); margin-bottom: 1rem;"><?php echo nl2br(htmlspecialchars($ticket['commentaire_resolution'])); ?></p>
                        
                        <?php if (!empty($ticket['preuve_resolution'])): ?>
                            <a href="../../uploads/<?php echo htmlspecialchars($ticket['preuve_resolution']); ?>" target="_blank" class="btn btn-secondary" style="font-size: 0.85rem; padding: 0.4rem 0.8rem;">
                                <i class="fas fa-download"></i> Voir la preuve de résolution
                            </a>
                        <?php endif; ?>
                    </div>
                    
                    <div style="margin-top: 1rem; text-align: right; color: var(--text-muted); font-size: 0.75rem;">
                        Validé le : <?php echo date('d/m/Y H:i', strtotime($ticket['date_decision'])); ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- NEW: TRAITEMENT AQF (Read Only) -->
                <?php if (!empty($ticket['statut_aqf'])): ?>
                <div class="glass-card" style="padding: 2rem; margin-bottom: 2rem; border-left: 4px solid #3B82F6;">
                    <h3 style="margin: 0 0 1.5rem 0; display: flex; align-items: center; gap: 0.75rem;">
                        <i class="fas fa-microchip" style="color: #3B82F6;"></i>
                        Expertise Qualité (AQF)
                    </h3>
                    
                    <div style="background: #F8FAFC; padding: 1.25rem; border-radius: 12px; border: 1px solid #E2E8F0; margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <p style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.25rem;">Verdict Qualité</p>
                            <p style="font-weight: 800; color: <?php echo ($ticket['statut_aqf'] === 'retenu') ? '#10B981' : (($ticket['statut_aqf'] === 'non retenu') ? '#EF4444' : '#3B82F6'); ?>; text-transform: uppercase;">
                                ● <?php echo htmlspecialchars($ticket['statut_aqf']); ?>
                            </p>
                        </div>
                        <div style="text-align: right; font-size: 0.75rem; color: #94A3B8; font-weight: 600;">
                            Traitée le : <?php echo date('d/m/Y', strtotime($ticket['date_traitement_aqf'])); ?>
                        </div>
                    </div>

                    <?php if ($ticket['statut_aqf'] === 'non retenu' && !empty($ticket['raison_non_retenu_aqf'])): ?>
                        <div style="background: #FFF1F2; border-left: 4px solid #EF4444; padding: 1.25rem; border-radius: 0 8px 8px 0; margin-bottom: 1.5rem;">
                            <p style="font-size: 0.75rem; font-weight: 700; color: #BE123C; text-transform: uppercase; margin-bottom: 0.4rem;">Motif du rejet</p>
                            <p style="color: #9F1239; font-size: 0.9rem; font-weight: 500;"><?php echo nl2br(htmlspecialchars($ticket['raison_non_retenu_aqf'])); ?></p>
                        </div>
                    <?php endif; ?>

                    <div style="margin-bottom: 1.5rem;">
                        <p style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.5rem;">Commentaire de l'expert AQF</p>
                        <p style="color: #334155; line-height: 1.6; font-size: 0.95rem; font-weight: 500;">
                            <?php echo nl2br(htmlspecialchars($ticket['commentaire_resolution_aqf'])); ?>
                        </p>
                    </div>

                    <?php if (!empty($ticket['preuve_resolution_aqf'])): ?>
                        <a href="../../uploads/<?php echo htmlspecialchars($ticket['preuve_resolution_aqf']); ?>" target="_blank" class="btn btn-secondary" style="font-size: 0.85rem; padding: 0.4rem 0.8rem; background: white; border: 1px solid #E2E8F0; color: #3B82F6;">
                            <i class="fas fa-file-pdf"></i> Voir le document d'expertise AQF
                        </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- Pièces jointes -->
                <?php if (!empty($piecesJointes)): ?>
                <div class="glass-card" style="padding: 2rem; margin-bottom: 2rem;">
                    <h3 style="margin: 0 0 1.5rem 0; display: flex; align-items: center; gap: 0.75rem;">
                        <i class="fas fa-paperclip" style="color: var(--primary);"></i>
                        Pièces jointes (<?php echo count($piecesJointes); ?>)
                    </h3>
                    <div style="display: flex; flex-wrap: wrap; gap: 1rem;">
                        <?php foreach ($piecesJointes as $piece): ?>
                        <a href="../../uploads/<?php echo htmlspecialchars($piece['chemin_fichier']); ?>" target="_blank" 
                           style="display: flex; align-items: center; gap: 0.5rem; padding: 0.75rem 1rem; background: #F5F5F5; border-radius: var(--radius-md); text-decoration: none; color: var(--text-dark);">
                            <i class="fas fa-file"></i>
                            <?php echo htmlspecialchars($piece['nom_fichier']); ?>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Colonne latérale -->
            <div>
                <!-- Détails -->
                <div class="glass-card" style="padding: 2rem; margin-bottom: 2rem;">
                    <h3 style="margin: 0 0 1.5rem 0; display: flex; align-items: center; gap: 0.75rem;">
                        <i class="fas fa-info-circle" style="color: var(--primary);"></i>
                        Détails
                    </h3>
                    <div style="display: flex; flex-direction: column; gap: 1rem;">
                        <?php if ($ticket['date_cloture']): ?>
                        <div>
                            <label style="font-size: 0.875rem; color: var(--text-muted);">Date de clôture</label>
                            <p style="margin: 0.25rem 0 0 0; font-weight: 600;"><?php echo date('d/m/Y H:i', strtotime($ticket['date_cloture'])); ?></p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Historique -->
                <div class="glass-card" style="padding: 2rem;">
                    <h3 style="margin: 0 0 1.5rem 0; display: flex; align-items: center; gap: 0.75rem;">
                        <i class="fas fa-history" style="color: var(--primary);"></i>
                        Historique
                    </h3>
                    <?php if (empty($historique)): ?>
                    <p style="color: var(--text-muted); text-align: center;">Aucun historique</p>
                    <?php else: ?>
                    <div style="display: flex; flex-direction: column; gap: 1rem;">
                        <?php foreach ($historique as $action): ?>
                        <div style="padding: 1rem; background: #F5F5F5; border-radius: var(--radius-md); border-left: 3px solid var(--primary);">
                            <p style="margin: 0; font-weight: 600; font-size: 0.875rem;">
                                <?php echo htmlspecialchars($action['action']); ?>
                            </p>
                            <?php if ($action['ancien_statut_nom'] && $action['nouveau_statut_nom']): ?>
                            <p style="margin: 0.25rem 0 0 0; font-size: 0.8125rem; color: var(--text-muted);">
                                <?php echo htmlspecialchars($action['ancien_statut_nom']); ?> → <?php echo htmlspecialchars($action['nouveau_statut_nom']); ?>
                            </p>
                            <?php endif; ?>
                            <p style="margin: 0.5rem 0 0 0; font-size: 0.75rem; color: var(--text-gray);">
                                <?php echo date('d/m/Y H:i', strtotime($action['date_action'])); ?>
                                <?php if ($action['user_prenom']): ?>
                                - <?php echo htmlspecialchars($action['user_prenom'] . ' ' . $action['user_nom']); ?>
                                <?php endif; ?>
                            </p>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Note: Lecture seule -->
        <div class="alert alert-info" style="margin-top: 2rem;">
            <i class="fas fa-info-circle"></i>
            <div>
                <strong>Mode lecture seule</strong><br>
                En tant que commercial, vous pouvez consulter les détails de cette réclamation mais vous ne pouvez pas la modifier.
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer" style="margin-top: 3rem;">
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?> - Tous droits réservés</p>
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
                mobileMenu.classList.add('show'); // Changed from 'active' to 'show' for consistency
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
