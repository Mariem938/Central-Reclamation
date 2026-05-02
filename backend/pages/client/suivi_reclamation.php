<?php
/**
 * Suivi de Réclamation - Premium Design
 */
require_once '../../config/database.php';
require_once '../../api/functions.php';

$error = '';
$ticket = null;
$historique = [];
$commentaires = [];
$piecesJointes = [];

$numeroTicket = $_GET['ticket'] ?? $_GET['numero'] ?? $_POST['numero'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' || !empty($numeroTicket)) {
    $numeroTicket = cleanInput($numeroTicket);
    if (empty($numeroTicket)) {
        $error = 'Veuillez saisir un numéro de ticket.';
    } else {
        $ticket = getTicketByNumero($numeroTicket);
        if ($ticket) {
            $commentaires = getCommentairesTicket($ticket['id'], 'public');
            $piecesJointes = getPiecesJointes($ticket['id']);
        } else {
            $error = 'Ticket introuvable.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Suivi - <?php echo SITE_NAME; ?></title>
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
                <a href="nouvelle_reclamation.php"><i class="fas fa-plus-circle"></i> <span>Nouvelle</span></a>
                <a href="suivi_reclamation.php" class="active"><i class="fas fa-search"></i> <span>Suivi</span></a>
            </div>
            <button id="navbarToggler" class="navbar-toggler"><i class="fas fa-bars"></i></button>
        </div>
    </nav>
    <!-- Mobile Menu -->
    <div class="mobile-menu" id="mobileMenu">
        <div class="mobile-menu-header">
            <i class="fas fa-search"></i>
            <strong>Client</strong>
            <p class="mobile-menu-subtitle">Espace Suivi</p>
        </div>
        <a href="../../index.php" class="mobile-menu-link"><i class="fas fa-home"></i> <span>Accueil</span></a>
        <a href="nouvelle_reclamation.php" class="mobile-menu-link"><i class="fas fa-plus-circle"></i> <span>Nouvelle</span></a>
        <a href="suivi_reclamation.php" class="mobile-menu-link active"><i class="fas fa-search"></i> <span>Suivi</span></a>
        <a href="login.php" class="mobile-menu-link"><i class="fas fa-sign-in-alt"></i> <span>Connexion</span></a>
    </div>

    <div class="section" style="padding-top: 3rem; padding-bottom: 3rem; background: var(--gradient-hero);">
        <div class="container">
            <?php if (!$ticket): ?>
            <!-- Search Form - Hero Style -->
            <div class="card glass-card fade-in" style="max-width: 600px; margin: 0 auto;">
                <div class="card-header" style="text-align: center; background: var(--surface-light);">
                    <h2 style="margin: 0; display: flex; align-items: center; justify-content: center; gap: 0.75rem;">
                        <i class="fas fa-search" style="color: var(--primary);"></i> 
                        Suivre ma Réclamation
                    </h2>
                </div>
                <div class="card-body p-5">
                    <?php if ($error): ?>
                    <div class="alert alert-danger" style="margin-bottom: 1.5rem;">
                        <i class="fas fa-exclamation-circle"></i> 
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                    <?php endif; ?>
                    
                    <form method="POST" style="display: flex; flex-direction: column; gap: 1.5rem;">
                        <div class="form-group">
                            <label class="form-label" style="font-weight: 600; color: var(--text-dark);">
                                <i class="fas fa-ticket-alt" style="color: var(--primary); margin-right: 0.5rem;"></i>
                                Numéro de ticket
                            </label>
                            <input type="text" name="numero" class="form-control" style="padding: 0.875rem 1rem; border: 1px solid #E0E0E0; background: var(--surface-light); color: var(--text-dark);" placeholder="Ex: TK-202501-0001" value="<?php echo htmlspecialchars($numeroTicket); ?>" required>
                        </div>
                        <button type="submit" class="btn btn-primary" style="width: 100%; padding: 0.875rem; font-size: 1rem;">
                            <i class="fas fa-search"></i> Rechercher
                        </button>
                    </form>
                    
                    <div class="alert alert-info" style="margin-top: 2rem; background: rgba(255, 107, 53, 0.1); border: 1px solid var(--primary); color: var(--text-dark);">
                        <i class="fas fa-info-circle" style="color: var(--primary); margin-right: 0.5rem;"></i>
                        Votre numéro de ticket a été envoyé par email lors de la création.
                    </div>
                    
                    <div style="text-align: center; margin-top: 1.5rem;">
                        <a href="../../index.php" style="color: var(--primary); text-decoration: none; font-weight: 500;">
                            ← Retour à l'accueil
                        </a>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <!-- Ticket Display -->
            <div class="fade-in" style="max-width: 1100px; margin: 0 auto;">
                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; margin-bottom: 2rem;">
                    <a href="suivi_reclamation.php" class="btn btn-secondary" style="display: inline-flex; align-items: center; gap: 0.5rem;">
                        <i class="fas fa-arrow-left"></i> Nouvelle recherche
                    </a>
                    <a href="statut_ticket.php?numero=<?php echo urlencode($ticket['numero_ticket']); ?>" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 0.5rem;">
                        <i class="fas fa-route"></i> Suivre le statut
                    </a>
                </div>
                
                <!-- Main Info Card -->
                <div class="card glass-card mb-4">
                    <div class="card-header">
                        <h2 style="margin-bottom: 0; display: flex; align-items: center; gap: 0.75rem;">
                            <i class="fas fa-info-circle" style="color: var(--primary);"></i>
                            Détails du Ticket
                        </h2>
                    </div>
                    <div class="card-body p-5">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 2rem; margin-bottom: 2rem;">
                            <div style="flex: 1; min-width: 250px;">
                                <h3 style="margin-bottom: 0.5rem; color: var(--text-dark);"><?php echo htmlspecialchars($ticket['sujet']); ?></h3>
                                <p style="color: var(--text-gray); margin: 0; font-size: 0.9rem;">
                                    <i class="fas fa-ticket-alt"></i> <?php echo htmlspecialchars($ticket['numero_ticket']); ?>
                                </p>
                            </div>
                            <div>
                                <span class="badge badge-lg" style="background: <?php echo $ticket['statut_couleur']; ?>; color: white; padding: 0.5rem 1rem; border-radius: var(--radius-full); font-weight: 600;">
                                    <?php echo htmlspecialchars($ticket['statut_nom']); ?>
                                </span>
                            </div>
                        </div>
                        
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 2rem; margin-bottom: 2rem;">
                            <div>
                                <p style="color: var(--text-gray); font-size: 0.875rem; font-weight: 600; margin-bottom: 0.5rem;">Client</p>
                                <p style="font-weight: 600; color: var(--text-dark); margin: 0;"><?php echo htmlspecialchars($ticket['prenom_client'] . ' ' . $ticket['nom_client']); ?></p>
                                <p style="color: var(--text-gray); font-size: 0.875rem; margin: 0.25rem 0 0; word-break: break-all;"><?php echo htmlspecialchars($ticket['email_client']); ?></p>
                            </div>
                            <div>
                                <p style="color: var(--text-gray); font-size: 0.875rem; font-weight: 600; margin-bottom: 0.5rem;">Date de création</p>
                                <p style="font-weight: 600; color: var(--text-dark); margin: 0;"><?php echo formatDate($ticket['date_creation']); ?></p>
                        <?php if (!empty($ticket['agent_nom'])): ?>
                                <p style="color: var(--text-gray); font-size: 0.875rem; margin: 0.25rem 0 0;">
                                    <i class="fas fa-user-tie"></i> <?php echo htmlspecialchars($ticket['agent_nom']); ?>
                                </p>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div style="background: linear-gradient(135deg, rgba(255, 107, 53, 0.05) 0%, rgba(255, 165, 0, 0.05) 100%); padding: 1.5rem; border-radius: var(--radius-md); border: 1px solid rgba(255, 107, 53, 0.2);">
                            <p style="color: var(--text-gray); font-size: 0.875rem; font-weight: 600; margin-bottom: 0.75rem;">Description</p>
                            <p style="margin: 0; color: var(--text-dark); white-space: pre-wrap; line-height: 1.6;"><?php echo htmlspecialchars($ticket['description']); ?></p>
                        </div>

                        <?php if (!empty($ticket['decision_rmq'])): ?>
                        <!-- NEW: Décision RMQ & Preuve (Read Only) -->
                        <div style="margin-top: 2rem; padding-top: 2rem; border-top: 2px dashed #EEE;">
                            <h4 style="color: var(--primary); margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                                <i class="fas fa-gavel"></i> Décision RMQ & Résolution
                            </h4>
                            
                            <div style="background: #FFF; border-left: 4px solid var(--primary); padding: 1.5rem; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.03);">
                                <p style="color: var(--text-gray); font-size: 0.8rem; font-weight: 700; text-transform: uppercase; margin-bottom: 0.5rem;">Décision Finale</p>
                                <p style="color: var(--text-dark); margin-bottom: 1.5rem; line-height: 1.6;"><?php echo nl2br(htmlspecialchars($ticket['decision_rmq'])); ?></p>
                                
                                <div style="background: #F8FAFC; padding: 1.25rem; border-radius: 8px; border: 1px solid #E2E8F0;">
                                    <p style="color: #64748B; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; margin-bottom: 0.5rem;">Actions & Preuve de résolution</p>
                                    <p style="color: #334155; font-size: 0.95rem; margin-bottom: 1rem;"><?php echo nl2br(htmlspecialchars($ticket['commentaire_resolution'])); ?></p>
                                    
                                    <?php if (!empty($ticket['preuve_resolution'])): ?>
                                        <a href="../../uploads/<?php echo htmlspecialchars($ticket['preuve_resolution']); ?>" target="_blank" class="btn btn-secondary" style="font-size: 0.85rem; padding: 0.5rem 1rem;">
                                            <i class="fas fa-download"></i> Télécharger la preuve
                                        </a>
                                    <?php endif; ?>
                                </div>
                                <div style="margin-top: 1rem; text-align: right; color: #94A3B8; font-size: 0.75rem;">
                                    Date décision : <?php echo date('d/m/Y H:i', strtotime($ticket['date_decision'])); ?>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr; gap: 2rem;">
                    
                    <!-- Messages & Attachments -->
                    <div>
                        
                        <?php if (!empty($piecesJointes)): ?>
                        <div class="card glass-card">
                            <div class="card-header">
                                <h4 style="margin: 0; display: flex; align-items: center; gap: 0.5rem;">
                                    <i class="fas fa-paperclip" style="color: var(--primary);"></i> Pièces jointes
                                </h4>
                            </div>
                            <div class="card-body p-4">
                                <div style="display: flex; flex-direction: column; gap: 1rem;">
                                    <?php foreach ($piecesJointes as $f): ?>
                                    <div style="display: flex; align-items: center; gap: 1rem; padding: 1rem; background: var(--surface-gray); border-radius: var(--radius-md);">
                                        <i class="fas fa-file" style="font-size: 1.5rem; color: var(--primary);"></i>
                                        <div style="flex: 1; min-width: 0;">
                                            <p style="color: var(--text-dark); font-weight: 600; margin: 0; word-break: break-word;">
                                                <?php echo htmlspecialchars($f['nom_fichier']); ?>
                                            </p>
                                            <p style="color: var(--text-gray); font-size: 0.875rem; margin: 0;">
                                                <?php echo number_format($f['taille_fichier']/1024, 2); ?> KB
                                            </p>
                                        </div>
                                        <a href="../../uploads/<?php echo htmlspecialchars($f['chemin_fichier']); ?>" target="_blank" class="btn btn-primary" style="padding: 0.5rem 1rem; white-space: nowrap;">
                                            <i class="fas fa-download"></i>
                                        </a>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
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