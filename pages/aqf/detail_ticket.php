<?php
/**
 * Détail Ticket AQF (Assistant Qualité Fabrication/Fournisseur)
 * Fichier: pages/aqf/detail_ticket.php
 * Harmonisé avec le style RMQ
 */

require_once '../../config/database.php';
require_once '../../api/functions.php';
require_once '../../api/EmailManager.php';

// Vérifier que l'utilisateur est AQF (ou admin/rmq pour la vue lecture seule)
if (!isset($_SESSION['user_id'])) {
    header('Location: ../agent/login.php?role=aqf');
    exit;
}

$db = getDB();
$ticketId = $_GET['id'] ?? 0;
$error = '';
$success = '';

// Récupérer le ticket
$stmt = $db->prepare("
    SELECT t.*,
           c.nom as categorie_nom, c.couleur as categorie_couleur,
           s.nom as statut_nom, s.couleur as statut_couleur,
           CONCAT(u.prenom, ' ', u.nom) as client_nom_complet,
           u.email as email_client
    FROM tickets t
    LEFT JOIN categories c ON t.categorie_id = c.id
    LEFT JOIN statuts s ON t.statut_id = s.id
    LEFT JOIN utilisateurs u ON t.client_id = u.id
    WHERE t.id = ?
");
$stmt->execute([$ticketId]);
$ticket = $stmt->fetch();

if (!$ticket) {
    header('Location: dashboard.php');
    exit;
}

// Traitement des actions (uniquement pour AQF)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_SESSION['user_role'] === 'aqf') {
    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'enregistrer_traitement_aqf') {
            $statut_aqf = cleanInput($_POST['statut_aqf'] ?? '');
            $commentaire_aqf = cleanInput($_POST['commentaire_resolution_aqf'] ?? '');
            $raison_non_retenu = cleanInput($_POST['raison_non_retenu_aqf'] ?? '');
            
            if (empty($statut_aqf) || empty($commentaire_aqf)) {
                throw new Exception("Le statut AQF et le commentaire de résolution sont obligatoires.");
            }
            if ($statut_aqf === 'non retenu' && empty($raison_non_retenu)) {
                throw new Exception("La raison du non-retenu est obligatoire.");
            }
            
            $preuve_aqf = $ticket['preuve_resolution_aqf'];
            
            // Gestion de l'upload
            if (!empty($_FILES['preuve_resolution_aqf']['name'])) {
                $file = $_FILES['preuve_resolution_aqf'];
                $allowedExts = ['pdf', 'jpg', 'jpeg', 'png'];
                $allowedMime = ['application/pdf', 'image/jpeg', 'image/png'];
                $maxSize = 5 * 1024 * 1024;
                
                $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $fileMime = mime_content_type($file['tmp_name']);
                
                if (!in_array($fileExt, $allowedExts) || !in_array($fileMime, $allowedMime)) {
                    throw new Exception("Format de fichier non autorisé (PDF, JPG, PNG uniquement).");
                }
                if ($file['size'] > $maxSize) {
                    throw new Exception("Le fichier est trop volumineux (max 5 Mo).");
                }
                
                $uploadDir = '../../uploads/preuves_aqf/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
                
                $fileName = 'aqf_' . $ticketId . '_' . time() . '.' . $fileExt;
                if (move_uploaded_file($file['tmp_name'], $uploadDir . $fileName)) {
                    $preuve_aqf = 'preuves_aqf/' . $fileName;
                } else {
                    throw new Exception("Erreur lors de l'upload du fichier.");
                }
            }
            
            // Update BDD
            $stmt = $db->prepare("UPDATE tickets SET statut_aqf = ?, commentaire_resolution_aqf = ?, preuve_resolution_aqf = ?, raison_non_retenu_aqf = ?, date_traitement_aqf = NOW() WHERE id = ?");
            $stmt->execute([$statut_aqf, $commentaire_aqf, $preuve_aqf, $raison_non_retenu, $ticketId]);
            
            // History
            $db->prepare("INSERT INTO historique_actions (ticket_id, action, details, utilisateur_id, date_action) VALUES (?, 'traitement_aqf', 'Traitement AQF enregistré (Statut: $statut_aqf)', ?, NOW())")
               ->execute([$ticketId, $_SESSION['user_id']]);
            
            $success = "Traitement AQF enregistré avec succès.";
            header("Refresh: 2; url=detail_ticket.php?id=$ticketId");
        }
    } catch(Exception $e) {
        $error = 'Erreur: ' . $e->getMessage();
    }
}

$historique = getHistoriqueTicket($ticketId);
$piecesJointes = getPiecesJointes($ticketId);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket #<?php echo $ticket['numero_ticket']; ?> - AQF</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/fontawesome/css/all.min.css">
    <style>
        /* ========== Harmonisation Style RMQ ========== */
        * { box-sizing: border-box; }
        
        body { 
            background: #f3f4f6; 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #1f2937;
            line-height: 1.5;
        }
        
        .page-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 1rem;
        }
        
        .card-section {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
            margin-bottom: 1.5rem;
            border: 1px solid #e5e7eb;
            transition: box-shadow 0.2s ease;
        }
        
        .card-section:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        }
        
        .card-section.accent {
            border-left: 4px solid #FF6B35;
            background: linear-gradient(to right, #fff7ed 0%, #ffffff 100%);
        }
        
        .page-title {
            font-size: 1.75rem;
            font-weight: 700;
            color: #111;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .section-title {
            font-size: 1.2rem;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 1.2rem;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid #f3f4f6;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .subsection-title {
            font-size: 1rem;
            font-weight: 600;
            color: #374151;
            margin-bottom: 0.75rem;
        }
        
        .grid-2col {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
        }
        
        .grid-3col {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 1.5rem;
        }
        
        .info-row {
            display: flex;
            flex-direction: column;
            margin-bottom: 1rem;
        }
        
        .info-label {
            font-size: 0.8rem;
            font-weight: 700;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.3rem;
        }
        
        .info-value {
            font-size: 0.95rem;
            font-weight: 500;
            color: #111;
        }
        
        .info-value.highlight {
            color: #FF6B35;
            font-weight: 700;
            font-size: 1rem;
        }
        
        .status-badge {
            display: inline-block;
            padding: 0.4rem 1rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 700;
            color: white;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            font-size: 0.95rem;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            white-space: nowrap;
            text-decoration: none;
        }
        
        .btn-primary { background: #FF6B35; color: white; }
        .btn-primary:hover { background: #e85a2b; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(255, 107, 53, 0.3); }
        
        .btn-outline-secondary { border: 2px solid #e5e7eb; background: white; color: #6b7280; }
        .btn-outline-secondary:hover { background: #f9fafb; border-color: #d1d5db; color: #374151; }

        .form-input {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 0.95rem;
            font-weight: 500;
            transition: all 0.2s ease;
            font-family: inherit;
        }
        
        .form-input:focus {
            outline: none;
            border-color: #FF6B35;
            box-shadow: 0 0 0 3px rgba(255, 107, 53, 0.1);
        }
        
        textarea.form-input {
            resize: vertical;
            min-height: 100px;
        }

        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            border-left: 4px solid;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .alert-error {
            background: #fef2f2;
            border-left-color: #ef4444;
            color: #991b1b;
        }
        
        .alert-success {
            background: #f0fdf4;
            border-left-color: #10b981;
            color: #166534;
        }

        .timeline { border-left: 2px solid #e5e7eb; padding-left: 2rem; margin-top: 1rem; }
        .timeline-item { margin-bottom: 2rem; position: relative; }
        .timeline-item::before {
            content: '';
            position: absolute;
            left: calc(-2rem - 6px);
            top: 4px;
            width: 10px;
            height: 10px;
            background: #FF6B35;
            border-radius: 50%;
            border: 2px solid white;
        }

        .attachment-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 1rem;
        }

        .attachment-item {
            background: #f9fafb;
            padding: 1rem;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
            text-align: center;
            text-decoration: none;
            color: inherit;
            transition: all 0.2s;
        }

        .attachment-item:hover {
            border-color: #FF6B35;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }

        @media (max-width: 992px) {
            .grid-3col, .grid-2col { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php" style="display: flex; align-items: center; gap: 10px;">
                <img src="../../assets/images/logo.png" alt="Logo SCPC" style="height: 40px; width: auto;">
                <div style="display: flex; flex-direction: column; line-height: 1.2;">
                    <span style="font-weight: 700; font-size: 1.1rem; color: #1A1A1A;">SCPC Comptoir Hammami</span>
                    <span style="font-size: 0.75rem; color: #666; font-weight: 500;"><?php echo SITE_NAME; ?></span>
                </div>
                <!-- Badge spécifique AQF -->
                <span class="navbar-brand-badge" style="background: #2563EB;">
                    AQF
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
    
    <div class="mobile-menu" id="mobileMenu">
        <div class="mobile-menu-header">
            <i class="fas fa-clipboard-check"></i>
            <strong>AQF</strong>
            <p class="mobile-menu-subtitle">Responsable Qualité</p>
        </div>
        <a href="dashboard.php" class="mobile-menu-link"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a>
        <a href="statistiques.php" class="mobile-menu-link"><i class="fas fa-chart-bar"></i> <span>Statistiques</span></a>
        <a href="../../pages/logout.php" class="mobile-menu-link" style="color: var(--danger-color) !important;"><i class="fas fa-sign-out-alt"></i> <span>Déconnexion</span></a>
    </div>

    <div class="page-container" style="margin-top: 2rem; margin-bottom: 3rem;">
        
        <!-- Breadcrumb & Back -->
        <div style="margin-bottom: 1.5rem;">
            <a href="dashboard.php" class="btn btn-outline-secondary" style="padding: 0.5rem 1rem; font-size: 0.9rem;">
                <i class="fas fa-arrow-left"></i> Retour au Dashboard
            </a>
        </div>

        <!-- Messages -->
        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <span><?php echo $error; ?></span>
            </div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <span><?php echo $success; ?></span>
            </div>
        <?php endif; ?>

        <!-- ========== 1. HEADER DU TICKET ========== -->
        <div class="card-section" style="border-left: 5px solid <?php echo $ticket['statut_couleur']; ?>;">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1.5rem;">
                <div>
                    <div class="page-title" style="margin: 0;">
                        <span>Ticket N° <?php echo htmlspecialchars($ticket['numero_ticket']); ?></span>
                        <span class="status-badge" style="background: <?php echo $ticket['statut_couleur']; ?>;">
                            <?php echo htmlspecialchars($ticket['statut_nom']); ?>
                        </span>
                    </div>
                    <div style="margin: 0.5rem 0 0 0; display: flex; gap: 1.5rem; align-items: center; flex-wrap: wrap;">
                        <p style="margin: 0; color: #6b7280; font-size: 0.95rem; font-weight: 600;">
                            <i class="fas fa-user-tag" style="color: #FF6B35; margin-right: 0.25rem;"></i> 
                            Code Client: <span style="color: #111;"><?php echo htmlspecialchars($ticket['code_client'] ?? 'N/A'); ?></span>
                        </p>
                        <p style="margin: 0; color: #6b7280; font-size: 0.95rem;">
                            <i class="fas fa-calendar-alt" style="margin-right: 0.25rem;"></i> 
                            Créé le: <?php echo date('d/m/Y', strtotime($ticket['date_creation'])); ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- ========== 2. INFORMATIONS RÉCLAMATION ========== -->
        <div class="card-section">
            <h3 class="section-title">
                <i class="fas fa-info-circle"></i> Informations de la Réclamation
            </h3>
            
            <div class="grid-2col">
                
                <div class="info-row">
                    <span class="info-label">Catégorie</span>
                    <span class="info-value">
                        <?php 
                        $cat = $ticket['type_ticket'] ?? 'standard';
                        if (strpos($cat, 'produit') !== false && strpos($cat, 'service') !== false) {
                            echo '<span style="background: #fef3c7; color: #92400e; padding: 0.3rem 0.7rem; border-radius: 4px; font-size: 0.85rem;">Produit & Service</span>';
                        } elseif (strpos($cat, 'produit') !== false) {
                            echo '<span style="background: #dbeafe; color: #1e40af; padding: 0.3rem 0.7rem; border-radius: 4px; font-size: 0.85rem;">Produit</span>';
                        } elseif (strpos($cat, 'service') !== false) {
                            echo '<span style="background: #dcfce7; color: #166534; padding: 0.3rem 0.7rem; border-radius: 4px; font-size: 0.85rem;">Service</span>';
                        } else {
                            echo htmlspecialchars($ticket['categorie_nom']);
                        }
                        ?>
                    </span>
                </div>
            </div>
            
            <div class="info-row" style="margin-top: 1rem;">
                <span class="info-label">Objet</span>
                <span class="info-value highlight"><?php echo nl2br(htmlspecialchars(html_entity_decode($ticket['description'], ENT_QUOTES, 'UTF-8'))); ?></span>
            </div>

            <div class="info-row" style="margin-top: 1.5rem; background: #fff7ed; padding: 1.5rem; border-radius: 8px; border: 1px solid #ffedd5;">
                <span class="info-label" style="color: #c2410c;">Demande Explicite du Client</span>
                <div style="font-size: 1.1rem; font-weight: 700; color: #9a3412; margin-top: 0.5rem;">
                    <?php echo htmlspecialchars(html_entity_decode($ticket['demande_client'] ?: 'Aucune demande spécifique formulée.', ENT_QUOTES, 'UTF-8')); ?>
                </div>
            </div>
        </div>

        <!-- ========== 3. PARTIES IMPLIQUÉES ========== -->
        <div class="card-section">
            <h3 class="section-title">
                <i class="fas fa-users"></i> Parties Impliquées
            </h3>
            
            <div class="grid-3col">
                <!-- CLIENT -->
                <div style="padding: 1.25rem; background: #f0f9ff; border-radius: 10px; border: 1px solid #bae6fd;">
                    <h4 class="subsection-title" style="color: #0369a1;">
                        <i class="fas fa-user-circle"></i> Entité Client
                    </h4>
                    <div class="info-row">
                        <span class="info-label">Nom / Société</span>
                        <span class="info-value"><?php echo htmlspecialchars($ticket['client_nom_complet'] ?? 'N/A'); ?></span>
                    </div>
                </div>
                
                <!-- FOURNISSEUR -->
                <div style="padding: 1.25rem; background: #fff7ed; border-radius: 10px; border: 1px solid #ffedd5;">
                    <h4 class="subsection-title" style="color: #c2410c;">
                        <i class="fas fa-industry"></i> Fournisseur Assigné
                    </h4>
                    <?php if (!empty($ticket['fournisseur_nom'])): ?>
                        <div class="info-row">
                            <span class="info-label">Nom du Fournisseur</span>
                            <span class="info-value"><?php echo htmlspecialchars($ticket['fournisseur_nom']); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Email</span>
                            <span class="info-value" style="font-size: 14px;"><?php echo htmlspecialchars($ticket['fournisseur_email'] ?? ''); ?></span>
                        </div>
                    <?php else: ?>
                        <p style="color: #9a3412; font-style: italic; margin: 0; font-size: 0.9rem;">
                            Aucun fournisseur assigné
                        </p>
                    <?php endif; ?>
                </div>
                
                <!-- LOGISTIQUE -->
                <div style="padding: 1.25rem; background: #fdf2f8; border-radius: 10px; border: 1px solid #fce7f3;">
                    <h4 class="subsection-title" style="color: #9d174d;">
                        <i class="fas fa-truck"></i> Logistique
                    </h4>
                    <?php if (!empty($ticket['societe_transport'])): ?>
                        <div class="info-row">
                            <span class="info-label">Transporteur</span>
                            <span class="info-value"><?php echo htmlspecialchars($ticket['societe_transport']); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Livraison le</span>
                            <span class="info-value">
                                <?php echo $ticket['date_livraison'] ? date('d/m/Y', strtotime($ticket['date_livraison'])) : 'N/A'; ?>
                            </span>
                        </div>
                    <?php else: ?>
                        <p style="color: #be185d; font-style: italic; margin: 0; font-size: 0.9rem;">
                            Non assigné logistiquement
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- ========== 4. DÉTAILS TECHNIQUES ========== -->
        <div class="card-section">
            <h3 class="section-title">
                <i class="fas fa-microchip"></i> Détails Techniques
            </h3>
            
            <?php
            // The screenshot shows data is separated by pipes " | " 
            // example: brique | ciment | bois
            // and quantity: 120 | 200 | 400
            
            $produitsStr = $ticket['produit_concerne'] ?: 'Non spécifié';
            $qtesStr = $ticket['qte_nc'] ?: '';
            $unitesStr = $ticket['unite_nc'] ?: '';
            
            // Split by pipe
            $prodArray = explode('|', $produitsStr);
            $qteArray = explode('|', $qtesStr);
            $unitArray = explode('|', $unitesStr);
            ?>

            <?php foreach($prodArray as $index => $prodName): 
                $qte = trim($qteArray[$index] ?? '');
                $unit = trim($unitArray[$index] ?? '');
                $qteDisplay = $qte ? ($qte . ' ' . $unit) : 'Non renseignée';
                
                // Skip empty entries if any
                if (trim($prodName) === '') continue;
            ?>
            <div class="grid-3col" style="margin-bottom: 0.5rem; background: #f9fafb; padding: 1rem; border-radius: 8px; border: 1px solid #e5e7eb; align-items: center;">
                <div class="info-row" style="margin-bottom: 0;">
                    <span class="info-label">Produit / Service</span>
                    <span class="info-value"><?php echo htmlspecialchars(trim($prodName)); ?></span>
                </div>
                <div class="info-row" style="margin-bottom: 0;">
                    <span class="info-label">Quantité NC</span>
                    <span class="info-value" style="color: #dc2626; font-weight: 700;">
                        <?php echo htmlspecialchars($qteDisplay); ?>
                    </span>
                </div>
                <!-- Only show nature for first item or if it varies? Nature is usually global for the ticket in current DB schema -->
                <?php if ($index === 0): ?>
                <div class="info-row" style="margin-bottom: 0;">
                    <span class="info-label">Nature / Type NC</span>
                    <span class="info-value"><?php echo htmlspecialchars($ticket['type_nc'] ?: 'Standard'); ?></span>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>

            <div class="grid-2col">
                <div class="info-row">
                    <span class="info-label" style="color: #FF6B35; margin-bottom: 0.75rem;"><i class="fas fa-search"></i> Analyse des Causes</span>
                    <div style="font-size: 0.95rem; font-style: italic; color: #4b5563; background: #fffaf0; padding: 1rem; border-radius: 8px; border-left: 3px solid #FF6B35;">
                        <?php echo nl2br(htmlspecialchars(html_entity_decode($ticket['analyse_causes'] ?: 'Aucune analyse de cause enregistrée.', ENT_QUOTES, 'UTF-8'))); ?>
                    </div>
                </div>
                <div class="info-row">
                    <span class="info-label" style="color: #dc2626; margin-bottom: 0.75rem;"><i class="fas fa-exclamation-triangle"></i> Analyse des Conséquences</span>
                    <div style="font-size: 0.95rem; font-style: italic; color: #4b5563; background: #fff1f2; padding: 1rem; border-radius: 8px; border-left: 3px solid #dc2626;">
                        <?php echo nl2br(htmlspecialchars(html_entity_decode($ticket['analyse_consequences'] ?: 'Aucune analyse de conséquence enregistrée.', ENT_QUOTES, 'UTF-8'))); ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- ========== 5. DÉCISION RMQ & RÉSOLUTION (Read Only) ========== -->
        <?php if (!empty($ticket['decision_rmq'])): ?>
        <div class="card-section accent">
            <h3 class="section-title">
                <i class="fas fa-gavel"></i> Décision RMQ & Résolution
            </h3>
            
            <div class="info-row" style="margin-bottom: 1.5rem;">
                <span class="info-label">Décision Finale</span>
                <div style="background: white; padding: 1.25rem; border-radius: 10px; border: 1px solid #e5e7eb; color: #111; font-weight: 500; line-height: 1.6;">
                    <?php echo nl2br(htmlspecialchars(html_entity_decode($ticket['decision_rmq'], ENT_QUOTES, 'UTF-8'))); ?>
                </div>
            </div>

            <div class="info-row">
                <span class="info-label">Actions de résolution & Preuve</span>
                <div style="background: #f0fdf4; padding: 1.25rem; border-radius: 10px; border: 1px solid #dcfce7; color: #166534; font-weight: 500;">
                    <p style="margin-bottom: 1rem;"><?php echo nl2br(htmlspecialchars(html_entity_decode($ticket['commentaire_resolution'], ENT_QUOTES, 'UTF-8'))); ?></p>
                    
                    <?php if (!empty($ticket['preuve_resolution'])): ?>
                        <a href="../../uploads/<?php echo htmlspecialchars($ticket['preuve_resolution']); ?>" target="_blank" class="btn btn-primary" style="font-size: 0.85rem; padding: 0.5rem 1rem;">
                            <i class="fas fa-download"></i> Télécharger la preuve RMQ
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            
            <div style="margin-top: 1rem; text-align: right; color: #6b7280; font-size: 12px; font-weight: 600;">
                Date de la décision : <?php echo date('d/m/Y H:i', strtotime($ticket['date_decision'])); ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- ========== 6. RAISON DU NON-RETENU (Card Dédiée) ========== -->
        <?php if ($ticket['statut_aqf'] === 'non retenu' || ($_SESSION['user_role'] === 'aqf' && !isset($_GET['edit']))): ?>
        <div class="card-section" id="raisonNonRetenuCard" style="border-left: 5px solid #dc2626; display: <?php echo ($ticket['statut_aqf'] === 'non retenu') ? 'block' : 'none'; ?>;">
            <h3 class="section-title" style="color: #dc2626; border-bottom-color: #fee2e2;">
                <i class="fas fa-exclamation-circle"></i> Raison du non-retenu
            </h3>
            <div style="background: #fef2f2; padding: 1.5rem; border-radius: 10px; color: #991b1b; font-weight: 500; font-size: 1.05rem; line-height: 1.7;">
                <?php echo nl2br(htmlspecialchars(html_entity_decode($ticket['raison_non_retenu_aqf'] ?? '', ENT_QUOTES, 'UTF-8'))); ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- ========== 7. TRAITEMENT AQF ========== -->
        <div class="card-section" style="border-top: 4px solid #FF6B35;">
            <h3 class="section-title">
                <i class="fas fa-certificate"></i> Traitement Qualité
            </h3>

            <?php if ($_SESSION['user_role'] === 'aqf'): ?>
                <form method="POST" enctype="multipart/form-data" onsubmit="return confirm('Enregistrer ce traitement ?');">
                    <input type="hidden" name="action" value="enregistrer_traitement_aqf">
                    
                    <div class="grid-2col">
                        <!-- Statut AQF -->
                        <div class="info-row">
                            <label class="info-label" for="statut_aqf">Décision AQF *</label>
                            <select name="statut_aqf" id="statut_aqf" class="form-input" required style="font-weight: 700;" onchange="handleStatutAQF(this.value)">
                                <option value="en cours de traitement" <?php echo ($ticket['statut_aqf'] === 'en cours de traitement') ? 'selected' : ''; ?>>En cours de traitement</option>
                                <option value="retenu" <?php echo ($ticket['statut_aqf'] === 'retenu') ? 'selected' : ''; ?>>Retenu</option>
                                <option value="non retenu" <?php echo ($ticket['statut_aqf'] === 'non retenu') ? 'selected' : ''; ?>>Non retenu</option>
                            </select>
                        </div>
                        
                        <!-- Preuve AQF -->
                        <div class="info-row">
                            <label class="info-label" for="preuve_resolution_aqf">Document d'expertise / Preuve (PDF, Images)</label>
                            <input type="file" name="preuve_resolution_aqf" id="preuve_resolution_aqf" class="form-input">
                            <?php if (!empty($ticket['preuve_resolution_aqf'])): ?>
                                <div style="margin-top: 8px;">
                                    <a href="../../uploads/<?php echo htmlspecialchars($ticket['preuve_resolution_aqf']); ?>" target="_blank" style="color: #FF6B35; font-size: 0.9rem; font-weight: 600;">
                                        <i class="fas fa-file-alt"></i> Voir le document actuel
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Bloc Raison Rejet (Formulaire) -->
                    <div id="raisonRejetFormBlock" style="display: <?php echo ($ticket['statut_aqf'] === 'non retenu') ? 'block' : 'none'; ?>; margin-top: 1rem;">
                        <div class="info-row">
                            <label class="info-label" for="raison_non_retenu_aqf" style="color: #dc2626;">Motif détaillé du non-retenu *</label>
                            <textarea name="raison_non_retenu_aqf" id="raison_non_retenu_aqf_field" class="form-input" rows="3" placeholder="Saisissez ici la raison pour laquelle cette réclamation n'est pas retenue..." style="border-color: #fecaca;"><?php echo htmlspecialchars(html_entity_decode($ticket['raison_non_retenu_aqf'] ?? '', ENT_QUOTES, 'UTF-8')); ?></textarea>
                        </div>
                    </div>

                    <!-- Commentaire Résolution AQF -->
                    <div class="info-row" style="margin-top: 1rem;">
                        <label class="info-label" for="commentaire_resolution_aqf">Actions de résolution / Rapport d'expertise *</label>
                        <textarea name="commentaire_resolution_aqf" id="commentaire_resolution_aqf" class="form-input" rows="5" required placeholder="Détaillez ici votre analyse technique et les actions entreprises..."><?php echo htmlspecialchars(html_entity_decode($ticket['commentaire_resolution_aqf'] ?? '', ENT_QUOTES, 'UTF-8')); ?></textarea>
                    </div>

                    <div style="text-align: right; margin-top: 2rem;">
                        <button type="submit" class="btn btn-primary" style="height: 55px; min-width: 250px; font-weight: 700;">
                            <i class="fas fa-save"></i> Enregistrer le traitement AQF
                        </button>
                    </div>
                </form>
            <?php else: ?>
                <!-- Vue Lecture Seule pour les autres rôles -->
                <?php if (empty($ticket['statut_aqf'])): ?>
                    <div style="text-align: center; padding: 3rem; border: 2px dashed #e5e7eb; border-radius: 12px; color: #9ca3af;">
                        <i class="fas fa-hourglass-half" style="font-size: 2.5rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                        <p style="font-weight: 600; font-size: 1rem;">L'expertise AQF est actuellement en attente.</p>
                    </div>
                <?php else: ?>
                    <div style="background: #f9fafb; border-radius: 12px; border: 1px solid #e5e7eb; overflow: hidden;">
                        <div style="padding: 1.5rem; border-bottom: 1px solid #e5e7eb; display: flex; justify-content: space-between; align-items: center; background: white;">
                            <div class="info-group" style="margin: 0;">
                                <div class="info-label">Statut Expertise</div>
                                <div style="font-weight: 800; font-size: 1rem; color: <?php echo ($ticket['statut_aqf'] === 'retenu') ? '#10b981' : (($ticket['statut_aqf'] === 'non retenu') ? '#ef4444' : '#FF6B35'); ?>; text-transform: uppercase; display: flex; align-items: center; gap: 8px;">
                                    <i class="fas fa-circle" style="font-size: 0.7rem;"></i> <?php echo htmlspecialchars($ticket['statut_aqf']); ?>
                                </div>
                            </div>
                            <div style="text-align: right; font-size: 12px; color: #6b7280; font-weight: 600;">
                                Expertise réalisée le : <?php echo date('d/m/Y H:i', strtotime($ticket['date_traitement_aqf'])); ?>
                            </div>
                        </div>
                        
                        <div style="padding: 2rem;">
                            <div class="info-row">
                                <span class="info-label">Rapport d'expertise / Actions</span>
                                <div style="color: #374151; line-height: 1.8; font-size: 1.05rem;">
                                    <?php echo nl2br(htmlspecialchars(html_entity_decode($ticket['commentaire_resolution_aqf'], ENT_QUOTES, 'UTF-8'))); ?>
                                </div>
                            </div>

                            <?php if (!empty($ticket['preuve_resolution_aqf'])): ?>
                                <div style="margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid #e5e7eb;">
                                    <a href="../../uploads/<?php echo htmlspecialchars($ticket['preuve_resolution_aqf']); ?>" target="_blank" class="btn btn-outline-secondary" style="border-radius: 10px; font-weight: 700;">
                                        <i class="fas fa-file-download"></i> Télécharger le document d'expertise AQF
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- ========== 8. PIECES JOINTES & HISTORIQUE ========== -->
        <div class="grid-2col">
            <!-- Documents -->
            <div class="card-section">
                <h3 class="section-title">
                    <i class="fas fa-paperclip"></i> Galerie de Documents
                </h3>
                <?php if (count($piecesJointes) > 0): ?>
                    <div class="attachment-grid">
                        <?php foreach ($piecesJointes as $pj): ?>
                            <?php 
                                $ext = strtolower(pathinfo($pj['nom_fichier'], PATHINFO_EXTENSION));
                                $icon = 'fa-file';
                                if (in_array($ext, ['jpg','jpeg','png','gif'])) $icon = 'fa-file-image';
                                elseif ($ext === 'pdf') $icon = 'fa-file-pdf';
                            ?>
                            <a href="../../uploads/<?php echo $pj['chemin_fichier']; ?>" target="_blank" class="attachment-item">
                                <div style="font-size: 2rem; color: #FF6B35; margin-bottom: 0.5rem;"><i class="fas <?php echo $icon; ?>"></i></div>
                                <div style="font-size: 13px; font-weight: 700; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                    <?php echo htmlspecialchars($pj['nom_fichier']); ?>
                                </div>
                                <div style="font-size: 11px; color: #6b7280;"><?php echo round($pj['taille_fichier']/1024, 1); ?> KB</div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div style="text-align: center; padding: 2.5rem; border: 2px dashed #e5e7eb; border-radius: 12px; color: #9ca3af;">
                        <i class="fas fa-folder-open" style="font-size: 2rem; margin-bottom: 1rem;"></i><br>
                        Aucun document associé au ticket
                    </div>
                <?php endif; ?>
            </div>

            <!-- Historique -->
            <div class="card-section">
                <h3 class="section-title">
                    <i class="fas fa-history"></i> Historique du Ticket
                </h3>
                <div class="timeline" style="max-height: 400px; overflow-y: auto; padding-right: 1rem;">
                    <?php foreach($historique as $h): ?>
                        <div class="timeline-item">
                            <div style="font-size: 11px; color: #6b7280; font-weight: 700;"><?php echo date('d/m/Y H:i', strtotime($h['date_action'])); ?></div>
                            <div style="font-size: 14px; font-weight: 700; color: #111; margin: 4px 0;"><?php echo htmlspecialchars($h['action']); ?></div>
                            <div style="font-size: 13px; color: #6b7280; line-height: 1.4;"><?php echo htmlspecialchars($h['details']); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

    </div>

    <script src="../../assets/js/main.js"></script>
    <script>
    function handleStatutAQF(val) {
        const formBlock = document.getElementById('raisonRejetFormBlock');
        const cardBlock = document.getElementById('raisonNonRetenuCard');
        const field = document.getElementById('raison_non_retenu_aqf_field');
        
        if (val === 'non retenu') {
            if(formBlock) formBlock.style.display = 'block';
            if(cardBlock) cardBlock.style.display = 'block';
            if(field) field.setAttribute('required', 'required');
        } else {
            if(formBlock) formBlock.style.display = 'none';
            if(cardBlock && !('<?php echo $ticket['statut_aqf']; ?>' === 'non retenu')) {
                cardBlock.style.display = 'none';
            }
            if(field) field.removeAttribute('required');
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        // Handle decision field
        const currentStatut = document.getElementById('statut_aqf');
        if(currentStatut) handleStatutAQF(currentStatut.value);

        // --- GESTION MENU MOBILE ---
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
