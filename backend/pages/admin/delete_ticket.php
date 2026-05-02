<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/functions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$id = intval($_GET['id'] ?? 0);

if ($id > 0) {
    $db = getDB();
    
    try {
        $db->beginTransaction();
        
        // Récupérer le numéro de ticket pour le message de succès
        $stmt = $db->prepare("SELECT numero_ticket FROM tickets WHERE id = ?");
        $stmt->execute([$id]);
        $ticket = $stmt->fetch();
        
        if ($ticket) {
            // Supprimer les dépendances (si non gérées par ON DELETE CASCADE)
            // Pièces jointes
            $stmt = $db->prepare("DELETE FROM pieces_jointes WHERE ticket_id = ?");
            $stmt->execute([$id]);
            
            // Historique
            $stmt = $db->prepare("DELETE FROM historique_actions WHERE ticket_id = ?");
            $stmt->execute([$id]);
            
            // Commentaires
            $stmt = $db->prepare("DELETE FROM commentaires WHERE ticket_id = ?");
            $stmt->execute([$id]);
            
            // Supprimer le ticket
            $stmt = $db->prepare("DELETE FROM tickets WHERE id = ?");
            $stmt->execute([$id]);
            
            $db->commit();
            $_SESSION['success_message'] = "La réclamation #{$ticket['numero_ticket']} a été supprimée avec succès.";
        } else {
            $_SESSION['error_message'] = "Réclamation non trouvée.";
        }
        
    } catch (Exception $e) {
        $db->rollBack();
        $_SESSION['error_message'] = "Erreur lors de la suppression : " . $e->getMessage();
    }
}

header('Location: reclamations.php');
exit;
