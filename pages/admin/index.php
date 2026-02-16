<?php
session_start();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

// Vérification de session - Redirection si non connecté
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../agent/login.php?role=admin');
    exit;
}

$pageTitle = "Tableau de Bord - Central Réclamation";

// Récupérer les statistiques
$db = getDB();
$totalTickets = $db->query("SELECT COUNT(*) FROM tickets")->fetchColumn();
$nouveauxTickets = $db->query("SELECT COUNT(*) FROM tickets WHERE statut_id = 1")->fetchColumn();
$enCoursTickets = $db->query("SELECT COUNT(*) FROM tickets WHERE statut_id = 2")->fetchColumn();
$resoluTickets = $db->query("SELECT COUNT(*) FROM tickets WHERE statut_id = 4")->fetchColumn();

// Récupérer les dernières réclamations
$recentTickets = $db->query("
    SELECT t.*, s.nom as statut_nom, c.nom as categorie_nom 
    FROM tickets t 
    JOIN statuts s ON t.statut_id = s.id 
    JOIN categories c ON t.categorie_id = c.id 
    ORDER BY t.date_creation DESC 
    LIMIT 5
")->fetchAll();

include __DIR__ . '/includes/header.php';
?>

<div class="page-header" style="margin-bottom: 2rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
    <div>
        <h2 style="font-size: 1.75rem; font-weight: 800; letter-spacing: -0.03em; color: #0f172a; margin: 0;">Tableau de Bord</h2>
        <p style="color: var(--text-muted); font-size: 0.95rem; margin-top: 0.25rem;">Bienvenue sur votre espace de gestion premium.</p>
    </div>
    <div style="display: flex; gap: 0.75rem;">
        <a href="users.php" class="btn btn-outline-primary btn-sm"><i class="fas fa-users"></i> Utilisateurs</a>
        <a href="activities.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-history"></i> Activités</a>
    </div>
</div>

<div class="stats-grid">
    <div class="stat-card" style="border-top: 5px solid #3b82f6;">
        <div class="stat-icon" style="background-color: var(--bg-pastel-blue); color: #3b82f6;">
            <i class="fa-solid fa-ticket"></i>
        </div>
        <div class="stat-info">
            <h3>Total Réclamations</h3>
            <p><?php echo $totalTickets; ?></p>
        </div>
    </div>
    <div class="stat-card" style="border-top: 5px solid var(--warning-color);">
        <div class="stat-icon" style="background-color: var(--warning-pastel); color: var(--warning-color);">
            <i class="fa-solid fa-clock"></i>
        </div>
        <div class="stat-info">
            <h3>En Attente</h3>
            <p><?php echo $nouveauxTickets; ?></p>
        </div>
    </div>
    <div class="stat-card" style="border-top: 5px solid var(--success-color);">
        <div class="stat-icon" style="background-color: var(--success-pastel); color: var(--success-color);">
            <i class="fa-solid fa-check-double"></i>
        </div>
        <div class="stat-info">
            <h3>Résolues</h3>
            <p><?php echo $resoluTickets; ?></p>
        </div>
    </div>
    <div class="stat-card" style="border-top: 5px solid #6366f1;">
        <div class="stat-icon" style="background-color: #eef2ff; color: #6366f1;">
            <i class="fa-solid fa-users"></i>
        </div>
        <div class="stat-info">
            <h3>Clients</h3>
            <?php 
                $totalClients = $db->query("SELECT COUNT(*) FROM utilisateurs WHERE role = 'client'")->fetchColumn();
            ?>
            <p><?php echo $totalClients; ?></p>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">Réclamations Récentes</h3>
        <a href="reclamations.php" class="btn btn-outline btn-sm">Voir tout</a>
    </div>
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Ticket</th>
                    <th>Client</th>
                    <th>Sujet</th>
                    <th>Catégorie</th>
                    <th>Statut</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentTickets as $ticket): ?>
                <tr>
                    <td style="font-weight: 800; color: var(--admin-primary);">#<?php echo $ticket['numero_ticket']; ?></td>
                    <td><?php echo htmlspecialchars($ticket['prenom_client'] . ' ' . $ticket['nom_client']); ?></td>
                    <td><?php echo htmlspecialchars($ticket['sujet']); ?></td>
                    <td><?php echo htmlspecialchars($ticket['categorie_nom']); ?></td>
                    <td><?php echo getStatutBadge($ticket['statut_nom']); ?></td>
                    <td><?php echo formatDateTime($ticket['date_creation']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
