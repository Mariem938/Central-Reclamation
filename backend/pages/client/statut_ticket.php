<?php
/**
 * Statut Timeline - Premium Design
 */
require_once '../../config/database.php';
require_once '../../api/functions.php';

$error = '';
$ticket = null;

$numeroTicket = $_GET['numero'] ?? '';
if (!$numeroTicket) {
    $error = 'Numéro de ticket manquant.';
} else {
    $ticket = getTicketByNumero(cleanInput($numeroTicket));
    if (!$ticket) {
        $error = 'Ticket introuvable.';
    } else {
        $piecesJointes = getPiecesJointes($ticket['id']);
        $statuts = getStatuts();
        
        $ordreIndex = [];
        foreach ($statuts as $s) $ordreIndex[$s['id']] = $s;
        
        $totalSteps = count($statuts);
        $currentStepIndex = 0;
        foreach ($statuts as $i => $s) {
            if ($s['id'] == $ticket['statut_id']) { $currentStepIndex = $i; break; }
        }
        $progressPercent = ($totalSteps <= 1) ? 100 : round($currentStepIndex / ($totalSteps - 1) * 100);
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Statut - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/fontawesome/css/all.min.css">
</head>
<body>
    <nav class="navbar">
        <div class="container">
            <a class="navbar-brand" href="../../index.php" style="display: flex; align-items: center; gap: 10px; text-decoration: none;">
                <img src="../../assets/images/logo.png" alt="Logo SCPC" style="height: 40px; width: auto;">
                <div style="display: flex; flex-direction: column; line-height: 1.2;">
                    <span style="font-weight: 700; font-size: 1.1rem; color: #1f2937;">SCPC Comptoir Hammami</span>
                    <span style="font-size: 0.8rem; color: #6b7280; font-weight: normal;"><?php echo SITE_NAME; ?></span>
                </div>
            </a>
            <div class="navbar-menu">
                <a href="../../index.php"><i class="fas fa-home"></i> <span>Accueil</span></a>
                <a href="nouvelle_reclamation.php"><i class="fas fa-plus-circle"></i> <span>Nouvelle</span></a>
                <a href="suivi_reclamation.php" class="active"><i class="fas fa-search"></i> <span>Suivi</span></a>
                <a href="../agent/login.php"><i class="fas fa-user-shield"></i> <span>Agent</span></a>
            </div>
            <button id="navbarToggler" class="navbar-toggler"><i class="fas fa-bars"></i></button>
        </div>
    </nav>
    <div class="mobile-menu" id="mobileMenu">
        <div style="padding: 1.5rem; text-align: center; border-bottom: 2px solid var(--gray-200); margin-bottom: 1rem;">
            <strong style="display: block; font-size: 1.1rem; color: var(--primary); margin-bottom: 0.25rem;">
                <i class="fas fa-user-circle"></i> Espace Client
            </strong>
            <p style="margin: 0; font-size: 0.9rem; color: var(--text-gray);">Suivi de dossier</p>
        </div>
        <a href="../../index.php" class="mobile-menu-link"><i class="fas fa-home"></i> <span>Accueil</span></a>
        <a href="nouvelle_reclamation.php" class="mobile-menu-link"><i class="fas fa-plus-circle"></i> <span>Nouvelle</span></a>
        <a href="suivi_reclamation.php" class="mobile-menu-link active"><i class="fas fa-search"></i> <span>Suivi</span></a>
    </div>

    <div class="section" style="padding-top: 3rem; padding-bottom: 3rem; background: var(--gradient-hero);">
        <div class="container">
            <?php if ($error): ?>
            <div class="card glass-card fade-in" style="max-width: 700px; margin: 0 auto;">
                <div class="card-header">
                    <h2 style="margin: 0; color: var(--text-dark);">Erreur</h2>
                </div>
                <div class="card-body p-4">
                    <a href="suivi_reclamation.php" class="btn btn-secondary" style="margin-bottom: 1.5rem; display: inline-flex; align-items: center; gap: 0.5rem;">
                        <i class="fas fa-arrow-left"></i> Retour
                    </a>
                    <div style="background: #FFE5E5; border: 1px solid #FF6B6B; color: #C92A2A; padding: 1rem; border-radius: var(--radius-md); display: flex; gap: 1rem; align-items: flex-start;">
                        <i class="fas fa-exclamation-circle" style="flex-shrink: 0; margin-top: 0.25rem;"></i>
                        <p style="margin: 0;"><?php echo htmlspecialchars($error); ?></p>
                    </div>
                </div>
            </div>
            <?php else: ?>
            
            <!-- Header Card -->
            <div class="card glass-card fade-in mb-4" style="max-width: 1000px; margin: 0 auto;">
                <div class="card-body p-5">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 2rem;">
                        <div>
                            <h2 style="margin-bottom: 0.5rem; color: var(--text-dark);">
                                Ticket 
                                <span style="background: var(--gradient-primary); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;">
                                    <?php echo htmlspecialchars($ticket['numero_ticket']); ?>
                                </span>
                            </h2>
                            <p style="margin: 0.5rem 0 0; color: var(--text-gray); font-size: 0.9375rem;">
                                <?php echo htmlspecialchars($ticket['sujet']); ?>
                            </p>
                        </div>
                        <div style="display: flex; flex-direction: column; gap: 1rem; align-items: flex-end;">
                            <a href="suivi_reclamation.php?numero=<?php echo urlencode($ticket['numero_ticket']); ?>" class="btn btn-secondary" style="display: inline-flex; align-items: center; gap: 0.5rem;">
                                <i class="fas fa-arrow-left"></i> Détails
                            </a>
                            <div style="text-align: right;">
                                <p style="margin: 0; color: var(--text-gray); font-size: 0.875rem;">Statut actuel</p>
                                <span class="badge badge-lg" style="background: <?php echo $ticket['statut_couleur']; ?>; color: white; padding: 0.5rem 1rem; border-radius: var(--radius-full); font-weight: 600; margin-top: 0.5rem; display: inline-block;">
                                    <?php echo htmlspecialchars($ticket['statut_nom']); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Timeline Card -->
            <?php 
                // Simplified client-facing steps
                $clientSteps = [
                    ['nom' => 'En cours de vérification', 'icon' => 'fa-search', 'ids' => [1, 6, 7, 9]],
                    ['nom' => 'En cours de traitement', 'icon' => 'fa-tools', 'ids' => [2]],
                    ['nom' => 'En attente réponse fournisseur', 'icon' => 'fa-hourglass-half', 'ids' => [3]],
                    ['nom' => (in_array($ticket['statut_id'], [5, 8]) ? 'Refusé' : 'Résolu'), 'icon' => (in_array($ticket['statut_id'], [5, 8]) ? 'fa-times-circle' : 'fa-check-double'), 'ids' => [4, 5, 8]]
                ];

                $currentClientStepIndex = 0;
                foreach ($clientSteps as $idx => $cs) {
                    if (in_array($ticket['statut_id'], $cs['ids'])) {
                        $currentClientStepIndex = $idx;
                        break;
                    }
                }
                
                // Progress Calculation: 33% per step logic
                // If ID is 5 or 7 (Terminal), progress is always 100%
                if (in_array($ticket['statut_id'], [4, 5, 8])) {
                    $progressPercent = 100;
                } else {
                    $progressPercent = round(($currentClientStepIndex / (count($clientSteps) - 1)) * 100);
                }
            ?>
            <div class="card glass-card fade-in mb-4" style="max-width: 1000px; margin: 0 auto;">
                <div class="card-header">
                    <h3 style="margin: 0; display: flex; align-items: center; gap: 0.75rem;">
                        <i class="fas fa-route" style="color: var(--primary);"></i> Suivi du Statut
                    </h3>
                </div>
                <div class="card-body p-5">
                    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 2rem; margin-bottom: 3.5rem;">
                        <div>
                            <p style="margin: 0; color: var(--text-gray); font-size: 0.875rem; font-weight: 700;">CRÉÉ LE</p>
                            <p style="margin: 0.3rem 0 0; font-weight: 800; color: var(--text-dark); font-size: 1.1rem;"><?php echo formatDate($ticket['date_creation'], 'd/m/Y'); ?></p>
                        </div>
                        <div style="text-align: center; flex-grow: 1; max-width: 500px;">
                            <div style="display: flex; justify-content: space-between; margin-bottom: 0.8rem;">
                                <p style="margin: 0; color: var(--text-gray); font-size: 0.8125rem; font-weight: 700;">PROGRESSION</p>
                                <p style="margin: 0; color: var(--primary); font-size: 0.8125rem; font-weight: 800;"><?php echo $progressPercent; ?>%</p>
                            </div>
                            <div style="width: 100%; height: 10px; background: #eaedf2; border-radius: 50px; overflow: hidden;">
                                <div style="width: <?php echo $progressPercent; ?>%; height: 100%; background: var(--gradient-primary); transition: width 0.8s ease; border-radius: 50px;"></div>
                            </div>
                        </div>
                    </div>

                    <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 0.5rem; position: relative;">
                        <!-- Progress line background -->
                        <div style="position: absolute; top: 25px; left: 12.5%; right: 12.5%; height: 2px; background: #eaedf2; z-index: 1;"></div>
                        
                        <?php foreach ($clientSteps as $index => $s):
                            $isActive = in_array($ticket['statut_id'], $s['ids']);
                            $isCompleted = false;
                            
                            // History based logic not needed here, simpler step-index comparison
                            if ($currentClientStepIndex > $index) $isCompleted = true;
                            // Terminal state forces completion of previous steps
                            if (in_array($ticket['statut_id'], [4, 5, 8]) && $index < 3) $isCompleted = true;

                            $stepColor = ($isCompleted || $isActive) ? 'var(--primary)' : '#e2e8f0';
                            $iconBg = $isCompleted ? 'var(--gradient-primary)' : ($isActive ? 'rgba(255, 107, 53, 0.1)' : '#f8fafc');
                            $iconColor = $isCompleted ? 'white' : ($isActive ? 'var(--primary)' : '#94a3b8');
                            $border = $isCompleted ? 'none' : ($isActive ? '2px solid var(--primary)' : '2px solid #e2e8f0');
                        ?>
                        <div style="text-align: center; flex: 1; z-index: 2; position: relative;">
                            <div style="width: 50px; height: 50px; margin: 0 auto 1rem; display: flex; align-items: center; justify-content: center; border-radius: 50%; 
                                 background: <?php echo $iconBg; ?>; color: <?php echo $iconColor; ?>; border: <?php echo $border; ?>;
                                 box-shadow: <?php echo $isActive ? '0 0 0 4px rgba(255, 107, 53, 0.15)' : 'none'; ?>; transition: all 0.3s ease;">
                                <?php if ($isCompleted): ?>
                                    <i class="fas fa-check" style="font-size: 1rem;"></i>
                                <?php else: ?>
                                    <i class="fas <?php echo $s['icon']; ?>" style="font-size: 1rem; <?php echo $isActive ? 'animation: pulse 2s infinite;' : ''; ?>"></i>
                                <?php endif; ?>
                            </div>
                            <p style="margin: 0; color: <?php echo ($isActive || $isCompleted) ? 'var(--text-dark)' : 'var(--text-gray)'; ?>; font-weight: <?php echo ($isActive || $isCompleted) ? '700' : '600'; ?>; font-size: 0.8rem; line-height: 1.3;">
                                <?php echo htmlspecialchars($s['nom']); ?>
                            </p>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <style>
                @keyframes pulse {
                    0% { transform: scale(1); }
                    50% { transform: scale(1.1); }
                    100% { transform: scale(1); }
                }
            </style>


            <?php if (!empty($piecesJointes)): ?>
            <!-- Attachments Card -->
            <div class="card glass-card fade-in" style="max-width: 1000px; margin: 0 auto;">
                <div class="card-header">
                    <h3 style="margin: 0; display: flex; align-items: center; gap: 0.75rem;">
                        <i class="fas fa-paperclip" style="color: var(--primary);"></i> Pièces jointes
                    </h3>
                </div>
                <div class="card-body p-5">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem;">
                        <?php foreach ($piecesJointes as $f): ?>
                        <div style="padding: 1.5rem; background: var(--surface-gray); border-radius: var(--radius-md); display: flex; align-items: center; gap: 1rem;">
                            <div style="width: 50px; height: 50px; display: flex; align-items: center; justify-content: center; border-radius: var(--radius-md); background: linear-gradient(135deg, rgba(255, 107, 53, 0.1), rgba(255, 165, 0, 0.1)); color: var(--primary); font-size: 1.5rem; flex-shrink: 0;">
                                <i class="fas fa-file"></i>
                            </div>
                            <div style="flex: 1; min-width: 0;">
                                <p style="color: var(--text-dark); font-weight: 600; margin: 0; word-break: break-word;">
                                    <?php echo htmlspecialchars($f['nom_fichier']); ?>
                                </p>
                                <p style="color: var(--text-gray); font-size: 0.875rem; margin: 0.25rem 0 0;">
                                    <?php echo number_format($f['taille_fichier']/1024, 2); ?> KB
                                </p>
                            </div>
                            <a href="../../uploads/<?php echo htmlspecialchars($f['chemin_fichier']); ?>" target="_blank" class="btn btn-primary" style="padding: 0.5rem 1rem; white-space: nowrap; display: inline-flex; align-items: center; gap: 0.5rem;">
                                <i class="fas fa-download"></i> <span style="display: none;">Télécharger</span>
                            </a>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
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
    </script>
</body>
</html>