<?php
/**
 * Dashboard AQF (Assistant Qualité Fournisseur/Fabrication)
 * Fichier: pages/aqf/dashboard.php
 */

require_once '../../config/database.php';
require_once '../../api/functions.php';

// Vérifier que l'utilisateur est AQF
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'aqf') {
    header('Location: ../agent/login.php?role=aqf');
    exit;
}

$db = getDB();

// Filtres
$filtreStatut = $_GET['statut'] ?? 'tous';
$recherche = $_GET['recherche'] ?? '';

// Récupérer les tickets avec un fournisseur assigné (manuelle entrée)
$sql = "
    SELECT t.*, 
           c.nom as categorie_nom, c.couleur as categorie_couleur,
           s.nom as statut_nom, s.couleur as statut_couleur,
           CONCAT(u.prenom, ' ', u.nom) as client_nom_complet,
           COALESCE(t.fournisseur_nom, t.fournisseur_prenom) as fournisseur_nom
    FROM tickets t
    LEFT JOIN categories c ON t.categorie_id = c.id
    LEFT JOIN statuts s ON t.statut_id = s.id
    LEFT JOIN utilisateurs u ON t.client_id = u.id
    WHERE (t.fournisseur_nom IS NOT NULL AND t.fournisseur_nom != '')
";

$params = [];

if ($filtreStatut !== 'tous') {
    $sql .= " AND t.statut_id = ?";
    $params[] = $filtreStatut;
}

if (!empty($recherche)) {
    $sql .= " AND (t.numero_ticket LIKE ? OR t.sujet LIKE ? OR CONCAT(u.prenom, ' ', u.nom) LIKE ? OR t.fournisseur_nom LIKE ?)";
    $searchTerm = "%$recherche%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

$sql .= " ORDER BY t.date_creation DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$tickets = $stmt->fetchAll();

// Récupérer les statuts pour le filtre
$statuts = getStatuts();

// Statistiques
$stats = [];

// Total assignés (avec fournisseur)
$stmt = $db->prepare("SELECT COUNT(*) as total FROM tickets WHERE (fournisseur_nom IS NOT NULL AND fournisseur_nom != '')");
$stmt->execute();
$stats['total'] = $stmt->fetch()['total'];

// En cours (Statut 2)
$stmt = $db->prepare("SELECT COUNT(*) as total FROM tickets WHERE (fournisseur_nom IS NOT NULL AND fournisseur_nom != '') AND statut_id = 2");
$stmt->execute();
$stats['encours'] = $stmt->fetch()['total'];

// Résolus (Statut 4)
$stmt = $db->prepare("SELECT COUNT(*) as total FROM tickets WHERE (fournisseur_nom IS NOT NULL AND fournisseur_nom != '') AND statut_id = 4");
$stmt->execute();
$stats['resolus'] = $stmt->fetch()['total'];

// En attente (Statut 1, 6, etc)
$stmt = $db->prepare("SELECT COUNT(*) as total FROM tickets WHERE (fournisseur_nom IS NOT NULL AND fournisseur_nom != '') AND statut_id NOT IN (2, 4)");
$stmt->execute();
$stats['attente'] = $stmt->fetch()['total'];

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard AQF - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/fontawesome/css/all.min.css">
    <style>
        body { background: var(--gray-100); }
        .stat-card { background: white; padding: 1rem 0.75rem; border-radius: var(--radius-lg); box-shadow: 0 2px 8px rgba(0,0,0,0.1); text-align: center; transition: all 0.3s ease; }
        .stat-card:hover { transform: translateY(-4px); box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
        .stat-card h3 { margin: 0.25rem 0; font-size: 1.5rem; font-weight: 700; color: var(--primary-color); }
        .stat-card p { margin: 0; color: var(--gray-600); font-size: 0.8rem; font-weight: 500; }
        .stat-card i { font-size: 1.75rem; margin-bottom: 0.5rem; display: block; }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar">
        <div class="container">
            <a class="navbar-brand" href="../../index.php" style="display: flex; align-items: center; gap: 10px;">
                <img src="../../assets/images/logo.png" alt="Logo SCPC" style="height: 40px; width: auto;">
                <div style="display: flex; flex-direction: column; line-height: 1.2;">
                    <span style="font-weight: 700; font-size: 1.1rem; color: #1A1A1A;">SCPC Comptoir Hammami</span>
                    <span style="font-size: 0.75rem; color: #666; font-weight: 500;"><?php echo SITE_NAME; ?></span>
                </div>
                <!-- Badge spécifique AQF -->
                <span class="navbar-brand-badge" style="background: #2563EB;">
                    AQF
                </span>
            </a>
            
            <div class="navbar-menu" id="mainMenu">
                <a href="dashboard.php" class="navbar-link">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
                <a href="statistiques.php" class="navbar-link">
                    <i class="fas fa-chart-bar"></i>
                    <span>Statistiques</span>
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
    
    <div class="mobile-menu" id="mobileMenu">
        <div class="mobile-menu-header">
            <i class="fas fa-clipboard-check"></i>
            <strong>AQF</strong>
            <p class="mobile-menu-subtitle">Responsable Qualité</p>
        </div>
        <a href="../../index.php" class="mobile-menu-link"><i class="fas fa-home"></i> <span>Accueil Site</span></a>
        <a href="dashboard.php" class="mobile-menu-link"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a>
        <a href="statistiques.php" class="mobile-menu-link"><i class="fas fa-chart-bar"></i> <span>Statistiques</span></a>
        <a href="../../pages/logout.php" class="mobile-menu-link" style="color: var(--danger-color) !important;"><i class="fas fa-sign-out-alt"></i> <span>Déconnexion</span></a>
    </div>
    
    <div class="container-fluid" style="padding: 2rem;">
        <!-- Stats Row -->
        <div class="row" style="margin-bottom: 2rem;">
            <div class="col-md-2" style="margin-bottom: 1rem;">
                <div class="stat-card">
                    <i class="fas fa-tasks" style="font-size: 2rem; color: var(--primary-color);"></i>
                    <h3><?php echo $stats['total']; ?></h3>
                    <p>Total Assignés</p>
                </div>
            </div>
            <div class="col-md-2" style="margin-bottom: 1rem;">
                <div class="stat-card">
                    <i class="fas fa-spinner" style="font-size: 2rem; color: #FFA500;"></i>
                    <h3><?php echo $stats['encours']; ?></h3>
                    <p>En Traitement</p>
                </div>
            </div>
            <div class="col-md-2" style="margin-bottom: 1rem;">
                <div class="stat-card">
                    <i class="fas fa-business-time" style="font-size: 2rem; color: #8B5CF6;"></i>
                    <h3><?php echo $stats['attente']; ?></h3>
                    <p>En Attente</p>
                </div>
            </div>
            <div class="col-md-2" style="margin-bottom: 1rem;">
                <div class="stat-card">
                    <i class="fas fa-check-circle" style="font-size: 2rem; color: #4CAF50;"></i>
                    <h3><?php echo $stats['resolus']; ?></h3>
                    <p>Résolus</p>
                </div>
            </div>
        </div>

        <!-- Filters & Search -->
        <div class="card" style="margin-bottom: 2rem;">
            <div class="card-body">
                <form method="GET" action="" style="display: flex; gap: 1rem; flex-wrap: wrap; align-items: center;">
                    <div style="flex: 1; min-width: 250px;">
                        <input type="text" name="recherche" placeholder="Rechercher (Ticket, Sujet, Client)..." 
                               value="<?php echo htmlspecialchars($recherche); ?>"
                               style="width: 100%; padding: 0.75rem; border: 1px solid var(--gray-300); border-radius: var(--radius-md);">
                    </div>
                    <select name="statut" style="padding: 0.75rem; border: 1px solid var(--gray-300); border-radius: var(--radius-md);">
                        <option value="tous">Tous les statuts</option>
                        <?php 
                        $statutsAQF = [
                            6 => 'En attente validation RMQ',
                            1 => 'En cours de vérification', 
                            2 => 'En cours de traitement',
                            3 => 'En attente réponse fournisseur',
                            4 => 'Résolu',
                            8 => 'Refusé'
                        ];
                        foreach ($statutsAQF as $id => $nom): ?>
                            <option value="<?php echo $id; ?>" <?php echo $filtreStatut == $id ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($nom); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i></button>
                </form>
            </div>
        </div>

        <!-- Tickets List -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-clipboard-list"></i> Mes Tickets (<?php echo count($tickets); ?>)</h3>
            </div>
            <div class="card-body">
                <?php if (empty($tickets)): ?>
                    <div style="text-align: center; padding: 2rem;">
                        <i class="fas fa-folder-open" style="font-size: 3rem; color: var(--gray-400); margin-bottom: 1rem;"></i>
                        <p style="color: var(--gray-600);">Aucun ticket assigné pour le moment.</p>
                    </div>
                <?php else: ?>
                    <div style="overflow-x: auto;">
                        <table style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr style="background: var(--gray-50); border-bottom: 2px solid var(--gray-200);">
                                    <th style="padding: 1rem; text-align: left; font-weight: 600;">Code Client</th>
                                    <th style="padding: 1rem; text-align: left; font-weight: 600;">Date</th>
                                    <th style="padding: 1rem; text-align: left; font-weight: 600;">Client</th>
                                    <th style="padding: 1rem; text-align: left; font-weight: 600;">Sujet</th>
                                    <th style="padding: 1rem; text-align: left; font-weight: 600;">Fournisseur</th>
                                    <th style="padding: 1rem; text-align: left; font-weight: 600;">Statut</th>
                                    <th style="padding: 1rem; text-align: left; font-weight: 600;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tickets as $ticket): ?>
                                    <tr style="border-bottom: 1px solid var(--gray-200);">
                                        <td style="padding: 1rem;">
                                            <span style="font-weight: 700; color: var(--primary-color);"><?php echo htmlspecialchars($ticket['code_client'] ?? $ticket['numero_ticket']); ?></span>
                                        </td>
                                        <td style="padding: 1rem; font-size: 0.9rem; color: var(--gray-600);">
                                            <?php echo date('d/m/Y', strtotime($ticket['date_creation'])); ?>
                                        </td>
                                        <td style="padding: 1rem;">
                                            <?php echo htmlspecialchars($ticket['client_nom_complet'] ?? $ticket['nom_client'] . ' ' . $ticket['prenom_client']); ?>
                                        </td>
                                        <td style="padding: 1rem;">
                                            <?php echo htmlspecialchars(substr($ticket['sujet'], 0, 40)) . (strlen($ticket['sujet'])>40?'...':''); ?>
                                        </td>
                                        <td style="padding: 1rem;">
                                            <?php if($ticket['fournisseur_nom']): ?>
                                                <span class="badge" style="background: #e0e7ff; color: #4338ca; padding: 2px 8px; border-radius: 4px;">
                                                    <i class="fas fa-boxes"></i> <?php echo htmlspecialchars($ticket['fournisseur_nom']); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted">Non assigné</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="padding: 1rem;">
                                            <span style="background: <?php echo $ticket['statut_couleur']; ?>; color: white; padding: 0.25rem 0.75rem; border-radius: var(--radius-sm); font-size: 0.8rem;">
                                                <?php echo htmlspecialchars($ticket['statut_nom']); ?>
                                            </span>
                                        </td>
                                        <td style="padding: 1rem;">
                                            <a href="detail_ticket.php?id=<?php echo $ticket['id']; ?>" 
                                               class="btn btn-outline-primary"
                                               style="padding: 0.5rem 1rem; font-size: 0.875rem;">
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
        </div>
    </div>
    
    <script src="../../assets/js/main.js"></script>
    <script>
        // Menu mobile fonctionnel - Version Robuste
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
