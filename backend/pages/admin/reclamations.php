<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/functions.php';

$pageTitle = "Réclamations - Central Réclamation";
$db = getDB();

// Récupérer toutes les réclamations
$tickets = $db->query("
    SELECT t.*, s.nom as statut_nom, c.nom as categorie_nom 
    FROM tickets t 
    JOIN statuts s ON t.statut_id = s.id 
    JOIN categories c ON t.categorie_id = c.id 
    ORDER BY t.date_creation DESC
")->fetchAll();

include 'includes/header.php';
?>

<?php
// TRAITEMENT SOUMISSION NOUVELLE RÉCLAMATION (ADMIN)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_admin']) && $_POST['action_admin'] === 'create_ticket') {
    require_once '../../api/functions_extended.php';
    
    // Logique identique à pages/client/nouvelle_reclamation.php
    $site_scpc = cleanInput($_POST['site_scpc'] ?? '');
    $site_autre = cleanInput($_POST['site_autre'] ?? '');
    
    if ($site_scpc === 'autre' && empty($site_autre)) {
        $msg_error = 'Veuillez préciser le site.';
    } elseif (empty($site_scpc)) {
        $msg_error = 'Veuillez sélectionner un site SCPC.';
    } else {
        $categories_cochees = $_POST['categorie'] ?? [];
        if (!is_array($categories_cochees)) $categories_cochees = [$categories_cochees];
        $categories_cochees = array_map('strtolower', $categories_cochees);
        $categorie_value = implode(',', $categories_cochees);
        
        // Déterminer l'ID catégorie (3: Produit, 10: Service, 7: Réclamation Client)
        $categorie_id = 7; 
        if (in_array('produit', $categories_cochees)) {
            $categorie_id = 3;
        } elseif (in_array('service', $categories_cochees)) {
            $categorie_id = 10;
        }

        $type_nc_raw = $_POST['type_nc'] ?? [];
        $type_nc_autre = cleanInput($_POST['type_nc_autre'] ?? '');
        if (in_array('Autre', $type_nc_raw) && !empty($type_nc_autre)) {
            $type_nc_raw = array_map(function($val) use ($type_nc_autre) {
                return ($val === 'Autre') ? 'Autre: ' . $type_nc_autre : $val;
            }, $type_nc_raw);
        }
        $type_nc = implode(',', $type_nc_raw);

        $description = cleanInput($_POST['description'] ?? '');
        $sujet = trim($description);
        if (strlen($sujet) > 50) $sujet = substr($sujet, 0, 47) . '...';

        try {
            $result = creerTicket([
                'nom' => cleanInput($_POST['nom'] ?? ''),
                'prenom' => cleanInput($_POST['prenom'] ?? ''),
                'email' => cleanInput($_POST['email'] ?? ''),
                'telephone' => cleanInput($_POST['telephone'] ?? ''),
                'code_client' => cleanInput($_POST['code_client'] ?? ''),
                'nom_societe' => cleanInput($_POST['nom_societe'] ?? ''),
                'matricule' => cleanInput($_POST['matricule'] ?? ''),
                'sujet' => $sujet,
                'description' => $description,
                'categorie' => $categorie_id,
                'site_scpc' => $site_scpc,
                'site_autre' => ($site_scpc === 'autre') ? $site_autre : null,
                'statut_id' => 1, // En cours de vérification par défaut pour l'admin
                'type_ticket' => $categorie_value,
                'type_nc' => $type_nc,
                // PRODUITS MULTIPLES
                'produit_concerne' => is_array($_POST['produit_concerne'] ?? '') 
                    ? implode(' | ', array_map('cleanInput', $_POST['produit_concerne'])) 
                    : cleanInput($_POST['produit_concerne'] ?? ''),
                
                'qte_nc' => is_array($_POST['qte_nc'] ?? '') 
                    ? implode(' | ', array_map('cleanInput', $_POST['qte_nc'])) 
                    : cleanInput($_POST['qte_nc'] ?? ''),
                    
                'unite_nc' => is_array($_POST['unite_nc'] ?? '') 
                    ? implode(' | ', array_map('cleanInput', $_POST['unite_nc'])) 
                    : cleanInput($_POST['unite_nc'] ?? ''),
                'societe_transport' => cleanInput($_POST['societe_transport'] ?? ''),
                'chauffeur' => cleanInput($_POST['chauffeur'] ?? ''),
                'date_livraison' => !empty($_POST['date_livraison']) ? $_POST['date_livraison'] : null,
                'num_facture' => cleanInput($_POST['num_facture'] ?? ''),
                'date_facture' => !empty($_POST['date_facture']) ? $_POST['date_facture'] : null,
                'demande_client' => cleanInput($_POST['demande_client'] ?? ''),
                'analyse_causes' => cleanInput($_POST['analyse_causes'] ?? ''),
                'analyse_consequences' => cleanInput($_POST['analyse_consequences'] ?? ''),
                'notes' => cleanInput($_POST['notes'] ?? ''),
                'est_valide_par_rmq' => 1,
                'is_commercial' => false
            ]);
        } catch (Exception $e) {
            $msg_error = $e->getMessage();
            $result = ['success' => false];
        }

        if ($result['success']) {
            // Upload Fichiers
            if (!empty($_FILES['fichiers']['name'][0])) {
                foreach ($_FILES['fichiers']['tmp_name'] as $key => $tmpName) {
                    if (!empty($tmpName)) {
                        $fileData = ['name'=>$_FILES['fichiers']['name'][$key], 'type'=>$_FILES['fichiers']['type'][$key], 'tmp_name'=>$tmpName, 'error'=>$_FILES['fichiers']['error'][$key], 'size'=>$_FILES['fichiers']['size'][$key]];
                        uploadFichier($fileData, $result['ticket_id']);
                    }
                }
            }
            $_SESSION['success_message'] = "Réclamation #{$result['numero_ticket']} créée avec succès.";
            echo "<script>window.location.href='reclamations.php';</script>";
            exit;
        } else {
            $msg_error = $result['error'];
        }
    }
}
?>

<div class="page-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
    <div>
        <h2 style="font-size: 1.75rem; font-weight: 800; letter-spacing: -0.03em; margin: 0;"><i class="fas fa-clipboard-list" style="color: var(--admin-primary); font-size: 1.5rem; margin-right: 0.5rem;"></i> Réclamations</h2>
        <p style="color: var(--text-muted); font-size: 0.95rem; margin-top: 0.25rem;">Gérez l'ensemble des dossiers clients en temps réel.</p>
    </div>
    <div style="display: flex; gap: 0.75rem;">
        <button class="btn btn-primary btn-sm" onclick="openModal('addTicketModal')">
            <i class="fa-solid fa-plus-circle"></i>
            Nouvelle Réclamation
        </button>
        <a href="index.php" class="btn btn-outline btn-sm"><i class="fas fa-arrow-left"></i> Retour</a>
    </div>
</div>

<?php if(isset($msg_error)): ?>
    <div class="alert alert-danger" style="margin-bottom: 1.5rem; padding: 1rem; border-radius: 8px; background: #fee2e2; color: #991b1b; border: 1px solid #fecaca;">
        <i class="fas fa-exclamation-circle"></i> <?php echo $msg_error; ?>
    </div>
<?php endif; ?>

<?php if(isset($_SESSION['success_message'])): ?>
    <div class="alert alert-success" style="margin-bottom: 1.5rem; padding: 1rem; border-radius: 8px; background: #dcfce7; color: #166534; border: 1px solid #bbf7d0;">
        <i class="fas fa-check-circle"></i> <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
    </div>
<?php endif; ?>

<?php if(isset($_SESSION['error_message'])): ?>
    <div class="alert alert-danger" style="margin-bottom: 1.5rem; padding: 1rem; border-radius: 8px; background: #fee2e2; color: #991b1b; border: 1px solid #fecaca;">
        <i class="fas fa-exclamation-circle"></i> <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
    </div>
<?php endif; ?>

<div class="card">
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Ticket</th>
                    <th>Client</th>
                    <th>Sujet</th>
                    <th>Catégorie</th>
                    <th>Statut</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tickets as $ticket): ?>
                <tr>
                    <td style="font-weight: 800; color: var(--admin-primary);">#<?php echo $ticket['numero_ticket']; ?></td>
                    <td>
                        <div style="font-weight: 700; color: #0f172a;"><?php echo htmlspecialchars($ticket['prenom_client'] . ' ' . $ticket['nom_client']); ?></div>
                        <div style="font-size: 0.8rem; color: var(--text-muted); font-weight: 500;"><?php echo htmlspecialchars($ticket['email_client']); ?></div>
                    </td>
                    <td><?php echo htmlspecialchars($ticket['sujet']); ?></td>
                    <td><?php echo htmlspecialchars($ticket['categorie_nom']); ?></td>
                    <td><?php echo getStatutBadge($ticket['statut_nom']); ?></td>
                    <td><?php echo formatDateTime($ticket['date_creation']); ?></td>
                    <td>
                        <div style="display: flex; gap: 0.4rem; justify-content: flex-end;">
                            <a href="view_ticket.php?id=<?php echo $ticket['id']; ?>" class="btn btn-sm btn-outline-primary" title="Voir">
                                <i class="fa-solid fa-eye"></i>
                            </a>
                            <a href="edit_claim.php?id=<?php echo $ticket['id']; ?>" class="btn btn-sm btn-outline-secondary" title="Modifier">
                                <i class="fa-solid fa-pen"></i>
                            </a>
                            <button onclick="confirmDelete(<?php echo $ticket['id']; ?>, '<?php echo $ticket['numero_ticket']; ?>')" class="btn btn-sm btn-outline-danger" title="Supprimer">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal pour nouvelle réclamation -->
<div id="addTicketModal" class="modal" style="display:<?php echo isset($msg_error)?'block':'none'; ?>; position:fixed; z-index:2000; left:0; top:0; width:100%; height:100%; background:rgba(0, 0, 0, 0.5); backdrop-filter: blur(4px);">
    <div class="modal-content" style="background:transparent; margin:2% auto; padding:0; border-radius:1.5rem; width:95%; max-width:1100px; box-shadow: none; border: none; overflow: visible; animation: slideDown 0.4s cubic-bezier(0.16, 1, 0.3, 1);">
        <!-- Close button (floating) -->
        <button onclick="closeModal('addTicketModal')" style="position: absolute; right: 20px; top: 20px; z-index: 10; background:rgba(255,255,255,0.2); border:none; cursor:pointer; font-size:1.1rem; color: white; width: 35px; height: 35px; border-radius: 50%; display: flex; align-items: center; justify-content: center; transition: all 0.2s; backdrop-filter: blur(4px);">
            <i class="fas fa-times"></i>
        </button>
        
        <!-- Modal Body -->
        <div style="padding: 0; max-height: 90vh; overflow-y: auto;">
            <?php 
            unset($ticket); // Assurer que le formulaire est vide
            $contexte = 'admin_modal';
            $categories = getCategories();
            $sites = getSites();
            include '../includes/form_reclamation_inc.php'; 
            ?>
        </div>
    </div>
</div>

<style>
@keyframes slideDown {
    from { transform: translateY(-20px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}
/* Surcharges pour le formulaire dans la modale */
#addTicketModal .glass-card { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); }
</style>

<script>
function openModal(id) {
    document.getElementById(id).style.display = 'block';
}
function closeModal(id) {
    document.getElementById(id).style.display = 'none';
}
// Ajouter un champ caché pour l'action admin dans le formulaire inclus
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('#addTicketModal form');
    if (form) {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'action_admin';
        input.value = 'create_ticket';
        form.appendChild(input);
        
        // Modifier l'action du bouton annuler de l'include
        const cancelBtn = form.querySelector('.btn-secondary');
        if (cancelBtn) {
            cancelBtn.type = 'button';
            cancelBtn.setAttribute('onclick', "closeModal('addTicketModal')");
        }
    }
});

function confirmDelete(id, numero) {
    if (confirm("Voulez-vous vraiment supprimer la réclamation #" + numero + " ? Cette action est irréversible.")) {
        window.location.href = "delete_ticket.php?id=" + id;
    }
}
</script>

<?php include 'includes/footer.php'; ?>
