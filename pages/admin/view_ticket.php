<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/functions.php';

$id = $_GET['id'] ?? 0;
$db = getDB();

// Récupérer le ticket
$stmt = $db->prepare("
    SELECT t.*, s.nom as statut_nom, c.nom as categorie_nom 
    FROM tickets t 
    JOIN statuts s ON t.statut_id = s.id 
    JOIN categories c ON t.categorie_id = c.id 
    WHERE t.id = ?
");
$stmt->execute([$id]);
$ticket = $stmt->fetch();

if (!$ticket) {
    redirect('reclamations.php');
}

// Récupérer les commentaires
$stmt = $db->prepare("
    SELECT co.*, u.nom as user_nom, u.prenom as user_prenom 
    FROM commentaires co 
    JOIN utilisateurs u ON co.utilisateur_id = u.id 
    WHERE co.ticket_id = ? 
    ORDER BY co.date_creation ASC
");
$stmt->execute([$id]);
$commentaires = $stmt->fetchAll();

$pageTitle = "Ticket #{$ticket['numero_ticket']} - Central Réclamation";
include 'includes/header.php';
?>

<div class="page-header" style="margin-bottom: 2rem;">
    <a href="reclamations.php" style="color: var(--text-muted); text-decoration: none; font-size: 0.875rem; display: inline-flex; align-items: center; gap: 0.5rem; margin-bottom: 1rem;">
        <i class="fa-solid fa-arrow-left"></i> Retour aux réclamations
    </a>
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        <h2 style="font-size: 1.875rem; font-weight: 700; margin: 0;">Ticket #<?php echo $ticket['numero_ticket']; ?></h2>
        <div style="display: flex; gap: 0.75rem;">
            <?php echo getStatutBadge($ticket['statut_nom']); ?>
        </div>
    </div>
</div>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem; align-items: start;">
    <!-- Main Column: content and comments -->
    <div style="display: flex; flex-direction: column; gap: 1.5rem; grid-column: span 2;">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fa-solid fa-circle-info"></i>
                    <?php echo htmlspecialchars($ticket['sujet']); ?>
                </h3>
            </div>
            <div class="card-body">
                <div style="white-space: pre-wrap; color: var(--text-main); font-size: 1rem; line-height: 1.7;">
                    <?php echo htmlspecialchars($ticket['description']); ?>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fa-solid fa-comments"></i>
                    Historique & Commentaires
                </h3>
            </div>
            <div class="card-body">
                <div class="comments-list">
                    <?php foreach ($commentaires as $com): ?>
                    <div style="margin-bottom: 2rem; padding: 1rem; background: var(--gray-50); border-radius: var(--radius-md); border-left: 4px solid var(--admin-primary);">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 0.75rem; align-items: center;">
                            <span style="font-weight: 600; font-size: 0.95rem; color: var(--text-main);">
                                <i class="fa-solid fa-user-circle"></i>
                                <?php echo htmlspecialchars($com['user_prenom'] . ' ' . $com['user_nom']); ?>
                            </span>
                            <span style="font-size: 0.75rem; color: var(--text-muted);">
                                <i class="fa-regular fa-clock"></i>
                                <?php echo formatDateTime($com['date_creation']); ?>
                            </span>
                        </div>
                        <p style="font-size: 0.9rem; margin: 0; color: var(--text-main); line-height: 1.5;">
                            <?php echo htmlspecialchars($com['contenu']); ?>
                        </p>
                    </div>
                    <?php endforeach; ?>
                    
                    <?php if (empty($commentaires)): ?>
                        <div style="text-align: center; padding: 2rem; color: var(--text-muted);">
                            <i class="fa-solid fa-comment-slash" style="font-size: 2rem; opacity: 0.3; margin-bottom: 1rem; display: block;"></i>
                            <p style="font-style: italic; margin: 0;">Aucun commentaire pour le moment.</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div style="margin-top: 2rem; border-top: 1px solid var(--border-color); padding-top: 2rem;">
                    <form action="api/add_comment.php" method="POST">
                        <input type="hidden" name="ticket_id" value="<?php echo $id; ?>">
                        <div class="form-group" style="margin-bottom: 1.5rem;">
                            <label class="form-label" style="font-weight: 600; margin-bottom: 0.75rem; display: block;">Ajouter un commentaire</label>
                            <textarea name="contenu" class="form-control" rows="4" placeholder="Votre message..." required style="width: 100%; padding: 1rem; border: 1px solid var(--border-color); border-radius: var(--radius-md);"></textarea>
                        </div>
                        <div style="display: flex; justify-content: flex-end;">
                            <button type="submit" class="btn btn-primary" style="padding: 0.75rem 2rem;">
                                <i class="fa-solid fa-paper-plane"></i> Envoyer
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Sidebar: meta info -->
    <div style="display: flex; flex-direction: column; gap: 1.5rem;">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fa-solid fa-user-tag"></i>
                    Informations Client
                </h3>
            </div>
            <div class="card-body">
                <div style="display: flex; flex-direction: column; gap: 1.25rem;">
                    <div>
                        <label style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-muted); display: block; margin-bottom: 0.25rem;">Nom complet</label>
                        <span style="font-weight: 600; font-size: 1rem; color: var(--text-main);"><?php echo htmlspecialchars($ticket['prenom_client'] . ' ' . $ticket['nom_client']); ?></span>
                    </div>
                    <div>
                        <label style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-muted); display: block; margin-bottom: 0.25rem;">Email</label>
                        <a href="mailto:<?php echo htmlspecialchars($ticket['email_client']); ?>" style="font-weight: 500; color: var(--admin-primary); text-decoration: none;">
                            <?php echo htmlspecialchars($ticket['email_client']); ?>
                        </a>
                    </div>
                    <div>
                        <label style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-muted); display: block; margin-bottom: 0.25rem;">Téléphone</label>
                        <span style="font-weight: 500; color: var(--text-main);"><?php echo htmlspecialchars($ticket['telephone_client'] ?? 'N/A'); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fa-solid fa-gear"></i>
                    Détails Techniques
                </h3>
            </div>
            <div class="card-body">
                <div style="display: flex; flex-direction: column; gap: 1.25rem;">
                    <div>
                        <label style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-muted); display: block; margin-bottom: 0.5rem;">Catégorie</label>
                        <span class="badge" style="background: var(--gray-100); color: var(--admin-primary); border: 1px solid var(--border-color); padding: 0.5rem 1rem;">
                            <i class="fa-solid fa-folder-open"></i>
                            <?php echo htmlspecialchars($ticket['categorie_nom']); ?>
                        </span>
                    </div>
                    <div>
                        <label style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-muted); display: block; margin-bottom: 0.25rem;">Date de création</label>
                        <span style="font-weight: 500; color: var(--text-main); font-size: 0.9rem;">
                            <i class="fa-regular fa-calendar-plus" style="margin-right: 0.25rem;"></i>
                            <?php echo formatDateTime($ticket['date_creation']); ?>
                        </span>
                    </div>
                    <div>
                        <label style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-muted); display: block; margin-bottom: 0.25rem;">Dernière modification</label>
                        <span style="font-weight: 500; color: var(--text-main); font-size: 0.9rem;">
                            <i class="fa-regular fa-calendar-check" style="margin-right: 0.25rem;"></i>
                            <?php echo formatDateTime($ticket['date_modification']); ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
