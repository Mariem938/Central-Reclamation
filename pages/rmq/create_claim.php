<?php
/**
 * Page Création Réclamation - RMQ
 * Design Premium & Logique V3 (Aligné avec Commercial)
 */

require_once '../../config/database.php';
require_once '../../api/functions.php';
require_once '../../api/functions_extended.php';

// Vérification Rôle
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'rmq') {
    header('Location: ../agent/login.php?role=rmq');
    exit;
}

$db = getDB();
$rmqId = $_SESSION['user_id'];
$error = '';
$success = false;
$resultData = [];

// ID Statut "En cours de validation" (Workflow RMQ)
$statutDefaultId = 6; 

// Données préalables
$clientInfo = null;
if (isset($_GET['client_id'])) {
    $stmt = $db->prepare("SELECT * FROM utilisateurs WHERE id = ? AND role = 'client'");
    $stmt->execute([$_GET['client_id']]);
    $clientInfo = $stmt->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Client
    $clientId = intval($_POST['client_id'] ?? 0);
    $nom = cleanInput($_POST['nom'] ?? '');
    $prenom = cleanInput($_POST['prenom'] ?? '');
    $email = cleanInput($_POST['email'] ?? '');
    $telephone = cleanInput($_POST['telephone'] ?? '');
    $nomSociete = cleanInput($_POST['nom_societe'] ?? '');
    $codeClientSaisi = cleanInput($_POST['code_client'] ?? '');
    
    // Matricule
    $matricule = cleanInput($_POST['matricule'] ?? '');

    // 2. Détails Réclamation
    $site_scpc = cleanInput($_POST['site_scpc'] ?? '');
    $site_autre = cleanInput($_POST['site_autre'] ?? '');
    $final_site = ($site_scpc === 'autre') ? $site_autre : $site_scpc;

    // ✅ Récupération des catégories cochées (Produit/Service)
    $categories_cochees = $_POST['categorie'] ?? [];
    if (!is_array($categories_cochees)) $categories_cochees = [$categories_cochees];
    $categories_cochees = array_map('strtolower', $categories_cochees);
    $categorie_value = implode(',', $categories_cochees);
    
    // Déterminer l'ID catégorie (3: Produit, 10: Service, 7: Réclamation Client)
    $categorie_id = 7; // Default to Réclamation Client
    if (in_array('produit', $categories_cochees)) {
        $categorie_id = 3; // Produit
    } elseif (in_array('service', $categories_cochees)) {
        $categorie_id = 10; // Service
    }

    $description = cleanInput($_POST['description'] ?? '');
    $demande_client = cleanInput($_POST['demande_client'] ?? '');
    
    // Sujet auto
    $sujet = trim($description);
    if (strlen($sujet) > 50) $sujet = substr($sujet, 0, 47) . '...';

    // 3. Spécifiques
    
    // GESTION PRODUITS MULTIPLES (Arrays)
    $produit_concernes = $_POST['produit_concerne'] ?? []; // Array
    $qtes_nc = $_POST['qte_nc'] ?? []; // Array
    $unites_nc = $_POST['unite_nc'] ?? []; // Array
    
    if (is_array($produit_concernes)) {
        $produit_concerne = implode(' | ', array_map('cleanInput', $produit_concernes));
        $qte_nc = implode(' | ', array_map('cleanInput', $qtes_nc));
        $unite_nc = implode(' | ', array_map('cleanInput', $unites_nc));
    } else {
        $produit_concerne = cleanInput($_POST['produit_concerne'] ?? '');
        $qte_nc = cleanInput($_POST['qte_nc'] ?? '');
        $unite_nc = cleanInput($_POST['unite_nc'] ?? '');
    }

    // Type NC avec logic "Autre"
    $type_nc_raw = $_POST['type_nc'] ?? [];
    $type_nc_autre = cleanInput($_POST['type_nc_autre'] ?? '');
    if (in_array('Autre', $type_nc_raw) && !empty($type_nc_autre)) {
        $type_nc_raw = array_map(function($v) use ($type_nc_autre) {
            return ($v === 'Autre') ? 'Autre: ' . $type_nc_autre : $v;
        }, $type_nc_raw);
    }
    $type_nc_str = implode(',', $type_nc_raw);
    
    $final_transport = cleanInput($_POST['societe_transport'] ?? '');
    
    $chauffeur = cleanInput($_POST['chauffeur'] ?? '');
    
    $num_facture = cleanInput($_POST['num_facture'] ?? '');
    $date_facture = !empty($_POST['date_facture']) ? $_POST['date_facture'] : null;
    $date_livraison = !empty($_POST['date_livraison']) ? $_POST['date_livraison'] : null;

    $notes = cleanInput($_POST['notes'] ?? '');
    $analyse_causes = cleanInput($_POST['analyse_causes'] ?? '');
    $analyse_consequences = cleanInput($_POST['analyse_consequences'] ?? '');

    // Validation
    if (empty($nom) || empty($prenom) || empty($email) || empty($final_site) || empty($categorie_value) || empty($description) || empty($demande_client) || empty($telephone) || empty($codeClientSaisi) || empty($nomSociete)) {
        $error = "Veuillez remplir tous les champs obligatoires (*).";
    } elseif (in_array('service', $categories_cochees) && empty($matricule)) {
        $error = 'Le matricule est obligatoire pour une réclamation Service.';
    } elseif (in_array('produit', $categories_cochees) && empty($produit_concerne)) {
            $error = 'Veuillez saisir au moins un produit.';
    } elseif (!preg_match('/^(CL|CI)-\d{6}$/', $codeClientSaisi)) {
        $error = "Le format du code client est incorrect (Attendu: CL-000000 ou CI-000000).";
    } elseif ($date_facture && $date_livraison && strtotime($date_facture) > strtotime($date_livraison)) {
        $error = "La date de facture ne peut pas être postérieure à la date de livraison.";
    } else {
        try {
            $db->beginTransaction();

            // Gestion Client (Existant ou Nouveau)
            if ($clientId === 0) {
                // 1. Validation par code client (Priorité)
                require_once '../../api/functions.php';
                $clientParCode = validerClientParCode($codeClientSaisi, $nom, $prenom);
                
                // 2. Check email if not found by code
                $client = $clientParCode ?: utilisateurExiste($email);

                if ($client) {
                    $clientId = $client['id'];
                    // Si on a trouvé le client par email mais qu'il a un code_client différent de celui saisi
                    if (!empty($codeClientSaisi) && !empty($client['code_client']) && $client['code_client'] !== $codeClientSaisi) {
                         throw new Exception("L'email **$email** est déjà associé au code client **" . $client['code_client'] . "**. Vous ne pouvez pas utiliser un autre code client (**$codeClientSaisi**).");
                    }
                    // Si le client n'avait pas de code client en base, on lui assigne
                    if (empty($client['code_client']) && !empty($codeClientSaisi)) {
                        $stmtUpd = $db->prepare("UPDATE utilisateurs SET code_client = ? WHERE id = ?");
                        $stmtUpd->execute([$codeClientSaisi, $clientId]);
                    }
                } else {
                    $pass = substr(md5(uniqid()), 0, 8);
                    $hash = password_hash($pass, PASSWORD_DEFAULT);
                    $stmtIns = $db->prepare("INSERT INTO utilisateurs (nom, prenom, email, telephone, code_client, nom_societe, mot_de_passe, role, actif, date_creation) VALUES (?, ?, ?, ?, ?, ?, ?, 'client', 1, NOW())");
                    $stmtIns->execute([$nom, $prenom, $email, $telephone, $codeClientSaisi, $nomSociete, $hash]);
                    $clientId = $db->lastInsertId();
                    
                    // Logger les credentials pour l'email plus tard
                    $motDePasseTemp = $pass;
                }
            }

            // Création Ticket
            $numeroTicket = genererNumeroTicket();
            $codeFinal = !empty($codeClientSaisi) ? $codeClientSaisi : ('C' . str_pad($clientId, 5, '0', STR_PAD_LEFT));

            $sql = "INSERT INTO tickets (
                numero_ticket, code_client, client_id, nom_client, prenom_client, email_client, telephone_client,
                sujet, description, categorie_id,
                site_scpc, site_autre, type_ticket, type_nc,
                produit_concerne, qte_nc, unite_nc,
                societe_transport, chauffeur, matricule,
                num_facture, date_facture, date_livraison,
                demande_client, analyse_causes, analyse_consequences, notes,
                statut_id, created_by_role, nom_societe, date_creation, est_valide_par_rmq
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?,
                ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, ?,
                ?, ?, ?,
                ?, ?, ?,
                ?, ?, ?, ?,
                ?, 'rmq', ?, NOW(), 1
            )";

            $stmt = $db->prepare($sql);
            $stmt->execute([
                $numeroTicket, $codeFinal, $clientId, $nom, $prenom, $email, $telephone,
                $sujet, $description, $categorie_id,
                $site_scpc, ($site_scpc === 'autre' ? $site_autre : null), $categorie_value, $type_nc_str,
                $produit_concerne, $qte_nc, $unite_nc,
                $final_transport, $chauffeur, $matricule,
                $num_facture, $date_facture, $date_livraison,
                $demande_client, $analyse_causes, $analyse_consequences, $notes,
                $statutDefaultId, $nomSociete
            ]);

            $ticketId = $db->lastInsertId();
            
            // Upload Fichiers
            if (!empty($_FILES['fichiers']['name'][0])) {
                foreach ($_FILES['fichiers']['tmp_name'] as $k => $tmp) {
                    if ($_FILES['fichiers']['error'][$k] === 0) {
                        $fileData = [
                            'name' => $_FILES['fichiers']['name'][$k],
                            'type' => $_FILES['fichiers']['type'][$k],
                            'tmp_name' => $tmp,
                            'error' => 0,
                            'size' => $_FILES['fichiers']['size'][$k]
                        ];
                        uploadFichier($fileData, $ticketId);
                    }
                }
            }

            $db->commit();
            $success = true;
            $resultData = ['ticket_id' => $ticketId, 'numero' => $numeroTicket];

            // --- ENVOI EMAIL NOTIFICATION (AJOUTÉ) ---
            try {
                require_once '../../api/EmailManager.php';
                $emailManager = new EmailManager($db);
                
                // Données pour l'email
                $ticketMailData = [
                    'id' => $ticketId,
                    'numero_ticket' => $numeroTicket,
                    'sujet' => $sujet,
                    'categorie_nom' => 'Réclamation (RMQ)'
                ];
                $clientMailData = [
                    'nom_complet' => $prenom . ' ' . $nom,
                    'email' => $email
                ];

                // 1. Confirmer au client
                $emailManager->envoyerConfirmationClientAuth($ticketMailData, $clientMailData);
                
                // 2. Notifier les agents
                $emailManager->notifierAgents($ticketMailData, $clientMailData);
                
            } catch (Exception $eMail) {
                // On n'interrompt pas le succès si l'email échoue
            }

        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            $error = "Erreur: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nouvelle Réclamation - RMQ</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/reclamation-form.css">
    <link rel="stylesheet" href="../../assets/fontawesome/css/all.min.css">
</head>
<body>
    <nav class="navbar" style="background: white; border-bottom: 2px solid #f0f0f0; box-shadow: 0 2px 8px rgba(0,0,0,0.05);">
        <div class="container" style="display: flex; align-items: center; justify-content: space-between; padding: 0 1rem;">
            <a class="navbar-brand" href="../../index.php" style="display: flex; align-items: center; gap: 10px; text-decoration: none;">
                <img src="../../assets/images/logo.png" alt="Logo SCPC" style="height: 40px; width: auto;">
                <div style="display: flex; flex-direction: column; line-height: 1.2;">
                    <span style="font-weight: 700; font-size: 1.1rem; color: #1A1A1A;">SCPC Comptoir Hammami</span>
                    <span style="font-size: 0.75rem; color: #666; font-weight: 500;">Nouvelle Réclamation</span>
                </div>
                <span style="background: linear-gradient(135deg, #FF6B35 0%, #FFA500 100%); color: white; padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.7rem; font-weight: 600; margin-left: auto;">
                    Nouvelle Réclamation
                </span>
            </a>

            <div class="navbar-menu" id="mainMenu" style="display: flex; gap: 0.5rem; list-style: none; margin: 0; padding: 0;">
                <a href="dashboard.php" style="display: flex; align-items: center; gap: 0.5rem; padding: 0.75rem 1rem; text-decoration: none; color: #4b5563; font-weight: 500; border-radius: 8px; transition: all 0.2s;">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
                <a href="statistiques.php" style="display: flex; align-items: center; gap: 0.5rem; padding: 0.75rem 1rem; text-decoration: none; color: #4b5563; font-weight: 500; border-radius: 8px; transition: all 0.2s;">
                    <i class="fas fa-chart-bar"></i> Statistiques
                </a>
                <a href="../../pages/logout.php" style="display: flex; align-items: center; gap: 0.5rem; padding: 0.75rem 1rem; text-decoration: none; color: #dc2626; font-weight: 500; border-radius: 8px; transition: all 0.2s;">
                    <i class="fas fa-sign-out-alt"></i> Déconnexion
                </a>
            </div>

            <button id="navbarToggler" class="navbar-toggler" style="display: none; background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #333;">
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
        <a href="create_claim.php" class="mobile-menu-link active"><i class="fas fa-plus-circle"></i> <span>Nouvelle Réclamation</span></a>
        
        <a href="../../pages/logout.php" class="mobile-menu-link" style="color: #ef4444 !important;"><i class="fas fa-sign-out-alt"></i> <span>Déconnexion</span></a>
    </div>

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

    <div class="section" style="padding-top: 3rem; padding-bottom: 3rem; background: var(--gradient-hero);">
        <div class="container">
            <div style="margin-bottom: 2rem; border-left: 4px solid var(--primary); padding-left: 1rem;">
                <h2 style="margin: 0; color: var(--text-dark); font-size: 2rem; text-shadow: none;">
                    Nouvelle Réclamation
                </h2>
                <p style="margin: 0.5rem 0 0 0; color: var(--text-gray);">Enregistrez une nouvelle réclamation cliente.</p>
            </div>

            <div class="form-container" style="max-width: 950px; margin: 0 auto;">
                <?php if ($success): ?>
                <div class="card glass-card fade-in" style="max-width: 700px; margin: 0 auto;">
                    <div class="card-body p-5" style="text-align: center;">
                        <div style="width: 100px; height: 100px; margin: 0 auto 2rem; display: flex; align-items: center; justify-content: center; border-radius: var(--radius-lg); background: var(--gradient-success); color: white; font-size: 2.5rem;">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <h2 style="margin-bottom: 1rem; color: var(--text-dark);">Réclamation enregistrée ! 🎉</h2>
                        
                        <?php if (!empty($error)): ?>
                            <div class="alert alert-warning mb-4" style="text-align: left; font-size: 0.9rem;">
                                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                            </div>
                        <?php endif; ?>

                        <p style="color: var(--text-gray); margin-bottom: 2rem;">La réclamation a été créée avec le statut <strong>En cours de vérification</strong>.</p>
                        
                        <div style="background: var(--gradient-primary); padding: 2rem; border-radius: var(--radius-xl); margin-bottom: 2rem; color: white;">
                            <p style="color: rgba(255,255,255,0.85); margin-bottom: 0.5rem; font-size: 0.875rem; font-weight: 600;">Numéro de Ticket</p>
                            <h1 style="color: white; font-size: 2.5rem; margin: 0;"><?php echo $resultData['numero']; ?></h1>
                        </div>
                        
                        <div style="margin-top: 2rem;">
                            <a href="dashboard.php" class="btn btn-secondary">Retour au Dashboard</a>
                            <a href="create_claim.php" class="btn btn-primary">Créer une autre</a>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                
                <div style="max-width: 1000px; margin: 0 auto;">
                    <?php if ($error): ?>
                    <div class="alert alert-danger mb-4">
                        <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php 
                    $contexte = 'rmq';
                    include '../../pages/includes/form_reclamation_inc.php'; 
                    ?>
                </div>
                
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="../../assets/js/main.js"></script>
    <script src="../../assets/js/main.js"></script>
    <!-- Les scripts sont gérés dans form_reclamation_inc.php -->
</body>
</html>
