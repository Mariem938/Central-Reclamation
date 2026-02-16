<?php
/**
 * Refuser une Réclamation (RMQ)
 * Fichier: pages/rmq/refuse_claim_v2.php
 * Page dédiée pour refuser une réclamation avec motif obligatoire
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
$stmt = $db->prepare("
    SELECT t.*, u.email, u.prenom, u.nom
    FROM tickets t
    LEFT JOIN utilisateurs u ON t.client_id = u.id
    WHERE t.id = ?
");
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
                $message .= "Si vous avez des questions ou désirez contester ce refus, veuillez nous contacter.\n\n";
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
</head>
<body>
    <div style="max-width: 800px; margin: 2rem auto; padding: 0 1rem;">
        <!-- Header DANGER -->
        <div style="background: linear-gradient(135deg, #F44336 0%, #E53935 100%); color: white; padding: 2rem; border-radius: 8px; margin-bottom: 2rem; text-align: center;">
            <h1 style="font-size: 2rem; margin: 0;"><i class="fas fa-times-circle"></i> REFUSER LA RÉCLAMATION</h1>
            <p style="margin: 1rem 0 0 0; font-size: 1.1rem;">Ticket #<?php echo htmlspecialchars($ticket['numero_ticket']); ?></p>
            <p style="margin: 0.5rem 0 0 0;">Client: <strong><?php echo htmlspecialchars($ticket['prenom'] . ' ' . $ticket['nom']); ?></strong></p>
        </div>

        <!-- Alerte Importante -->
        <div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 1.5rem; border-radius: 4px; margin-bottom: 2rem;">
            <h3 style="margin-top: 0;"><i class="fas fa-exclamation-triangle"></i> Attention</h3>
            <p>🔴 <strong>Cette action est définitive.</strong> Une fois la réclamation refusée:</p>
            <ul>
                <li>✓ Le statut passera à "REFUSÉ"</li>
                <li>✓ Un email sera automatiquement envoyé au client</li>
                <li>✓ L'action sera enregistrée dans l'historique</li>
            </ul>
        </div>

        <!-- Messages d'alerte -->
        <?php if ($error): ?>
            <div style="background: #ffebee; color: #c62828; padding: 1rem; border-radius: 4px; margin-bottom: 1rem;">
                <strong>❌ Erreur:</strong> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div style="background: #e8f5e9; color: #2e7d32; padding: 1rem; border-radius: 4px; margin-bottom: 1rem;">
                <strong><?php echo htmlspecialchars($success); ?></strong>
                <p style="margin: 0.5rem 0 0 0; font-size: 0.9rem;">Redirection vers le dashboard dans 3 secondes...</p>
            </div>
        <?php endif; ?>

        <!-- Informations de la Réclamation -->
        <div style="background: #f9f9f9; padding: 1.5rem; border-left: 4px solid #FF6B35; border-radius: 4px; margin-bottom: 2rem;">
            <h4><i class="fas fa-info-circle"></i> Détails de la Réclamation</h4>
            <p><strong>Description:</strong> <?php echo htmlspecialchars(substr($ticket['description'], 0, 200)); ?></p>
            <p><strong>Demande Client:</strong> <?php echo htmlspecialchars($ticket['demande_client'] ?? 'N/A'); ?></p>
            <p><strong>Créée le:</strong> <?php echo date('d/m/Y à H:i', strtotime($ticket['date_creation'])); ?></p>
        </div>

        <!-- Formulaire de Refus -->
        <form method="POST" style="background: white; padding: 2rem; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
            <h3><i class="fas fa-comment"></i> Motif du Refus</h3>

            <div style="margin: 1.5rem 0;">
                <label for="motif_rejet"><strong style="color: #F44336;">* Motif du Refus (Obligatoire)</strong></label>
                <p style="color: #666; font-size: 0.9rem; margin: 0.5rem 0 0 0;">Expliquez clairement pourquoi cette réclamation est refusée. Le client recevra ce message.</p>
                <textarea name="motif_rejet" id="motif_rejet" required rows="6" style="width: 100%; padding: 1rem; border: 2px solid #F44336; border-radius: 4px; font-size: 1rem; font-family: Arial, sans-serif;" placeholder="Exemples:
- La réclamation n'est pas conforme aux conditions générales
- Le délai de réclamation a expiré
- Les informations fournies sont insuffisantes
- Le produit ne relève pas de notre responsabilité
- ..."></textarea>
            </div>

            <!-- Confirmation -->
            <div style="background: #ffe0e0; padding: 1rem; border-radius: 4px; margin: 1.5rem 0; border-left: 4px solid #F44336;">
                <label style="display: flex; align-items: flex-start; gap: 10px; cursor: pointer;">
                    <input type="checkbox" required style="margin-top: 5px;">
                    <span>Je confirme que cette réclamation doit être refusée et que le client sera notifié par email.</span>
                </label>
            </div>

            <!-- Boutons -->
            <div style="display: flex; gap: 1rem; margin-top: 2rem;">
                <button type="submit" class="btn btn-danger" onclick="return confirm('⚠️ Êtes-vous CERTAIN(E) de vouloir refuser cette réclamation? Cette action est DÉFINITIVE et un email sera envoyé au client.');"><i class="fas fa-check"></i> Refuser Définitivement</button>
                <a href="detail_ticket_v2.php?id=<?php echo $ticketId; ?>" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Retour au Détail</a>
            </div>
        </form>
    </div>

    <style>
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            transition: 0.2s;
        }
        .btn-danger {
            background: #F44336;
            color: white;
        }
        .btn-danger:hover {
            background: #D32F2F;
        }
        .btn-secondary {
            background: #ccc;
            color: #333;
        }
        .btn-secondary:hover {
            background: #bbb;
        }
    </style>
</body>
</html>
