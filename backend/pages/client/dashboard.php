<?php
/**
 * Dashboard Client Harmonisé - Version Commerciale
 * Fichier: pages/client/dashboard.php
 * Interface alignée sur le design commercial avec menu hamburger
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

// Récupérer les informations du client
$stmt = $db->prepare("SELECT * FROM utilisateurs WHERE id = ?");
$stmt->execute([$userId]);
$client = $stmt->fetch();

// Récupérer les tickets du client
$stmt = $db->prepare("
    SELECT t.*, 
           c.nom as categorie_nom, c.couleur as categorie_couleur,
           s.nom as statut_nom, s.couleur as statut_couleur
    FROM tickets t
    LEFT JOIN categories c ON t.categorie_id = c.id
    LEFT JOIN statuts s ON t.statut_id = s.id
    WHERE t.client_id = ?
    ORDER BY t.date_creation DESC
");
$stmt->execute([$userId]);
$tickets = $stmt->fetchAll();

// --- STATISTIQUES CLIENT ---
$stats = [
    'total' => count($tickets),
    'valides' => 0,
    'encours' => 0,
    'resolus' => 0
];

// On peut aussi les recompter via SQL pour être précis sur les IDs
// Validés (en attente traitement ou vérification poussée)
$stmt = $db->prepare("SELECT COUNT(*) as count FROM tickets WHERE client_id = ? AND statut_id IN (1, 6, 7, 9)");
$stmt->execute([$userId]);
$stats['valides'] = $stmt->fetch()['count'];

// En traitement
$stmt = $db->prepare("SELECT COUNT(*) as count FROM tickets WHERE client_id = ? AND statut_id = 2");
$stmt->execute([$userId]);
$stats['encours'] = $stmt->fetch()['count'];

// Résolus
$stmt = $db->prepare("SELECT COUNT(*) as count FROM tickets WHERE client_id = ? AND statut_id = 4");
$stmt->execute([$userId]);
$stats['resolus'] = $stmt->fetch()['count'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon Dashboard - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/fontawesome/css/all.min.css">
</head>
<body style="background-color: var(--gray-100);">
    <!-- Navigation (Inspirée du Commercial) -->
    <nav class="navbar">
        <div class="container">
            <a class="navbar-brand" href="../../index.php" style="display: flex; align-items: center; gap: 10px;">
                <img src="../../assets/images/logo.png" alt="Logo SCPC" style="height: 40px; width: auto;">
                <div style="display: flex; flex-direction: column; line-height: 1.2;">
                    <span style="font-weight: 700; font-size: 1.1rem; color: #1A1A1A;">SCPC Comptoir Hammami</span>
                    <span style="font-size: 0.75rem; color: #666; font-weight: 500;"><?php echo SITE_NAME; ?></span>
                </div>
                <span class="navbar-brand-badge" style="background: var(--gradient-primary);">
                    Client
                </span>
            </a>
            
            <div class="navbar-menu" id="mainMenu">
                <a href="dashboard.php" class="navbar-link active">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
                <a href="nouvelle_reclamation.php" class="navbar-link">
                    <i class="fas fa-plus-circle"></i>
                    <span>Nouvelle Réclamation</span>
                </a>
                <a href="satisfaction.php" class="navbar-link">
                    <i class="fas fa-smile"></i>
                    <span>Satisfaction</span>
                </a>
                <a href="../../pages/logout.php" class="navbar-link">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Déconnexion</span>
                </a>
            </div>
            
            <button id="navbarToggler" class="navbar-toggler">
                <i class="fas fa-bars"></i>
            </button>
        </div>
    </nav>

    <!-- Mobile Menu (Strictement identique au Commercial) -->
    <div class="mobile-menu" id="mobileMenu">
        <div class="mobile-menu-header">
            <strong><i class="fas fa-user-circle"></i> Client</strong>
            <p class="mobile-menu-subtitle">Espace Personnel</p>
        </div>
        <a href="dashboard.php" class="mobile-menu-link active"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a>
        <a href="nouvelle_reclamation.php" class="mobile-menu-link"><i class="fas fa-plus-circle"></i> <span>Nouvelle Réclamation</span></a>
        <a href="satisfaction.php" class="mobile-menu-link"><i class="fas fa-smile"></i> <span>Satisfaction</span></a>
        <a href="../../pages/logout.php" class="mobile-menu-link" style="color: var(--danger-color) !important;"><i class="fas fa-sign-out-alt"></i> <span>Déconnexion</span></a>
    </div>

    <div class="container" style="padding: 2rem 1rem;">
        
        <!-- Welcome Hero Section -->
        <div class="glass-card" style="padding: 2.5rem; margin-bottom: 2rem; border-left: 6px solid var(--primary);">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1.5rem;">
                <div>
                    <h1 style="margin: 0; font-size: 1.8rem; font-weight: 800; display: flex; align-items: center; gap: 0.75rem;">
                        <i class="fas fa-circle-user" style="color: var(--primary);"></i>
                        Bienvenue, <?php echo htmlspecialchars($client['prenom']); ?>
                    </h1>
                    <p style="margin: 0.5rem 0 0 0; color: var(--text-muted); font-size: 1.05rem;">
                        Retrouvez ici le suivi de toutes vos réclamations auprès du Groupe Hammami.
                    </p>
                </div>
                <div style="display: flex; gap: 1rem;">
                    <a href="nouvelle_reclamation.php" class="btn btn-primary btn-lg" style="box-shadow: 0 8px 20px rgba(255, 107, 53, 0.25);">
                        <i class="fas fa-plus-circle"></i> Nouvelle Réclamation
                    </a>
                </div>
            </div>
        </div>

        <!-- Statistiques (Style AQF) -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 2.5rem;">
            <div class="stat-card">
                <i class="fas fa-folder-open" style="color: var(--primary-color);"></i>
                <h3><?php echo $stats['total']; ?></h3>
                <p>Total Réclamations</p>
            </div>
            <div class="stat-card">
                <i class="fas fa-check-circle" style="color: #2563EB;"></i>
                <h3><?php echo $stats['valides']; ?></h3>
                <p>Validées / Neuves</p>
            </div>
            <div class="stat-card">
                <i class="fas fa-spinner fa-spin-pulse" style="color: #FFA500;"></i>
                <h3><?php echo $stats['encours']; ?></h3>
                <p>En Traitement</p>
            </div>
            <div class="stat-card">
                <i class="fas fa-circle-check" style="color: #10B981;"></i>
                <h3><?php echo $stats['resolus']; ?></h3>
                <p>Résolues</p>
            </div>
        </div>

        <!-- Réclamations Table (Design Commercial) -->
        <div class="glass-card" style="padding: 2rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
                <h2 style="margin: 0; display: flex; align-items: center; gap: 0.75rem; font-weight: 800;">
                    <i class="fas fa-clipboard-list" style="color: var(--primary);"></i>
                    Mes Réclamations
                </h2>
                <div style="color: var(--text-muted); font-size: 0.9rem; font-weight: 600;">
                    Total : <?php echo count($tickets); ?> dossier(s)
                </div>
            </div>
            
            <?php if (empty($tickets)): ?>
                <div style="text-align: center; padding: 4rem 2rem; color: var(--text-muted);">
                    <div style="font-size: 4rem; margin-bottom: 1.5rem; opacity: 0.2;">
                         <i class="fas fa-inbox"></i>
                    </div>
                    <h3 style="margin-bottom: 1rem;">Aucune réclamation trouvée</h3>
                    <p>Vous n'avez pas encore soumis de réclamation via ce portail.</p>
                    <a href="nouvelle_reclamation.php" class="btn btn-primary" style="margin-top: 1.5rem;">
                         <i class="fas fa-plus"></i> Commencer maintenant
                    </a>
                </div>
            <?php else: ?>
                <div style="overflow-x: auto; margin: 0 -1rem;">
                    <table style="width: 100%; border-collapse: separate; border-spacing: 0 10px; min-width: 900px;">
                        <thead>
                            <tr style="color: var(--text-muted); font-size: 0.85rem; text-transform: uppercase; letter-spacing: 1px;">
                                <th style="padding: 1rem; text-align: left;">N° Ticket</th>
                                <th style="padding: 1rem; text-align: left;">Sujet / Motif</th>
                                <th style="padding: 1rem; text-align: left;">Catégorie</th>
                                <th style="padding: 1rem; text-align: left;">État / Statut</th>
                                <th style="padding: 1rem; text-align: left;">Date de dépôt</th>
                                <th style="padding: 1rem; text-align: center;">Satisfaction</th>
                                <th style="padding: 1rem; text-align: center;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tickets as $ticket): ?>
                                <tr class="table-row-hover" style="background: white; border-radius: 12px; transition: all 0.3s ease;">
                                    <td style="padding: 1.25rem 1rem; font-weight: 700; color: var(--primary); border-radius: 12px 0 0 12px;">
                                        #<?php echo htmlspecialchars($ticket['numero_ticket']); ?>
                                    </td>
                                    <td style="padding: 1.25rem 1rem;">
                                        <div style="font-weight: 600; color: var(--text-dark);">
                                            <?php echo htmlspecialchars(substr($ticket['sujet'], 0, 45)); ?><?php if (strlen($ticket['sujet']) > 45) echo '...'; ?>
                                        </div>
                                    </td>
                                    <td style="padding: 1.25rem 1rem;">
                                        <span style="background: <?php echo $ticket['categorie_couleur']; ?>; color: white; padding: 0.4rem 0.8rem; border-radius: 20px; font-size: 0.75rem; font-weight: 700; display: inline-block;">
                                            <?php echo htmlspecialchars($ticket['categorie_nom']); ?>
                                        </span>
                                    </td>
                                    <td style="padding: 1.25rem 1rem;">
                                        <span style="background: <?php echo $ticket['statut_couleur']; ?>22; color: <?php echo $ticket['statut_couleur']; ?>; padding: 0.4rem 1rem; border-radius: 20px; font-size: 0.8rem; font-weight: 800; border: 1px solid <?php echo $ticket['statut_couleur']; ?>44;">
                                            <?php echo htmlspecialchars($ticket['statut_nom']); ?>
                                        </span>
                                    </td>
                                    <td style="padding: 1.25rem 1rem; font-size: 0.9rem; color: var(--text-muted); font-weight: 500;">
                                        <?php echo date('d/m/Y', strtotime($ticket['date_creation'])); ?>
                                    </td>
                                    <td style="padding: 1.25rem 1rem; text-align: center;">
                                        <?php if ($ticket['note_satisfaction'] !== null): ?>
                                            <div style="color: #FFC107; font-size: 0.9rem;">
                                                <?php for ($i=1; $i<=5; $i++) echo ($i<=$ticket['note_satisfaction']) ? '★' : '☆'; ?>
                                            </div>
                                        <?php else: ?>
                                            <span style="font-size: 0.75rem; color: var(--text-gray);">À évaluer</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding: 1.25rem 1rem; text-align: center; border-radius: 0 12px 12px 0;">
                                        <a href="detail_reclamation.php?id=<?php echo $ticket['id']; ?>" class="btn btn-primary btn-sm" style="border-radius: 8px;">
                                            <i class="fas fa-eye"></i> Détails
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Dashboard Action Buttons -->
        <div class="glass-card" style="margin-top: 2rem; padding: 2.5rem; text-align: center;">
            <div style="display: flex; justify-content: center; gap: 1.5rem; flex-wrap: wrap;">
                <a href="nouvelle_reclamation.php" class="btn btn-primary btn-lg" style="box-shadow: 0 8px 20px rgba(255, 107, 53, 0.25); min-width: 280px; display: flex; align-items: center; justify-content: center; gap: 10px;">
                    <i class="fas fa-plus-circle" style="font-size: 1.2rem;"></i>
                    <span>Créer une nouvelle réclamation</span>
                </a>
                <a href="satisfaction.php" class="btn btn-secondary btn-lg" style="min-width: 280px; display: flex; align-items: center; justify-content: center; gap: 10px;">
                    <i class="fas fa-smile" style="font-size: 1.2rem;"></i>
                    <span>Avis de satisfaction</span>
                </a>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer" style="background: white; border-top: 1px solid #e2e8f0; margin-top: 4rem;">
        <div class="container">
            <p style="font-weight: 600; color: var(--text-muted);">&copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?> - Portail Client Sécurisé</p>
        </div>
    </footer>

    <!-- Scripts (Hamburger functionality strictly aligned with Commercial) -->
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
        .table-row-hover:hover {
            transform: scale(1.005);
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            cursor: pointer;
        }
        .navbar-brand-badge {
            color: white; 
            padding: 0.25rem 0.75rem; 
            border-radius: 20px; 
            font-size: 0.75rem; 
            font-weight: 800; 
            margin-left: 10px;
            letter-spacing: 0.5px;
        }
        .stat-card { 
            background: white; 
            padding: 1.5rem 1rem; 
            border-radius: var(--radius-lg); 
            box-shadow: 0 4px 15px rgba(0,0,0,0.05); 
            text-align: center; 
            transition: all 0.3s ease;
            border: 1px solid rgba(0,0,0,0.05);
        }
        .stat-card:hover { 
            transform: translateY(-5px); 
            box-shadow: 0 8px 25px rgba(0,0,0,0.1); 
        }
        .stat-card h3 { 
            margin: 0.5rem 0; 
            font-size: 2rem; 
            font-weight: 800; 
            color: var(--text-dark); 
        }
        .stat-card p { 
            margin: 0; 
            color: var(--gray-600); 
            font-size: 0.85rem; 
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .stat-card i { 
            font-size: 2.2rem; 
            margin-bottom: 0.75rem; 
            display: block; 
        }
    </style>
</body>
</html>
