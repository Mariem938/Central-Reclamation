<?php
/**
 * Page de Refus de Réclamation - RMQ (VERSION CORRIGÉE)
 * Fichier: pages/rmq/refuse_claim.php
 * 
 * Fonctionnalités:
 * ✅ Formulaire avec raison OBLIGATOIRE
 * ✅ Mise à jour statut en BD
 * ✅ Enregistrement de la raison en BD
 * ✅ Envoi email HTML au client avec:
 *    - Texte en ROUGE pour le refus
 *    - Numéro de la réclamation
 *    - Message clair de refus
 *    - Raison exacte saisie
 * ✅ Gestion erreurs try/catch
 * ✅ Message de succès/erreur après action
 * ✅ Email envoyé AVANT redirection
 */

require_once '../../config/database.php';
require_once '../../api/functions.php';
require_once '../../api/EmailManager.php';

// Vérification Rôle
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'rmq') {
    header('Location: ../agent/login.php');
    exit;
}

$db = getDB();

// ✅ MIGRATION AUTO: Ajouter les colonnes si nécessaire
try {
    $db->exec("ALTER TABLE tickets ADD COLUMN IF NOT EXISTS motif_refus LONGTEXT DEFAULT NULL");
    $db->exec("ALTER TABLE tickets ADD COLUMN IF NOT EXISTS date_fin_traitement DATETIME DEFAULT NULL");
} catch (Exception $e) {
    error_log("Migration auto: " . $e->getMessage());
}

$ticketId = intval($_GET['id'] ?? 0);
$error = '';
$success = false;
$successMessage = '';

// Récupérer le ticket avec les infos client
$stmt = $db->prepare("
    SELECT t.*, 
           CONCAT(COALESCE(t.prenom_client, ''), ' ', COALESCE(t.nom_client, '')) as client_nom,
           t.email_client,
           t.numero_ticket,
           s.nom as statut_nom
    FROM tickets t
    LEFT JOIN statuts s ON t.statut_id = s.id
    WHERE t.id = ?
");
$stmt->execute([$ticketId]);
$ticket = $stmt->fetch();

if (!$ticket) {
    header('Location: dashboard.php');
    exit;
}

// ID Statut "Refusé" = 5 (selon schema)
$statutRefuseId = 5;

// ================================================================================
// TRAITEMENT DU FORMULAIRE DE REFUS
// ================================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $motifRefus = trim($_POST['motif_refus'] ?? '');
    
    // ✅ VALIDATION 1: Raison obligatoire
    if (empty($motifRefus)) {
        $error = "❌ Le motif du refus est OBLIGATOIRE. Veuillez argumenter votre décision.";
    } else if (strlen($motifRefus) < 10) {
        $error = "❌ Le motif doit contenir au moins 10 caractères.";
    } else {
        try {
            // ✅ ÉTAPE 1: Démarrer transaction
            $db->beginTransaction();
            
            // ✅ ÉTAPE 2: Mettre à jour le ticket
            //    - Changer statut à "Refusé" (ID 5)
            //    - Enregistrer la raison
            $stmt = $db->prepare("
                UPDATE tickets 
                SET statut_id = ?, 
                    motif_refus = ?,
                    date_fin_traitement = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$statutRefuseId, $motifRefus, $ticketId]);
            
            // ✅ ÉTAPE 3: Enregistrer dans l'historique
            $stmt = $db->prepare("
                INSERT INTO historique_actions 
                (ticket_id, utilisateur_id, action, details, date_action) 
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $ticketId,
                $_SESSION['user_id'],
                'rejet_rmq',
                'Réclamation refusée par RMQ. Motif: ' . substr($motifRefus, 0, 100) . '...'
            ]);
            
            // ✅ ÉTAPE 4: ENVOYER EMAIL AU CLIENT (AVANT validation transaction)
            $emailSent = false;
            $emailError = '';
            
            // ✅ ÉTAPE 4: ENVOYER EMAIL AU CLIENT (AVANT validation transaction)
            $emailSent = false;
            $emailError = '';
            
            if (!empty($ticket['email_client'])) {
                $emailManager = new EmailManager($db);
                $emailSent = $emailManager->envoyerEmailRefus($ticket, $motifRefus);
                
                if (!$emailSent) {
                    $emailError = "Erreur lors de l'envoi de l'email au client";
                }
            }
            
            // ✅ ÉTAPE 5: Valider la transaction
            $db->commit();
            
            // ✅ ÉTAPE 6: Afficher succès ou avertissement
            $success = true;
            if ($emailSent) {
                $successMessage = "✅ Réclamation refusée et email envoyé avec succès au client";
            } else {
                $successMessage = "✅ Réclamation refusée dans le système. " . 
                                 ($emailError ? "⚠️ " . $emailError : "⚠️ Email non envoyé");
            }
            
        } catch (Exception $e) {
            // Rollback en cas d'erreur
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $error = "❌ Erreur lors du traitement: " . $e->getMessage();
            error_log("[" . date('Y-m-d H:i:s') . "] ERROR REFUS: " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Refuser Réclamation - RMQ</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/fontawesome/css/all.min.css">
    <style>
        :root { --glass-bg: rgba(255, 255, 255, 0.95); --glass-border: 1px solid rgba(255, 255, 255, 0.2); }
        body { background: #f3f4f6; background-image: radial-gradient(at 0% 0%, hsla(0,16%,7%,1) 0, transparent 50%), radial-gradient(at 50% 0%, hsla(0,39%,30%,1) 0, transparent 50%), radial-gradient(at 100% 0%, hsla(0,49%,30%,1) 0, transparent 50%); background-attachment: fixed; }
        .main-container { max-width: 800px; margin: 2rem auto; padding: 0 1rem; }
        .glass-card { background: var(--glass-bg); backdrop-filter: blur(10px); border-radius: 16px; border: var(--glass-border); box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); padding: 2rem; }
        .btn-danger { background: #dc2626; color: white; padding: 0.75rem 1.5rem; border-radius: 0.5rem; border: none; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn-secondary { background: #9ca3af; color: white; padding: 0.75rem 1.5rem; border-radius: 0.5rem; border: none; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-block; margin-right: 1rem; }
    </style>
</head>
<body>
<!-- Navbar RMQ (Identique au Dashboard) -->
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
            <a href="dashboard.php" class="navbar-link">
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
    <div style="padding: 1.5rem; text-align: center; border-bottom: 2px solid var(--gray-200); margin-bottom: 1rem;">
        <strong style="display: block; font-size: 1.1rem; color: var(--primary-color); margin-bottom: 0.25rem;">
            <i class="fas fa-tasks"></i> RMQ
        </strong>
        <p style="margin: 0; font-size: 0.9rem; color: var(--gray-600);">Gestionnaire RMQ</p>
    </div>
    <a href="../../index.php" class="mobile-menu-link"><i class="fas fa-home"></i> <span>Accueil</span></a>
    <a href="dashboard.php" class="mobile-menu-link"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a>
    <a href="statistiques.php" class="mobile-menu-link"><i class="fas fa-chart-bar"></i> <span>Statistiques</span></a>
    <a href="create_claim.php" class="mobile-menu-link"><i class="fas fa-plus-circle"></i> <span>Nouvelle Réclamation</span></a>
    <a href="../../pages/logout.php" class="mobile-menu-link"><i class="fas fa-sign-out-alt"></i> <span>Déconnexion</span></a>
</div>

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

    <div class="main-container">
        <?php if ($success): ?>
            <!-- ✅ PAGE DE SUCCÈS -->
            <div class="glass-card" style="text-align: center;">
                <div style="font-size: 4rem; color: #10b981; margin-bottom: 1rem;">
                    <i class="fas fa-check-circle"></i>
                </div>
                <h2 style="color: #059669; margin-bottom: 1rem;">Refus Enregistré</h2>
                <div style="background: #ecfdf5; border-left: 4px solid #10b981; padding: 1rem; border-radius: 0.5rem; margin-bottom: 2rem; text-align: left;">
                    <p style="color: #065f46; margin: 0;">
                        <i class="fas fa-check"></i> <strong><?php echo htmlspecialchars($successMessage); ?></strong>
                    </p>
                </div>
                
                <div style="background: #f0f9ff; padding: 1rem; border-radius: 0.5rem; margin-bottom: 2rem; text-align: left;">
                    <p style="margin: 0.5rem 0; color: #0c4a6e;">
                        <strong>Réclamation:</strong> <?php echo htmlspecialchars($ticket['numero_ticket']); ?><br>
                        <strong>Client:</strong> <?php echo htmlspecialchars($ticket['prenom_client'] . ' ' . $ticket['nom_client']); ?><br>
                        <strong>Email:</strong> <?php echo htmlspecialchars($ticket['email_client']); ?><br>
                        <strong>Motif enregistré:</strong> <?php echo htmlspecialchars(substr($motifRefus, 0, 80) . (strlen($motifRefus) > 80 ? '...' : '')); ?>
                    </p>
                </div>
                
                <div style="margin-top: 2rem; display: flex; gap: 1rem; justify-content: center;">
                    <a href="dashboard.php" class="btn-secondary" style="text-decoration: none; display: inline-block;">
                        <i class="fas fa-arrow-left"></i> Retour Dashboard
                    </a>
                    <a href="../../index.php" class="btn-secondary" style="text-decoration: none; display: inline-block; background: #6b7280;">
                        <i class="fas fa-home"></i> Accueil
                    </a>
                </div>
            </div>
        <?php else: ?>
            <!-- ❌ FORMULAIRE DE REFUS -->
            <div class="glass-card">
                <h1 style="color: #dc2626; display: flex; align-items: center; gap: 10px; margin-bottom: 0.5rem;">
                    <i class="fas fa-times-circle"></i> Refuser la Réclamation
                </h1>
                
                <div style="background: #fff7ed; border-left: 4px solid #f97316; padding: 1rem; border-radius: 0.5rem; margin-bottom: 2rem;">
                    <p style="margin: 0; color: #92400e;">
                        <i class="fas fa-exclamation-triangle"></i> 
                        <strong>ATTENTION:</strong> Cette action est <strong>DÉFINITIVE</strong> et <strong>IRRÉVERSIBLE</strong>. 
                        Un email de notification sera envoyé automatiquement au client.
                    </p>
                </div>
                
                <div style="background: #f3f4f6; padding: 1rem; border-radius: 0.5rem; margin-bottom: 2rem;">
                    <p style="margin: 0.5rem 0; font-size: 0.95rem;">
                        <strong>Réclamation:</strong> <?php echo htmlspecialchars($ticket['numero_ticket']); ?><br>
                        <strong>Client:</strong> <?php echo htmlspecialchars($ticket['prenom_client'] . ' ' . $ticket['nom_client']); ?><br>
                        <strong>Sujet:</strong> <?php echo htmlspecialchars($ticket['sujet']); ?><br>
                        <strong>Status actuel:</strong> <span style="color: #dc2626; font-weight: 600;"><?php echo htmlspecialchars($ticket['statut_nom'] ?? 'N/A'); ?></span>
                    </p>
                </div>

                <?php if ($error): ?>
                    <div style="background: #fee2e2; color: #991b1b; padding: 1rem; border-radius: 0.5rem; margin-bottom: 1.5rem; border-left: 4px solid #dc2626;">
                        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" onsubmit="return confirm('⚠️ ÊTES-VOUS CERTAIN? Cette action est DÉFINITIVE et un email sera envoyé au client.');">
                    
                    <div style="margin-bottom: 2rem;">
                        <label style="display: block; font-weight: 600; margin-bottom: 0.5rem; color: #111827;">
                            <span style="color: #dc2626;">*</span> Motif du Refus (OBLIGATOIRE - Argumentation Requise)
                        </label>
                        <p style="margin: 0.5rem 0 1rem 0; font-size: 0.9rem; color: #6b7280;">
                            <i class="fas fa-info-circle"></i> Ce motif sera inclus dans l'email de notification envoyé au client. 
                            Soyez clair et précis dans votre argumentation (minimum 10 caractères).
                        </p>
                        <textarea 
                            name="motif_refus" 
                            rows="7" 
                            style="width: 100%; border: 2px solid #dc2626; border-radius: 0.5rem; padding: 1rem; font-family: inherit; font-size: 1rem;" 
                            required 
                            placeholder="Exemple: La réclamation n'est pas conforme aux conditions générales car...&#10;&#10;Soyez précis et détaillé pour que le client comprenne votre décision."
                            minlength="10"
                        ><?php echo isset($_POST['motif_refus']) ? htmlspecialchars($_POST['motif_refus']) : ''; ?></textarea>
                        <div style="margin-top: 0.5rem; font-size: 0.85rem; color: #666;">
                            <span id="charCount">0</span>/500 caractères recommandés
                        </div>
                    </div>

                    <div style="background: #fee2e2; border-left: 4px solid #dc2626; padding: 1rem; border-radius: 0.5rem; margin-bottom: 2rem;">
                        <label style="display: flex; align-items: flex-start; gap: 10px; cursor: pointer; color: #991b1b;">
                            <input type="checkbox" id="confirmCheckbox" required style="margin-top: 5px; width: 18px; height: 18px; cursor: pointer;">
                            <span>
                                <strong>Je confirme</strong> que je refuse définitivement cette réclamation et que le client recevra automatiquement 
                                un email contenant le motif ci-dessus.
                            </span>
                        </label>
                    </div>

                    <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                        <a href="detail_ticket.php?id=<?php echo intval($ticketId); ?>" class="btn-secondary" style="text-decoration: none; display: inline-block;">
                            <i class="fas fa-arrow-left"></i> Annuler
                        </a>
                        <button type="submit" class="btn-danger" style="cursor: pointer;">
                            <i class="fas fa-times-circle"></i> CONFIRMER LE REFUS
                        </button>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Compteur de caractères
        const textarea = document.querySelector('textarea[name="motif_refus"]');
        const charCount = document.getElementById('charCount');
        if (textarea) {
            textarea.addEventListener('input', function() {
                charCount.textContent = this.value.length;
            });
        }
    </script>
</body>
</html>
