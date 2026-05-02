<?php
/**
 * Dashboard RMQ - VERSION CORRIGÉE COMPLÈTE
 * Fichier: pages/rmq/dashboard.php
 * Modifications:
 * - Code Client au lieu de N° Ticket
 * - Durée de Traitement (jours) au lieu de Temps Écoulé
 * - Boutons Modifier et Refuser
 * - Recherche par Code Client
 */

require_once '../../config/database.php';
require_once '../../api/functions.php';

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'rmq') {
    header('Location: ../agent/login.php?role=rmq');
    exit;
}

$db = getDB();
$userName = $_SESSION['user_prenom'] ?? 'RMQ';

// ========== TRAITEMENT ACTIONS POST ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $ticketId = intval($_POST['ticket_id'] ?? 0);

    if ($action === 'valider' && $ticketId > 0) {
        try {
            // Récupérer ID du statut "En cours de vérification" (ID 1)
            $statutVerif = 1;
            
            // Mettre à jour
            $db->prepare("UPDATE tickets SET statut_id = ?, date_debut_traitement = NOW() WHERE id = ?")->execute([$statutVerif, $ticketId]);
            
            // Enregistrer dans l'historique
            $db->prepare("INSERT INTO historique_actions (ticket_id, utilisateur_id, action, details, date_action) VALUES (?, ?, 'validation', 'Ticket validé par RMQ', NOW())")->execute([$ticketId, $_SESSION['user_id']]);
            
            // Redirection avec message de succès
            header('Location: dashboard.php?validation=success');
            exit;
        } catch (Exception $e) {
            // En cas d'erreur, rester sur la page
        }
    }
}

// ========== RÉCUPÉRATION DES DONNÉES ==========
$filtreStatut = $_GET['statut'] ?? 'tous';
$recherche = trim($_GET['recherche'] ?? '');

// Récupérer les tickets
$sql = "
    SELECT t.*, 
           c.nom as categorie_nom, c.couleur as categorie_couleur,
           s.nom as statut_nom, s.couleur as statut_couleur
    FROM tickets t
    LEFT JOIN categories c ON t.categorie_id = c.id
    LEFT JOIN statuts s ON t.statut_id = s.id
    WHERE 1=1
";

$params = [];

// Filtre par statut
if ($filtreStatut !== 'tous') {
    $sql .= " AND t.statut_id = ?";
    $params[] = $filtreStatut;
}

// Recherche
if (!empty($recherche)) {
    $sql .= " AND (
        t.numero_ticket LIKE ? 
        OR t.code_client LIKE ?
        OR CONCAT(t.prenom_client, ' ', t.nom_client) LIKE ?
    )";
    $searchTerm = "%$recherche%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

$sql .= " ORDER BY t.date_creation DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$tickets = $stmt->fetchAll();

// Récupérer les statuts
$statuts = getStatuts();

// Durée de traitement helper (still useful for the table)
function calculerDureeTraitement($ticket) {
    if ($ticket['date_debut_traitement'] && $ticket['date_fin_traitement']) {
        $debut = new DateTime($ticket['date_debut_traitement']);
        $fin = new DateTime($ticket['date_fin_traitement']);
        $interval = $debut->diff($fin);
        return $interval->days;
    } else if ($ticket['date_debut_traitement'] && $ticket['statut_id'] != 5) {
        // En cours: depuis début jusqu'à maintenant
        $debut = new DateTime($ticket['date_debut_traitement']);
        $maintenant = new DateTime();
        $interval = $debut->diff($maintenant);
        return $interval->days;
    }
    return 0;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard RMQ - <?php echo SITE_NAME; ?></title>
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
                <span class="navbar-brand-badge" style="background: linear-gradient(135deg, #FF6B35 0%, #FFA500 100%);">
                    Espace RMQ
                </span>
            </a>
            
            <div class="navbar-menu" id="mainMenu">
                <a href="dashboard.php" class="navbar-link active">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
                <a href="statistiques.php" class="navbar-link">
                    <i class="fas fa-chart-bar"></i>
                    <span>Statistiques</span>
                </a>
                <a href="create_claim.php" class="navbar-link">
                    <i class="fas fa-plus-circle"></i>
                    <span>Nouvelle Réclamation</span>
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

    <!-- Mobile Menu -->
    <div class="mobile-menu" id="mobileMenu">
        <div class="mobile-menu-header">
            <i class="fas fa-tasks"></i>
            <strong>RMQ</strong>
            <p class="mobile-menu-subtitle">Espace RMQ</p>
        </div>
        <a href="dashboard.php" class="mobile-menu-link active"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a>
        <a href="statistiques.php" class="mobile-menu-link"><i class="fas fa-chart-bar"></i> <span>Statistiques</span></a>
        <a href="create_claim.php" class="mobile-menu-link"><i class="fas fa-plus-circle"></i> <span>Nouvelle Réclamation</span></a>
        
        <a href="../../pages/logout.php" class="mobile-menu-link" style="color: #ef4444 !important;"><i class="fas fa-sign-out-alt"></i> <span>Déconnexion</span></a>
    </div>

    <div class="container-fluid" style="padding: 2rem;">
        <!-- Message de Succès - VALIDATION -->
        <?php if (isset($_GET['validation']) && $_GET['validation'] === 'success'): ?>
            <div style="background: #d1fae5; border: 1px solid #6ee7b7; color: #065f46; padding: 1rem 1.5rem; border-radius: 8px; margin-bottom: 2rem; display: flex; align-items: center; gap: 1rem;">
                <i class="fas fa-check-circle" style="font-size: 1.5rem; color: #10B981;"></i>
                <div>
                    <strong>✅ Validation réussie!</strong> Le ticket a été accepté et mis en statut "En cours de vérification".
                </div>
                <button onclick="this.parentElement.style.display='none';" style="margin-left: auto; background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #065f46;">×</button>
            </div>
        <?php endif; ?>

        <!-- Message de Succès - REFUS -->
        <?php if (isset($_GET['refus']) && $_GET['refus'] === 'success'): ?>
            <div style="background: #fee2e2; border: 1px solid #fca5a5; color: #7f1d1d; padding: 1rem 1.5rem; border-radius: 8px; margin-bottom: 2rem; display: flex; align-items: center; gap: 1rem;">
                <i class="fas fa-times-circle" style="font-size: 1.5rem; color: #dc2626;"></i>
                <div>
                    <strong>❌ Refus enregistré!</strong> Le ticket a été refusé et le client a été notifié.
                </div>
                <button onclick="this.parentElement.style.display='none';" style="margin-left: auto; background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #7f1d1d;">×</button>
            </div>
        <?php endif; ?>
        
        <!-- Barre de recherche et filtres -->
        <div class="glass-card" style="margin-bottom: 2rem; padding: 1.5rem;">
            <form method="GET" action="" style="display: flex; gap: 0.75rem; flex-wrap: wrap; align-items: center; justify-content: space-between;">
                <div style="flex: 1; min-width: 250px;">
                    <div style="position: relative;">
                        <i class="fas fa-search" style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: var(--text-muted);"></i>
                        <input type="text" name="recherche" class="form-control" placeholder="Numéro ticket, sujet, client..." 
                               value="<?php echo htmlspecialchars($recherche); ?>"
                               style="padding-left: 2.75rem;">
                    </div>
                </div>
                <select name="statut" class="form-control" style="flex-shrink: 1; min-width: 180px; max-width: 220px;">
                    <option value="tous">Tous les statuts</option>
                    <option value="6" <?php echo $filtreStatut == 6 ? 'selected' : ''; ?>>En attente validation RMQ</option>
                    <option value="1" <?php echo $filtreStatut == 1 ? 'selected' : ''; ?>>En cours de vérification</option>
                    <option value="2" <?php echo $filtreStatut == 2 ? 'selected' : ''; ?>>En cours de traitement</option>
                    <option value="3" <?php echo $filtreStatut == 3 ? 'selected' : ''; ?>>En attente réponse fournisseur</option>
                    <option value="4" <?php echo $filtreStatut == 4 ? 'selected' : ''; ?>>Résolu</option>
                    <option value="8" <?php echo $filtreStatut == 8 ? 'selected' : ''; ?>>Refusé</option>
                </select>
                <button type="submit" class="btn btn-primary" style="flex-shrink: 0; white-space: nowrap;">
                    <i class="fas fa-filter"></i> Filtrer
                </button>
            </form>
        </div>

        <!-- Liste des tickets -->
        <div class="glass-card">
            <div class="card-header" style="background: white; border-bottom: 2px solid var(--gray-200); padding: 1.25rem 1.5rem;">
                <h3 style="margin: 0; display: flex; align-items: center; gap: 0.75rem; font-size: 1.1rem; color: var(--text-dark);">
                    <i class="fas fa-ticket-alt" style="color: var(--primary);"></i>
                    Tickets à Gérer
                    <span style="background: var(--gray-100); padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.875rem; color: var(--text-gray);">
                        <?php echo count($tickets); ?>
                    </span>
                </h3>
            </div>
            <div class="card-body" style="padding: 0;">
                <?php if (empty($tickets)): ?>
                    <div style="text-align: center; padding: 4rem 2rem;">
                        <div style="width: 80px; height: 80px; margin: 0 auto 1.5rem; background: #F5F5F5; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                            <i class="fas fa-inbox" style="font-size: 2rem; color: var(--text-muted);"></i>
                        </div>
                        <h4 style="margin: 0 0 0.5rem 0; color: var(--text-dark);">Aucun Ticket</h4>
                        <p style="margin: 0; color: var(--text-muted);">Aucun ticket à afficher avec ces critères</p>
                    </div>
                <?php else: ?>
                    <div style="overflow-x: auto;">
                        <table style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr style="background: var(--gray-50); border-bottom: 2px solid var(--gray-200);">
                                    <th style="padding: 1rem; text-align: left; font-weight: 600;">Code Client</th>
                                    <th style="padding: 1rem; text-align: left; font-weight: 600;">Sujet</th>
                                    <th style="padding: 1rem; text-align: left; font-weight: 600;">Client</th>
                                    <th style="padding: 1rem; text-align: left; font-weight: 600;">Catégorie</th>
                                    <th style="padding: 1rem; text-align: left; font-weight: 600;">Statut</th>
                                    <th style="padding: 1rem; text-align: left; font-weight: 600;">⏱️ Durée (Jours)</th>
                                    <th style="padding: 1rem; text-align: left; font-weight: 600;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tickets as $ticket): ?>
                                    <tr style="border-bottom: 1px solid var(--gray-200);">
                                        <td style="padding: 1rem;"><strong><?php echo !empty($ticket['code_client']) ? htmlspecialchars($ticket['code_client']) : '<span style="color: #999; font-weight: normal;">N/A</span>'; ?></strong></td>
                                        <td style="padding: 1rem;"><?php echo htmlspecialchars(mb_substr(html_entity_decode($ticket['sujet'], ENT_QUOTES), 0, 50)); ?></td>
                                        <td style="padding: 1rem;"><?php echo htmlspecialchars($ticket['prenom_client'] . ' ' . $ticket['nom_client']); ?></td>
                                        <td style="padding: 1rem;">
                                            <?php 
                                            // Afficher la catégorie basée sur type_ticket
                                            $typeTicket = $ticket['type_ticket'] ?? '';
                                            $categorieDisplay = '';
                                            $categorieColor = '#6B7280'; // Gris par défaut
                                            
                                            if ($typeTicket === 'produit') {
                                                $categorieDisplay = 'Produit';
                                                $categorieColor = '#3B82F6'; // Bleu
                                            } elseif ($typeTicket === 'service') {
                                                $categorieDisplay = 'Service';
                                                $categorieColor = '#10B981'; // Vert
                                            } elseif ($typeTicket === 'produit,service' || $typeTicket === 'service,produit') {
                                                $categorieDisplay = 'Produit & Service';
                                                $categorieColor = '#F59E0B'; // Orange
                                            } else {
                                                $categorieDisplay = !empty($ticket['categorie_nom']) ? $ticket['categorie_nom'] : 'N/A';
                                                $categorieColor = $ticket['categorie_couleur'] ?? '#6B7280';
                                            }
                                            ?>
                                            <span style="background: <?php echo $categorieColor; ?>; color: white; padding: 0.25rem 0.75rem; border-radius: var(--radius-sm); font-size: 0.875rem;">
                                                <?php echo htmlspecialchars($categorieDisplay); ?>
                                            </span>
                                        </td>
                                        <td style="padding: 1rem;">
                                            <span style="background: <?php echo $ticket['statut_couleur']; ?>; color: white; padding: 0.25rem 0.75rem; border-radius: var(--radius-sm); font-size: 0.875rem;">
                                                <?php echo htmlspecialchars($ticket['statut_nom']); ?>
                                            </span>
                                        </td>
                                        <td style="padding: 1rem;">
                                            <?php 
                                                // Calcul Durée stockée ou dynamique
                                                $duree = 0;
                                                if ($ticket['statut_nom'] == 'Résolu' && isset($ticket['duree_traitement'])) {
                                                    $duree = $ticket['duree_traitement'];
                                                } elseif ($ticket['date_debut_traitement']) {
                                                    $start = new DateTime($ticket['date_debut_traitement']);
                                                    $now = new DateTime();
                                                    $duree = $start->diff($now)->days;
                                                }
                                                echo '<span style="color: var(--gray-600); font-weight: 500;">' . $duree . ' j</span>';
                                            ?>
                                        </td>
                                        <td style="padding: 1rem;">
                                            <!-- Bouton Gérer standard -->
                                            <a href="detail_ticket.php?id=<?php echo $ticket['id']; ?>" 
                                                class="btn btn-primary"
                                                style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.75rem 1.25rem; background: linear-gradient(135deg, #FF6B35 0%, #FFA500 100%); color: white; text-decoration: none; border: none; border-radius: 12px; font-weight: 600; font-size: 0.875rem; transition: all 0.3s; font-family: 'Poppins', sans-serif; box-shadow: 0 2px 8px rgba(255, 107, 53, 0.2);">
                                                <i class="fas fa-cog"></i> Gérer
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
