<?php
session_start();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../agent/login.php');
    exit;
}

$pageTitle = "Journal d'Activités - Admin";
$db = getDB();

// Filtres
$filtreUser = $_GET['user_id'] ?? 'tous';
$filtreAction = $_GET['action'] ?? 'tous';
$dateDebut = $_GET['date_debut'] ?? '';
$dateFin = $_GET['date_fin'] ?? '';
$filtreTicket = $_GET['ticket_id'] ?? '';

// Construction de la requête
$sql = "SELECT h.*, 
               CONCAT(u.prenom, ' ', u.nom) as utilisateur_nom,
               u.role as utilisateur_role,
               u.email as utilisateur_email,
               t.numero_ticket
        FROM historique_actions h
        LEFT JOIN utilisateurs u ON h.utilisateur_id = u.id
        LEFT JOIN tickets t ON h.ticket_id = t.id
        WHERE h.annee = (SELECT annee FROM gestion_annees WHERE active = 1)";
$params = [];

if ($filtreUser !== 'tous') {
    $sql .= " AND h.utilisateur_id = ?";
    $params[] = $filtreUser;
}

if ($filtreAction !== 'tous') {
    $sql .= " AND h.action = ?";
    $params[] = $filtreAction;
}

if (!empty($dateDebut)) {
    $sql .= " AND DATE(h.date_action) >= ?";
    $params[] = $dateDebut;
}

if (!empty($dateFin)) {
    $sql .= " AND DATE(h.date_action) <= ?";
    $params[] = $dateFin;
}

if (!empty($filtreTicket)) {
    $sql .= " AND h.ticket_id = ?";
    $params[] = $filtreTicket;
}

$sql .= " ORDER BY h.date_action DESC LIMIT 500";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$activities = $stmt->fetchAll();

// Liste des utilisateurs pour le filtre
$users = $db->query("SELECT id, CONCAT(prenom, ' ', nom) as nom_complet, role FROM utilisateurs ORDER BY nom")->fetchAll();

// Liste des types d'actions
$actionsQuery = $db->query("SELECT DISTINCT action FROM historique_actions ORDER BY action");
$actions = $actionsQuery->fetchAll(PDO::FETCH_COLUMN);

// Statistiques
$totalActivites = $db->query("SELECT COUNT(*) FROM historique_actions")->fetchColumn();
$activitesAujourdhui = $db->query("SELECT COUNT(*) FROM historique_actions WHERE DATE(date_action) = CURDATE()")->fetchColumn();
$activitesSemaine = $db->query("SELECT COUNT(*) FROM historique_actions WHERE date_action >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();

// Export CSV
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=activites_' . date('Y-m-d_H-i-s') . '.csv');
    
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM
    
    // En-têtes
    fputcsv($output, ['Date/Heure', 'Utilisateur', 'Rôle', 'Email', 'Action', 'Détails', 'Ticket'], ';');
    
    // Données
    foreach ($activities as $activity) {
        fputcsv($output, [
            date('d/m/Y H:i:s', strtotime($activity['date_action'])),
            $activity['utilisateur_nom'] ?? 'Système',
            $activity['utilisateur_role'] ?? '-',
            $activity['utilisateur_email'] ?? '-',
            $activity['action'],
            $activity['details'],
            $activity['numero_ticket'] ?? '-'
        ], ';');
    }
    
    fclose($output);
    exit;
}

include __DIR__ . '/includes/header.php';
?>

<div class="page-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
    <div>
        <h2 style="font-size: 1.75rem; font-weight: 800; letter-spacing: -0.03em; margin: 0;"><i class="fas fa-history" style="color: var(--admin-primary); font-size: 1.5rem; margin-right: 0.5rem;"></i> Activités</h2>
        <p style="color: var(--text-muted); font-size: 0.95rem; margin-top: 0.25rem;">Consultez les dernières opérations système.</p>
    </div>
    <div style="display: flex; gap: 0.75rem;">
        <a href="?<?php echo http_build_query(array_merge($_GET, ['export' => 'csv'])); ?>" 
           class="btn btn-outline-success btn-sm">
            <i class="fas fa-file-csv"></i> Exporter
        </a>
        <a href="index.php" class="btn btn-outline btn-sm"><i class="fas fa-arrow-left"></i> Retour</a>
    </div>
</div>

<div class="stats-grid">
    <div class="stat-card" style="border-top: 5px solid #3b82f6;">
        <div class="stat-icon" style="background-color: var(--bg-pastel-blue); color: #3b82f6;">
            <i class="fa-solid fa-list-check"></i>
        </div>
        <div class="stat-info">
            <h3>Total Activités</h3>
            <p><?php echo $totalActivites; ?></p>
        </div>
    </div>
    <div class="stat-card" style="border-top: 5px solid var(--success-color);">
        <div class="stat-icon" style="background-color: var(--success-pastel); color: var(--success-color);">
            <i class="fa-solid fa-calendar-day"></i>
        </div>
        <div class="stat-info">
            <h3>Aujourd'hui</h3>
            <p><?php echo $activitesAujourdhui; ?></p>
        </div>
    </div>
    <div class="stat-card" style="border-top: 5px solid #a855f7;">
        <div class="stat-icon" style="background-color: var(--bg-pastel-purple); color: #a855f7;">
            <i class="fa-solid fa-calendar-week"></i>
        </div>
        <div class="stat-info">
            <h3>Cette Semaine</h3>
            <p><?php echo $activitesSemaine; ?></p>
        </div>
    </div>
    <div class="stat-card" style="border-top: 5px solid var(--gray-400);">
        <div class="stat-icon" style="background-color: var(--bg-pastel-gray); color: var(--gray-600);">
            <i class="fa-solid fa-filter"></i>
        </div>
        <div class="stat-info">
            <h3>Filtrées</h3>
            <p><?php echo count($activities); ?></p>
        </div>
    </div>
</div>

<!-- Filtres -->
<div class="card">
    <div class="card-body" style="padding: 1.25rem;">
        <form method="GET" action="" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem;">
            <div>
                <label class="form-label">Utilisateur</label>
                <select name="user_id" class="form-input">
                    <option value="tous">Tous les utilisateurs</option>
                    <?php foreach ($users as $user): ?>
                        <option value="<?php echo $user['id']; ?>" <?php echo $filtreUser == $user['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($user['nom_complet']) . ' (' . strtoupper($user['role']) . ')'; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div>
                <label class="form-label">Type d'Action</label>
                <select name="action" class="form-input">
                    <option value="tous">Toutes les actions</option>
                    <?php foreach ($actions as $action): ?>
                        <option value="<?php echo htmlspecialchars($action); ?>" <?php echo $filtreAction === $action ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($action); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div>
                <label class="form-label">Date Début</label>
                <input type="date" name="date_debut" value="<?php echo htmlspecialchars($dateDebut); ?>" class="form-input">
            </div>
            
            <div>
                <label class="form-label">Date Fin</label>
                <input type="date" name="date_fin" value="<?php echo htmlspecialchars($dateFin); ?>" class="form-input">
            </div>
            
            <div style="display: flex; align-items: flex-end; gap: 0.5rem;">
                <button type="submit" class="btn btn-primary btn-sm" style="flex: 2;"><i class="fas fa-filter"></i> Filtrer</button>
                <a href="activities.php" class="btn btn-outline btn-sm" style="flex: 1;" title="Réinitialiser"><i class="fas fa-redo"></i></a>
            </div>
        </form>
    </div>
</div>

<!-- Liste des activités -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">
            <i class="fa-solid fa-list-ul"></i> 
            Historique des activités (<?php echo count($activities); ?>)
        </h3>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Date/Heure</th>
                        <th>Utilisateur</th>
                        <th>Rôle</th>
                        <th>Action</th>
                        <th>Détails</th>
                        <th>Ticket</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($activities)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 2rem; color: var(--gray-500);">
                                <i class="fas fa-inbox" style="font-size: 3rem; display: block; margin-bottom: 1rem;"></i>
                                Aucune activité trouvée.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($activities as $activity): ?>
                        <tr>
                            <td style="white-space: nowrap; font-size: 0.85rem; font-weight: 600;">
                                <i class="fas fa-clock" style="color: #cbd5e1; margin-right: 0.5rem;"></i>
                                <?php echo date('d/m/Y H:i:s', strtotime($activity['date_action'])); ?>
                            </td>
                            <td>
                                <div style="font-weight: 700; color: #0f172a;"><?php echo htmlspecialchars($activity['utilisateur_nom'] ?? 'Système'); ?></div>
                                <div style="font-size: 0.8rem; color: var(--text-muted); font-weight: 500;"><?php echo htmlspecialchars($activity['utilisateur_email'] ?? ''); ?></div>
                            </td>
                            <td>
                                <span class="badge" style="background: var(--gray-100); color: var(--gray-700);">
                                    <?php echo strtoupper(htmlspecialchars($activity['utilisateur_role'] ?? 'SYSTEM')); ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge" style="background: #dbeafe; color: #1e40af; padding: 0.25rem 0.75rem;">
                                    <?php echo htmlspecialchars($activity['action']); ?>
                                </span>
                            </td>
                            <td style="max-width: 300px;">
                                <div style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?php echo htmlspecialchars($activity['details']); ?>">
                                    <?php echo htmlspecialchars($activity['details']); ?>
                                </div>
                            </td>
                            <td>
                                <?php if ($activity['numero_ticket']): ?>
                                    <a href="view_ticket.php?id=<?php echo $activity['ticket_id']; ?>" 
                                       style="color: var(--admin-primary); font-weight: 800; text-decoration: none;">
                                        #<?php echo htmlspecialchars($activity['numero_ticket']); ?>
                                    </a>
                                <?php else: ?>
                                    <span style="color: #cbd5e1;">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <?php if (count($activities) >= 500): ?>
            <div style="margin-top: 1rem; padding: 1rem; background: #fef9c3; border-radius: var(--radius-md); color: #854d0e;">
                <i class="fas fa-info-circle"></i> Affichage limité à 500 résultats. Utilisez les filtres pour affiner votre recherche.
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
