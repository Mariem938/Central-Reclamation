<?php
session_start();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../agent/login.php');
    exit;
}

$pageTitle = "Gestion des Utilisateurs - Admin";
$db = getDB();

// Filtres
$filtreRole = $_GET['role'] ?? 'tous';
$filtreStatut = $_GET['statut'] ?? 'tous';
$recherche = $_GET['recherche'] ?? '';

// Construction de la requête
// FILTRAGE PAR ANNÉE ACTIVE (Basé sur date_creation)
$sql = "SELECT * FROM utilisateurs 
        WHERE role IN ('client', 'rmq', 'aqf', 'commercial')
        AND YEAR(date_creation) = (SELECT annee FROM gestion_annees WHERE active = 1)";
$params = [];

if ($filtreRole !== 'tous') {
    $sql .= " AND role = ?";
    $params[] = $filtreRole;
}

if ($filtreStatut === 'actifs') {
    $sql .= " AND actif = 1";
} elseif ($filtreStatut === 'inactifs') {
    $sql .= " AND actif = 0";
}

if (!empty($recherche)) {
    $sql .= " AND (nom LIKE ? OR prenom LIKE ? OR email LIKE ?)";
    $searchTerm = "%$recherche%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

$sql .= " ORDER BY role, nom";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

// Construct search query

// Total actifs/inactifs (Seulement pour les rôles autorisés)
$totalActifs = $db->query("SELECT COUNT(*) FROM utilisateurs WHERE actif = 1 AND role IN ('client', 'rmq', 'aqf', 'commercial') AND YEAR(date_creation) = (SELECT annee FROM gestion_annees WHERE active = 1)")->fetchColumn();
$totalInactifs = $db->query("SELECT COUNT(*) FROM utilisateurs WHERE actif = 0 AND role IN ('client', 'rmq', 'aqf', 'commercial') AND YEAR(date_creation) = (SELECT annee FROM gestion_annees WHERE active = 1)")->fetchColumn();

include __DIR__ . '/includes/header.php';
?>

<div class="page-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
    <div>
        <h2 style="font-size: 1.75rem; font-weight: 800; letter-spacing: -0.03em; margin: 0;"><i class="fas fa-users" style="color: var(--admin-primary); font-size: 1.5rem; margin-right: 0.5rem;"></i> Utilisateurs</h2>
        <p style="color: var(--text-muted); font-size: 0.95rem; margin-top: 0.25rem;">Gérez les accès et les profils des collaborateurs.</p>
    </div>
    <div style="display: flex; gap: 0.75rem;">
        <a href="index.php" class="btn btn-outline btn-sm"><i class="fas fa-arrow-left"></i> Retour au Dashboard</a>
    </div>
</div>

<div class="stats-grid">
    <div class="stat-card" style="border-top: 5px solid #3b82f6;">
        <div class="stat-icon" style="background-color: var(--bg-pastel-blue); color: #3b82f6;">
            <i class="fa-solid fa-users"></i>
        </div>
        <div class="stat-info">
            <h3>Total Utilisateurs</h3>
            <p><?php echo count($users); ?></p>
        </div>
    </div>
    <div class="stat-card" style="border-top: 5px solid var(--success-color);">
        <div class="stat-icon" style="background-color: var(--success-pastel); color: var(--success-color);">
            <i class="fa-solid fa-user-check"></i>
        </div>
        <div class="stat-info">
            <h3>Actifs</h3>
            <p><?php echo $totalActifs; ?></p>
        </div>
    </div>
    <div class="stat-card" style="border-top: 5px solid var(--danger-color);">
        <div class="stat-icon" style="background-color: var(--danger-pastel); color: var(--danger-color);">
            <i class="fa-solid fa-user-slash"></i>
        </div>
        <div class="stat-info">
            <h3>Inactifs</h3>
            <p><?php echo $totalInactifs; ?></p>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body" style="padding: 1.5rem;">
        <form method="GET" action="" style="display: flex; gap: 1.25rem; flex-wrap: wrap; align-items: center;">
            <div style="flex: 2; min-width: 300px; position: relative;">
                <i class="fas fa-search" style="position: absolute; left: 1.25rem; top: 50%; transform: translateY(-50%); color: #94a3b8;"></i>
                <input type="text" name="recherche" placeholder="Rechercher un utilisateur..." 
                       value="<?php echo htmlspecialchars($recherche); ?>"
                       class="form-input" style="padding-left: 3rem;">
            </div>
            <select name="role" class="form-input" style="flex: 1; min-width: 150px;">
                <option value="tous">Tous les rôles</option>
                <option value="client" <?php echo $filtreRole === 'client' ? 'selected' : ''; ?>>Client</option>
                <option value="commercial" <?php echo $filtreRole === 'commercial' ? 'selected' : ''; ?>>Commercial</option>
                <option value="rmq" <?php echo $filtreRole === 'rmq' ? 'selected' : ''; ?>>RMQ</option>
                <option value="aqf" <?php echo $filtreRole === 'aqf' ? 'selected' : ''; ?>>AQF</option>
            </select>
            <select name="statut" class="form-input" style="flex: 1; min-width: 150px;">
                <option value="tous">Tous les statuts</option>
                <option value="actifs" <?php echo $filtreStatut === 'actifs' ? 'selected' : ''; ?>>Actifs uniquement</option>
                <option value="inactifs" <?php echo $filtreStatut === 'inactifs' ? 'selected' : ''; ?>>Inactifs uniquement</option>
            </select>
            <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Filtrer</button>
            <a href="users.php" class="btn btn-outline" title="Réinitialiser"><i class="fas fa-sync-alt"></i></a>
        </form>
    </div>
</div>

<!-- Liste des utilisateurs -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-list"></i> Liste des Utilisateurs (<?php echo count($users); ?>)</h3>
    </div>
    <div class="card-body">
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nom Complet</th>
                        <th>Email</th>
                        <th>Téléphone</th>
                        <th>Rôle</th>
                        <th>Statut</th>
                        <th>Date d'inscription</th>
                        <th>Dernière connexion</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="9" style="text-align: center; padding: 2rem; color: var(--gray-500);">
                                <i class="fas fa-inbox" style="font-size: 3rem; display: block; margin-bottom: 1rem;"></i>
                                Aucun utilisateur trouvé.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($users as $user): ?>
                        <tr>
                            <td style="font-weight: 800; color: var(--admin-primary);">#<?php echo $user['id']; ?></td>
                            <td style="font-weight: 700; color: #0f172a;"><?php echo htmlspecialchars($user['prenom'] . ' ' . $user['nom']); ?></td>
                            <td style="color: var(--text-muted); font-weight: 500;"><?php echo htmlspecialchars($user['email']); ?></td>
                            <td><?php echo htmlspecialchars($user['telephone'] ?? '-'); ?></td>
                            <td>
                                <span class="badge badge-info">
                                    <?php echo strtoupper(htmlspecialchars($user['role'])); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($user['actif']): ?>
                                    <span class="badge badge-success">
                                        <i class="fas fa-check-circle"></i> Actif
                                    </span>
                                <?php else: ?>
                                    <span class="badge badge-danger">
                                        <i class="fas fa-times-circle"></i> Inactif
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo date('d/m/Y', strtotime($user['date_creation'] ?? 'now')); ?></td>
                            <td>
                                <?php if (isset($user['date_derniere_connexion']) && $user['date_derniere_connexion']): ?>
                                    <?php echo date('d/m/Y H:i', strtotime($user['date_derniere_connexion'])); ?>
                                <?php else: ?>
                                    <span style="color: var(--gray-400);">Jamais</span>
                                <?php endif; ?>
                            </td>
                             <td>
                                <div style="display: flex; gap: 0.4rem; justify-content: flex-end;">
                                    <a href="edit_user.php?id=<?php echo $user['id']; ?>" 
                                       class="btn btn-sm btn-outline-primary" 
                                       title="Modifier">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="activities.php?user_id=<?php echo $user['id']; ?>" 
                                       class="btn btn-sm btn-outline-secondary" 
                                       title="Activités">
                                        <i class="fas fa-history"></i>
                                    </a>
                                    <?php if ($user['actif']): ?>
                                        <a href="toggle_user.php?id=<?php echo $user['id']; ?>&action=desactiver" 
                                           class="btn btn-sm btn-outline-danger" 
                                           title="Désactiver"
                                           onclick="return confirm('Désactiver cet utilisateur ?');">
                                            <i class="fas fa-ban"></i>
                                        </a>
                                    <?php else: ?>
                                        <a href="toggle_user.php?id=<?php echo $user['id']; ?>&action=activer" 
                                           class="btn btn-sm btn-outline-success" 
                                           title="Activer"
                                           onclick="return confirm('Activer cet utilisateur ?');">
                                            <i class="fas fa-check"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
