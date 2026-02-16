<?php
/**
 * Formulaire de Nouvelle Réclamation (Client / Public)
 * Fichier: pages/client/nouvelle_reclamation.php
 */

require_once '../../config/database.php';
require_once '../../api/functions.php';
require_once '../../api/functions_extended.php';

$error = '';
$success = false;
$numeroTicket = '';
$codeClient = '';
$categories = getCategories();
$sites = getSites();

// Si l'utilisateur est connecté en tant que client, on pré-remplit
$clientInfo = null;
if (isset($_SESSION['user_id']) && $_SESSION['user_role'] === 'client') {
    // Récupérer infos fraîches
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM utilisateurs WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $clientInfo = $stmt->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Récupération des données via le formulaire unifié
    $site_scpc = cleanInput($_POST['site_scpc'] ?? '');
    $site_autre = cleanInput($_POST['site_autre'] ?? '');
    
    // Si "Autre" est sélectionné, il doit y avoir une valeur
    if ($site_scpc === 'autre' && empty($site_autre)) {
        $error = 'Veuillez préciser le site.';
    } elseif (empty($site_scpc)) {
        $error = 'Veuillez sélectionner un site SCPC.';
    } else {
        // ✅ Récupération des catégories cochées par l'utilisateur
        $categories_cochees = $_POST['categorie'] ?? [];
        if (!is_array($categories_cochees)) {
            $categories_cochees = [$categories_cochees];
        }
        // Convertir en minuscules et nettoyer
        $categories_cochees = array_map('strtolower', $categories_cochees);
        $categorie_value = implode(',', $categories_cochees); // "produit", "service", ou "produit,service"
        
        // Mapping pour l'ID catégorie en base (3: Produit, 10: Service, 7: Réclamation Client)
        $categorie = 7;
        if (in_array('produit', $categories_cochees)) {
            $categorie = 3;
        } elseif (in_array('service', $categories_cochees)) {
            $categorie = 10;
        }
        
        // Mapping Type Ticket (ancien système, garde pour compatibilité)
        $types = $categories_cochees;
        $type_ticket = implode(',', array_map('ucfirst', $types));
        $type_ticket = cleanInput($type_ticket);
        
        // Client Info
        $nom = cleanInput($_POST['nom'] ?? '');
        $prenom = cleanInput($_POST['prenom'] ?? '');
        $email = cleanInput($_POST['email'] ?? '');
        $telephone = cleanInput($_POST['telephone'] ?? '');
        $codeClient = cleanInput($_POST['code_client'] ?? ''); // Code client saisi par l'utilisateur
        $codeClient = cleanInput($_POST['code_client'] ?? ''); // Code client saisi par l'utilisateur
        $nomSociete = cleanInput($_POST['nom_societe'] ?? ''); // Nouveau champ
        
        // Matricule
        $matricule = cleanInput($_POST['matricule'] ?? '');
        
        // Détails
        $description = cleanInput($_POST['description'] ?? '');
        $demande_client = cleanInput($_POST['demande_client'] ?? '');
        $sujet = trim($description);
        if (strlen($sujet) > 50) $sujet = substr($sujet, 0, 47) . '...';
        
        // Nouveaux champs
        $type_nc_raw = $_POST['type_nc'] ?? [];
        $type_nc_autre = cleanInput($_POST['type_nc_autre'] ?? '');
        
        if (in_array('Autre', $type_nc_raw) && empty($type_nc_autre)) {
            $error = "Veuillez préciser le type de non-conformité 'Autre'.";
        }
        
        // Combiner pour stockage
        if (in_array('Autre', $type_nc_raw) && !empty($type_nc_autre)) {
            // Remplacer 'Autre' par 'Autre: [précision]'
            $type_nc_raw = array_map(function($val) use ($type_nc_autre) {
                return ($val === 'Autre') ? 'Autre: ' . $type_nc_autre : $val;
            }, $type_nc_raw);
        }
        $type_nc = implode(',', $type_nc_raw);
        $type_nc = implode(',', $type_nc_raw);
        
        // GESTION PRODUITS MULTIPLES (Arrays)
        $produit_concernes = $_POST['produit_concerne'] ?? []; // Array
        $qtes_nc = $_POST['qte_nc'] ?? []; // Array
        $unites_nc = $_POST['unite_nc'] ?? []; // Array
        
        // Nettoyage et conversion en string pour le stockage (Compatible avec structure actuelle)
        if (is_array($produit_concernes)) {
            $produit_concerne = implode(' | ', array_map('cleanInput', $produit_concernes));
            $qte_nc = implode(' | ', array_map('cleanInput', $qtes_nc));
            $unite_nc = implode(' | ', array_map('cleanInput', $unites_nc));
        } else {
            $produit_concerne = cleanInput($_POST['produit_concerne'] ?? '');
            $qte_nc = cleanInput($_POST['qte_nc'] ?? '');
            $unite_nc = cleanInput($_POST['unite_nc'] ?? '');
        }

        $societe_transport = cleanInput($_POST['societe_transport'] ?? '');
        
        $societe_transport = cleanInput($_POST['societe_transport'] ?? '');
        
        $chauffeur = cleanInput($_POST['chauffeur'] ?? '');
        $date_livraison = !empty($_POST['date_livraison']) ? $_POST['date_livraison'] : null;
        $num_facture = cleanInput($_POST['num_facture'] ?? '');
        $date_facture = !empty($_POST['date_facture']) ? $_POST['date_facture'] : null;
        
        // Champs optionnels spécifiques
        $analyse_causes = cleanInput($_POST['analyse_causes'] ?? '');
        $analyse_consequences = cleanInput($_POST['analyse_consequences'] ?? '');
        $notes = cleanInput($_POST['notes'] ?? ''); // Observations

        // Validation
        if (empty($site_scpc) || empty($nom) || empty($prenom) || empty($email) || empty($description) || empty($type_ticket) || empty($demande_client) || empty($telephone) || empty($codeClient) || empty($nomSociete)) {
            $error = 'Veuillez remplir tous les champs obligatoires (*).';
        } elseif (in_array('service', $categories_cochees) && empty($matricule)) {
            $error = 'Le matricule est obligatoire pour une réclamation Service.';
        } elseif (in_array('produit', $categories_cochees) && empty($produit_concerne)) {
             $error = 'Veuillez saisir au moins un produit.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Adresse email invalide.';
        } elseif (!preg_match('/^(CL|CI)-\d{6}$/', $codeClient)) {
            $error = 'Le format du code client est incorrect (Attendu: CL-000000 ou CI-000000).';
        } elseif ($date_facture && $date_livraison && strtotime($date_facture) > strtotime($date_livraison)) {
            $error = 'La date de facture ne peut pas être postérieure à la date de livraison.';
        } else {
            // Logique de création
            try {
                $result = creerTicket([
                    'nom' => $nom,
                    'prenom' => $prenom,
                    'email' => $email,
                    'telephone' => $telephone,
                    'code_client' => $codeClient, // Code client saisi (non généré)
                    'nom_societe' => $nomSociete, // Nouveau champ
                    'sujet' => $sujet,
                    'description' => $description,
                    'categorie' => $categorie,
                    'site_scpc' => $site_scpc,
                    'site_autre' => ($site_scpc === 'autre') ? $site_autre : null,
                    'statut_id' => 6, // En cours de validation (ID 6 - Workflow RMQ)
                    
                    // ✅ Catégories saisies par l'utilisateur (produit, service, ou produit,service)
                    'type_ticket' => $categorie_value,
                    'type_nc' => $type_nc,
                    'produit_concerne' => $produit_concerne,
                    'qte_nc' => $qte_nc,
                    'unite_nc' => $unite_nc, // Nouveau champ
                    'societe_transport' => $societe_transport,
                    'chauffeur' => $chauffeur,
                    'matricule' => $matricule,
                    'date_livraison' => $date_livraison,
                    'num_facture' => $num_facture,
                    'date_facture' => $date_facture,
                    'demande_client' => $demande_client,
                    'analyse_causes' => $analyse_causes,
                    'analyse_consequences' => $analyse_consequences,
                    'notes' => $notes,
                    
                    'is_commercial' => false
                ]);
            } catch (Exception $e) {
                $error = $e->getMessage();
                $result = ['success' => false];
            }
            
            if ($result['success']) {
                $ticketId = $result['ticket_id'];
                $numeroTicket = $result['numero_ticket'];
                
                // Upload Fichiers
                if (!empty($_FILES['fichiers']['name'][0])) {
                    $uploadErrors = [];
                    foreach ($_FILES['fichiers']['tmp_name'] as $key => $tmpName) {
                        if (!empty($tmpName)) {
                            $fileData = [
                                'name'     => $_FILES['fichiers']['name'][$key],
                                'type'     => $_FILES['fichiers']['type'][$key],
                                'tmp_name' => $tmpName,
                                'error'    => $_FILES['fichiers']['error'][$key],
                                'size'     => $_FILES['fichiers']['size'][$key]
                            ];
                            
                            $uploadResult = uploadFichier($fileData, $ticketId);
                            if (!$uploadResult['success']) {
                                $uploadErrors[] = "Fichier '{$fileData['name']}': {$uploadResult['error']}";
                            }
                        }
                    }
                    
                    if (!empty($uploadErrors)) {
                        $error = "Ticket créé (#$numeroTicket), mais des erreurs d'upload sont survenues :<br>• " . implode("<br>• ", $uploadErrors);
                    }
                }
                
                // Si pas d'erreurs d'upload bloquantes ou si on accepte le ticket quand même
                if (empty($uploadErrors)) {
                    $success = true;
                } else {
                    // On affiche quand même le succès mais avec les erreurs d'upload
                    // Pour cet exercice, on va considérer que le ticket est créé
                    $success = true; 
                    // L'erreur sera affichée via la variable $error si on n'était pas en succès, 
                    // mais ici le $success bascule l'affichage. 
                    // On va ajuster nouvelle_reclamation.php pour afficher les erreurs même en cas de succès partiel
                }
            } else {
                $error = $result['error'];
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
    <title>Nouvelle Réclamation - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/reclamation-form.css">
    <link rel="stylesheet" href="../../assets/fontawesome/css/all.min.css">
</head>
<body>
    <!-- Navigation (Simplifiée pour Client/Guest) -->
    <nav class="navbar" style="background: white; padding: 1rem;">
        <div class="container" style="display: flex; justify-content: space-between; align-items: center;">
            <a href="../../index.php" class="navbar-brand" style="display: flex; align-items: center; gap: 10px; text-decoration: none;">
                <img src="../../assets/images/logo.png" alt="Logo SCPC" style="height: 40px; width: auto;">
                <div style="display: flex; flex-direction: column; line-height: 1.2;">
                    <span style="font-weight: 700; font-size: 1.1rem; color: #1f2937;">SCPC Comptoir Hammami</span>
                    <span style="font-size: 0.8rem; color: #6b7280; font-weight: normal;"><?php echo SITE_NAME; ?></span>
                </div>
            </a>
            
            <div class="navbar-menu" id="mainMenu" style="display: flex; gap: 1.5rem;">
                <a href="../../index.php" style="color: #374151; text-decoration: none;"><i class="fas fa-home"></i> Accueil</a>
                <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'client'): ?>
                    <a href="dashboard.php" style="color: #374151; text-decoration: none;"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                <?php endif; ?>
                <a href="nouvelle_reclamation.php" style="color: #FF6B35; font-weight: 600; text-decoration: none;"><i class="fas fa-plus-circle"></i> Nouvelle</a>
                <a href="suivi_reclamation.php" style="color: #374151; text-decoration: none;"><i class="fas fa-search"></i> Suivi</a>
            </div>

            <button id="navbarToggler" class="navbar-toggler" style="display: none; background: none; border: none; font-size: 1.5rem; color: #374151; cursor: pointer;">
                <i class="fas fa-bars"></i>
            </button>
        </div>
    </nav>

    <!-- Mobile Menu -->
    <div class="mobile-menu" id="mobileMenu">
        <div class="mobile-menu-header">
            <i class="fas fa-user-circle"></i>
            <strong>Client</strong>
            <p class="mobile-menu-subtitle">Espace Client</p>
        </div>
        <a href="../../index.php" class="mobile-menu-link"><i class="fas fa-home"></i> <span>Accueil</span></a>
        <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'client'): ?>
            <a href="dashboard.php" class="mobile-menu-link"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a>
        <?php endif; ?>
        <a href="nouvelle_reclamation.php" class="mobile-menu-link active"><i class="fas fa-plus-circle"></i> <span>Nouvelle</span></a>
        <a href="suivi_reclamation.php" class="mobile-menu-link"><i class="fas fa-search"></i> <span>Suivi</span></a>
        <a href="login.php" class="mobile-menu-link"><i class="fas fa-sign-in-alt"></i> <span>Connexion</span></a>
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
                // Ensure display block is set if needed by overrides, but class 'show' usually handles it.
                // For this specific file which had inline styles, we ensure the class works.
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

        // Re-inject the media query for toggler visibility if it was lost
        const style = document.createElement('style');
        style.textContent = `
            @media (max-width: 768px) {
                #mainMenu { display: none !important; }
                .navbar-toggler { display: block !important; }
            }
        `;
        document.head.appendChild(style);
    });
    </script>

    <div class="section" style="padding-top: 3rem; padding-bottom: 3rem; background: var(--gradient-hero);">
        <div class="container">
            <?php if ($success): ?>
            <!-- Success Message -->
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

                    <p style="color: var(--text-gray); margin-bottom: 2rem;">Votre réclamation a été transmise au service qualité.</p>
                    
                    <div style="background: var(--gradient-primary); padding: 2rem; border-radius: var(--radius-xl); margin-bottom: 2rem; color: white;">
                        <p style="color: rgba(255,255,255,0.85); margin-bottom: 0.5rem; font-size: 0.875rem; font-weight: 600;">Numéro de Ticket</p>
                        <h1 style="color: white; font-size: 2.5rem; margin: 0;"><?php echo $numeroTicket; ?></h1>
                    </div>
                    
                    <p style="color: var(--text-muted);">Un email de confirmation a été envoyé à <strong><?php echo htmlspecialchars($email); ?></strong></p>
                    
                    <div style="margin-top: 2rem;">
                        <a href="../../index.php" class="btn btn-secondary">Retour à l'accueil</a>
                        <a href="suivi_reclamation.php" class="btn btn-primary">Suivre ma réclamation</a>
                    </div>
                </div>
            </div>
            <?php else: ?>
            
            <div style="margin-bottom: 2rem; border-left: 4px solid var(--primary); padding-left: 1rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                <div>
                    <h2 style="margin: 0; color: var(--text-dark); font-size: 2rem; text-shadow: none;">
                        Déposer une Réclamation
                    </h2>
                    <p style="margin: 0.5rem 0 0 0; color: var(--text-gray);">Nous traiterons votre demande dans les plus brefs délais.</p>
                </div>
                <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'client'): ?>
                    <a href="dashboard.php" class="btn btn-outline" style="border-radius: 12px; padding: 0.6rem 1.2rem; font-size: 0.9rem; font-weight: 600; color: var(--primary); border: 1px solid var(--primary); text-decoration: none; display: flex; align-items: center; gap: 8px; transition: all 0.2s;">
                        <i class="fas fa-arrow-left"></i> Retour au Dashboard
                    </a>
                <?php endif; ?>
            </div>

            <!-- Formulaire Unifié -->
            <div class="form-container" style="max-width: 950px; margin: 0 auto;">
                <?php if ($error): ?>
                <div class="alert alert-danger mb-4">
                    <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                </div>
                <?php endif; ?>
                
                <?php 
                $contexte = 'client';
                include '../../pages/includes/form_reclamation_inc.php'; 
                ?>
            </div>
            
            <?php endif; ?>
        </div>
    </div>
    
    <script src="../../assets/js/main.js"></script>
</body>
</html>