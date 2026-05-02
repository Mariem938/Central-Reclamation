<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/functions.php';

$pageTitle = "Fournisseurs - Central Réclamation";
$db = getDB();

// Récupérer tous les fournisseurs (basé sur le rôle 'fournisseur' dans la table utilisateurs)
$fournisseurs = $db->query("
    SELECT * FROM utilisateurs 
    WHERE role = 'fournisseur' 
    ORDER BY nom ASC
")->fetchAll();

include 'includes/header.php';
?>

<div class="page-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
    <div>
        <h2 style="font-size: 1.875rem; font-weight: 700;">Fournisseurs</h2>
        <p style="color: var(--text-muted);">Gérez vos partenaires fournisseurs.</p>
    </div>
    <button class="btn btn-primary">
        <i class="fa-solid fa-plus"></i>
        Ajouter un Fournisseur
    </button>
</div>

<div class="card">
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Nom</th>
                    <th>Entreprise</th>
                    <th>Email</th>
                    <th>Téléphone</th>
                    <th>Statut</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($fournisseurs as $f): ?>
                <tr>
                    <td style="font-weight: 600;"><?php echo htmlspecialchars($f['prenom'] . ' ' . $f['nom']); ?></td>
                    <td><?php echo htmlspecialchars($f['entreprise'] ?? 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($f['email']); ?></td>
                    <td><?php echo htmlspecialchars($f['telephone'] ?? 'N/A'); ?></td>
                    <td>
                        <?php if ($f['actif']): ?>
                            <span class="badge bg-green-100 text-green-800">Actif</span>
                        <?php else: ?>
                            <span class="badge bg-red-100 text-red-800">Inactif</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div style="display: flex; gap: 0.5rem;">
                            <button class="btn btn-outline btn-sm"><i class="fa-solid fa-pen"></i></button>
                            <button class="btn btn-outline btn-sm" style="color: var(--danger-color);"><i class="fa-solid fa-trash"></i></button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
