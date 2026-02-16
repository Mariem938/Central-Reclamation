<?php
/**
 * Modification de Réclamation par RMQ - Version Premium
 * Fichier: pages/rmq/edit_claim.php
 */

require_once '../../config/database.php';
require_once '../../api/functions.php';
require_once '../../api/functions_extended.php';

// Vérifier que l'utilisateur est connecté et est RMQ ou ADMIN
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['rmq', 'admin'])) {
    header('Location: ../agent/login.php?role=rmq');
    exit;
}

$db = getDB();
$ticketId = $_GET['id'] ?? 0;
$error = '';
$success = false;

// Récupérer le ticket
$stmt = $db->prepare("SELECT t.*, u.nom_societe as client_nom_societe FROM tickets t LEFT JOIN utilisateurs u ON t.client_id = u.id WHERE t.id = ?");
$stmt->execute([$ticketId]);
$ticket = $stmt->fetch();

if (!$ticket) {
    header('Location: dashboard.php');
    exit;
}

// Traitement de la soumission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'update';
    
    if ($action === 'update') {
        // Validation basique
        $description = cleanInput($_POST['description'] ?? '');
        $demande_client = cleanInput($_POST['demande_client'] ?? '');
        
        // Validation et récupération
        $nom = cleanInput($_POST['nom'] ?? '');
        $prenom = cleanInput($_POST['prenom'] ?? '');
        $email = cleanInput($_POST['email'] ?? '');
        $telephone = cleanInput($_POST['telephone'] ?? '');
        $code_client = cleanInput($_POST['code_client'] ?? '');
        
        if (empty($description) || empty($demande_client) || empty($nom) || empty($prenom) || empty($email) || empty($code_client)) {
            $error = 'Veuillez remplir tous les champs obligatoires (*).';
        } elseif (!preg_match('/^(CL|CI)-\d{6}$/', $code_client)) {
            $error = 'Le format du code client est incorrect (Attendu: CL-000000 ou CI-000000).';
        } else {
            // Logic alignée
            $nom_societe = cleanInput($_POST['nom_societe'] ?? '');
            $matricule = cleanInput($_POST['matricule'] ?? '');
            
            $site_scpc = cleanInput($_POST['site_scpc'] ?? '');
            $site_autre = cleanInput($_POST['site_autre'] ?? '');
            $final_site = ($site_scpc === 'autre') ? $site_autre : $site_scpc;

            $cats = $_POST['categorie'] ?? [];
            $type_ticket_str = is_array($cats) ? implode(',', $cats) : $cats;

            $type_nc_raw = $_POST['type_nc'] ?? [];
            $type_nc_autre = cleanInput($_POST['type_nc_autre'] ?? '');
            if (in_array('Autre', $type_nc_raw) && !empty($type_nc_autre)) {
                $type_nc_raw = array_map(function($v) use ($type_nc_autre) {
                    return ($v === 'Autre') ? 'Autre: ' . $type_nc_autre : $v;
                }, $type_nc_raw);
            }
            $type_nc_str = implode(',', $type_nc_raw);

            $final_transport = cleanInput($_POST['societe_transport'] ?? '');

            $date_facture = !empty($_POST['date_facture']) ? $_POST['date_facture'] : null;
            $date_livraison = !empty($_POST['date_livraison']) ? $_POST['date_livraison'] : null;
            
            // GESTION PROD MULTIPLES
            $produit_concernes = $_POST['produit_concerne'] ?? [];
            $qtes_nc = $_POST['qte_nc'] ?? [];
            $unites_nc = $_POST['unite_nc'] ?? [];
            
            if (is_array($produit_concernes)) {
                $produit = implode(' | ', array_map('cleanInput', $produit_concernes));
                $qte = implode(' | ', array_map('cleanInput', $qtes_nc));
                $unite_nc = implode(' | ', array_map('cleanInput', $unites_nc));
            } else {
                $produit = cleanInput($_POST['produit_concerne'] ?? '');
                $qte = cleanInput($_POST['qte_nc'] ?? '');
                $unite_nc = cleanInput($_POST['unite_nc'] ?? '');
            }

            $chauffeur = cleanInput($_POST['chauffeur'] ?? '');
            $num_fact = cleanInput($_POST['num_facture'] ?? '');
            $causes = cleanInput($_POST['analyse_causes'] ?? '');
            $conseq = cleanInput($_POST['analyse_consequences'] ?? '');
            $notes = cleanInput($_POST['notes'] ?? '');

            try {
                $sql = "UPDATE tickets SET 
                        nom_client=?, prenom_client=?, email_client=?, telephone_client=?,
                        nom_societe=?, code_client=?,
                        site_scpc=?, type_ticket=?, type_nc=?,
                        produit_concerne=?, qte_nc=?, unite_nc=?,
                        societe_transport=?, chauffeur=?, matricule=?,
                        num_facture=?, date_facture=?, date_livraison=?,
                        description=?, demande_client=?, 
                        analyse_causes=?, analyse_consequences=?, notes=?
                        WHERE id=?";
                
                $stmt = $db->prepare($sql);
                $stmt->execute([
                    $nom, $prenom, $email, $telephone,
                    $nom_societe, $code_client,
                    $final_site, $type_ticket_str, $type_nc_str,
                    $produit, $qte, $unite_nc,
                    $final_transport, $chauffeur, $matricule,
                    $num_fact, $date_facture, $date_livraison,
                    $description, $demande_client,
                    $causes, $conseq, $notes,
                    $ticketId
                ]);

                // Historique
                $db->prepare("INSERT INTO historique_actions (ticket_id, action, details, utilisateur_id, date_action) VALUES (?, 'modification', 'Modification données par RMQ', ?, NOW())")->execute([$ticketId, $_SESSION['user_id']]);

                // Gestion Notification
                if (isset($_POST['notify_client']) && $_POST['notify_client'] == '1' && !empty($email)) {
                    require_once '../../api/EmailManager.php';
                    $emailManager = new EmailManager($db);
                    // On utilise le template stylé pour la notification
                    $emailManager->envoyerChangementStatut($ticket, $email, "Détails mis à jour");
                    $success = "Données mises à jour et client notifié (Email Stylé) !";
                } else {
                    $success = "Données mises à jour !";
                }

                // Rafraîchir les données
                $stmt = $db->prepare("SELECT t.*, u.nom_societe as client_nom_societe FROM tickets t LEFT JOIN utilisateurs u ON t.client_id = u.id WHERE t.id = ?");
                $stmt->execute([$ticketId]);
                $ticket = $stmt->fetch();

            } catch (Exception $e) {
                $error = "Erreur SQL: " . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier Réclamation - RMQ</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/reclamation-form.css">
    <link rel="stylesheet" href="../../assets/fontawesome/css/all.min.css">
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
        <div class="mobile-menu-header">
            <i class="fas fa-tasks"></i>
            <strong>RMQ</strong>
            <p class="mobile-menu-subtitle">Espace RMQ</p>
        </div>
        <a href="dashboard.php" class="mobile-menu-link"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a>
        <a href="statistiques.php" class="mobile-menu-link"><i class="fas fa-chart-bar"></i> <span>Statistiques</span></a>
        <a href="create_claim.php" class="mobile-menu-link"><i class="fas fa-plus-circle"></i> <span>Nouvelle Réclamation</span></a>
        
        <a href="../../pages/logout.php" class="mobile-menu-link" style="color: #ef4444 !important;"><i class="fas fa-sign-out-alt"></i> <span>Déconnexion</span></a>
    </div>

    <div class="section" style="padding-top: 3rem; padding-bottom: 3rem; background: var(--gradient-hero);">
        <div class="container">
            <div style="margin-bottom: 2rem; border-left: 4px solid var(--primary); padding-left: 1rem;">
                <h2 style="margin: 0; color: var(--text-dark); font-size: 2rem;">
                    Modifier une Réclamation
                </h2>
                <p style="margin: 0.5rem 0 0 0; color: var(--text-gray);">Mettez à jour les informations de la réclamation.</p>
            </div>

            <div class="form-container" style="max-width: 950px; margin: 0 auto;">
                <?php if ($success): ?>
                <div class="alert alert-success mb-4">
                    <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                </div>
                <?php endif; ?>
                <?php if ($error): ?>
                <div class="alert alert-danger mb-4">
                    <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
                </div>
                <?php endif; ?>
                
                <?php 
                // On définit le contexte pour form_reclamation_inc.php
                $contexte = 'rmq_edit';
                include '../../pages/includes/form_reclamation_inc.php'; 
                ?>
            </div>
        </div>
    </div>

    <script src="../../assets/js/main.js"></script>
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
</body>
</html>
