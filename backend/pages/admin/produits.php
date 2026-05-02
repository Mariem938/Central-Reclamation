<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/functions.php';
$pageTitle = "Produits - Central Réclamation";
include 'includes/header.php';
?>
<div class="page-header">
    <h2 style="font-size: 1.875rem; font-weight: 700;">Produits</h2>
    <p style="color: var(--text-muted);">Gestion des partenaires de transport.</p>
</div>
<div class="card" style="margin-top: 2rem; text-align: center; padding: 4rem;">
    <i class="fa-solid fa-box" style="font-size: 4rem; color: var(--border-color); margin-bottom: 1rem;"></i>
    <p>Cette section est en cours de migration.</p>
</div>
<?php include 'includes/footer.php'; ?>
