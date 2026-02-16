<?php
/**
 * Page de Connexion Agent
 * Fichier: pages/agent/login.php
 */

session_start(); // IMPORTANT: Démarrer la session
require_once '../../config/database.php';

$error = '';
$success = '';

// ========== AUTO-CREATION COMPTES TEST (À ENLEVER EN PRODUCTION) ==========
try {
    $db = getDB();
    
    // Créer comptes de test s'ils n'existent pas
    $test_accounts = [
        ['email' => 'aqf@reclamation.com', 'role' => 'aqf', 'name' => 'AQF'],
        ['email' => 'rmq@reclamation.com', 'role' => 'rmq', 'name' => 'RMQ'],
        ['email' => 'admin@reclamation.com', 'role' => 'admin', 'name' => 'Admin'],
    ];
    
    $test_password = '1234';
    $hashed_password = password_hash($test_password, PASSWORD_DEFAULT);
    
    foreach ($test_accounts as $acc) {
        $stmt = $db->prepare("SELECT id FROM utilisateurs WHERE email = ? AND actif = 1");
        $stmt->execute([$acc['email']]);
        
        if (!$stmt->fetch()) {
            // Supprimer les versions inactives
            $db->prepare("DELETE FROM utilisateurs WHERE email = ?")->execute([$acc['email']]);
            
            // Créer le compte actif
            $db->prepare("
                INSERT INTO utilisateurs (email, mot_de_passe, nom, prenom, role, actif, date_creation)
                VALUES (?, ?, ?, 'Test', ?, 1, NOW())
            ")->execute([$acc['email'], $hashed_password, $acc['name'], $acc['role']]);
        }
    }
} catch (Exception $e) {
    // Silencieusement ignorer les erreurs de création
}
// ========== FIN AUTO-CREATION ==========

// Traitement du formulaire de connexion
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = 'Veuillez remplir tous les champs.';
    } else {
        try {
            $db = getDB();
            $stmt = $db->prepare("SELECT * FROM utilisateurs WHERE email = ? AND actif = 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            // Debug: Vérifier si l'utilisateur existe
            if (!$user) {
                $error = 'Aucun compte actif trouvé avec cet email.';
            } else {
                // Vérifier le mot de passe
                if (password_verify($password, $user['mot_de_passe'])) {
                    // Connexion réussie
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_nom'] = $user['nom'];
                    $_SESSION['user_prenom'] = $user['prenom'];
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['user_role'] = $user['role'];
                    
                    // Mettre à jour la dernière connexion (si la colonne existe)
                    try {
                        $stmt = $db->prepare("UPDATE utilisateurs SET date_derniere_connexion = NOW() WHERE id = ?");
                        $stmt->execute([$user['id']]);
                    } catch (PDOException $e) {
                        // Ignorer si la colonne n'existe pas encore
                    }
                    
                    // Rediriger selon le rôle
                    switch ($user['role']) {
                        case 'rmq':
                            header('Location: ../rmq/dashboard.php');
                            break;
                        case 'aqf':
                            header('Location: ../aqf/dashboard.php');
                            break;
                        case 'admin':
                            header('Location: ../admin/index.php');
                            break;
                        case 'commercial':
                            header('Location: ../commercial/dashboard.php');
                            break;
                        case 'rfq':
                            header('Location: ../rfq/dashboard.php');
                            break;
                        case 'agent':
                            header('Location: dashboard.php');
                            break;
                        default:
                            header('Location: dashboard.php');
                            break;
                    }
                    exit;
                } else {
                    $error = 'Mot de passe incorrect.';
                }
            }
        } catch(PDOException $e) {
            $error = 'Erreur de connexion : ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion Agent - <?php echo SITE_NAME; ?></title>
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
            </div>
        </div>
    </nav>
    <div class="hero-section">
        <div class="container">
            <div class="card fade-in" style="max-width: 500px; margin: 0 auto;">
                <div class="card-body p-5">
                    <div class="text-center mb-4">
                        <div class="option-icon icon-info" style="width: 80px; height: 80px; margin: 0 auto 1rem;">
                            <i class="fas fa-user-tie"></i>
                        </div>
                        <?php 
                        $role = $_GET['role'] ?? 'agent';
                        $titles = [
                            'rmq' => 'Espace RMQ',
                            'aqf' => 'Espace AQF',
                            'admin' => 'Espace Admin',
                            'commercial' => 'Espace Commercial',
                            'rfq' => 'Espace RFQ',
                            'agent' => 'Espace Agent',
                            'fournisseur' => 'Espace Fournisseur'
                        ];
                        $pageTitle = $titles[$role] ?? 'Espace Agent';
                        ?>
                        <h2 class="mb-2"><?php echo htmlspecialchars($pageTitle); ?></h2>
                        <p style="color: var(--gray-600);">Connectez-vous pour accéder au tableau de bord</p>
                    </div>

                    <!-- Messages d'erreur/succès -->
                    <?php if ($error): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle"></i>
                            <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i>
                            <?php echo htmlspecialchars($success); ?>
                        </div>
                    <?php endif; ?>

                    <!-- Formulaire de connexion -->
                    <form method="POST" action="">
                        <div class="form-group">
                            <label class="form-label" for="email">
                                <i class="fas fa-envelope"></i> Email
                            </label>
                            <input 
                                type="email" 
                                id="email" 
                                name="email" 
                                class="form-control" 
                                placeholder="votre@email.com"
                                value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                                required
                            >
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="password">
                                <i class="fas fa-lock"></i> Mot de passe
                            </label>
                            <input 
                                type="password" 
                                id="password" 
                                name="password" 
                                class="form-control" 
                                placeholder="••••••••"
                                required
                            >
                        </div>

                        <button type="submit" class="btn btn-primary btn-block btn-lg">
                            <i class="fas fa-sign-in-alt"></i> Se connecter
                        </button>
                    </form>

                    <!-- Informations de test -->
                    <div style="margin-top: 2rem; padding: 1rem; background: var(--gray-50); border-radius: var(--radius-lg); border-left: 4px solid var(--info-color);">
                        <h4 style="font-size: 0.875rem; margin-bottom: 0.5rem;">
                            <i class="fas fa-info-circle"></i> Comptes de test
                        </h4>
                        <p style="font-size: 0.75rem; color: var(--gray-600); margin: 0;">
                            <strong>Admin:</strong> admin@reclamation.com / 1234<br>
                            <strong>Commercial:</strong> commercial@reclamation.com / commercial2026<br>
                            <strong>AQF:</strong> aqf@groupe-hammami.com / 1234<br>
                            <strong>RMQ:</strong> rmq.scpc@groupe-hammami.com / rmq1234
                        </p>
                    </div>

                    <!-- Lien retour -->
                    <div class="text-center mt-4">
                        <a href="../../index.php" style="color: var(--gray-600); text-decoration: none; font-size: 0.875rem;">
                            <i class="fas fa-arrow-left"></i> Retour à l'accueil
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="../../assets/js/main.js"></script>
</body>
</html>
