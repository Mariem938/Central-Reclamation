<?php
/**
 * Page Création Réclamation - Commercial
 * Design Premium & Logique V3 (Aligné avec Modèle Client)
 */

require_once '../../config/database.php';
require_once '../../api/functions.php';
require_once '../../api/functions_extended.php';

// Vérification Rôle
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'commercial') {
    header('Location: ../agent/login.php?role=commercial');
    exit;
}

$db = getDB();
$commercialId = $_SESSION['user_id'];
$error = '';
$success = false;
$resultData = [];
$categories = getCategories();
$sites = getSites();

// Récupération ID Statut "En attente validation RMQ"
$statutValidationId = 6;

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
    $codeClientSaisi = cleanInput($_POST['code_client'] ?? '');

    // 2. Détails Réclamation
    $site_scpc = cleanInput($_POST['site_scpc'] ?? '');
    $site_autre = cleanInput($_POST['site_autre'] ?? '');
    
    // Mapping des sites pour la BD (garder cohérence avec anciennes versions)
    $siteMapping = [
        'sfax' => 'Sfax-Sfax',
        'tunis_mnihla' => 'Tunis-Mnihla',
        'sousse' => 'Sousse-Sousse',
        'tunis_mhamdia' => 'Tunis-Mhamdia',
        'zarzis' => 'Zarzis-Zarzis',
        'site_client' => 'Site client'
    ];
    $final_site = ($site_scpc === 'autre') ? $site_autre : ($siteMapping[$site_scpc] ?? $site_scpc);

    // ✅ Récupération des catégories (Produit/Service) - Aligné avec l'include 'categorie[]'
    $types = $_POST['categorie'] ?? [];
    if (!is_array($types)) $types = [$types];
    $type_ticket_str = implode(',', array_map('strtolower', $types));

    $description = cleanInput($_POST['description'] ?? '');
    $demande_client = cleanInput($_POST['demande_client'] ?? '');
    
    // Sujet auto
    $sujet = (strlen($description) > 50) ? substr($description, 0, 47) . '...' : $description;

    // 3. Spécifiques
    
    // GESTION PRODUITS MULTIPLES (Arrays)
    $produit_concernes = $_POST['produit_concerne'] ?? []; // Array
    $qtes_nc = $_POST['qte_nc'] ?? []; // Array
    $unites_nc = $_POST['unite_nc'] ?? []; // Array
    
    // Nettoyage et conversion en string pour le stockage
    if (is_array($produit_concernes)) {
        $produit_concerne = implode(' | ', array_map('cleanInput', $produit_concernes));
        $qte_nc = implode(' | ', array_map('cleanInput', $qtes_nc));
        $unite_nc = implode(' | ', array_map('cleanInput', $unites_nc));

        $categories_cochees = array_map('strtolower', $types); // Using $types as it holds the selected categories
        $categorie_value = implode(',', $categories_cochees);
        
        // Déterminer l'ID catégorie (3: Produit, 10: Service, 7: Réclamation Client)
        $categorie_id = 7; // Default to Réclamation Client
        if (in_array('produit', $categories_cochees)) {
            $categorie_id = 3;
        } elseif (in_array('service', $categories_cochees)) {
            $categorie_id = 10;
        }
    } else {
        $produit_concerne = cleanInput($_POST['produit_concerne'] ?? '');
        $qte_nc = cleanInput($_POST['qte_nc'] ?? '');
        $unite_nc = cleanInput($_POST['unite_nc'] ?? '');

        // If not an array, assume single selection or default
        $categories_cochees = array_map('strtolower', $types);
        $categorie_id = 7; // Default
        if (in_array('produit', $categories_cochees)) {
            $categorie_id = 3;
        } elseif (in_array('service', $categories_cochees)) {
            $categorie_id = 10;
        }
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
    if (empty($nom) || empty($prenom) || empty($email) || empty($final_site) || empty($type_ticket_str) || empty($description) || empty($demande_client) || empty($telephone) || empty($codeClientSaisi) || empty($nomSociete)) {
        $error = "Veuillez remplir tous les champs obligatoires (*).";
    } elseif (in_array('service', $types) && empty($matricule)) {
        $error = 'Le matricule est obligatoire pour une réclamation Service.';
    } elseif (in_array('produit', $types) && empty($produit_concerne)) {
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
                    // Création
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
                statut_id, commercial_id, created_by_role, nom_societe, date_creation
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?,
                ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, ?,
                ?, ?, ?,
                ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, 'commercial', ?, NOW()
            )";

            $stmt = $db->prepare($sql);
            $stmt->execute([
                $numeroTicket, $codeFinal, $clientId, $nom, $prenom, $email, $telephone,
                $sujet, $description, $categorie_id, 
                $final_site, ($site_scpc === 'autre' ? $site_autre : null), $type_ticket_str, $type_nc_str,
                $produit_concerne, $qte_nc, $unite_nc,
                $final_transport, $chauffeur, $matricule,
                $num_facture, $date_facture, $date_livraison,
                $demande_client, $analyse_causes, $analyse_consequences, $notes,
                $statutValidationId, $commercialId, $nomSociete
            ]);

            $ticketId = $db->lastInsertId();
              // Upload Fichiers
              if (!empty($_FILES['fichiers']['name'][0])) {
                foreach ($_FILES['fichiers']['tmp_name'] as $k => $tmp) {
                    if ($_FILES['fichiers']['error'][$k] === 0) {
                        $fileData = [
                            'name'     => $_FILES['fichiers']['name'][$k],
                            'type'     => $_FILES['fichiers']['type'][$k],
                            'tmp_name' => $tmp,
                            'error'    => 0,
                            'size'     => $_FILES['fichiers']['size'][$k]
                        ];
                        uploadFichier($fileData, $ticketId);
                    }
                }
            }

            $db->commit();
            $success = true;
            $resultData = ['ticket_id' => $ticketId, 'numero' => $numeroTicket];

            // --- ENVOI EMAIL NOTIFICATION (Désactivé pour Commercial - Manuel après confirmation) ---
            /*
            try {
                require_once '../../api/EmailManager.php';
                
                // Récupérer les données complètes pour la notification intelligente
                $stmtFull = $db->prepare("SELECT * FROM tickets WHERE id = ?");
                $stmtFull->execute([$ticketId]);
                $ticketFull = $stmtFull->fetch(PDO::FETCH_ASSOC);
                
                $clientMailData = [
                    'nom_complet' => $prenom . ' ' . $nom,
                    'email' => $email
                ];

                $emailManager = new EmailManager($db);
                
                // 1. Confirmer au client (Sauter pour Commercial)
                // $emailManager->envoyerConfirmationClientAuth($ticketFull, $clientMailData);
                
                // 2. Notifier les agents (Intelligence: RMQ + AQF contextuel)
                $emailManager->notifierAgents($ticketFull, $clientMailData);
                
            } catch (Exception $eMail) {
                // Silence email errors
            }
            */
            
            // Toujours notifier les agents
            try {
                require_once '../../api/EmailManager.php';
                $stmtFull = $db->prepare("SELECT * FROM tickets WHERE id = ?");
                $stmtFull->execute([$ticketId]);
                $ticketFull = $stmtFull->fetch(PDO::FETCH_ASSOC);
                $clientMailData = ['nom_complet' => $prenom . ' ' . $nom, 'email' => $email];
                $emailManager = new EmailManager($db);
                $emailManager->notifierAgents($ticketFull, $clientMailData);
            } catch (Exception $eMail) {}

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
    <title>Nouvelle Réclamation - Commercial</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/reclamation-form.css">
    <link rel="stylesheet" href="../../assets/fontawesome/css/all.min.css">
</head>
<body>
    <nav class="navbar" style="background: white; padding: 1rem; border-bottom: 2px solid #f0f0f0;">
        <div class="container" style="display: flex; justify-content: space-between; align-items: center;">
            <a href="dashboard.php" class="navbar-brand" style="display: flex; align-items: center; gap: 10px; text-decoration: none;">
                <img src="../../assets/images/logo.png" style="height: 40px;">
                <div style="display: flex; flex-direction: column; line-height: 1.2;">
                    <span style="font-weight: 700; color: #1f2937;">SCPC Commercial</span>
                    <span style="font-size: 0.8rem; color: #6b7280; font-weight: normal;">Espace Agent</span>
                </div>
            </a>
            
            <div class="navbar-menu" id="mainMenu" style="display: flex; gap: 1.5rem;">
                <a href="dashboard.php" style="color: #374151; text-decoration: none;"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                <a href="../logout.php" style="color: #ef4444; text-decoration: none;"><i class="fas fa-sign-out-alt"></i> Déconnexion</a>
            </div>

            <button id="navbarToggler" class="navbar-toggler" style="display: none; background: none; border: none; font-size: 1.5rem; color: #374151; cursor: pointer;">
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
        <a href="dashboard.php" class="mobile-menu-link"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
        <a href="../logout.php" class="mobile-menu-link"><i class="fas fa-sign-out-alt"></i> Déconnexion</a>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const mobileMenu = document.getElementById('mobileMenu');
        const toggler = document.getElementById('navbarToggler');
        const togglerIcon = toggler ? toggler.querySelector('i') : null;

        const style = document.createElement('style');
        style.textContent = `
            @media (max-width: 768px) {
                #mainMenu { display: none !important; }
                .navbar-toggler { display: block !important; }
            }
        `;
        document.head.appendChild(style);

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
            <?php if ($success): ?>
                <div class="card glass-card fade-in" style="max-width: 700px; margin: 0 auto; text-align: center; padding: 4rem 2rem;">
                    <div style="width: 100px; height: 100px; margin: 0 auto 2rem; display: flex; align-items: center; justify-content: center; border-radius: var(--radius-lg); background: var(--gradient-success); color: white; font-size: 2.5rem;"><i class="fas fa-check-circle"></i></div>
                    <h2 style="margin-bottom: 1rem; color: var(--text-dark);">Réclamation enregistrée</h2>
                    <p style="color: var(--text-gray); margin-bottom: 2rem;">
                        La réclamation a été transmise pour validation RMQ.<br>
                        <strong>Aucun email n'a été envoyé au client pour le moment.</strong>
                    </p>
                    <div style="background: var(--gradient-primary); padding: 2rem; border-radius: var(--radius-xl); margin-bottom: 2rem; color: white;">
                        <p style="color: rgba(255,255,255,0.85); margin-bottom: 0.5rem; font-size: 0.875rem; font-weight: 600;">Numéro de Ticket</p>
                        <h1 style="color: white; font-size: 2.5rem; margin: 0;"><?php echo $resultData['numero']; ?></h1>
                    </div>
                    <div class="alert alert-warning" style="background: #fffbeb; border-left: 4px solid #f59e0b; color: #92400e; text-align: left; margin-bottom: 2rem;">
                        <i class="fas fa-info-circle"></i> 
                        <strong>Workflow Commercial :</strong> Aucun email n'a été envoyé au client automatiquement. L'envoi devra être confirmé manuellement par le RMQ après validation.
                    </div>
                        <a href="dashboard.php" class="btn btn-secondary">Retour au Dashboard</a>
                        <a href="create_claim.php" class="btn btn-primary" style="margin-left: 1rem;">Nouvelle Réclamation</a>
                    </div>
                </div>
            <?php else: ?>
                
                <div style="margin-bottom: 2rem; border-left: 4px solid var(--primary); padding-left: 1rem;">
                    <h2 style="margin: 0; color: var(--text-dark); font-size: 2rem; text-shadow: none;">
                        Nouvelle Réclamation
                    </h2>
                    <p style="margin: 0.5rem 0 0 0; color: var(--text-gray);">Remplissez le formulaire ci-dessous au nom du client.</p>
                </div>

                <div class="form-container" style="max-width: 950px; margin: 0 auto;">
                    <?php if ($error): ?>
                        <div class="alert alert-danger mb-4">
                            <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>

                    <?php 
                    $contexte = 'commercial';
                    include '../../pages/includes/form_reclamation_inc.php'; 
                    ?>

                    <script>
                        document.addEventListener('DOMContentLoaded', function() {
                            const submitBtn = document.querySelector('#reclamationForm button[type="submit"]');
                            if (submitBtn) {
                                submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Soumettre pour validation de RMQ';
                            }
                        });
                    </script>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="../../assets/js/main.js"></script>
</body>
</html>
