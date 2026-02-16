<?php
/**
 * Refuser Réclamation - RMQ - CORRIGÉ
 * Fichier: pages/rmq/refuse_claim.php
 */

require_once '../../config/database.php';
require_once '../../api/functions.php';

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'rmq') {
    header('Location: ../agent/login.php');
    exit;
}

$db = getDB();
$ticketId = intval($_GET['id'] ?? 0);
$error = '';
$success = '';

// Récupérer la réclamation
$stmt = $db->prepare("SELECT t.*, u.email, u.prenom, u.nom FROM tickets t LEFT JOIN utilisateurs u ON t.client_id = u.id WHERE t.id = ?");
$stmt->execute([$ticketId]);
$ticket = $stmt->fetch();

if (!$ticket) {
    header('Location: dashboard.php');
    exit;
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $motifRejet = cleanInput($_POST['motif_rejet'] ?? '');
    
    if (empty($motifRejet)) {
        $error = 'Le motif du refus est OBLIGATOIRE.';
    } else {
        try {
            // Statut 5 = Refusé
            $stmt = $db->prepare("UPDATE tickets SET statut_id = 5, motif_rejet = ? WHERE id = ?");
            $stmt->execute([$motifRejet, $ticketId]);
            
            // Historique
            $stmt = $db->prepare("INSERT INTO historique_actions (ticket_id, action, details, utilisateur_id, date_action) VALUES (?, 'rejet_rmq', ?, ?, NOW())");
            $stmt->execute([$ticketId, "Réclamation refusée. Motif: " . substr($motifRejet, 0, 100), $_SESSION['user_id']]);
            
            // EMAIL au client
            if (!empty($ticket['email'])) {
                $subject = "Réclamation #" . $ticket['numero_ticket'] . " - REFUSÉE";
                $message = "Bonjour " . $ticket['prenom'] . " " . $ticket['nom'] . ",\n\n";
                $message .= "⚠️ NOTIFICATION IMPORTANTE ⚠️\n\n";
                $message .= "Votre réclamation #" . $ticket['numero_ticket'] . " a été examinée et a été REFUSÉE.\n\n";
                $message .= "Motif du refus:\n";
                $message .= "---\n";
                $message .= $motifRejet . "\n";
                $message .= "---\n\n";
                $message .= "Si vous avez des questions, veuillez nous contacter.\n\n";
                $message .= "Cordialement,\nÉquipe de Gestion des Réclamations";
                
                if (function_exists('envoyerEmail')) {
                    envoyerEmail($ticket['email'], $subject, $message);
                }
            }
            
            $success = '✅ Réclamation refusée. Email envoyé au client.';
            
            // Redirection après quelques secondes
            header('Refresh: 3; url=dashboard.php');
            
        } catch (Exception $e) {
            $error = 'Erreur: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Refuser Réclamation #<?php echo htmlspecialchars($ticket['numero_ticket']); ?></title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/fontawesome/css/all.min.css">
    <style>
        body { background: #f5f5f5; }
        .container { max-width: 800px; margin: 2rem auto; padding: 0 1rem; }
        .header { background: linear-gradient(135deg, #F44336 0%, #E53935 100%); color: white; padding: 2rem; border-radius: 8px; margin-bottom: 2rem; text-align: center; }
        .header h1 { font-size: 2rem; margin: 0; }
        .alert { padding: 1.5rem; border-radius: 4px; margin-bottom: 1rem; }
        .alert-warning { background: #fff3cd; border-left: 4px solid #ffc107; color: #856404; }
        .alert-error { background: #ffebee; color: #c62828; }
        .alert-success { background: #e8f5e9; color: #2e7d32; }
        .form-container { background: white; padding: 2rem; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .form-group { margin: 1.5rem 0; }
        .form-label { display: block; font-weight: 600; margin-bottom: 0.5rem; color: #333; }
        textarea { width: 100%; padding: 1rem; border: 2px solid #F44336; border-radius: 4px; font-family: Arial, sans-serif; font-size: 1rem; }
        textarea:focus { border-color: #E53935; outline: none; }
        .confirmation { background: #ffe0e0; border-left: 4px solid #F44336; padding: 1rem; border-radius: 4px; margin: 1.5rem 0; }
        .buttons { display: flex; gap: 1rem; margin-top: 2rem; }
        .btn { padding: 0.75rem 1.5rem; border: none; border-radius: 4px; cursor: pointer; font-weight: 600; text-decoration: none; display: inline-block; }
        .btn-danger { background: #F44336; color: white; }
        .btn-danger:hover { background: #D32F2F; }
        .btn-secondary { background: #ccc; color: #333; }
        .btn-secondary:hover { background: #bbb; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-times-circle"></i> REFUSER LA RÉCLAMATION</h1>
            <p>Ticket #<?php echo htmlspecialchars($ticket['numero_ticket']); ?></p>
            <p>Client: <strong><?php echo htmlspecialchars($ticket['prenom'] . ' ' . $ticket['nom']); ?></strong></p>
        </div>

        <div class="alert alert-warning">
            <h3><i class="fas fa-exclamation-triangle"></i> Attention</h3>
            <p>🔴 <strong>Cette action est définitive.</strong></p>
            <ul>
                <li>✓ Le statut passera à "REFUSÉ"</li>
                <li>✓ Un email sera envoyé au client</li>
                <li>✓ L'action sera historisée</li>
            </ul>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><strong>❌ Erreur:</strong> <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><strong><?php echo htmlspecialchars($success); ?></strong></div>
        <?php endif; ?>

        <form method="POST" class="form-container">
            <div class="form-group">
                <label class="form-label"><strong style="color: #F44336;">* Motif du Refus (Obligatoire)</strong></label>
                <p style="color: #666; font-size: 0.9rem;">Expliquez pourquoi cette réclamation est refusée. Le client recevra ce message.</p>
                <textarea name="motif_rejet" required rows="6" placeholder="Exemples:
- La réclamation n'est pas conforme aux conditions générales
- Le délai de réclamation a expiré
- Les informations fournies sont insuffisantes
- Le produit ne relève pas de notre responsabilité"></textarea>
            </div>

            <div class="confirmation">
                <label style="display: flex; align-items: flex-start; gap: 10px; cursor: pointer;">
                    <input type="checkbox" required style="margin-top: 5px;">
                    <span>Je confirme que cette réclamation doit être refusée et que le client sera notifié.</span>
                </label>
            </div>

            <div class="buttons">
                <button type="submit" class="btn btn-danger" onclick="return confirm('⚠️ ÊTES-VOUS CERTAIN(E)? Cette action est DÉFINITIVE et un email sera envoyé au client.');"><i class="fas fa-check"></i> Refuser Définitivement</button>
                <a href="detail_ticket.php?id=<?php echo $ticketId; ?>" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Retour</a>
            </div>
        </form>
    </div>
</body>
</html>
