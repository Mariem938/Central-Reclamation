<?php
/**
 * Détail Ticket RMQ - Version Réorganisée V3
 * Fichier: pages/rmq/detail_ticket_reorganized.php
 * Nouvelle structure avec mise en page professionnelle
 */

require_once '../../config/database.php';
require_once '../../api/functions.php';
require_once '../../api/EmailManager.php';

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'rmq') {
    header('Location: ../agent/login.php?role=rmq');
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
           COALESCE(u.email, t.email_client) as client_email, 
           COALESCE(CONCAT(u.prenom, ' ', u.nom), CONCAT(t.prenom_client, ' ', t.nom_client)) as client_nom_complet
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

// Vérification du statut pour affichage conditionnel
// ID 1: Nouveau, ID 6: En attente validation
$showValidationButtons = ($ticket['statut_id'] == 1 || $ticket['statut_id'] == 6);

// Logique Status IDs
$statutIds = [];
$stmtStat = $db->query("SELECT id, nom FROM statuts");
while ($row = $stmtStat->fetch()) {
    $statutIds[$row['nom']] = $row['id'];
}
$statutNames = array_flip($statutIds);

// Traitement POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'update_status') {
            $newStatusId = intval($_POST['statut_id'] ?? 0);
            $notes = cleanInput($_POST['notes'] ?? '');
            $notifyClient = isset($_POST['notify_client']) && $_POST['notify_client'] == '1';
            
            // Update et triggers
            $updates = ["statut_id = ?, notes = ?"];
            $params = [$newStatusId, $notes];
            
            $idVerif = $statutIds['En cours de vérification'] ?? 2;
            if ($newStatusId == $idVerif && empty($ticket['date_debut_traitement'])) {
                $updates[] = "date_debut_traitement = NOW()";
            }

            $idResolu = $statutIds['Résolu'] ?? 4;
            if ($newStatusId == $idResolu) {
                $startStr = $ticket['date_debut_traitement'] ?? $ticket['date_creation'];
                $start = new DateTime($startStr);
                $now = new DateTime();
                $diff = $start->diff($now);
                $days = $diff->days;
                
                $updates[] = "duree_traitement = ?";
                $params[] = $days;
                // Fix: Enregistrer la date de fin réelle
                $updates[] = "date_fin_traitement = NOW()";
            }

            $params[] = $ticketId;
            $sql = "UPDATE tickets SET " . implode(', ', $updates) . " WHERE id = ?";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);

            // LOG
            $newStatusName = $statutNames[$newStatusId] ?? 'Inconnu';
            $logDetails = "Statut: $newStatusName. Notes: $notes";
            if ($notifyClient) $logDetails .= " (Client Notifié)";
            
            $stmt = $db->prepare("INSERT INTO historique_actions (ticket_id, action, details, utilisateur_id, date_action) VALUES (?, 'change_status', ?, ?, NOW())");
            $stmt->execute([$ticketId, $logDetails, $_SESSION['user_id']]);

            // EMAIL
            if ($notifyClient && !empty($ticket['client_email'])) {
                $emailManager = new EmailManager($db);
                
                // ✅ Refetch the ACTUAL status name to be 100% sure
                $stmtSn = $db->prepare("SELECT nom FROM statuts WHERE id = ?");
                $stmtSn->execute([$newStatusId]);
                $realStatusName = $stmtSn->fetchColumn();
                
                // On utilise la méthode de template officielle
                $resultMail = $emailManager->envoyerChangementStatut($ticket, $ticket['client_email'], $realStatusName);
                
                if ($resultMail) {
                    $success = "Mise à jour effectuée et client notifié.";
                } else {
                    $success = "Mise à jour effectuée en BDD, mais l'envoi de l'email a échoué.";
                }
            } else {
                $success = "Mise à jour effectuée (Sans notification).";
            }
            
            echo "<script>alert('Opération effectuée avec succès !'); window.location.href='detail_ticket.php?id=$ticketId';</script>";
            exit;

        } elseif ($action === 'valider_ticket') {
            $idVerif = $statutIds['En cours de vérification'] ?? 2;
            $db->prepare("UPDATE tickets SET statut_id = ?, date_debut_traitement = NOW() WHERE id = ?")->execute([$idVerif, $ticketId]);
            $db->prepare("INSERT INTO historique_actions (ticket_id, action, details, utilisateur_id, date_action) VALUES (?, 'validation', 'Ticket validé par RMQ', ?, NOW())")->execute([$ticketId, $_SESSION['user_id']]);
            echo "<script>alert('Ticket validé !'); window.location.href='detail_ticket.php?id=$ticketId';</script>";
            exit;

        } elseif ($action === 'assigner_fournisseur') {
            $fournisseurNom = cleanInput($_POST['fournisseur_nom'] ?? '');
            $fournisseurEmail = cleanInput($_POST['fournisseur_email'] ?? '');
            
            if (!empty($fournisseurNom)) {
                try {
                    $checkColumns = $db->query("DESCRIBE tickets")->fetchAll(PDO::FETCH_ASSOC);
                    $columnNames = array_column($checkColumns, 'Field');
                    
                    if (!in_array('fournisseur_nom', $columnNames)) {
                        $db->exec("ALTER TABLE tickets ADD COLUMN fournisseur_nom VARCHAR(100) NULL");
                    }
                    if (!in_array('fournisseur_email', $columnNames)) {
                        $db->exec("ALTER TABLE tickets ADD COLUMN fournisseur_email VARCHAR(150) NULL");
                    }
                    
                    // On ne demande plus le prénom, on le laisse vide ou on traite juste le nom comme raison sociale
                    $stmt = $db->prepare("UPDATE tickets SET fournisseur_nom = ?, fournisseur_email = ? WHERE id = ?");
                    $result = $stmt->execute([$fournisseurNom, $fournisseurEmail, $ticketId]);
                    
                    if ($result) {
                        $details = "Fournisseur assigné: $fournisseurNom" . ($fournisseurEmail ? " ($fournisseurEmail)" : "");
                        $db->prepare("INSERT INTO historique_actions (ticket_id, action, details, utilisateur_id, date_action) VALUES (?, 'assignation_fournisseur', ?, ?, NOW())")
                           ->execute([$ticketId, $details, $_SESSION['user_id']]);
                        
                        echo "<script>alert('Fournisseur enregistré avec succès !'); window.location.href='detail_ticket.php?id=$ticketId';</script>";
                        exit;
                    }
                } catch (Exception $e) {
                    $error = "Erreur: " . $e->getMessage();
                }
            } else {
                $error = "Veuillez entrer le nom du fournisseur.";
            }
        } elseif ($action === 'enregistrer_decision_rmq') {
            $decision_rmq = cleanInput($_POST['decision_rmq'] ?? '');
            $commentaire_resolution = cleanInput($_POST['commentaire_resolution'] ?? '');
            
            if (empty($decision_rmq) || empty($commentaire_resolution)) {
                $error = "La décision RMQ et le commentaire de résolution sont obligatoires.";
            } else {
                $preuve_resolution = $ticket['preuve_resolution']; // Garder l'ancienne si pas de nouvelle
                
                // Gestion de l'upload
                if (!empty($_FILES['preuve_resolution']['name'])) {
                    $file = $_FILES['preuve_resolution'];
                    $allowedExts = ['pdf', 'jpg', 'jpeg', 'png'];
                    $allowedMime = ['application/pdf', 'image/jpeg', 'image/png'];
                    $maxSize = 5 * 1024 * 1024; // 5 Mo
                    
                    $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                    $fileMime = mime_content_type($file['tmp_name']);
                    
                    if (!in_array($fileExt, $allowedExts) || !in_array($fileMime, $allowedMime)) {
                        throw new Exception("Format de fichier non autorisé (PDF, JPG, PNG uniquement).");
                    }
                    if ($file['size'] > $maxSize) {
                        throw new Exception("Le fichier est trop volumineux (max 5 Mo).");
                    }
                    
                    $uploadDir = '../../uploads/preuves/';
                    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
                    
                    $fileName = 'preuve_' . $ticketId . '_' . time() . '.' . $fileExt;
                    if (move_uploaded_file($file['tmp_name'], $uploadDir . $fileName)) {
                        $preuve_resolution = 'preuves/' . $fileName;
                    } else {
                        throw new Exception("Erreur lors de l'upload du fichier.");
                    }
                }
                
                // Mise à jour BDD
                $stmt = $db->prepare("UPDATE tickets SET decision_rmq = ?, commentaire_resolution = ?, preuve_resolution = ?, date_decision = NOW() WHERE id = ?");
                $stmt->execute([$decision_rmq, $commentaire_resolution, $preuve_resolution, $ticketId]);
                
                // Historique
                $db->prepare("INSERT INTO historique_actions (ticket_id, action, details, utilisateur_id, date_action) VALUES (?, 'decision_rmq', 'Décision RMQ enregistrée', ?, NOW())")
                   ->execute([$ticketId, $_SESSION['user_id']]);
                
                $success = "Décision RMQ enregistrée avec succès.";
                header("Refresh: 2; url=detail_ticket.php?id=$ticketId");
            }
        
        } elseif ($action === 'save_global') {
            // 1. UPDATE STATUS & NOTES
            $newStatusId = intval($_POST['statut_id'] ?? 0);
            $notes = cleanInput($_POST['notes'] ?? '');
            $notifyClient = isset($_POST['notify_client']) && $_POST['notify_client'] == '1';
            
            $updates = ["notes = ?"];
            $params = [$notes];
            
            // Only update status if one is selected
            if ($newStatusId > 0) {
                $updates[] = "statut_id = ?";
                $params[] = $newStatusId;
                
                // Triggers logic
                $idVerif = $statutIds['En cours de vérification'] ?? 2;
                if ($newStatusId == $idVerif && empty($ticket['date_debut_traitement'])) {
                    $updates[] = "date_debut_traitement = NOW()";
                }
                $idResolu = $statutIds['Résolu'] ?? 4;
                if ($newStatusId == $idResolu) {
                    $startStr = $ticket['date_debut_traitement'] ?? $ticket['date_creation'];
                    $start = new DateTime($startStr);
                    $now = new DateTime();
                    $diff = $start->diff($now);
                    $updates[] = "duree_traitement = ?";
                    $params[] = $diff->days;
                    // Fix: Enregistrer la date de fin réelle
                    $updates[] = "date_fin_traitement = NOW()";
                }
            }
            
            // 2. UPDATE SUPPLIER
            $fournisseurNom = cleanInput($_POST['fournisseur_nom'] ?? '');
            $fournisseurEmail = cleanInput($_POST['fournisseur_email'] ?? '');
            
            // Update columns if needed
            $checkColumns = $db->query("DESCRIBE tickets")->fetchAll(PDO::FETCH_ASSOC);
            $columnNames = array_column($checkColumns, 'Field');
            if (!in_array('fournisseur_nom', $columnNames)) $db->exec("ALTER TABLE tickets ADD COLUMN fournisseur_nom VARCHAR(100) NULL");
            if (!in_array('fournisseur_email', $columnNames)) $db->exec("ALTER TABLE tickets ADD COLUMN fournisseur_email VARCHAR(150) NULL");
            
            $updates[] = "fournisseur_nom = ?";
            $params[] = $fournisseurNom;
            $updates[] = "fournisseur_email = ?";
            $params[] = $fournisseurEmail;

            // 3. UPDATE DECISION
            $decision_rmq = cleanInput($_POST['decision_rmq'] ?? '');
            $commentaire_resolution = cleanInput($_POST['commentaire_resolution'] ?? '');
            
            if (!empty($decision_rmq)) {
                $updates[] = "decision_rmq = ?";
                $params[] = $decision_rmq;
                $updates[] = "date_decision = NOW()";
            }
            if (!empty($commentaire_resolution)) {
                $updates[] = "commentaire_resolution = ?";
                $params[] = $commentaire_resolution;
            }
            
            // Upload proof
            if (!empty($_FILES['preuve_resolution']['name'])) {
                $file = $_FILES['preuve_resolution'];
                $allowedExts = ['pdf', 'jpg', 'jpeg', 'png'];
                $allowedMime = ['application/pdf', 'image/jpeg', 'image/png'];
                if (in_array(strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)), $allowedExts) 
                    && in_array(mime_content_type($file['tmp_name']), $allowedMime) 
                    && $file['size'] <= 5*1024*1024) {
                    
                    $uploadDir = '../../uploads/preuves/';
                    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
                    $fileName = 'preuve_' . $ticketId . '_' . time() . '.' . strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                    if (move_uploaded_file($file['tmp_name'], $uploadDir . $fileName)) {
                        $updates[] = "preuve_resolution = ?";
                        $params[] = 'preuves/' . $fileName;
                    }
                }
            }

            // EXECUTE GLOBAL UPDATE
            $params[] = $ticketId;
            $sql = "UPDATE tickets SET " . implode(', ', $updates) . " WHERE id = ?";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);

            // LOGGING
            $logDetails = "Mise à jour globale sauvegarde.";
            if ($newStatusId > 0 && $newStatusId != $ticket['statut_id']) {
                $newStatusName = $statutNames[$newStatusId] ?? 'Inconnu';
                $logDetails .= " Statut changé vers: $newStatusName.";
            }
            if ($notifyClient) $logDetails .= " (Client notifié)";
            
            $db->prepare("INSERT INTO historique_actions (ticket_id, action, details, utilisateur_id, date_action) VALUES (?, 'global_save', ?, ?, NOW())")
               ->execute([$ticketId, $logDetails, $_SESSION['user_id']]);

            // NOTIFICATION
            if ($notifyClient && !empty($ticket['client_email']) && $newStatusId > 0) {
                 $emailManager = new EmailManager($db);
                 $stmtSn = $db->prepare("SELECT nom FROM statuts WHERE id = ?");
                 $stmtSn->execute([$newStatusId]);
                 $realStatusName = $stmtSn->fetchColumn();
                 $emailManager->envoyerChangementStatut($ticket, $ticket['client_email'], $realStatusName);
            }

            $success = "Toutes les informations ont été enregistrées avec succès !";
            header("Refresh: 1; url=detail_ticket.php?id=$ticketId");
            exit;
        } elseif ($action === 'confirmer_envoi_email') {
            $emailManager = new EmailManager($db);
            
            // On vérifie le statut du ticket pour savoir quel email envoyer
            $idResolu = $statutIds['Résolu'] ?? 4;
            $idRefuse = $statutIds['Refusé'] ?? 8;
            $idRejete = $statutIds['Rejeté'] ?? 5;
            
            $sent = false;
            if ($ticket['statut_id'] == $idRefuse || $ticket['statut_id'] == $idRejete) {
                // Email Refus
                $motif = $ticket['decision_rmq'] ?: $ticket['notes'] ?: "Dossier clôturé par le gestionnaire.";
                $sent = $emailManager->envoyerEmailRefus($ticket, $motif);
            } else {
                // Email Validation / Autre statut
                $sent = $emailManager->envoyerChangementStatut($ticket, $ticket['client_email'], $ticket['statut_nom']);
            }
            
            if ($sent) {
                $db->prepare("UPDATE tickets SET email_client_confirme = 1 WHERE id = ?")->execute([$ticketId]);
                $db->prepare("INSERT INTO historique_actions (ticket_id, action, details, utilisateur_id, date_action) VALUES (?, 'email_confirmation', 'Envoi de l\'email au client confirmé par le RMQ', ?, NOW())")->execute([$ticketId, $_SESSION['user_id']]);
                $success = "Email envoyé au client avec succès !";
            } else {
                throw new Exception("L'envoi de l'email a échoué.");
            }
            header("Refresh: 2; url=detail_ticket.php?id=$ticketId");
        }

    } catch (Exception $e) {
        $error = "Erreur: " . $e->getMessage();
    }
}

// Récupérer les données
$piecesJointes = getPiecesJointes($ticketId);
$historique = getHistoriqueTicket($ticketId);
$fournisseurs = getUsersByRole('fournisseur');
$statuts = getStatuts();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Détail Ticket - RMQ</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/fontawesome/css/all.min.css">
    <style>
        /* ========== STYLES RÉORGANISÉS ========== */
        * { box-sizing: border-box; }
        
        body { 
            background: #f5f7fa; 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
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
            border-left: 4px solid #8b5cf6;
            background: linear-gradient(to right, #f8f6ff 0%, #ffffff 100%);
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
            color: #8b5cf6;
            font-weight: 700;
            font-size: 1rem;
        }
        
        .btn-group {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
            align-items: center;
            margin-top: 1.5rem;
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
        }
        
        .btn-primary { background: #3b82f6; color: white; }
        .btn-primary:hover { background: #2563eb; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3); }
        
        .btn-success { background: #10b981; color: white; }
        .btn-success:hover { background: #059669; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3); }
        
        .btn-danger { background: #ef4444; color: white; }
        .btn-danger:hover { background: #dc2626; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3); }
        
        .btn-secondary { background: #8b5cf6; color: white; }
        .btn-secondary:hover { background: #7c3aed; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(139, 92, 246, 0.3); }
        
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
            border-color: #8b5cf6;
            box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1);
        }
        
        textarea.form-input {
            resize: vertical;
            min-height: 100px;
        }
        
        @media (max-width: 768px) {
            .grid-2col, .grid-3col {
                grid-template-columns: 1fr;
            }
            
            .page-title {
                font-size: 1.4rem;
            }
            
            .section-title {
                font-size: 1.05rem;
            }
            
            .card-section {
                padding: 1.2rem;
            }
            
            .btn-group {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
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
        
        .history-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .history-table th {
            background: #f9fafb;
            padding: 0.75rem;
            text-align: left;
            font-weight: 700;
            font-size: 0.85rem;
            color: #6b7280;
            border-bottom: 2px solid #e5e7eb;
        }
        
        .history-table td {
            padding: 0.75rem;
            border-bottom: 1px solid #e5e7eb;
            font-size: 0.9rem;
        }
        
        .history-table tbody tr:hover {
            background: #f9fafb;
        }
    </style>
</head>
<body style="background: #f3f4f6;">

<!-- Navbar RMQ -->
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

<div class="page-container" style="margin-top: 2rem; margin-bottom: 3rem;">
    
    <!-- Breadcrumb -->
    <div style="margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center;">
        <a href="dashboard.php" style="display: inline-block; text-decoration: none; color: #6b7280; font-weight: 500; transition: all 0.2s;">
            <i class="fas fa-arrow-left"></i> Retour au Dashboard
        </a>
        <div style="display: flex; gap: 10px;">
            <button onclick="window.print()" class="btn btn-secondary" style="background: #6b7280; padding: 0.5rem 1rem; font-size: 0.9rem;">
                <i class="fas fa-print"></i> Imprimer
            </button>
            <button onclick="submitGlobal()" class="btn btn-primary" style="background: #4b5563; padding: 0.5rem 1rem; font-size: 0.9rem;">
                <i class="fas fa-save"></i> Enregistrer tout
            </button>
        </div>
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

    <!-- ========== HEADER TICKET ========== -->
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
                        Code Client: <span style="color: #111;"><?php echo htmlspecialchars($ticket['code_client'] ?? 'Non défini'); ?></span>
                    </p>
                    <p style="margin: 0; color: #6b7280; font-size: 0.95rem;">
                        <i class="fas fa-calendar-alt" style="margin-right: 0.25rem;"></i> 
                        Créé le: <?php echo date('d/m/Y', strtotime($ticket['date_creation'])); ?>
                    </p>
                </div>
            </div>
            <div class="btn-group" style="margin-top: 0; gap: 0.5rem;">
                <?php if ($showValidationButtons): ?>
                    <!-- Bouton Valider (Header) -->
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="valider_ticket">
                        <button type="submit" class="btn btn-success" style="padding: 0.5rem 1rem; font-size: 0.9rem;" onclick="return confirm('Valider cette réclamation ?');">
                            <i class="fas fa-check"></i> Valider
                        </button>
                    </form>

                    <!-- Bouton Refuser (Header) -->
                    <a href="refuse_claim.php?id=<?php echo $ticketId; ?>" class="btn btn-danger" style="padding: 0.5rem 1rem; font-size: 0.9rem;">
                        <i class="fas fa-times"></i> Refuser
                    </a>
                <?php endif; ?>

                <a href="edit_claim.php?id=<?php echo $ticketId; ?>" class="btn btn-secondary" style="padding: 0.5rem 1rem; font-size: 0.9rem;">
                    <i class="fas fa-edit"></i> Modifier
                </a>
            </div>
        </div>
    </div>

    <!-- ========== SECTION INFORMATIONS RÉCLAMATION ========== -->
    <div class="card-section">
        <h3 class="section-title">
            <i class="fas fa-info-circle"></i> Informations de la Réclamation
        </h3>
        
        <div class="grid-2col">
            <!-- Sujet supprimé car identique à Description -->
            
            <div class="info-row">
                <span class="info-label">Catégorie</span>
                <span class="info-value">
                    <?php 
                    if ($ticket['type_ticket'] === 'produit,service') {
                        echo '<span style="background: #fef3c7; color: #92400e; padding: 0.3rem 0.7rem; border-radius: 4px; font-size: 0.85rem;">Produit & Service</span>';
                    } elseif ($ticket['type_ticket'] === 'produit') {
                        echo '<span style="background: #dbeafe; color: #1e40af; padding: 0.3rem 0.7rem; border-radius: 4px; font-size: 0.85rem;">Produit</span>';
                    } elseif ($ticket['type_ticket'] === 'service') {
                        echo '<span style="background: #dcfce7; color: #166534; padding: 0.3rem 0.7rem; border-radius: 4px; font-size: 0.85rem;">Service</span>';
                    }
                    ?>
                </span>
            </div>
        </div>
        
        <div class="info-row" style="margin-top: 1rem;">
            <span class="info-label">Description</span>
            <div style="background: #f9fafb; padding: 1rem; border-radius: 8px; border: 1px solid #e5e7eb; font-size: 1rem; color: #8b5cf6; font-weight: 700; line-height: 1.6;">
                <?php echo nl2br(htmlspecialchars($ticket['description'])); ?>
            </div>
        </div>
        
        <div style="margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid #e5e7eb;">
            <div class="info-row">
                <span class="info-label">Date de Création</span>
                <span class="info-value">
                    <i class="fas fa-calendar"></i> 
                    <?php echo date('d/m/Y H:i', strtotime($ticket['date_creation'])); ?>
                </span>
            </div>
        </div>
    </div>

    <!-- ========== SECTION PARTIES IMPLIQUÉES ========== -->
    <div class="card-section">
        <h3 class="section-title">
            <i class="fas fa-users"></i> Parties Impliquées
        </h3>
        
        <div class="grid-3col">
            <!-- CLIENT -->
            <div style="padding: 1rem; background: #f0f9ff; border-radius: 8px; border: 1px solid #bae6fd;">
                <h4 class="subsection-title" style="color: #0369a1;">
                    <i class="fas fa-user-circle"></i> Client
                </h4>
                <div class="info-row">
                    <span class="info-label">Nom</span>
                    <span class="info-value"><?php echo htmlspecialchars($ticket['nom_client']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Prénom</span>
                    <span class="info-value"><?php echo htmlspecialchars($ticket['prenom_client']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Email</span>
                    <span class="info-value" style="word-break: break-all; font-size: 0.85rem;"><?php echo htmlspecialchars($ticket['email_client']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Téléphone</span>
                    <span class="info-value"><?php echo htmlspecialchars($ticket['telephone_client']); ?></span>
                </div>
            </div>
            
            <!-- FOURNISSEUR -->
            <div style="padding: 1rem; background: #fef2f2; border-radius: 8px; border: 1px solid #fecaca;">
                <h4 class="subsection-title" style="color: #7f1d1d;">
                    <i class="fas fa-industry"></i> Fournisseur
                </h4>
                <?php if (!empty($ticket['fournisseur_nom'])): ?>
                    <div class="info-row">
                        <span class="info-label">Nom du Fournisseur</span>
                        <span class="info-value"><?php echo htmlspecialchars($ticket['fournisseur_nom']); ?></span>
                    </div>
                    <?php if (!empty($ticket['fournisseur_email'])): ?>
                    <div class="info-row">
                        <span class="info-label">Email</span>
                        <span class="info-value"><?php echo htmlspecialchars($ticket['fournisseur_email']); ?></span>
                    </div>
                    <?php endif; ?>
                <?php else: ?>
                    <p style="color: #991b1b; font-style: italic; margin: 0; font-size: 0.9rem;">
                        <i class="fas fa-info-circle"></i> Aucun fournisseur assigné
                    </p>
                <?php endif; ?>
            </div>
            
            <!-- TRANSPORTEUR -->
            <div style="padding: 1rem; background: #f5f3ff; border-radius: 8px; border: 1px solid #ddd6fe;">
                <h4 class="subsection-title" style="color: #6d28d9;">
                    <i class="fas fa-truck"></i> Transporteur
                </h4>
                <div class="info-row">
                    <span class="info-label">Transporteur</span>
                    <span class="info-value"><?php echo htmlspecialchars($ticket['societe_transport'] ?? 'N/A'); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Chauffeur</span>
                    <span class="info-value"><?php echo htmlspecialchars($ticket['chauffeur'] ?? 'N/A'); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Date Livraison</span>
                    <span class="info-value">
                        <?php echo $ticket['date_livraison'] ? date('d/m/Y', strtotime($ticket['date_livraison'])) : 'N/A'; ?>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- ========== SECTION TRAITEMENT RMQ ========== -->
    <div class="card-section accent">
        <h3 class="section-title">
            <i class="fas fa-cogs"></i> Traitement RMQ
        </h3>
        
        <form id="form-rmq" method="POST" onsubmit="return confirm('Êtes-vous sûr ?');">
            <input type="hidden" name="action" value="update_status">
            <input type="hidden" id="notifyInput" name="notify_client" value="0">
            
            <div class="grid-2col">
                <!-- Statut -->
                <div class="info-row">
                    <label class="info-label" for="statut_id">
                        <i class="fas fa-flag"></i> Statut du Ticket *
                    </label>
                    <select name="statut_id" id="statut_id" class="form-input" required>
                        <option value="">-- Sélectionner un statut --</option>
                        <?php 
                            $statusRMQ = ['En cours de vérification', 'En cours de traitement', 'En attente réponse fournisseur', 'Résolu', 'Refusé'];
                            foreach ($statutIds as $nom => $id) {
                                if (in_array($nom, $statusRMQ)) {
                                    $selected = ($ticket['statut_id'] == $id) ? 'selected' : '';
                                    echo "<option value='$id' $selected>" . htmlspecialchars($nom) . "</option>";
                                }
                            }
                        ?>
                    </select>
                </div>
            </div>
            
            <!-- Notes -->
            <div class="info-row" style="margin-top: 1.5rem;">
                <label class="info-label" for="notes">
                    <i class="fas fa-sticky-note"></i> Notes
                </label>
                <textarea name="notes" id="notes" class="form-input" placeholder="Ajoutez vos notes ici..."><?php echo htmlspecialchars($ticket['notes'] ?? ''); ?></textarea>
            </div>
            
            <!-- Boutons -->
            <div class="btn-group" style="margin-top: 1.5rem;">
                <button type="button" onclick="document.getElementById('notifyInput').value='0'; this.form.submit();" class="btn btn-primary">
                    <i class="fas fa-save"></i> Enregistrer
                </button>
                <button type="button" onclick="document.getElementById('notifyInput').value='1'; this.form.submit();" class="btn btn-success">
                    <i class="fas fa-paper-plane"></i> Enregistrer & Notifier
                </button>
            </div>
        </form>
    </div>

    <!-- ========== SECTION FOURNISSEUR (ASSIGNATION MANUELLE) ========== -->
    <div class="card-section">
        <h3 class="section-title">
            <i class="fas fa-industry"></i> Assignation du Fournisseur
        </h3>
        
        <form id="form-fournisseur" method="POST" onsubmit="return confirm('Enregistrer ce fournisseur ?');">
            <input type="hidden" name="action" value="assigner_fournisseur">
            
            <div style="display: flex; align-items: flex-end; gap: 1.5rem; flex-wrap: wrap;">
                <!-- Nom / Raison Sociale -->
                <div style="flex: 1; min-width: 250px;">
                    <label class="info-label" for="fournisseur_nom">
                        <i class="fas fa-building"></i> Nom du Fournisseur *
                    </label>
                    <input type="text" name="fournisseur_nom" id="fournisseur_nom" class="form-input" 
                           placeholder="Ex: Société Example SARL"
                           value="<?php echo htmlspecialchars($ticket['fournisseur_nom'] ?? ''); ?>"
                           required>
                </div>
                
                <!-- Email -->
                <div style="flex: 1; min-width: 250px;">
                    <label class="info-label" for="fournisseur_email">
                        <i class="fas fa-envelope"></i> Email du Fournisseur
                    </label>
                    <input type="email" name="fournisseur_email" id="fournisseur_email" class="form-input" 
                           placeholder="contact@fournisseur.com"
                           value="<?php echo htmlspecialchars($ticket['fournisseur_email'] ?? ''); ?>">
                </div>
                
                <!-- Bouton -->
                <button type="submit" class="btn btn-secondary">
                    <i class="fas fa-save"></i> Enregistrer
                </button>
            </div>
        </form>
    </div>

    <!-- ========== SECTION DÉCISION RMQ ET PREUVE DE RÉSOLUTION ========== -->
    <div class="card-section" style="border-top: 4px solid #FF6B35;">
        <h3 class="section-title">
            <i class="fas fa-gavel"></i> Décision RMQ et preuve de résolution
        </h3>
        
        <form id="form-decision" method="POST" enctype="multipart/form-data" onsubmit="return confirm('Enregistrer la décision finale ?');">
            <input type="hidden" name="action" value="enregistrer_decision_rmq">
            <input type="hidden" id="globalAction" name="" value="">
            
            <div class="info-row">
                <label class="info-label" for="decision_rmq">
                    <i class="fas fa-balance-scale"></i> Décision RMQ *
                </label>
                <textarea name="decision_rmq" id="decision_rmq" class="form-input" required 
                          placeholder="Saisissez ici la décision finale concernant cette réclamation..."><?php echo htmlspecialchars($ticket['decision_rmq'] ?? ''); ?></textarea>
            </div>

            <div style="background: #fdf6ec; padding: 20px; border-radius: 12px; margin-top: 1.5rem; border: 1px solid #ffe8cc;">
                <h4 class="subsection-title" style="color: #e67e22; margin-top: 0;">
                    <i class="fas fa-check-double"></i> Preuve de résolution
                </h4>
                
                <div class="grid-2col">
                    <!-- Upload Preuve -->
                    <div class="info-row">
                        <label class="info-label" for="preuve_resolution">
                            <i class="fas fa-file-upload"></i> Joindre une preuve (PDF, JPG, PNG - Max 5Mo)
                        </label>
                        <input type="file" name="preuve_resolution" id="preuve_resolution" class="form-input" accept=".pdf,.jpg,.jpeg,.png">
                        
                        <?php if (!empty($ticket['preuve_resolution'])): ?>
                            <div style="margin-top: 10px;">
                                <a href="../../uploads/<?php echo htmlspecialchars($ticket['preuve_resolution']); ?>" target="_blank" class="btn btn-secondary" style="padding: 0.4rem 0.8rem; font-size: 0.85rem;">
                                    <i class="fas fa-download"></i> Voir la preuve actuelle
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Commentaire Résolution -->
                    <div class="info-row">
                        <label class="info-label" for="commentaire_resolution">
                            <i class="fas fa-comment-dots"></i> Commentaire de résolution *
                        </label>
                        <textarea name="commentaire_resolution" id="commentaire_resolution" class="form-input" required 
                                  placeholder="Décrivez les actions menées pour résoudre ce dossier..."><?php echo htmlspecialchars($ticket['commentaire_resolution'] ?? ''); ?></textarea>
                    </div>
                </div>
            </div>
            
            <div class="btn-group" style="margin-top: 2rem; justify-content: flex-end;">
                <button type="submit" class="btn btn-primary" style="background: #FF6B35;">
                    <i class="fas fa-save"></i> Enregistrer la décision RMQ
                </button>
            </div>
        </form>
    </div>

    <?php 
    // ========== SECTION CONFIRMATION EMAIL (UNIQUEMENT COMMERCIAL NON CONFIRMÉ) ==========
    $isFromCommercial = ($ticket['created_by_role'] === 'commercial' || (isset($ticket['cree_par_commercial']) && $ticket['cree_par_commercial'] == 1));
    $emailNotSentYet = (isset($ticket['email_client_confirme']) && $ticket['email_client_confirme'] == 0);
    $hasDecisionBeenMade = ($ticket['statut_id'] != 1 && $ticket['statut_id'] != 6); // Pas Nouveau ou En attente validation

    if ($isFromCommercial && $emailNotSentYet && $hasDecisionBeenMade): 
    ?>
    <div class="card-section" style="border: 2px solid #10b981; background: #f0fdf4;">
        <h3 class="section-title" style="color: #047857; border-bottom-color: #d1fae5;">
            <i class="fas fa-envelope-open-text"></i> Confirmation d'envoi client
        </h3>
        <p style="color: #065f46; margin-bottom: 1.5rem; font-size: 0.95rem;">
            Cette réclamation a été créée par un <strong>Commercial</strong>. Le client n'a reçu aucune notification automatique jusqu'à présent.<br>
            Veuillez confirmer l'envoi de l'email détaillant votre décision (Validation ou Refus) au client.
        </p>
        
        <form method="POST" onsubmit="return confirm('Confirmer l\'envoi de l\'email au client ?');">
            <input type="hidden" name="action" value="confirmer_envoi_email">
            <div style="text-align: right;">
                <button type="submit" class="btn btn-success" style="padding: 1rem 2rem; font-size: 1.1rem; box-shadow: 0 10px 15px -3px rgba(16, 185, 129, 0.2);">
                    <i class="fas fa-paper-plane"></i> Confirmer l’envoi de l’email au client
                </button>
            </div>
        </form>
    </div>
    <?php endif; ?>
    
    <script>
    function submitGlobal() {
        if (!confirm('Voulez-vous enregistrer toutes les sections (Statut, Fournisseur, Décision) ?')) return;

        var formDecision = document.getElementById('form-decision');
        
        // Copier les valeurs des autres formulaires vers formDecision
        var inputsMsg = ['statut_id', 'notes', 'notify_client'];
        inputsMsg.forEach(function(name) {
             var el = document.querySelector('#form-rmq [name="'+name+'"]');
             if(el) {
                 var val = el.value;
                 // Handle checkbox separately? No, select works, textarea works.
                 // Notify Checkbox: removed in this version, it's a hidden input in #form-rmq updated by button click.
                 // But wait, the top button doesn't trigger "Enregistrer & Notifier", it likely defaults to... no notification?
                 // Or we let user choose? 
                 // Simple logic based on previous global form: notify_client defaults to 0 or checkbox.
                 // In form-rmq, notify is hidden. We'll default to 0 for global save unless we add a specific global checkbox.
                 var input = document.createElement('input');
                 input.type = 'hidden';
                 input.name = name;
                 input.value = val;
                 formDecision.appendChild(input);
             }
        });

        var inputsFourn = ['fournisseur_nom', 'fournisseur_email'];
        inputsFourn.forEach(function(name) {
             var el = document.querySelector('#form-fournisseur [name="'+name+'"]');
             if(el) {
                 var input = document.createElement('input');
                 input.type = 'hidden';
                 input.name = name;
                 input.value = el.value;
                 formDecision.appendChild(input);
             }
        });

        // Set action to save_global
        var actionInput = formDecision.querySelector('input[name="action"]');
        if(actionInput) actionInput.value = 'save_global';

        // Bypass 'required' check on Decision form if we are just saving status?
        // No, 'save_global' implies saving everything.
        // However, HTML5 'required' attribute on decision form might block submission if decision is empty.
        // If user wants to save "Status" globally but hasn't filled Decision...
        // The previous Global Form had 'required' on Decision fields. So user MUST fill Decision to use Global Save.
        // This is consistent.
        
        formDecision.submit();
    }
    </script>

    <!-- NEW: TRAITEMENT AQF (Read Only for RMQ) -->
    <?php if (!empty($ticket['statut_aqf'])): ?>
    <div class="card-section" style="border-left: 5px solid #3b82f6;">
        <h3 class="section-title">
            <i class="fas fa-microchip"></i> Qualité (AQF)
        </h3>
        
        <div style="background: #f8fafc; padding: 1.25rem; border-radius: 12px; border: 1px solid #e2e8f0; margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center;">
            <div>
                <p class="info-label" style="margin-bottom: 0.25rem;">Verdict Qualité</p>
                <p class="info-value" style="color: <?php echo ($ticket['statut_aqf'] === 'retenu') ? '#10B981' : (($ticket['statut_aqf'] === 'non retenu') ? '#EF4444' : '#3B82F6'); ?>; text-transform: uppercase; font-weight: 800;">
                    ● <?php echo htmlspecialchars($ticket['statut_aqf']); ?>
                </p>
            </div>
            <div style="text-align: right; font-size: 0.75rem; color: #94A3B8; font-weight: 600;">
                Traitée le : <?php echo date('d/m/Y H:i', strtotime($ticket['date_traitement_aqf'])); ?>
            </div>
        </div>

        <?php if ($ticket['statut_aqf'] === 'non retenu' && !empty($ticket['raison_non_retenu_aqf'])): ?>
            <div style="background: #FFF1F2; border-left: 4px solid #EF4444; padding: 1.25rem; border-radius: 0 8px 8px 0; margin-bottom: 1.5rem;">
                <p class="info-label" style="color: #BE123C; margin-bottom: 0.4rem;">Motif du rejet</p>
                <p style="color: #9F1239; font-size: 0.9rem; font-weight: 500;"><?php echo nl2br(htmlspecialchars($ticket['raison_non_retenu_aqf'])); ?></p>
            </div>
        <?php endif; ?>

        <div style="margin-bottom: 1.5rem;">
            <p class="info-label" style="margin-bottom: 0.5rem;">Commentaire de l'expert AQF</p>
            <p style="color: #334155; line-height: 1.6; font-size: 0.95rem; font-weight: 500;">
                <?php echo nl2br(htmlspecialchars($ticket['commentaire_resolution_aqf'])); ?>
            </p>
        </div>

        <?php if (!empty($ticket['preuve_resolution_aqf'])): ?>
            <a href="../../uploads/<?php echo htmlspecialchars($ticket['preuve_resolution_aqf']); ?>" target="_blank" class="btn btn-secondary" style="padding: 0.5rem 1rem; font-size: 0.85rem; background: white; border: 1px solid #E2E8F0; color: #3B82F6;">
                <i class="fas fa-file-pdf"></i> Voir le document d'expertise AQF
            </a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- ========== SECTION PIÈCES JOINTES ========== -->
    <div class="card-section">
        <h3 class="section-title">
            <i class="fas fa-paperclip"></i> Pièces Jointes
        </h3>
        
        <?php if (count($piecesJointes) > 0): ?>
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 1rem;">
                <?php foreach($piecesJointes as $pj): ?>
                    <a href="../../uploads/<?php echo htmlspecialchars($pj['chemin_fichier']); ?>" target="_blank" 
                       style="padding: 1rem; background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; text-decoration: none; color: #374151; text-align: center; transition: all 0.2s;"
                       onmouseover="this.style.background='#f3f4f6'; this.style.boxShadow='0 2px 8px rgba(0,0,0,0.1)';"
                       onmouseout="this.style.background='#f9fafb'; this.style.boxShadow='none';">
                        <i class="fas fa-file" style="font-size: 1.5rem; color: #8b5cf6; display: block; margin-bottom: 0.5rem;"></i>
                        <span style="font-size: 0.85rem; font-weight: 600; word-break: break-word;">
                            <?php echo htmlspecialchars($pj['nom_fichier']); ?>
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p style="text-align: center; color: #6b7280; font-style: italic;">
                <i class="fas fa-inbox"></i> Aucune pièce jointe
            </p>
        <?php endif; ?>
    </div>

    <!-- ========== SECTION HISTORIQUE ========== -->
    <div class="card-section">
        <h3 class="section-title">
            <i class="fas fa-history"></i> Historique des Actions
        </h3>
        
        <?php if (count($historique) > 0): ?>
            <div style="overflow-x: auto;">
                <table class="history-table">
                    <thead>
                        <tr>
                            <th style="width: 20%;">Date</th>
                            <th style="width: 20%;">Action</th>
                            <th style="width: 40%;">Détails</th>
                            <th style="width: 20%;">Utilisateur</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($historique as $action): ?>
                            <tr>
                                <td><?php echo date('d/m/Y H:i', strtotime($action['date_action'])); ?></td>
                                <td>
                                    <span style="background: #e0e7ff; color: #6366f1; padding: 0.2rem 0.6rem; border-radius: 4px; font-size: 0.85rem; font-weight: 600;">
                                        <?php echo htmlspecialchars($action['action']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($action['details']); ?></td>
                                <td><?php echo htmlspecialchars($action['utilisateur_id']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p style="text-align: center; color: #6b7280; font-style: italic;">
                <i class="fas fa-history"></i> Aucun historique
            </p>
        <?php endif; ?>
    </div>

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

</div>

<script>
    // Mobile menu toggle
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
