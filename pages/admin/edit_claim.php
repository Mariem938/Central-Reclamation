<?php
session_start();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../agent/login.php');
    exit;
}

$pageTitle = "Modifier Réclamation - Admin";
$db = getDB();
$ticketId = $_GET['id'] ?? 0;
$error = '';
$success = '';

// Récupérer le ticket
$stmt = $db->prepare("
    SELECT t.* 
    FROM tickets t
    WHERE t.id = ?
");
$stmt->execute([$ticketId]);
$ticket = $stmt->fetch();

if (!$ticket) {
    header('Location: reclamations.php');
    exit;
}

// Récupérer les listes pour les selects
$categories = $db->query("SELECT * FROM categories ORDER BY nom")->fetchAll();
$statuts = $db->query("SELECT * FROM statuts ORDER BY ordre")->fetchAll();
$sites = $db->query("SELECT * FROM sites WHERE actif = 1 ORDER BY nom")->fetchAll();
$fournisseurs = $db->query("SELECT id, CONCAT(prenom, ' ', nom) as nom_complet FROM utilisateurs WHERE role = 'fournisseur' ORDER BY nom")->fetchAll();
$rfqs = $db->query("SELECT id, CONCAT(prenom, ' ', nom) as nom_complet FROM utilisateurs WHERE role = 'rfq' ORDER BY nom")->fetchAll();
$rmqs = $db->query("SELECT id, CONCAT(prenom, ' ', nom) as nom_complet FROM utilisateurs WHERE role = 'rmq' ORDER BY nom")->fetchAll();
$aqfs = $db->query("SELECT id, CONCAT(prenom, ' ', nom) as nom_complet FROM utilisateurs WHERE role = 'aqf' ORDER BY nom")->fetchAll();

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db->beginTransaction();
        
        // Collecter les anciennes valeurs pour l'historique
        $oldValues = $ticket;
        $changes = [];
        
        // Préparer les données
        $data = [
            'nom_client' => trim($_POST['nom_client'] ?? ''),
            'prenom_client' => trim($_POST['prenom_client'] ?? ''),
            'email_client' => trim($_POST['email_client'] ?? ''),
            'telephone_client' => trim($_POST['telephone_client'] ?? ''),
            'sujet' => trim($_POST['sujet'] ?? ''),
            'description' => trim($_POST['description'] ?? ''),
            'categorie_id' => intval($_POST['categorie_id'] ?? 0),
            'statut_id' => intval($_POST['statut_id'] ?? 0),
            'type_ticket' => $_POST['type_ticket'] ?? 'mixte',
            'type_nc' => trim($_POST['type_nc'] ?? ''),
            'produit_concerne' => trim($_POST['produit_concerne'] ?? ''),
            'qte_nc' => trim($_POST['qte_nc'] ?? ''),
            'societe_transport' => trim($_POST['societe_transport'] ?? ''),
            'chauffeur' => trim($_POST['chauffeur'] ?? ''),
            'num_facture' => trim($_POST['num_facture'] ?? ''),
            'date_facture' => $_POST['date_facture'] ?? null,
            'date_livraison' => $_POST['date_livraison'] ?? null,
            'site_scpc' => trim($_POST['site_scpc'] ?? ''),
            'site_autre' => trim($_POST['site_autre'] ?? ''),
            'demande_client' => trim($_POST['demande_client'] ?? ''),
            'analyse_causes' => trim($_POST['analyse_causes'] ?? ''),
            'analyse_consequences' => trim($_POST['analyse_consequences'] ?? ''),
            'actions' => trim($_POST['actions'] ?? ''),
            'responsable_actions' => trim($_POST['responsable_actions'] ?? ''),
            'date_realisation' => $_POST['date_realisation'] ?? null,
            'observations' => trim($_POST['observations'] ?? ''),
            'code_client' => trim($_POST['code_client'] ?? ''),
        ];
        
        // Nouvelle validation: Cohérence Code Client / Nom / Prénom
        if (!empty($data['code_client'])) {
            require_once __DIR__ . '/../../api/functions.php';
            validerClientParCode($data['code_client'], $data['nom_client'], $data['prenom_client']);
        }
        
        // Détecter les changements
        foreach ($data as $key => $newValue) {
            if ($oldValues[$key] != $newValue) {
                $changes[] = "$key: '" . ($oldValues[$key] ?? 'NULL') . "' → '$newValue'";
            }
        }
        
        $sql = "UPDATE tickets SET 
                nom_client = ?, prenom_client = ?, email_client = ?, telephone_client = ?,
                sujet = ?, description = ?, categorie_id = ?, statut_id = ?,
                type_ticket = ?, type_nc = ?, produit_concerne = ?, qte_nc = ?,
                societe_transport = ?, chauffeur = ?, num_facture = ?, date_facture = ?, date_livraison = ?,
                site_scpc = ?, site_autre = ?, demande_client = ?,
                analyse_causes = ?, analyse_consequences = ?, actions = ?, responsable_actions = ?,
                date_realisation = ?, observations = ?,
                code_client = ?,
                date_modification = NOW()
                WHERE id = ?";
        
        $params = array_values($data);
        $params[] = $ticketId;
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        
        // Logger les modifications
        if (!empty($changes)) {
            $stmt = $db->prepare("INSERT INTO historique_actions (ticket_id, utilisateur_id, action, details, date_action) VALUES (?, ?, ?, ?, NOW())");
            $details = "Ticket modifié par admin. Changements: " . implode(', ', $changes);
            $stmt->execute([$ticketId, $_SESSION['user_id'], 'admin_edit', $details]);
        }
        
        $db->commit();
        $success = "Réclamation modifiée avec succès.";
        
        // Rafraîchir les données
        $stmt = $db->prepare("SELECT t.* FROM tickets t WHERE t.id = ?");
        $stmt->execute([$ticketId]);
        $ticket = $stmt->fetch();
        
    } catch (Exception $e) {
        $db->rollBack();
        $error = "Erreur lors de la modification : " . $e->getMessage();
    }
}

include __DIR__ . '/includes/header.php';
?>

<div class="dashboard-header" style="background: white; padding: 2rem; border-radius: var(--radius-xl); border: 1px solid var(--border-color); box-shadow: var(--shadow-sm); margin-bottom: 2.5rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1.5rem;">
    <div>
        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 8px;">
            <span style="background: var(--admin-gradient); color: white; padding: 4px 12px; border-radius: 8px; font-size: 0.65rem; font-weight: 800; letter-spacing: 0.05em; text-transform: uppercase;">ADMINISTRATION</span>
            <span style="color: var(--text-muted); font-size: 0.85rem; font-weight: 600;">Ticket #<?php echo htmlspecialchars($ticket['numero_ticket']); ?></span>
        </div>
        <h2 style="font-size: 2.25rem; font-weight: 900; color: #0f172a; margin: 0; letter-spacing: -0.03em; display: flex; align-items: center; gap: 15px;">
            <i class="fas fa-edit" style="color: var(--admin-primary); font-size: 1.8rem;"></i> Modifier la Réclamation
        </h2>
    </div>
    <div style="display: flex; gap: 0.75rem;">
        <a href="view_ticket.php?id=<?php echo $ticketId; ?>" class="btn btn-outline" style="padding: 0.75rem 1.25rem; border-radius: 12px; font-weight: 700;">
            <i class="fas fa-eye"></i> Voir Détails
        </a>
        <a href="reclamations.php" class="btn btn-outline" style="padding: 0.75rem 1.25rem; border-radius: 12px; font-weight: 700;">
            <i class="fas fa-arrow-left"></i> Retour
        </a>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger mb-4">
        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
    </div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success mb-4">
        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
    </div>
<?php endif; ?>

<form method="POST" action="">
    <div class="row">
        <!-- Colonne Principale -->
        <div class="col-md-8">
            <!-- Informations Client -->
            <div class="card mb-4" style="border-radius: var(--radius-lg); border: 1px solid var(--border-color); box-shadow: var(--shadow-sm); overflow: hidden;">
                <div class="card-header" style="background: #f8fafc; padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border-color);">
                    <h3 class="card-title" style="margin: 0; font-size: 1.1rem; font-weight: 800; color: #1e293b; display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-user" style="color: var(--admin-primary);"></i> Informations Client
                    </h3>
                </div>
                <div class="card-body" style="padding: 1.75rem;">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Prénom <span style="color: red;">*</span></label>
                            <input type="text" name="prenom_client" class="form-control" 
                                   value="<?php echo htmlspecialchars($ticket['prenom_client']); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nom <span style="color: red;">*</span></label>
                            <input type="text" name="nom_client" class="form-control" 
                                   value="<?php echo htmlspecialchars($ticket['nom_client']); ?>" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email <span style="color: red;">*</span></label>
                            <input type="email" name="email_client" class="form-control" 
                                   value="<?php echo htmlspecialchars($ticket['email_client']); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Téléphone</label>
                            <input type="text" name="telephone_client" class="form-control" 
                                   value="<?php echo htmlspecialchars($ticket['telephone_client'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Code Client <span style="color: red;">*</span></label>
                            <input type="text" name="code_client" class="form-control" 
                                   placeholder="CL-000000 ou CI-000000" required
                                   pattern="^(CL|CI)-\d{6}$"
                                   title="Format obligatoire : CL-000000 ou CI-000000"
                                   value="<?php echo htmlspecialchars($ticket['code_client'] ?? ''); ?>">
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Détails Réclamation -->
            <div class="card mb-4" style="border-radius: var(--radius-lg); border: 1px solid var(--border-color); box-shadow: var(--shadow-sm); overflow: hidden;">
                <div class="card-header" style="background: #f8fafc; padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border-color);">
                    <h3 class="card-title" style="margin: 0; font-size: 1.1rem; font-weight: 800; color: #1e293b; display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-file-alt" style="color: var(--admin-primary);"></i> Détails de la Réclamation
                    </h3>
                </div>
                <div class="card-body" style="padding: 1.75rem;">
                    <div class="mb-3">
                        <label class="form-label">Sujet <span style="color: red;">*</span></label>
                        <input type="text" name="sujet" class="form-control" 
                               value="<?php echo htmlspecialchars($ticket['sujet']); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description <span style="color: red;">*</span></label>
                        <textarea name="description" class="form-control" rows="4" required><?php echo htmlspecialchars($ticket['description']); ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Demande Client</label>
                        <textarea name="demande_client" class="form-control" rows="3"><?php echo htmlspecialchars($ticket['demande_client'] ?? ''); ?></textarea>
                    </div>
                </div>
            </div>
            
            <!-- Informations Qualité -->
            <div class="card mb-4" style="border-radius: var(--radius-lg); border: 1px solid var(--border-color); box-shadow: var(--shadow-sm); overflow: hidden;">
                <div class="card-header" style="background: #f8fafc; padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border-color);">
                    <h3 class="card-title" style="margin: 0; font-size: 1.1rem; font-weight: 800; color: #1e293b; display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-clipboard-check" style="color: var(--admin-primary);"></i> Informations Qualité
                    </h3>
                </div>
                <div class="card-body" style="padding: 1.75rem;">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Type NC</label>
                            <input type="text" name="type_nc" class="form-control" 
                                   value="<?php echo htmlspecialchars($ticket['type_nc'] ?? ''); ?>" 
                                   placeholder="Ex: Qualité, Quantité">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Produit Concerné</label>
                            <input type="text" name="produit_concerne" class="form-control" 
                                   value="<?php echo htmlspecialchars($ticket['produit_concerne'] ?? ''); ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Quantité NC</label>
                            <input type="text" name="qte_nc" class="form-control" 
                                   value="<?php echo htmlspecialchars($ticket['qte_nc'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Site SCPC</label>
                            <input type="text" name="site_scpc" class="form-control" 
                                   value="<?php echo htmlspecialchars($ticket['site_scpc'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Autre Site</label>
                            <input type="text" name="site_autre" class="form-control" 
                                   value="<?php echo htmlspecialchars($ticket['site_autre'] ?? ''); ?>">
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Transport -->
            <div class="card mb-4" style="border-radius: var(--radius-lg); border: 1px solid var(--border-color); box-shadow: var(--shadow-sm); overflow: hidden;">
                <div class="card-header" style="background: #f8fafc; padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border-color);">
                    <h3 class="card-title" style="margin: 0; font-size: 1.1rem; font-weight: 800; color: #1e293b; display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-truck" style="color: var(--admin-primary);"></i> Informations Transport
                    </h3>
                </div>
                <div class="card-body" style="padding: 1.75rem;">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Société de Transport</label>
                            <input type="text" name="societe_transport" class="form-control" 
                                   value="<?php echo htmlspecialchars($ticket['societe_transport'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Chauffeur</label>
                            <input type="text" name="chauffeur" class="form-control" 
                                   value="<?php echo htmlspecialchars($ticket['chauffeur'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">N° Facture</label>
                            <input type="text" name="num_facture" class="form-control" 
                                   value="<?php echo htmlspecialchars($ticket['num_facture'] ?? ''); ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Date Facture</label>
                            <input type="date" name="date_facture" class="form-control" 
                                   value="<?php echo $ticket['date_facture'] ?? ''; ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Date Livraison</label>
                            <input type="date" name="date_livraison" class="form-control" 
                                   value="<?php echo $ticket['date_livraison'] ?? ''; ?>">
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Analyse et Actions -->
            <div class="card mb-4" style="border-radius: var(--radius-lg); border: 1px solid var(--border-color); box-shadow: var(--shadow-sm); overflow: hidden;">
                <div class="card-header" style="background: #f8fafc; padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border-color);">
                    <h3 class="card-title" style="margin: 0; font-size: 1.1rem; font-weight: 800; color: #1e293b; display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-microscope" style="color: var(--admin-primary);"></i> Analyse et Actions
                    </h3>
                </div>
                <div class="card-body" style="padding: 1.75rem;">
                    <div class="mb-3">
                        <label class="form-label">Analyse des Causes</label>
                        <textarea name="analyse_causes" class="form-control" rows="3"><?php echo htmlspecialchars($ticket['analyse_causes'] ?? ''); ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Analyse des Conséquences</label>
                        <textarea name="analyse_consequences" class="form-control" rows="3"><?php echo htmlspecialchars($ticket['analyse_consequences'] ?? ''); ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Actions Correctives</label>
                        <textarea name="actions" class="form-control" rows="3"><?php echo htmlspecialchars($ticket['actions'] ?? ''); ?></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Responsable Actions</label>
                            <input type="text" name="responsable_actions" class="form-control" 
                                   value="<?php echo htmlspecialchars($ticket['responsable_actions'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Date Réalisation</label>
                            <input type="date" name="date_realisation" class="form-control" 
                                   value="<?php echo $ticket['date_realisation'] ?? ''; ?>">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Observations</label>
                        <textarea name="observations" class="form-control" rows="2"><?php echo htmlspecialchars($ticket['observations'] ?? ''); ?></textarea>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Colonne Latérale -->
        <div class="col-md-4">
            <!-- Classification -->
            <div class="card mb-4" style="border-radius: var(--radius-lg); border: 1px solid var(--border-color); box-shadow: var(--shadow-sm); overflow: hidden;">
                <div class="card-header" style="background: #f8fafc; padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border-color);">
                    <h4 class="m-0" style="font-size: 1rem; font-weight: 800; color: #1e293b; display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-tags" style="color: var(--admin-primary);"></i> Classification
                    </h4>
                </div>
                <div class="card-body" style="padding: 1.5rem;">
                    <div class="mb-3">
                        <label class="form-label">Catégorie <span style="color: red;">*</span></label>
                        <select name="categorie_id" class="form-control" required>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>" <?php echo $ticket['categorie_id'] == $cat['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['nom']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Statut <span style="color: red;">*</span></label>
                        <select name="statut_id" class="form-control" required>
                            <?php 
                            $statusRMQ = ['En cours de vérification', 'En cours de traitement', 'En attente réponse fournisseur', 'Résolu', 'Refusé'];
                            foreach ($statuts as $statut): 
                                if (in_array($statut['nom'], $statusRMQ)):
                            ?>
                                <option value="<?php echo $statut['id']; ?>" <?php echo $ticket['statut_id'] == $statut['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($statut['nom']); ?>
                                </option>
                            <?php 
                                endif;
                            endforeach; 
                            ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Type de Ticket</label>
                        <select name="type_ticket" class="form-control">
                            <option value="produit" <?php echo $ticket['type_ticket'] === 'produit' ? 'selected' : ''; ?>>Produit</option>
                            <option value="logistique" <?php echo $ticket['type_ticket'] === 'logistique' ? 'selected' : ''; ?>>Logistique</option>
                            <option value="mixte" <?php echo $ticket['type_ticket'] === 'mixte' ? 'selected' : ''; ?>>Mixte</option>
                        </select>
                    </div>
                </div>
            </div>
            
            
            <!-- Informations -->
            <div class="card mb-4" style="border-radius: var(--radius-lg); border: 1px solid var(--border-color); box-shadow: var(--shadow-sm); overflow: hidden;">
                <div class="card-header" style="background: #f8fafc; padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border-color);">
                    <h4 class="m-0" style="font-size: 1rem; font-weight: 800; color: #1e293b; display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-info-circle" style="color: var(--admin-primary);"></i> Informations
                    </h4>
                </div>
                <div class="card-body" style="padding: 1.5rem;">
                    <p><strong>Numéro:</strong> <?php echo htmlspecialchars($ticket['numero_ticket']); ?></p>
                    <p><strong>Créé le:</strong><br><?php echo date('d/m/Y H:i', strtotime($ticket['date_creation'])); ?></p>
                    <?php if ($ticket['date_modification']): ?>
                        <p><strong>Modifié le:</strong><br><?php echo date('d/m/Y H:i', strtotime($ticket['date_modification'])); ?></p>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Actions -->
            <div class="card" style="border-radius: var(--radius-lg); border: 1px solid var(--border-color); box-shadow: var(--shadow-md); overflow: hidden; position: sticky; top: 100px;">
                <div class="card-body" style="padding: 1.75rem; background: #fdfdfd;">
                    <button type="submit" class="btn btn-primary btn-block mb-3" style="width: 100%; padding: 1rem; font-size: 0.95rem; text-transform: uppercase; letter-spacing: 0.05em;">
                        <i class="fas fa-save"></i> Enregistrer
                    </button>
                    <a href="reclamations.php" class="btn btn-outline btn-block" style="width: 100%; padding: 0.8rem; font-weight: 600; color: #64748b; border-color: #e2e8f0;">
                        <i class="fas fa-times"></i> Annuler
                    </a>
                </div>
            </div>
        </div>
    </div>
</form>

<style>
    /* Rafinement des inputs pour cette page */
    .form-control {
        border-radius: 10px;
        padding: 0.75rem 1rem;
        border: 1.5px solid #e2e8f0;
        background-color: #f8fafc;
        transition: all 0.2s ease;
        font-size: 0.9rem;
    }
    .form-control:focus {
        border-color: var(--admin-primary);
        background-color: white;
        box-shadow: 0 0 0 4px rgba(255, 107, 53, 0.1);
        outline: none;
    }
    .form-label {
        color: #475569;
        font-weight: 800;
        font-size: 0.7rem;
        margin-bottom: 0.6rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }
</style>

<?php include __DIR__ . '/includes/footer.php'; ?>
