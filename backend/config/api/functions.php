<?php
/**
 * Fonctions Utilitaires
 * Fichier: api/functions.php
 */



require_once __DIR__ . '/../config/database.php';

/**
 * Générer un numéro de ticket unique
 */
function genererNumeroTicket() {
    $db = getDB();
    
    $annee = date('Y');
    $mois = date('m');
    
    // Compter les tickets du mois en cours
    $stmt = $db->prepare("
        SELECT COUNT(*) as total 
        FROM tickets 
        WHERE YEAR(date_creation) = ? 
        AND MONTH(date_creation) = ?
    ");
    $stmt->execute([$annee, $mois]);
    $result = $stmt->fetch();
    
    $compteur = $result['total'] + 1;
    
    // Format: TK-YYYYMM-0001
    return sprintf('TK-%s%s-%04d', $annee, $mois, $compteur);
}

/**
 * Créer un nouveau ticket
 */
function creerTicket($data) {
    $db = getDB();
    
    try {
        $db->beginTransaction();
        
        // Générer le numéro de ticket
        $numeroTicket = genererNumeroTicket();
        
        // Insérer le ticket
        $stmt = $db->prepare("
            INSERT INTO tickets (
                numero_ticket, nom_client, prenom_client, email_client, 
                telephone_client, sujet, description, categorie_id, 
                statut_id
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)
        ");
        
        $stmt->execute([
            $numeroTicket,
            $data['nom'],
            $data['prenom'],
            $data['email'],
            $data['telephone'] ?? null,
            $data['sujet'],
            $data['description'],
            $data['categorie'],
        ]);
        
        $ticketId = $db->lastInsertId();
        
        // Ajouter une entrée dans l'historique
        $stmt = $db->prepare("
            INSERT INTO historique_actions (ticket_id, action, details, nouveau_statut_id)
            VALUES (?, 'Ticket créé', 'Réclamation soumise par le client', 1)
        ");
        $stmt->execute([$ticketId]);
        
        $db->commit();
        
        return [
            'success' => true,
            'ticket_id' => $ticketId,
            'numero_ticket' => $numeroTicket
        ];
        
    } catch(PDOException $e) {
        $db->rollBack();
        return [
            'success' => false,
            'error' => 'Erreur lors de la création du ticket: ' . $e->getMessage()
        ];
    }
}

/**
 * Upload de fichier
 */
function uploadFichier($file, $ticketId) {
    $uploadDir = __DIR__ . '/../uploads/';
    
    // Créer le dossier si nécessaire
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    // Vérifier le fichier
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'Erreur lors de l\'upload'];
    }
    
    // Vérifier la taille (max 5MB)
    if ($file['size'] > 5 * 1024 * 1024) {
        return ['success' => false, 'error' => 'Fichier trop volumineux (max 5MB)'];
    }
    
    // Extensions autorisées
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx', 'txt'];
    $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($fileExtension, $allowedExtensions)) {
        return ['success' => false, 'error' => 'Type de fichier non autorisé'];
    }
    
    // Générer un nom unique
    $newFileName = uniqid() . '_' . basename($file['name']);
    $targetPath = $uploadDir . $newFileName;
    
    // Déplacer le fichier
    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        // Enregistrer en base de données
        $db = getDB();
        $stmt = $db->prepare("
            INSERT INTO pieces_jointes (ticket_id, nom_fichier, chemin_fichier, taille_fichier, type_mime)
            VALUES (?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $ticketId,
            $file['name'],
            $newFileName,
            $file['size'],
            $file['type']
        ]);
        
        return ['success' => true, 'filename' => $newFileName];
    }
    
    return ['success' => false, 'error' => 'Erreur lors de l\'enregistrement'];
}

/**
 * Récupérer un ticket par son numéro
 */
function getTicketByNumero($numero) {
    $db = getDB();
    
    $stmt = $db->prepare("
        SELECT t.*, 
               c.nom as categorie_nom, c.couleur as categorie_couleur,
               s.nom as statut_nom, s.couleur as statut_couleur
        FROM tickets t
        LEFT JOIN categories c ON t.categorie_id = c.id
        LEFT JOIN statuts s ON t.statut_id = s.id
        WHERE t.numero_ticket = ?
    ");
    
    $stmt->execute([$numero]);
    return $stmt->fetch();
}

/**
 * Récupérer l'historique d'un ticket
 */
function getHistoriqueTicket($ticketId) {
    $db = getDB();
    
    $stmt = $db->prepare("
        SELECT h.*, 
               CONCAT(u.prenom, ' ', u.nom) as utilisateur_nom,
               s1.nom as ancien_statut_nom,
               s2.nom as nouveau_statut_nom
        FROM historique_actions h
        LEFT JOIN utilisateurs u ON h.utilisateur_id = u.id
        LEFT JOIN statuts s1 ON h.ancien_statut_id = s1.id
        LEFT JOIN statuts s2 ON h.nouveau_statut_id = s2.id
        WHERE h.ticket_id = ?
        ORDER BY h.date_action DESC
    ");
    
    $stmt->execute([$ticketId]);
    return $stmt->fetchAll();
}

/**
 * Récupérer les commentaires d'un ticket
 */
function getCommentairesTicket($ticketId, $typeVisible = null) {
    $db = getDB();
    
    $sql = "
        SELECT c.*, CONCAT(u.prenom, ' ', u.nom) as auteur_nom
        FROM commentaires c
        LEFT JOIN utilisateurs u ON c.utilisateur_id = u.id
        WHERE c.ticket_id = ?
    ";
    
    if ($typeVisible) {
        $sql .= " AND c.type = ?";
        $stmt = $db->prepare($sql . " ORDER BY c.date_creation ASC");
        $stmt->execute([$ticketId, $typeVisible]);
    } else {
        $stmt = $db->prepare($sql . " ORDER BY c.date_creation ASC");
        $stmt->execute([$ticketId]);
    }
    
    return $stmt->fetchAll();
}

/**
 * Récupérer les pièces jointes d'un ticket
 */
function getPiecesJointes($ticketId) {
    $db = getDB();
    
    $stmt = $db->prepare("
        SELECT * FROM pieces_jointes
        WHERE ticket_id = ?
        ORDER BY date_upload DESC
    ");
    
    $stmt->execute([$ticketId]);
    return $stmt->fetchAll();
}

/**
 * Récupérer toutes les catégories
 */
function getCategories() {
    $db = getDB();
    $stmt = $db->query("SELECT * FROM categories ORDER BY nom");
    return $stmt->fetchAll();
}

/**
 * Récupérer tous les statuts
 */
function getStatuts() {
    $db = getDB();
    $stmt = $db->query("SELECT * FROM statuts ORDER BY ordre");
    return $stmt->fetchAll();
}

/**
 * Envoyer un email (fonction simplifiée)
 */
function envoyerEmail($to, $subject, $message) {
    // Pour le développement, on log juste l'email
    // En production, utiliser PHPMailer ou mail()
    
    $logFile = __DIR__ . '/../logs/emails.log';
    $logDir = dirname($logFile);
    
    if (!file_exists($logDir)) {
        mkdir($logDir, 0777, true);
    }
    
    $logContent = sprintf(
        "[%s] TO: %s | SUBJECT: %s | MESSAGE: %s\n",
        date('Y-m-d H:i:s'),
        $to,
        $subject,
        $message
    );
    
    file_put_contents($logFile, $logContent, FILE_APPEND);
    
    return true;
}

/**
 * Formater une date
 */
function formatDate($date, $format = 'd/m/Y à H:i') {
    if (!$date) return 'N/A';
    $timestamp = strtotime($date);
    return date($format, $timestamp);
}

/**
 * Nettoyer une chaîne
 */
function cleanInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

/**
 * Vérifier si l'utilisateur est connecté
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * Vérifier si l'utilisateur est admin
 */
function isAdmin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

/**
 * Rediriger si non connecté
 */
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}
function get_header() {
    require_once __DIR__ . "/../includes/header.php";
}

function get_footer() {
    require_once __DIR__ . "/../includes/footer.php";
}
?>