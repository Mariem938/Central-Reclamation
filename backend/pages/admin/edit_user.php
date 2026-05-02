<?php
session_start();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../agent/login.php');
    exit;
}

$pageTitle = "Modifier Utilisateur - Admin";
$db = getDB();
$userId = $_GET['id'] ?? 0;
$error = '';
$success = '';

// Récupérer l'utilisateur
$stmt = $db->prepare("SELECT * FROM utilisateurs WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    header('Location: users.php');
    exit;
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = trim($_POST['nom'] ?? '');
    $prenom = trim($_POST['prenom'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telephone = trim($_POST['telephone'] ?? '');
    $role = $_POST['role'] ?? '';
    $typeFournisseur = $_POST['type_fournisseur'] ?? null;
    
    // Validation
    if (empty($nom) || empty($prenom) || empty($email)) {
        $error = "Les champs Nom, Prénom et Email sont obligatoires.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Email invalide.";
    } else {
        // Vérifier si l'email existe déjà (sauf pour cet utilisateur)
        $stmt = $db->prepare("SELECT id FROM utilisateurs WHERE email = ? AND id != ?");
        $stmt->execute([$email, $userId]);
        if ($stmt->fetch()) {
            $error = "Cet email est déjà utilisé par un autre utilisateur.";
        } else {
            try {
                // Mise à jour
                $sql = "UPDATE utilisateurs SET nom = ?, prenom = ?, email = ?, telephone = ?, role = ?";
                $params = [$nom, $prenom, $email, $telephone, $role];
                
                // Ajouter type_fournisseur si rôle = fournisseur
                if ($role === 'fournisseur' && !empty($typeFournisseur)) {
                    $sql .= ", type_fournisseur = ?";
                    $params[] = $typeFournisseur;
                }
                
                $sql .= " WHERE id = ?";
                $params[] = $userId;
                
                $stmt = $db->prepare($sql);
                $stmt->execute($params);
                
                // Logger l'action
                $stmt = $db->prepare("INSERT INTO historique_actions (utilisateur_id, action, details, date_action) VALUES (?, ?, ?, NOW())");
                $details = "Utilisateur #$userId ($prenom $nom) modifié par admin";
                $stmt->execute([$_SESSION['user_id'], 'user_edit', $details]);
                
                $success = "Utilisateur modifié avec succès.";
                
                // Rafraîchir les données
                $stmt = $db->prepare("SELECT * FROM utilisateurs WHERE id = ?");
                $stmt->execute([$userId]);
                $user = $stmt->fetch();
                
            } catch (Exception $e) {
                $error = "Erreur lors de la modification : " . $e->getMessage();
            }
        }
    }
}

include __DIR__ . '/includes/header.php';
?>

<div class="page-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
    <div>
        <h2 style="font-size: 1.75rem; font-weight: 800; letter-spacing: -0.03em; margin: 0;"><i class="fas fa-user-edit" style="color: var(--admin-primary); font-size: 1.5rem; margin-right: 0.5rem;"></i> Modifier l'utilisateur</h2>
        <p style="color: var(--text-muted); font-size: 0.95rem; margin-top: 0.25rem;">Profil : <?php echo htmlspecialchars($user['prenom'] . ' ' . $user['nom']); ?></p>
    </div>
    <div style="display: flex; gap: 0.75rem;">
        <a href="users.php" class="btn btn-outline btn-sm"><i class="fas fa-arrow-left"></i> Retour</a>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger mb-4">
        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
    </div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success mb-4">
        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-edit"></i> Informations Utilisateur</h3>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <label class="form-label">Prénom <span style="color: var(--danger-color);">*</span></label>
                            <input type="text" name="prenom" class="form-input" 
                                   value="<?php echo htmlspecialchars($user['prenom']); ?>" required>
                        </div>
                        <div class="col-md-6 mb-4">
                            <label class="form-label">Nom <span style="color: var(--danger-color);">*</span></label>
                            <input type="text" name="nom" class="form-input" 
                                   value="<?php echo htmlspecialchars($user['nom']); ?>" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <label class="form-label">Email <span style="color: var(--danger-color);">*</span></label>
                            <input type="email" name="email" class="form-input" 
                                   value="<?php echo htmlspecialchars($user['email']); ?>" required>
                        </div>
                        <div class="col-md-6 mb-4">
                            <label class="form-label">Téléphone</label>
                            <input type="text" name="telephone" class="form-input" 
                                   value="<?php echo htmlspecialchars($user['telephone'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-12 mb-4">
                            <label class="form-label">Rôle <span style="color: var(--danger-color);">*</span></label>
                            <select name="role" class="form-input" id="roleSelect" required>
                                <option value="client" <?php echo $user['role'] === 'client' ? 'selected' : ''; ?>>Client</option>
                                <option value="commercial" <?php echo $user['role'] === 'commercial' ? 'selected' : ''; ?>>Commercial</option>
                                <option value="rmq" <?php echo $user['role'] === 'rmq' ? 'selected' : ''; ?>>RMQ</option>
                                <option value="aqf" <?php echo $user['role'] === 'aqf' ? 'selected' : ''; ?>>AQF</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3" id="typeFournisseurDiv" style="display: <?php echo $user['role'] === 'fournisseur' ? 'block' : 'none'; ?>;">
                            <label class="form-label">Type de Fournisseur</label>
                            <select name="type_fournisseur" class="form-control">
                                <option value="">Non spécifié</option>
                                <option value="interne" <?php echo $user['type_fournisseur'] === 'interne' ? 'selected' : ''; ?>>Interne</option>
                                <option value="externe" <?php echo $user['type_fournisseur'] === 'externe' ? 'selected' : ''; ?>>Externe</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-5">
                        <label class="form-label">Statut du compte</label>
                        <div style="display: flex; align-items: center; gap: 1rem; background: var(--gray-50); padding: 1.25rem; border-radius: 12px; border: 1px solid var(--gray-100);">
                            <?php if ($user['actif']): ?>
                                <span class="badge badge-success">
                                    <i class="fas fa-check-circle"></i> Actif
                                </span>
                            <?php else: ?>
                                <span class="badge badge-danger">
                                    <i class="fas fa-times-circle"></i> Inactif
                                </span>
                            <?php endif; ?>
                            <a href="toggle_user.php?id=<?php echo $userId; ?>&action=<?php echo $user['actif'] ? 'desactiver' : 'activer'; ?>" 
                               class="btn btn-sm <?php echo $user['actif'] ? 'btn-outline-danger' : 'btn-outline-success'; ?>"
                               onclick="return confirm('<?php echo $user['actif'] ? 'Désactiver' : 'Activer'; ?> cet utilisateur ?');">
                                <i class="fas fa-<?php echo $user['actif'] ? 'ban' : 'check'; ?>"></i> 
                                <?php echo $user['actif'] ? 'Désactiver' : 'Activer le compte'; ?>
                            </a>
                        </div>
                    </div>
                    
                    <div style="margin-top: 2rem; padding-top: 1rem; border-top: 1px solid var(--gray-200); display: flex; justify-content: flex-end; gap: 0.75rem;">
                        <a href="users.php" class="btn btn-outline btn-sm">
                            Annuler
                        </a>
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="fas fa-save" style="margin-right: 0.4rem;"></i> Enregistrer les modifications
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card mb-3">
            <div class="card-header">
                <h4 class="m-0" style="font-size: 0.95rem; font-weight: 700;"><i class="fas fa-info-circle"></i> Informations</h4>
            </div>
            <div class="card-body">
                <p><strong>ID:</strong> #<?php echo $user['id']; ?></p>
                <p><strong>Date d'inscription:</strong><br><?php echo date('d/m/Y H:i', strtotime($user['date_creation'])); ?></p>
                <p><strong>Dernière connexion:</strong><br>
                    <?php if (isset($user['date_derniere_connexion']) && $user['date_derniere_connexion']): ?>
                        <?php echo date('d/m/Y H:i', strtotime($user['date_derniere_connexion'])); ?>
                    <?php else: ?>
                        <span style="color: var(--gray-400);">Jamais connecté</span>
                    <?php endif; ?>
                </p>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h4 class="m-0" style="font-size: 0.95rem; font-weight: 700;"><i class="fas fa-history"></i> Actions Rapides</h4>
            </div>
            <div class="card-body">
                <a href="activities.php?user_id=<?php echo $userId; ?>" class="btn btn-outline btn-block mb-2">
                    <i class="fas fa-list"></i> Voir ses Activités
                </a>
                <a href="reclamations.php?user_id=<?php echo $userId; ?>" class="btn btn-outline btn-block">
                    <i class="fas fa-ticket-alt"></i> Voir ses Réclamations
                </a>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('roleSelect').addEventListener('change', function() {
    const typeFournisseurDiv = document.getElementById('typeFournisseurDiv');
    if (this.value === 'fournisseur') {
        typeFournisseurDiv.style.display = 'block';
    } else {
        typeFournisseurDiv.style.display = 'none';
    }
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
