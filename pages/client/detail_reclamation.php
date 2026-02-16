<?php
/**
 * Détail Réclamation Client - Strict Harmonization (V3)
 * Alignement total sur le design commercial / Menu Hamburger
 */

require_once '../../config/database.php';
require_once '../../api/functions.php';

// Vérifier que l'utilisateur est connecté et est un client
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'client') {
    header('Location: login.php');
    exit;
}

$db = getDB();
$userId = $_SESSION['user_id'];
$ticketId = (int)($_GET['id'] ?? 0);

// Récupérer le ticket
$stmt = $db->prepare("
    SELECT t.*, 
           c.nom as categorie_nom, c.couleur as categorie_couleur,
           s.nom as statut_nom, s.couleur as statut_couleur
    FROM tickets t
    LEFT JOIN categories c ON t.categorie_id = c.id
    LEFT JOIN statuts s ON t.statut_id = s.id
    WHERE t.id = ? AND t.client_id = ?
");
$stmt->execute([$ticketId, $userId]);
$ticket = $stmt->fetch();

if (!$ticket) {
    header('Location: dashboard.php');
    exit;
}

// Récupération de l'activité (Filtrée client)
$activites = [];
// Status acts
$stmt = $db->prepare("SELECT date_action as date, action, details, 'status' as type FROM historique_actions WHERE ticket_id = ? AND (action LIKE '%statut%' OR action IN ('creation', 'validation', 'rejet', 'decision_rmq')) ORDER BY date DESC");
$stmt->execute([$ticketId]);
$activites = array_merge($activites, $stmt->fetchAll());
// Client comments
$stmt = $db->prepare("SELECT date_creation as date, 'Votre message' as action, contenu as details, 'comment' as type FROM commentaires WHERE ticket_id = ? AND (auteur_role = 'client' OR visible_pour_client = 1)");
$stmt->execute([$ticketId]);
$activites = array_merge($activites, $stmt->fetchAll());

usort($activites, function($a, $b) { return strtotime($b['date']) - strtotime($a['date']); });

// Pièces jointes
$stmt = $db->prepare("SELECT * FROM pieces_jointes WHERE ticket_id = ? ORDER BY date_upload DESC");
$stmt->execute([$ticketId]);
$piecesJointes = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Détail Ticket #<?php echo htmlspecialchars($ticket['numero_ticket']); ?> - SCPC</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/fontawesome/css/all.min.css">
</head>
<body style="background-color: var(--gray-100);">

    <!-- Navigation (Harmonisée) -->
    <nav class="navbar">
        <div class="container">
            <a class="navbar-brand" href="../../index.php" style="display: flex; align-items: center; gap: 10px;">
                <img src="../../assets/images/logo.png" alt="Logo SCPC" style="height: 40px; width: auto;">
                <div style="display: flex; flex-direction: column; line-height: 1.2;">
                    <span style="font-weight: 700; font-size: 1.1rem; color: #1A1A1A;">SCPC Comptoir Hammami</span>
                    <span style="font-size: 0.75rem; color: #666; font-weight: 500; text-transform: none; letter-spacing: 0;"><?php echo SITE_NAME; ?></span>
                </div>
                <span class="navbar-brand-badge" style="background: var(--gradient-primary);">
                    Client
                </span>
            </a>
            <div class="navbar-menu" id="mainMenu">
                <a href="dashboard.php" class="navbar-link active">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
                <a href="nouvelle_reclamation.php" class="navbar-link">
                    <i class="fas fa-plus-circle"></i> Nouvelle
                </a>
                <a href="../../pages/logout.php" class="navbar-link">
                    <i class="fas fa-sign-out-alt"></i> Déconnexion
                </a>
            </div>
            <button id="navbarToggler" class="navbar-toggler"><i class="fas fa-bars"></i></button>
        </div>
    </nav>

    <!-- Mobile Menu (Harmonisé) -->
    <div class="mobile-menu" id="mobileMenu">
        <div class="mobile-menu-header">
            <strong><i class="fas fa-user-circle"></i> Client</strong>
            <p class="mobile-menu-subtitle">Espace Personnel</p>
        </div>
        <a href="dashboard.php" class="mobile-menu-link active"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a>
        <a href="nouvelle_reclamation.php" class="mobile-menu-link"><i class="fas fa-plus-circle"></i> <span>Nouvelle Réclamation</span></a>
        <a href="../../pages/logout.php" class="mobile-menu-link" style="color: var(--danger-color) !important;"><i class="fas fa-sign-out-alt"></i> <span>Déconnexion</span></a>
    </div>

    <div class="container" style="padding: 2rem 1rem;">
        
        <!-- Retour Button -->
        <div style="margin-bottom: 1.5rem;">
            <a href="dashboard.php" class="btn btn-secondary btn-sm" style="display: inline-flex; align-items: center; gap: 0.5rem; background: white; border: 1px solid #e2e8f0; color: #64748b; font-weight: 700;">
                <i class="fas fa-arrow-left"></i> Retour au Dashboard
            </a>
        </div>

        <!-- Section Header Glass -->
        <div class="glass-card" style="padding: 2rem; margin-bottom: 2rem; border-bottom: 4px solid var(--primary); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
            <div>
                <h1 style="margin: 0; font-size: 1.7rem; font-weight: 800; color: var(--text-dark);">
                    Dossier #<?php echo htmlspecialchars($ticket['numero_ticket']); ?>
                </h1>
                <p style="margin: 0.25rem 0 0 0; color: var(--text-muted); font-weight: 600;">
                    <?php echo htmlspecialchars($ticket['sujet']); ?>
                </p>
            </div>
            <div style="display: flex; align-items: center; gap: 1rem;">
                <span style="background: <?php echo $ticket['statut_id'] == 4 ? '#10B98122' : 'var(--primary)11'; ?>; color: <?php echo $ticket['statut_id'] == 4 ? '#10B981' : 'var(--primary)'; ?>; padding: 0.6rem 1.5rem; border-radius: 50px; font-weight: 800; font-size: 0.9rem; border: 1px solid <?php echo $ticket['statut_id'] == 4 ? '#10B98133' : 'var(--primary)33'; ?>; text-transform: uppercase; letter-spacing: 0.5px;">
                    <?php echo htmlspecialchars($ticket['statut_nom']); ?>
                </span>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 2rem;">
            
            <!-- Col Gauche -->
            <div style="display: flex; flex-direction: column; gap: 2rem;">
                
                <!-- 1. Infos Générales -->
                <div class="glass-card" style="padding: 2rem;">
                    <h3 style="margin: 0 0 1.5rem 0; font-size: 1.25rem; font-weight: 800; display: flex; align-items: center; gap: 0.75rem; color: var(--text-dark);">
                        <i class="fas fa-info-circle" style="color: var(--primary);"></i>
                        Informations du dossier
                    </h3>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
                        <div>
                            <p style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.25rem;">Catégorie</p>
                            <p style="font-weight: 700; display: inline-flex; align-items: center; gap: 0.5rem;">
                                 <span style="width: 10px; height: 10px; border-radius: 50%; background: <?php echo $ticket['categorie_couleur']; ?>;"></span>
                                 <?php echo htmlspecialchars($ticket['categorie_nom']); ?>
                            </p>
                        </div>
                        <div>
                            <p style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.25rem;">Date d'ouverture</p>
                            <p style="font-weight: 700;"><?php echo date('d/m/Y à H:i', strtotime($ticket['date_creation'])); ?></p>
                        </div>
                        <div>
                            <p style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.25rem;">Type de réclamation</p>
                            <p style="font-weight: 700;"><?php echo htmlspecialchars($ticket['type_ticket'] ?: 'Standard'); ?></p>
                        </div>
                    </div>
                    <div>
                        <p style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.5rem;">Description explicite</p>
                        <div style="background: #F9FAFB; padding: 1.5rem; border-radius: 12px; border: 1px solid #E5E7EB; line-height: 1.7; color: #374151;">
                            <?php echo nl2br(htmlspecialchars($ticket['description'])); ?>
                        </div>
                    </div>
                </div>

                <!-- 2. Décision RMQ -->
                <div class="glass-card" style="padding: 2rem; border-left: 6px solid #10B981;">
                    <h3 style="margin: 0 0 1.5rem 0; font-size: 1.25rem; font-weight: 800; display: flex; align-items: center; gap: 0.75rem; color: var(--text-dark);">
                        <i class="fas fa-gavel" style="color: #10B981;"></i>
                        Décision RMQ & Résolution
                    </h3>
                    
                    <?php if (empty($ticket['decision_rmq'])): ?>
                        <div style="background: #F8FAFC; padding: 1.5rem; border-radius: 12px; text-align: center; color: #94A3B8; font-weight: 600; font-style: italic; border: 1px dashed #E2E8F0;">
                            <i class="fas fa-clock" style="margin-right: 0.5rem;"></i> Non disponible pour le moment (dossier en cours d'analyse)
                        </div>
                    <?php else: ?>
                        <div style="background: #FFF; padding: 1.5rem; border-radius: 12px; border: 1px solid #D1FAE5; margin-bottom: 1.5rem;">
                            <p style="font-size: 0.75rem; font-weight: 700; color: #059669; text-transform: uppercase; margin-bottom: 0.5rem;">Verdict Final</p>
                            <p style="font-size: 1.1rem; font-weight: 700; color: #064E3B; line-height: 1.6;">
                                <?php echo nl2br(htmlspecialchars($ticket['decision_rmq'])); ?>
                            </p>
                        </div>
                        <div style="background: #F0FDF4; padding: 1.5rem; border-radius: 12px; border: 1px solid #BBF7D0;">
                            <p style="font-size: 0.75rem; font-weight: 700; color: #166534; text-transform: uppercase; margin-bottom: 0.5rem;">Commentaire & Preuve</p>
                            <p style="color: #14532D; margin-bottom: 1.5rem; font-weight: 500;">
                                <?php echo nl2br(htmlspecialchars($ticket['commentaire_resolution'])); ?>
                            </p>
                            <?php if (!empty($ticket['preuve_resolution'])): ?>
                                <a href="../../uploads/<?php echo htmlspecialchars($ticket['preuve_resolution']); ?>" target="_blank" class="btn btn-primary btn-sm" style="background: #10B981; border: none; padding: 0.75rem 1.25rem;">
                                    <i class="fas fa-file-shield"></i> Télécharger la preuve de résolution
                                </a>
                            <?php endif; ?>
                        </div>
                        <div style="margin-top: 1rem; text-align: right; font-size: 0.75rem; color: #94A3B8; font-weight: 600;">
                            Décidé le : <?php echo date('d/m/Y à H:i', strtotime($ticket['date_decision'])); ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- NEW: TRAITEMENT AQF (Read Only) -->
                <?php if (!empty($ticket['statut_aqf'])): ?>
                <div class="glass-card" style="padding: 2rem; border-left: 6px solid <?php echo ($ticket['statut_aqf'] === 'retenu') ? '#10B981' : (($ticket['statut_aqf'] === 'non retenu') ? '#EF4444' : '#3B82F6'); ?>; margin-bottom: 2rem;">
                    <h3 style="margin: 0 0 1.5rem 0; font-size: 1.25rem; font-weight: 800; display: flex; align-items: center; gap: 0.75rem; color: var(--text-dark);">
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
                        <p style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.5rem;">Commentaire de l'expert</p>
                        <p style="color: #334155; line-height: 1.6; font-size: 0.95rem; font-weight: 500;">
                            <?php echo nl2br(htmlspecialchars($ticket['commentaire_resolution_aqf'])); ?>
                        </p>
                    </div>

                    <?php if (!empty($ticket['preuve_resolution_aqf'])): ?>
                        <a href="../../uploads/<?php echo htmlspecialchars($ticket['preuve_resolution_aqf']); ?>" target="_blank" class="btn btn-secondary btn-sm" style="background: white; border: 1px solid #E2E8F0; color: #3B82F6; font-weight: 700;">
                            <i class="fas fa-file-pdf"></i> Consulter le document d'expertise
                        </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- 3. Historique Interactif -->
                <div class="glass-card" style="padding: 2rem;">
                    <h3 style="margin: 0 0 2rem 0; font-size: 1.25rem; font-weight: 800; display: flex; align-items: center; gap: 0.75rem; color: var(--text-dark);">
                        <i class="fas fa-history" style="color: var(--primary);"></i>
                        Suivi d'activité de votre dossier
                    </h3>
                    
                    <?php if (empty($activites)): ?>
                        <p style="text-align: center; color: #94A3B8; font-style: italic;">Aucune activité enregistrée.</p>
                    <?php else: ?>
                        <div style="display: flex; flex-direction: column; gap: 1.5rem; position: relative; padding-left: 1.5rem; border-left: 3px solid #F3F4F6;">
                            <?php foreach ($activites as $act): ?>
                                <div style="position: relative;">
                                    <div style="position: absolute; left: calc(-1.5rem - 9px); top: 5px; width: 14px; height: 14px; background: <?php echo $act['type'] == 'status' ? 'var(--primary)' : '#3B82F6'; ?>; border: 3px solid white; border-radius: 50%; box-shadow: 0 0 0 2px #F3F4F6;"></div>
                                    <p style="font-size: 0.75rem; font-weight: 800; color: #94A3B8; margin-bottom: 0.4rem;">
                                        <?php echo date('d/m/Y H:i', strtotime($act['date'])); ?>
                                    </p>
                                    <div style="background: #F9FAFB; padding: 1.25rem; border-radius: 12px; border: 1px solid #F1F5F9;">
                                        <p style="font-weight: 800; color: #1E293B; font-size: 0.95rem; margin-bottom: 0.25rem;">
                                             <?php echo htmlspecialchars($act['action']); ?>
                                        </p>
                                        <?php if (!empty($act['details'])): ?>
                                            <p style="font-size: 0.9rem; color: #64748B; line-height: 1.5; margin: 0;"><?php echo nl2br(htmlspecialchars($act['details'])); ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Col Droite -->
            <div style="display: flex; flex-direction: column; gap: 2rem;">
                
                <!-- Statut Highlight Client -->
                <div class="glass-card" style="padding: 2rem; text-align: center; background: white;">
                    <div style="width: 70px; height: 70px; background: var(--primary)11; color: var(--primary); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.8rem; margin: 0 auto 1.5rem auto; border: 2px solid var(--primary)22;">
                        <i class="fas fa-wave-square"></i>
                    </div>
                    <p style="font-size: 0.75rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.5rem; letter-spacing: 1px;">Évolution du traitement</p>
                    <h4 style="margin: 0; font-weight: 800; color: var(--text-dark); font-size: 1.4rem;">
                        <?php echo htmlspecialchars($ticket['statut_nom']); ?>
                    </h4>
                </div>

                <!-- Pièces Jointes -->
                <div class="glass-card" style="padding: 2rem;">
                    <h4 style="margin: 0 0 1.5rem 0; font-weight: 800; display: flex; align-items: center; gap: 0.75rem;">
                         <i class="fas fa-paperclip" style="color: var(--primary);"></i> Fichiers
                    </h4>
                    <?php if (empty($piecesJointes)): ?>
                        <div style="text-align: center; padding: 1.5rem; color: #94A3B8; font-style: italic; font-size: 0.85rem; background: #F8FAFC; border-radius: 12px; border: 1px dashed #E2E8F0;">
                             Aucun document joint
                        </div>
                    <?php else: ?>
                        <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                            <?php foreach ($piecesJointes as $pj): ?>
                                <a href="../../uploads/<?php echo htmlspecialchars($pj['chemin_fichier']); ?>" target="_blank" style="display: flex; align-items: center; gap: 10px; padding: 1rem; background: #F9FAFB; border: 1px solid #E5E7EB; border-radius: 12px; text-decoration: none; transition: all 0.2s; border-left: 4px solid #EF4444;">
                                    <i class="fas fa-file-pdf" style="font-size: 1.2rem; color: #EF4444;"></i>
                                    <div style="flex: 1; overflow: hidden;">
                                        <p style="margin: 0; font-size: 0.8rem; font-weight: 700; color: #374151; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                            <?php echo htmlspecialchars($pj['nom_fichier']); ?>
                                        </p>
                                        <p style="margin: 0; font-size: 0.7rem; color: #94A3B8;">PDF - <?php echo round($pj['taille_fichier']/1024, 1); ?> KB</p>
                                    </div>
                                    <i class="fas fa-download" style="color: var(--text-muted); font-size: 0.9rem;"></i>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Assistance / Help -->
                <div class="glass-card" style="padding: 1.5rem; background: linear-gradient(135deg, #FFF 0%, #FFF5F0 100%);">
                    <h4 style="margin: 0 0 0.5rem 0; font-weight: 800; color: var(--primary);">Assistance</h4>
                    <p style="font-size: 0.85rem; color: #64748B; margin-bottom: 1rem; line-height: 1.5;">Vous avez une question sur ce dossier ? Nos agents sont à votre disposition.</p>
                    <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                        <a href="mailto:support@hammami.com.tn" style="color: #1E293B; font-weight: 700; font-size: 0.85rem; text-decoration: none; display: flex; align-items: center; gap: 8px;">
                            <i class="fas fa-envelope" style="color: var(--primary);"></i> support@hammami.com.tn
                        </a>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- Footer (Inspiré commercial) -->
    <footer class="footer" style="background: white; border-top: 1px solid #e2e8f0; margin-top: 4rem;">
        <div class="container">
            <p style="font-weight: 600; color: var(--text-muted);">&copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?> - État du dossier en temps réel</p>
        </div>
    </footer>

    <!-- Scripts Hamburger (Synchronisés Commercial) -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const mobileMenu = document.getElementById('mobileMenu');
        const toggler = document.getElementById('navbarToggler');
        const togglerIcon = toggler ? toggler.querySelector('i') : null;

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

        if (toggler) {
            toggler.addEventListener('click', function(e) {
                e.stopPropagation(); 
                const isCurrentlyOpen = mobileMenu.classList.contains('show');
                setMenuState(!isCurrentlyOpen);
            });
        }

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

    <style>
        .navbar-brand-badge {
            color: white; padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.75rem; font-weight: 800; margin-left: 10px; letter-spacing: 0.5px;
        }
        .btn-sm:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
    </style>
</body>
</html>
