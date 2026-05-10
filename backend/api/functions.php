<?php
/**
 * Fonctions Utilitaires - Adapté pour table utilisateurs
 * Fichier: api/functions.php

 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/EmailManager.php';
require_once __DIR__ . '/functions_extended.php';

/**
 * Générer un numéro de ticket unique
 */
function genererNumeroTicket() {
    $db = getDB();
   
    $annee = date('Y');
   
    // Chercher le numéro de ticket maximum pour l'année en cours
    // Format attendu: TKT-YYYY-XXXX
    $stmt = $db->prepare("
        SELECT numero_ticket 
        FROM tickets 
        WHERE numero_ticket LIKE ? 
        ORDER BY numero_ticket DESC 
        LIMIT 1
    ");
    $stmt->execute(["TKT-$annee-%"]);
    $result = $stmt->fetch();
   
    if ($result) {
        // Extraire la partie numérique du dernier numéro (ex: TKT-2026-0002 -> 2)
        $parts = explode('-', $result['numero_ticket']);
        $dernierCompteur = intval(end($parts));
        $compteur = $dernierCompteur + 1;
    } else {
        $compteur = 1;
    }
   
    return sprintf('TKT-%s-%04d', $annee, $compteur);
}

/**
 * Générer un mot de passe aléatoire sécurisé
 */
function genererMotDePasseAleatoire($longueur = 12) {
    $caracteres = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%';
    $password = '';
    $max = strlen($caracteres) - 1;
   
    for ($i = 0; $i < $longueur; $i++) {
        $password .= $caracteres[rand(0, $max)];
    }
   
    return $password;
}

/**
 * Vérifier si un utilisateur (client) existe par email
 */
function utilisateurExiste($email) {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT id, email, nom, prenom, code_client, CONCAT(prenom, ' ', nom) as nom_complet 
        FROM utilisateurs 
        WHERE email = ? AND role = 'client'
    ");
    $stmt->execute([$email]);
    return $stmt->fetch();
}

/**
 * Valider la cohérence du code client avec le nom et le prénom
 * @throws Exception si le code client existe déjà pour une autre personne
 */
function validerClientParCode($code_client, $nom, $prenom) {
    if (empty($code_client)) return null;
    
    $db = getDB();
    $stmt = $db->prepare("SELECT id, nom, prenom, email FROM utilisateurs WHERE code_client = ? AND role = 'client'");
    $stmt->execute([$code_client]);
    $client = $stmt->fetch();
    
    if ($client) {
        // Vérification insensible à la casse et aux espaces superflus
        $nomMatch = (mb_strtolower(trim($client['nom'])) === mb_strtolower(trim($nom)));
        $prenomMatch = (mb_strtolower(trim($client['prenom'])) === mb_strtolower(trim($prenom)));
        
        if (!$nomMatch || !$prenomMatch) {
            throw new Exception("Le code client **$code_client** est déjà associé à un autre client (**" . $client['prenom'] . " " . $client['nom'] . "**). Veuillez vérifier le code ou les informations saisies.");
        }
        return $client;
    }
    
    return null;
}

/**
 * Créer un nouveau client (utilisateur avec role='client')
 */
/**
 * Créer un nouveau client (utilisateur avec role='client')
 */
function creerNouveauClient($data, $motDePasse) {
    $db = getDB();
   
    try {
        // Hash du mot de passe
        $motDePasseHash = password_hash($motDePasse, PASSWORD_DEFAULT);
        
        $stmt = $db->prepare("
            INSERT INTO utilisateurs (email, nom, prenom, nom_societe, mot_de_passe, role, date_creation, actif, telephone, code_client)
            VALUES (?, ?, ?, ?, ?, 'client', NOW(), 1, ?, ?)
        ");
       
        $stmt->execute([
            $data['email'],
            $data['nom'],
            $data['prenom'],
            $data['nom_societe'] ?? null, // Nouveau champ
            $motDePasseHash,
            $data['telephone'] ?? null,   // Enregistrer aussi le téléphone
            $data['code_client'] ?? null  // Enregistrer aussi le code client
        ]);
       
        $clientId = $db->lastInsertId();
       
        return [
            'success' => true,
            'client_id' => $clientId,
            'password' => $motDePasse
        ];
       
    } catch(PDOException $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Créer un nouveau ticket avec gestion emails
 */
function creerTicket($data) {
    $db = getDB();
   
    try {
        // Validation du format du code client
        if (isset($data['code_client']) && !preg_match('/^(CL|CI)-\d{6}$/', $data['code_client'])) {
            throw new Exception("Le format du code client est incorrect (Attendu: CL-000000 ou CI-000000).");
        }

        $db->beginTransaction();
       
        // 1. D'abord valider par code client (Priorité absolue)
        $clientParCode = null;
        if (!empty($data['code_client'])) {
            $clientParCode = validerClientParCode($data['code_client'], $data['nom'], $data['prenom']);
        }

        // 2. Vérifier si le client existe (par email ou par code identifié)
        $client = $clientParCode ?: utilisateurExiste($data['email']);
        
        // Si on a trouvé le client par email mais qu'il a un code_client différent de celui saisi (ou que celui saisi est nouveau)
        if ($client && !empty($data['code_client'])) {
            if (!empty($client['code_client']) && $client['code_client'] !== $data['code_client']) {
                 throw new Exception("L'email **" . $data['email'] . "** est déjà associé au code client **" . $client['code_client'] . "**. Vous ne pouvez pas utiliser un autre code client (**" . $data['code_client'] . "**).");
            }
        }

        $estNouveauClient = !$client;
        $motDePasseGenere = null;
       
        // Si nouveau client, vérifier que l'email n'est pas déjà pris (double sécurité)
        if ($estNouveauClient) {
            // Si on est ici, c'est que ni le code n'existe, ni l'email (utilisateurExiste a retourné null)
            $motDePasseGenere = genererMotDePasseAleatoire();
            $resultClient = creerNouveauClient($data, $motDePasseGenere);
           
            if (!$resultClient['success']) {
                throw new Exception("Erreur création client: " . $resultClient['error']);
            }
           
            $clientId = $resultClient['client_id'];
            $nomComplet = $data['prenom'] . ' ' . $data['nom'];
        } else {
            $clientId = $client['id'];
            $nomComplet = ($client['nom_complet'] ?? ($client['prenom'] . ' ' . $client['nom']));
            
            // Si le client existait (par email) mais n'avait pas de code client, on lui assigne celui-ci
            if (empty($client['code_client']) && !empty($data['code_client'])) {
                $stmtUpd = $db->prepare("UPDATE utilisateurs SET code_client = ? WHERE id = ?");
                $stmtUpd->execute([$data['code_client'], $clientId]);
            }
        }
       
        // Générer le numéro de ticket
        $numeroTicket = genererNumeroTicket();
       
        // Insérer le ticket
        $colonnes = [
            'numero_ticket', 'nom_client', 'prenom_client', 'email_client', 'telephone_client',
            'code_client', 'sujet', 'description', 'categorie_id', 'statut_id', 'client_id', 'date_creation',
            'site_scpc', 'site_autre', 'type_ticket', 'type_nc', 'produit_concerne', 'qte_nc', 'unite_nc',
            'societe_transport', 'chauffeur', 'matricule', 'date_livraison', 'num_facture', 'date_facture',
            'demande_client', 'analyse_causes', 'analyse_consequences', 'notes', 
            'est_valide_par_rmq', 'nom_societe'
        ];

        $statutId = isset($data['statut_id']) ? $data['statut_id'] : 1;
        $estValide = isset($data['est_valide_par_rmq']) ? $data['est_valide_par_rmq'] : 1;
        
        // Si créé par commercial (statut 6), alors pas valide par défaut
        if (isset($data['is_commercial']) && $data['is_commercial'] === true) {
            $estValide = 0;
            // $statutId doit être passé à 6 dans l'appel, ou forcé ici ?
            // L'appelant gère le statut, mais on force l'invalidation
        }

        $sql = "INSERT INTO tickets (" . implode(', ', $colonnes) . ") VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(),
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
        )";

        $stmt = $db->prepare($sql);

        $params = [
            $numeroTicket,
            $data['nom'],
            $data['prenom'],
            $data['email'],
            $data['telephone'] ?? null,
            $data['code_client'] ?? null, // Code client saisi par l'utilisateur - DIRECTEMENT SANS MODIFICATION
            $data['sujet'],
            $data['description'],
            $data['categorie'],
            $statutId,
            $clientId,
            // Nouveaux champs
            $data['site_scpc'] ?? null,
            $data['site_autre'] ?? null,
            $data['type_ticket'] ?? 'mixte',
            $data['type_nc'] ?? null,
            $data['produit_concerne'] ?? null,
            $data['qte_nc'] ?? null,
            $data['unite_nc'] ?? null,
            $data['societe_transport'] ?? null,
            $data['chauffeur'] ?? null,
            $data['matricule'] ?? null,
            $data['date_livraison'] ?? null,
            $data['num_facture'] ?? null,
            $data['date_facture'] ?? null,
            $data['demande_client'] ?? null,
            $data['analyse_causes'] ?? null,
            $data['analyse_consequences'] ?? null,
            $data['notes'] ?? null,
            $estValide,
            $data['nom_societe'] ?? null
        ];

        $stmt->execute($params);
       
        $ticketId = $db->lastInsertId();
       
        // Ajouter une entrée dans l'historique
        $stmt = $db->prepare("
            INSERT INTO historique_actions (ticket_id, action, details, nouveau_statut_id, date_action)
            VALUES (?, 'created', 'Ticket créé par le client', 1, NOW())
        ");
        $stmt->execute([$ticketId]);
       
        // Récupérer les infos complètes du ticket pour l'email
        $stmt = $db->prepare("
            SELECT t.*, c.nom as categorie_nom
            FROM tickets t
            LEFT JOIN categories c ON t.categorie_id = c.id
            WHERE t.id = ?
        ");
        $stmt->execute([$ticketId]);
        $ticketData = $stmt->fetch();
       
        $clientData = [
            'nom_complet' => $nomComplet,
            'email' => $data['email'],
            'telephone' => $data['telephone'] ?? ''
        ];
       
        // ENVOI DES EMAILS (Sauf pour les réclamations Commerciales - Envoi différé après confirmation RMQ)
        $isCommercial = (isset($data['is_commercial']) && $data['is_commercial'] === true) || (isset($data['created_by_role']) && $data['created_by_role'] === 'commercial');
        
        $emailManager = new EmailManager($db);
       
        if (!$isCommercial) {
            if ($estNouveauClient) {
                // Email avec credentials pour nouveau client
                $emailManager->envoyerCredentialsNouveauClient($ticketData, $clientData, $motDePasseGenere);
            } else {
                // Email simple de confirmation
                $emailManager->envoyerConfirmationClientAuth($ticketData, $clientData);
            }
        }
       
        // Notifier les agents (Toujours notifier les agents, même pour le commercial)
        $emailManager->notifierAgents($ticketData, $clientData);
       
        $db->commit();
       
        return [
            'success' => true,
            'ticket_id' => $ticketId,
            'numero_ticket' => $numeroTicket,
            'est_nouveau_client' => $estNouveauClient,
            'password' => $motDePasseGenere
        ];
       
    } catch(Exception $e) {
        // Vérifier si une transaction est active avant de faire rollback
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        return [
            'success' => false,
            'error' => 'Erreur lors de la création du ticket: ' . $e->getMessage()
        ];
    }
}

/**
 * Upload de fichier avec messages d'erreur détaillés
 */
function uploadFichier($file, $ticketId) {
    // Chemin absolu vers le dossier uploads à la racine
    $uploadDir = __DIR__ . '/../uploads/';
   
    if (!file_exists($uploadDir)) {
        if (!mkdir($uploadDir, 0777, true)) {
            return ['success' => false, 'error' => "Impossible de créer le dossier d'upload."];
        }
    }
   
    if (!isset($file['error']) || is_array($file['error'])) {
         return ['success' => false, 'error' => 'Paramètres d\'upload invalides.'];
    }

    // Gestion des codes d'erreur PHP standard
    switch ($file['error']) {
        case UPLOAD_ERR_OK:
            break;
        case UPLOAD_ERR_NO_FILE:
            return ['success' => false, 'error' => 'Aucun fichier n\'a été envoyé.'];
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return ['success' => false, 'error' => 'Le fichier dépasse la taille autorisée.'];
        default:
            return ['success' => false, 'error' => 'Erreur inconnue lors de l\'upload.'];
    }
   
    if ($file['size'] > 5 * 1024 * 1024) {
        return ['success' => false, 'error' => 'Fichier trop volumineux (max 5 Mo).'];
    }
   
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx', 'txt'];
    $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
   
    if (!in_array($fileExtension, $allowedExtensions)) {
        return ['success' => false, 'error' => 'Extension non autorisée (autorisés: jpg, png, pdf, doc, docx, txt).'];
    }
   
    // Nettoyer le nom de fichier original
    $originalName = preg_replace("/[^a-zA-Z0-0.]/", "_", basename($file['name']));
    $newFileName = uniqid() . '_' . $originalName;
    $targetPath = $uploadDir . $newFileName;
   
    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        $db = getDB();
        try {
            $stmt = $db->prepare("
                INSERT INTO pieces_jointes (ticket_id, nom_fichier, chemin_fichier, type_mime, taille_fichier, date_upload)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
           
            $stmt->execute([
                $ticketId,
                $file['name'],
                $newFileName,
                $file['type'],
                $file['size']
            ]);
           
            return ['success' => true, 'filename' => $newFileName];
        } catch (Exception $e) {
            // Supprimer le fichier si l'insertion DB échoue
            unlink($targetPath);
            return ['success' => false, 'error' => "Erreur base de données : " . $e->getMessage()];
        }
    }
   
    return ['success' => false, 'error' => 'Le serveur a échoué à enregistrer le fichier.'];
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
               CONCAT(u.prenom, ' ', u.nom) as utilisateur_nom
        FROM historique_actions h
        LEFT JOIN utilisateurs u ON h.utilisateur_id = u.id
        WHERE h.ticket_id = ?
        ORDER BY h.date_action DESC
    ");
   
    $stmt->execute([$ticketId]);
    return $stmt->fetchAll();
}

/**
 * Récupérer les commentaires d'un ticket
 */
function getCommentairesTicket($ticketId) {
    $db = getDB();
   
    $stmt = $db->prepare("
        SELECT c.*, CONCAT(u.prenom, ' ', u.nom) as auteur_nom
        FROM commentaires c
        LEFT JOIN utilisateurs u ON c.utilisateur_id = u.id
        WHERE c.ticket_id = ?
        ORDER BY c.date_creation ASC
    ");
   
    $stmt->execute([$ticketId]);
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
?>