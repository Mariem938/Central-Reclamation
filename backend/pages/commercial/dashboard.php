<?php
/**
 * Dashboard Commercial
 * Fichier: pages/commercial/dashboard.php
 * Interface principale pour les commerciaux
 */

require_once '../../config/database.php';

// Vérifier que l'utilisateur est connecté et est un commercial
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'commercial') {
    header('Location: ../agent/login.php?role=commercial');
    exit;
}

$db = getDB();
$userId = $_SESSION['user_id'];

// Récupérer les informations du commercial
$stmt = $db->prepare("SELECT * FROM utilisateurs WHERE id = ?");
$stmt->execute([$userId]);
$commercial = $stmt->fetch();

// Variables pour la recherche
$searchResults = null;
$searchQuery = '';
$clientFound = false;
$clientData = null;
$clientTickets = [];
$errorMessage = '';

// Traitement de la recherche client
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['search_client'])) {
    $nom = trim($_POST['nom'] ?? '');
    $prenom = trim($_POST['prenom'] ?? '');
    $code = trim($_POST['code_client'] ?? ''); // Nouveau champ
    
    if (!empty($nom) || !empty($prenom) || !empty($code)) {
        // Rechercher le client
        $conditions = [];
        $params = [];
        
        // Recherche par Code Client (dans la table tickets, faut faire une jointure ou chercher d'abord le ticket)
        // Mais Code Client est sur le Ticket, pas l'utilisateur directement ? 
        // L'utilisateur a demandé "Code Client" (probablement l'identifiant).
        // Si "Code Client" est stocké dans tickets.code_client, on doit chercher les clients LIÉS à ce code.
        
        // Option A: Code Client est sur tickets. On cherche le ticket -> client_id.
        if (!empty($code)) {
            // Cherche client via ticket
            $stmtCode = $db->prepare("SELECT DISTINCT client_id FROM tickets WHERE code_client LIKE ?");
            $stmtCode->execute(["%$code%"]);
            $clientIds = $stmtCode->fetchAll(PDO::FETCH_COLUMN);
            
            if ($clientIds) {
                $placeholders = implode(',', array_fill(0, count($clientIds), '?'));
                $conditions[] = "id IN ($placeholders)";
                $params = array_merge($params, $clientIds);
            } else {
                // Pas de ticket avec ce code -> Pas de client
                $conditions[] = "1=0"; 
            }
        }

        // Si on a aussi nom/prenom
        if (!empty($nom)) {
            $conditions[] = "nom LIKE ?";
            $params[] = "%$nom%";
        }
        if (!empty($prenom)) {
            $conditions[] = "prenom LIKE ?";
            $params[] = "%$prenom%";
        }
        
        // Clause WHERE
        if (!empty($conditions)) {
             // Si on a plusieurs conditions (Nom ET Code), c'est un AND ou OR ?
             // Recherche UX standard : Champs remplis s'ajoutent (AND) ou recherche globale.
             // Ici, on va assumer que si Code est rempli, ça filtre. Si Nom est rempli, ça filtre.
             // Pour éviter des requêtes vides si code ne trouve rien mais nom est vide :
             $whereClause = implode(' AND ', $conditions);
             
             // Attention si conditions est vide (cas impossible vu le if initial)
             $stmt = $db->prepare("SELECT * FROM utilisateurs WHERE role = 'client' AND ($whereClause)");
             $stmt->execute($params);
             $clients = $stmt->fetchAll();
        } else {
            $clients = [];
        }
        
        if (count($clients) > 0) {
            $clientFound = true;
            $searchResults = $clients;
        } else {
            $errorMessage = 'Aucun client trouvé avec ces critères.';
        }
    }
}

// Afficher les détails d'un client spécifique
if (isset($_GET['client_id'])) {
    $clientId = intval($_GET['client_id']);
    $stmt = $db->prepare("SELECT * FROM utilisateurs WHERE id = ? AND role = 'client'");
    $stmt->execute([$clientId]);
    $clientData = $stmt->fetch();
    
    if ($clientData) {
        // Récupérer les tickets du client
        $stmt = $db->prepare("
            SELECT t.*, 
                   c.nom as categorie_nom, c.couleur as categorie_couleur,
                   s.nom as statut_nom, s.couleur as statut_couleur
            FROM tickets t
            LEFT JOIN categories c ON t.categorie_id = c.id
            LEFT JOIN statuts s ON t.statut_id = s.id
            WHERE t.client_id = ? OR t.email_client = ?
            ORDER BY t.date_creation DESC
        ");
        $stmt->execute([$clientId, $clientData['email']]);
        $clientTickets = $stmt->fetchAll();
    }
}

// Statistiques globales
$stmt = $db->query("SELECT COUNT(*) as total FROM utilisateurs WHERE role = 'client' AND YEAR(date_creation) = (SELECT annee FROM gestion_annees WHERE active = 1)");
$totalClients = $stmt->fetch()['total'];

$stmt = $db->query("SELECT COUNT(*) as total FROM tickets WHERE cree_par_commercial = 1");
$result = $stmt->fetch();
$ticketsParCommercial = $result ? $result['total'] : 0;

// Récupérer les dernières réclamations (accessibles au commercial - ici toutes pour l'instant)
$stmt = $db->query("
    SELECT t.*, 
           c.nom as categorie_nom, c.couleur as categorie_couleur,
           s.nom as statut_nom, s.couleur as statut_couleur,
           u_client.nom as client_nom, u_client.prenom as client_prenom, t.code_client as client_code
    FROM tickets t
    LEFT JOIN categories c ON t.categorie_id = c.id
    LEFT JOIN statuts s ON t.statut_id = s.id
    LEFT JOIN utilisateurs u_client ON t.client_id = u_client.id
    ORDER BY t.date_creation DESC
    LIMIT 50
");
$toutesLesReclamations = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Commercial - <?php echo SITE_NAME; ?></title>
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
                    <span style="font-weight: 700; font-size: 1.1rem; color: #1A1A1A;">SCPC Comptoir Hammami</span>
                    <span style="font-size: 0.75rem; color: #666; font-weight: 500;"><?php echo SITE_NAME; ?></span>
                </div>
                <span class="navbar-brand-badge" style="background: #3B82F6;">
                    Commercial
                </span>
            </a>
            
            <div class="navbar-menu" id="mainMenu">
                <a href="dashboard.php" class="navbar-link active">
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
    <div class="mobile-menu" id="mobileMenu">
        <div class="mobile-menu-header">
            <i class="fas fa-briefcase"></i>
            <strong>Commercial</strong>
            <p class="mobile-menu-subtitle">Espace Agent</p>
        </div>
        <a href="dashboard.php" class="mobile-menu-link active"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a>
        <a href="create_claim.php" class="mobile-menu-link"><i class="fas fa-plus-circle"></i> <span>Nouvelle Réclamation</span></a>
        <a href="../logout.php" class="mobile-menu-link" style="color: var(--danger-color) !important;"><i class="fas fa-sign-out-alt"></i> <span>Déconnexion</span></a>
    </div>

    <div class="container" style="padding: 2rem 1rem;">
        <!-- En-tête -->
        <div class="glass-card" style="padding: 1.5rem; margin-bottom: 1.5rem; border: 1px solid rgba(0,0,0,0.05); box-shadow: var(--shadow-soft);">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                <div>
                    <h1 style="margin: 0; font-size: 1.75rem; display: flex; align-items: center; gap: 0.75rem;">
                        <i class="fas fa-briefcase" style="color: var(--primary);"></i>
                        Espace Commercial
                    </h1>
                    <p style="margin: 0.25rem 0 0 0; color: var(--text-muted); font-size: 0.9rem;">
                        Bienvenue, <?php echo htmlspecialchars($commercial['prenom'] . ' ' . $commercial['nom']); ?>
                    </p>
                </div>
                <div style="display: flex; gap: 0.75rem; align-items: center;">
                    <a href="create_claim.php" class="btn btn-primary" style="height: fit-content; padding: 0.6rem 1.2rem; border-radius: 10px; display: flex; align-items: center; gap: 8px; font-weight: 600; font-size: 0.9rem;">
                        <i class="fas fa-plus-circle"></i> Nouveau Ticket
                    </a>
                    <div style="display: flex; gap: 0.5rem;">
                        <div class="glass-card" style="padding: 0.5rem 1rem; text-align: center; min-width: 100px; border: 1px solid rgba(0,0,0,0.03);">
                            <h3 style="margin: 0; font-size: 1.1rem;"><?php echo $totalClients; ?></h3>
                            <small style="color: var(--text-muted); font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.5px;">Clients</small>
                        </div>
                        <div class="glass-card" style="padding: 0.5rem 1rem; text-align: center; min-width: 100px; border: 1px solid rgba(0,0,0,0.03);">
                            <h3 style="margin: 0; font-size: 1.1rem;"><?php echo $ticketsParCommercial; ?></h3>
                            <small style="color: var(--text-muted); font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.5px;">Tickets</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recherche Client -->
        <div class="glass-card" style="padding: 1.5rem; margin-bottom: 1.5rem; border: 1px solid rgba(0,0,0,0.05);">
            <h2 style="margin: 0 0 1.25rem 0; font-size: 1.25rem; display: flex; align-items: center; gap: 0.75rem;">
                <i class="fas fa-search" style="color: var(--primary); font-size: 1.1rem;"></i>
                Recherche Client
            </h2>
            <form method="POST" action="">
                <div style="display: flex; gap: 0.75rem; flex-wrap: wrap; align-items: flex-end;">
                    <div class="form-group" style="flex: 1; min-width: 180px; margin-bottom: 0;">
                        <label class="form-label" for="nom" style="font-size: 0.8rem; margin-bottom: 0.25rem;">Nom</label>
                        <input type="text" id="nom" name="nom" class="form-control" style="padding: 0.6rem;" placeholder="Nom..." value="<?php echo htmlspecialchars($_POST['nom'] ?? ''); ?>">
                    </div>
                    <div class="form-group" style="flex: 1; min-width: 180px; margin-bottom: 0;">
                        <label class="form-label" for="prenom" style="font-size: 0.8rem; margin-bottom: 0.25rem;">Prénom</label>
                        <input type="text" id="prenom" name="prenom" class="form-control" style="padding: 0.6rem;" placeholder="Prénom..." value="<?php echo htmlspecialchars($_POST['prenom'] ?? ''); ?>">
                    </div>
                    <div class="form-group" style="flex: 1; min-width: 180px; margin-bottom: 0;">
                         <label class="form-label" for="code_client" style="font-size: 0.8rem; margin-bottom: 0.25rem;">Code Client</label>
                        <input type="text" id="code_client" name="code_client" class="form-control" style="padding: 0.6rem;" placeholder="Code..." value="<?php echo htmlspecialchars($_POST['code_client'] ?? ''); ?>">
                    </div>
                    <button type="submit" name="search_client" class="btn btn-primary" style="height: 40px; padding: 0 1.5rem;">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </form>
            
            <?php if ($errorMessage): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($errorMessage); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($clientFound && $searchResults): ?>
                <div style="margin-top: 1.5rem;">
                    <h4 style="margin-bottom: 1rem; color: var(--text-dark);">
                        <i class="fas fa-list"></i> Résultats de la recherche (<?php echo count($searchResults); ?> client(s) trouvé(s))
                    </h4>
                    <div style="overflow-x: auto;">
                        <table style="width: 100%; border-collapse: collapse; background: white; border-radius: 8px; overflow: hidden; font-size: 0.9rem;">
                            <thead>
                                <tr style="background: #f8fafc; color: #475569; border-bottom: 2px solid #edf2f7;">
                                    <th style="padding: 0.75rem 1rem; text-align: left; font-weight: 600;">Nom</th>
                                    <th style="padding: 0.75rem 1rem; text-align: left; font-weight: 600;">Prénom</th>
                                    <th style="padding: 0.75rem 1rem; text-align: left; font-weight: 600;">Email</th>
                                    <th style="padding: 0.75rem 1rem; text-align: center; font-weight: 600;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($searchResults as $client): ?>
                                    <tr style="border-bottom: 1px solid #eee;">
                                        <td style="padding: 1rem;"><?php echo htmlspecialchars($client['nom']); ?></td>
                                        <td style="padding: 1rem;"><?php echo htmlspecialchars($client['prenom']); ?></td>
                                        <td style="padding: 1rem;"><?php echo htmlspecialchars($client['email']); ?></td>
                                        <td style="padding: 1rem; text-align: center;">
                                            <a href="?client_id=<?php echo $client['id']; ?>" class="btn btn-primary btn-sm">
                                                <i class="fas fa-eye"></i> Voir détails
                                            </a>
                                            <a href="create_claim.php?client_id=<?php echo $client['id']; ?>" class="btn btn-success btn-sm">
                                                <i class="fas fa-plus"></i> Créer réclamation
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($clientData): ?>
        <!-- Détails du Client -->
        <div class="glass-card" style="padding: 1.5rem; margin-bottom: 1.5rem; border: 1px solid rgba(0,0,0,0.05);">
            <h2 style="margin: 0 0 1.25rem 0; font-size: 1.25rem; display: flex; align-items: center; gap: 0.75rem;">
                <i class="fas fa-user-circle" style="color: var(--primary); font-size: 1.1rem;"></i>
                Informations Client
            </h2>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
                <div class="form-section" style="margin-bottom: 0;">
                    <label style="font-weight: 600; color: var(--text-muted); font-size: 0.875rem;">Nom</label>
                    <p style="margin: 0.25rem 0 0 0; font-size: 1.1rem; font-weight: 600;"><?php echo htmlspecialchars($clientData['nom']); ?></p>
                </div>
                <div class="form-section" style="margin-bottom: 0;">
                    <label style="font-weight: 600; color: var(--text-muted); font-size: 0.875rem;">Prénom</label>
                    <p style="margin: 0.25rem 0 0 0; font-size: 1.1rem; font-weight: 600;"><?php echo htmlspecialchars($clientData['prenom']); ?></p>
                </div>
                <div class="form-section" style="margin-bottom: 0;">
                    <label style="font-weight: 600; color: var(--text-muted); font-size: 0.875rem;">Email (Login)</label>
                    <p style="margin: 0.25rem 0 0 0; font-size: 1.1rem; font-weight: 600;"><?php echo htmlspecialchars($clientData['email']); ?></p>
                </div>
                <div class="form-section" style="margin-bottom: 0;">
                    <label style="font-weight: 600; color: var(--text-muted); font-size: 0.875rem;">Téléphone</label>
                    <p style="margin: 0.25rem 0 0 0; font-size: 1.1rem; font-weight: 600;"><?php echo htmlspecialchars($clientData['telephone'] ?? 'Non renseigné'); ?></p>
                </div>
            </div>
            
            <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                <a href="create_claim.php?client_id=<?php echo $clientData['id']; ?>" class="btn btn-primary">
                    <i class="fas fa-plus-circle"></i> Créer une réclamation pour ce client
                </a>
            </div>
        </div>

        <!-- Réclamations du Client -->
        <div class="glass-card" style="padding: 1.5rem; border: 1px solid rgba(0,0,0,0.05);">
            <h2 style="margin: 0 0 1.25rem 0; font-size: 1.25rem; display: flex; align-items: center; gap: 0.75rem;">
                <i class="fas fa-list-alt" style="color: var(--primary); font-size: 1.1rem;"></i>
                Tickets du Client (<?php echo count($clientTickets); ?>)
            </h2>
            
            <?php if (empty($clientTickets)): ?>
                <div style="text-align: center; padding: 2rem; color: var(--text-muted);">
                    <i class="fas fa-inbox" style="font-size: 3rem; opacity: 0.5; margin-bottom: 1rem;"></i>
                    <p>Ce client n'a pas encore de réclamations.</p>
                </div>
            <?php else: ?>
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse; background: white; border-radius: 8px; overflow: hidden; font-size: 0.85rem;">
                        <thead>
                            <tr style="background: #f8fafc; color: #475569; border-bottom: 2px solid #edf2f7;">
                                <th style="padding: 0.75rem 1rem; text-align: left; font-weight: 600;">N° Ticket</th>
                                <th style="padding: 0.75rem 1rem; text-align: left; font-weight: 600;">Sujet</th>
                                <th style="padding: 0.75rem 1rem; text-align: left; font-weight: 600;">Catégorie</th>
                                <th style="padding: 0.75rem 1rem; text-align: left; font-weight: 600;">Statut</th>
                                <th style="padding: 0.75rem 1rem; text-align: left; font-weight: 600;">Date</th>
                                <th style="padding: 0.75rem 1rem; text-align: center; font-weight: 600;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($clientTickets as $ticket): ?>
                                <tr style="border-bottom: 1px solid #eee;">
                                    <td style="padding: 1rem; font-weight: 600; color: var(--primary);">
                                        <?php echo htmlspecialchars($ticket['numero_ticket']); ?>
                                        <?php if (!empty($ticket['cree_par_commercial'])): ?>
                                            <span class="badge badge-warning" style="margin-left: 0.5rem; font-size: 0.7rem;">
                                                <i class="fas fa-briefcase"></i> Commercial
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding: 1rem;"><?php echo htmlspecialchars(substr($ticket['sujet'], 0, 40)); ?><?php if (strlen($ticket['sujet']) > 40) echo '...'; ?></td>
                                    <td style="padding: 1rem;">
                                        <span style="background: <?php echo $ticket['categorie_couleur']; ?>; color: white; padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.875rem;">
                                            <?php echo htmlspecialchars($ticket['categorie_nom']); ?>
                                        </span>
                                    </td>
                                    <td style="padding: 1rem;">
                                        <span style="background: <?php echo $ticket['statut_couleur']; ?>; color: white; padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.875rem;">
                                            <?php echo htmlspecialchars($ticket['statut_nom']); ?>
                                        </span>
                                    </td>
                                    <td style="padding: 1rem; font-size: 0.875rem;"><?php echo date('d/m/Y H:i', strtotime($ticket['date_creation'])); ?></td>
                                    <td style="padding: 1rem; text-align: center;">
                                        <a href="view_ticket.php?id=<?php echo $ticket['id']; ?>" class="btn btn-primary btn-sm">
                                            <i class="fas fa-eye"></i> Consulter
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>


        <!-- Tableau de toutes les réclamations -->
        <div class="glass-card" style="padding: 1.5rem; margin-top: 1.5rem; border: 1px solid rgba(0,0,0,0.05);">
            <?php 
            // Total réel des réclamations
            $totalClaimsYear = $db->query("SELECT COUNT(*) FROM tickets")->fetchColumn();
            ?>
            <h2 style="margin: 0 0 1.25rem 0; font-size: 1.25rem; display: flex; align-items: center; gap: 0.75rem;">
                <i class="fas fa-clipboard-list" style="color: var(--primary); font-size: 1.1rem;"></i>
                Liste des Réclamations (<?php echo count($toutesLesReclamations); ?> affichées / <?php echo $totalClaimsYear; ?>)
            </h2>
            
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; background: white; border-radius: 8px; overflow: hidden; font-size: 0.85rem;">
                    <thead>
                        <tr style="background: #f8fafc; color: #475569; border-bottom: 2px solid #edf2f7;">
                            <th style="padding: 0.75rem 1rem; text-align: left; font-weight: 600;">N° Ticket</th>
                            <th style="padding: 0.75rem 1rem; text-align: left; font-weight: 600;">Date</th>
                            <th style="padding: 0.75rem 1rem; text-align: left; font-weight: 600;">Client</th>
                            <th style="padding: 0.75rem 1rem; text-align: left; font-weight: 600;">Catégorie</th>
                            <th style="padding: 0.75rem 1rem; text-align: left; font-weight: 600;">Statut</th>
                            <th style="padding: 0.75rem 1rem; text-align: left; font-weight: 600;">Fournisseur</th>
                            <th style="padding: 0.75rem 1rem; text-align: center; font-weight: 600;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($toutesLesReclamations)): ?>
                            <tr>
                                <td colspan="7" style="padding: 2rem; text-align: center; color: var(--text-muted);">
                                    Aucune réclamation trouvée.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($toutesLesReclamations as $ticket): ?>
                                <tr style="border-bottom: 1px solid #eee;">
                                    <td style="padding: 1rem; font-weight: 600; color: var(--primary);">
                                        <?php echo htmlspecialchars($ticket['numero_ticket']); ?>
                                    </td>
                                    <td style="padding: 1rem; font-size: 0.875rem;">
                                        <?php echo date('d/m/Y H:i', strtotime($ticket['date_creation'])); ?>
                                    </td>
                                    <td style="padding: 1rem;">
                                        <div style="font-weight: 500;"><?php echo htmlspecialchars($ticket['client_nom'] . ' ' . $ticket['client_prenom']); ?></div>
                                        <?php if (!empty($ticket['client_code'])): ?>
                                            <div style="font-size: 0.75rem; color: var(--text-muted);">
                                                <?php echo htmlspecialchars($ticket['client_code']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding: 1rem;">
                                        <span style="background: <?php echo $ticket['categorie_couleur']; ?>; color: white; padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.875rem;">
                                            <?php echo htmlspecialchars($ticket['categorie_nom']); ?>
                                        </span>
                                    </td>
                                    <td style="padding: 1rem;">
                                        <span style="background: <?php echo $ticket['statut_couleur']; ?>; color: white; padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.875rem;">
                                            <?php echo htmlspecialchars($ticket['statut_nom']); ?>
                                        </span>
                                    </td>
                                    <td style="padding: 1rem;">
                                        <?php echo !empty($ticket['fournisseur_nom']) ? htmlspecialchars($ticket['fournisseur_nom']) : '<span style="color: var(--text-muted);">-</span>'; ?>
                                    </td>
                                    <td style="padding: 1rem; text-align: center;">
                                        <a href="view_ticket.php?id=<?php echo $ticket['id']; ?>" class="btn btn-primary btn-sm" title="Voir détails">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
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
