<?php
/**
 * AJAX Client Stats Drill-down - Premium Dashboard Modal
 * Fichier: pages/aqf/get_client_stats.php
 */
require_once '../../config/database.php';
require_once '../../api/functions.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['aqf', 'rmq'])) {
    die('Accès refusé');
}

$clientId = intval($_GET['id'] ?? 0);
$db = getDB();

// 1. Synthèse Client
$totalGlobal = $db->query("SELECT COUNT(*) FROM tickets")->fetchColumn();
$stmt = $db->prepare("
    SELECT COUNT(*) as total_claims,
           ROUND((COUNT(*) * 100 / ?), 1) as pct_global,
           COUNT(DISTINCT fournisseur_nom, fournisseur_prenom) as total_suppliers,
           ROUND(AVG(DATEDIFF(date_traitement_aqf, date_creation)), 1) as avg_days
    FROM tickets 
    WHERE client_id = ?
");
$stmt->execute([$totalGlobal, $clientId]);
$synthese = $stmt->fetch(PDO::FETCH_ASSOC);

// 2. Top 5 Fournisseurs du client
$stmt = $db->prepare("
    SELECT fournisseur_nom, fournisseur_prenom, COUNT(*) as count,
           ROUND((COUNT(*) * 100 / ?), 1) as pct
    FROM tickets 
    WHERE client_id = ?
    GROUP BY fournisseur_nom, fournisseur_prenom
    ORDER BY count DESC 
    LIMIT 5
");
$stmt->execute([$synthese['total_claims'], $clientId]);
$topSuppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 3. Sites concernés
$stmt = $db->prepare("
    SELECT site_scpc, COUNT(*) as count
    FROM tickets 
    WHERE client_id = ? AND site_scpc IS NOT NULL
    GROUP BY site_scpc
    ORDER BY count DESC
");
$stmt->execute([$clientId]);
$sites = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 4. États de traitement (Groupé Dashboard)
$stmt = $db->prepare("
    SELECT 
        CASE 
            WHEN t.statut_id = 2 THEN 'En traitement'
            WHEN t.statut_id = 4 THEN 'Résolu'
            ELSE 'En attente'
        END as statut_aqf,
        COUNT(*) as count 
    FROM tickets t
    WHERE t.client_id = ?
    GROUP BY statut_aqf
");
$stmt->execute([$clientId]);
$statuts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 5. Liste des 10 derniers tickets
$stmt = $db->prepare("
    SELECT t.id, t.numero_ticket, t.sujet, t.date_creation, 
           s.nom as statut_nom, s.couleur as statut_couleur
    FROM tickets t
    JOIN statuts s ON t.statut_id = s.id
    WHERE t.client_id = ?
    ORDER BY t.date_creation DESC
    LIMIT 10
");
$stmt->execute([$clientId]);
$recentTickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Nom Client
$clientName = $db->query("SELECT CONCAT(IFNULL(prenom,''), ' ', IFNULL(nom,'')) FROM utilisateurs WHERE id = $clientId")->fetchColumn();

// Lien de détail selon le rôle
$detailLinkPrefix = ($_SESSION['user_role'] === 'rmq') ? '../rmq/detail_ticket.php' : 'detail_ticket.php';

// Calculer la moyenne globale pour comparaison
$avgGlobal = $db->query("SELECT AVG(DATEDIFF(date_traitement_aqf, date_creation)) FROM tickets WHERE date_traitement_aqf IS NOT NULL")->fetchColumn();

?>

<!-- MODAL CONTENT: PROFESSIONAL DASHBOARD LAYOUT -->
<div class="modal-dashboard">
    
    <!-- 1. Header Section -->
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 2.5rem; padding-bottom: 1.5rem; border-bottom: 2px solid #F1F5F9;">
        <div>
            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 10px;">
                <span style="background: linear-gradient(135deg, #3B82F6, #2563EB); color: white; padding: 5px 12px; border-radius: 8px; font-size: 0.65rem; font-weight: 800; letter-spacing: 0.08em; text-transform: uppercase;">PROFIL CLIENT</span>
            </div>
            <h3 style="font-size: 2.2rem; font-weight: 950; color: #0F172A; margin: 0; letter-spacing: -0.02em;"><?php echo htmlspecialchars($clientName ?? 'Client Inconnu'); ?></h3>
        </div>
        <div style="text-align: right;">
            <div style="background: linear-gradient(135deg, #FF6B35 0%, #FFA500 100%); padding: 12px 20px; border-radius: 16px; box-shadow: 0 10px 20px rgba(255, 107, 53, 0.15); display: inline-block;">
                <span style="display: block; font-size: 0.7rem; color: rgba(255,255,255,0.9); font-weight: 700; text-transform: uppercase; margin-bottom: 2px; letter-spacing: 0.05em;">Impact Global</span>
                <span style="font-size: 1.4rem; font-weight: 900; color: white;"><?php echo $synthese['pct_global'] ?? 0; ?>%</span>
            </div>
        </div>
    </div>

    <!-- 2. KPI Section -->
    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.25rem; margin-bottom: 2.5rem;">
        <div style="background: #FFF7ED; padding: 1.5rem; border-radius: 20px; border: 1px solid #FFEEDD; display: flex; align-items: center; gap: 1.25rem;">
            <div style="width: 50px; height: 50px; border-radius: 14px; background: #FFEDD5; color: #EA580C; display: flex; align-items: center; justify-content: center; font-size: 1.3rem;">
                <i class="fas fa-file-invoice"></i>
            </div>
            <div>
                <div style="font-size: 0.75rem; font-weight: 800; color: #9A3412; text-transform: uppercase;">Réclamations</div>
                <div style="font-size: 1.8rem; font-weight: 900; color: #7C2D12;"><?php echo $synthese['total_claims'] ?? 0; ?></div>
            </div>
        </div>
        <div style="background: #F0F9FF; padding: 1.5rem; border-radius: 20px; border: 1px solid #E0F2FE; display: flex; align-items: center; gap: 1.25rem;">
            <div style="width: 50px; height: 50px; border-radius: 14px; background: #E0F2FE; color: #0284C7; display: flex; align-items: center; justify-content: center; font-size: 1.3rem;">
                <i class="fas fa-truck-moving"></i>
            </div>
            <div>
                <div style="font-size: 0.75rem; font-weight: 800; color: #075985; text-transform: uppercase;">Fournisseurs</div>
                <div style="font-size: 1.8rem; font-weight: 900; color: #0C4A6E;"><?php echo $synthese['total_suppliers'] ?? 0; ?></div>
            </div>
        </div>
        <div style="background: #F0FDF4; padding: 1.5rem; border-radius: 20px; border: 1px solid #DCFCE7; display: flex; align-items: center; gap: 1.25rem;">
            <div style="width: 50px; height: 50px; border-radius: 14px; background: #DCFCE7; color: #16A34A; display: flex; align-items: center; justify-content: center; font-size: 1.3rem;">
                <i class="fas fa-clock"></i>
            </div>
            <div>
                <div style="font-size: 0.75rem; font-weight: 800; color: #166534; text-transform: uppercase;">Délai Moyen</div>
                <div style="font-size: 1.8rem; font-weight: 900; color: #064E3B;"><?php echo $synthese['avg_days'] ?? '0'; ?> <span style="font-size: 1rem; color: #065F46;">j</span></div>
            </div>
        </div>
    </div>

    <!-- 3. Two-Column Detailed Analytics -->
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2.5rem; margin-bottom: 2.5rem;">
        
        <!-- Left: Recent Tickets -->
        <div>
            <h4 style="font-size: 1.1rem; font-weight: 850; color: #1E293B; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 12px;">
                <span style="width: 32px; height: 32px; background: #FFF5F2; color: #FF6B35; display: flex; align-items: center; justify-content: center; border-radius: 8px;"><i class="fas fa-history" style="font-size: 0.85rem;"></i></span> 
                Dernières Réclamations
            </h4>
            
            <div style="background: white; border-radius: 20px; border: 1px solid #F1F5F9; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02);">
                <table style="width: 100%; border-collapse: collapse; font-size: 0.85rem;">
                    <thead>
                        <tr style="background: #F8FAFC; border-bottom: 1px solid #F1F5F9;">
                            <th style="padding: 1rem; text-align: left; color: #64748B; font-weight: 700;">Ticket</th>
                            <th style="padding: 1rem; text-align: left; color: #64748B; font-weight: 700;">Statut</th>
                            <th style="padding: 1rem; text-align: right; color: #64748B; font-weight: 700;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($recentTickets as $tick): ?>
                        <tr style="border-bottom: 1px solid #F8FAFC;">
                            <td style="padding: 1rem;">
                                <div style="font-weight: 700; color: #1E293B;"><?php echo htmlspecialchars($tick['numero_ticket']); ?></div>
                                <div style="font-size: 0.75rem; color: #94A3B8;"><?php echo date('d/m/Y', strtotime($tick['date_creation'])); ?></div>
                            </td>
                            <td style="padding: 1rem;">
                                <span style="background: <?php echo $tick['statut_couleur']; ?>15; color: <?php echo $tick['statut_couleur']; ?>; padding: 4px 10px; border-radius: 6px; font-weight: 700; font-size: 0.7rem; text-transform: uppercase;">
                                    <?php echo htmlspecialchars($tick['statut_nom']); ?>
                                </span>
                            </td>
                            <td style="padding: 1rem; text-align: right;">
                                <a href="<?php echo $detailLinkPrefix; ?>?id=<?php echo $tick['id']; ?>" class="btn-detail" style="color: #3B82F6; text-decoration: none; font-weight: 800; display: inline-flex; align-items: center; gap: 5px; transition: 0.2s;">
                                    Voir <i class="fas fa-external-link-alt" style="font-size: 0.7rem;"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($recentTickets)): ?>
                        <tr>
                            <td colspan="3" style="padding: 2rem; text-align: center; color: #94A3B8;">Aucune réclamation</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Right: Decisions & Sites -->
        <div style="display: flex; flex-direction: column; gap: 2rem;">
            <!-- Decisions Breakdown -->
            <div>
                <h4 style="font-size: 1.1rem; font-weight: 850; color: #1E293B; margin-bottom: 1.25rem; display: flex; align-items: center; gap: 12px;">
                    <span style="width: 32px; height: 32px; background: #EEF2FF; color: #4F46E5; display: flex; align-items: center; justify-content: center; border-radius: 8px;"><i class="fas fa-balance-scale" style="font-size: 0.85rem;"></i></span> 
                    Décisions Qualité
                </h4>
                <div style="display: grid; grid-template-columns: 1fr; gap: 0.75rem;">
                    <?php if (empty($statuts)): ?>
                        <div style="padding: 1.5rem; text-align: center; background: #FDFDFD; border-radius: 16px; border: 2px dashed #E2E8F0; color: #94A3B8; font-size: 0.85rem;">
                            Aucune expertise finalisée
                        </div>
                    <?php else: ?>
                        <?php foreach($statuts as $st): ?>
                            <?php 
                                $isResolved = ($st['statut_aqf'] === 'Résolu');
                                $isProcessing = ($st['statut_aqf'] === 'En traitement');
                                $color = $isResolved ? '#16A34A' : ($isProcessing ? '#FFA500' : '#8B5CF6');
                            ?>
                            <div style="background: <?php echo $color; ?>08; padding: 1rem 1.5rem; border-radius: 16px; border: 1px solid <?php echo $color; ?>20; display: flex; justify-content: space-between; align-items: center;">
                                <span style="font-weight: 700; font-size: 0.8rem; color: #1E293B;"><?php echo htmlspecialchars($st['statut_aqf']); ?></span>
                                <span style="background: white; color: <?php echo $color; ?>; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; border-radius: 10px; font-weight: 800; border: 1px solid <?php echo $color; ?>20;"><?php echo $st['count']; ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Distribution Site -->
            <div>
                <h4 style="font-size: 1.1rem; font-weight: 850; color: #1E293B; margin-bottom: 1.25rem; display: flex; align-items: center; gap: 12px;">
                     <span style="width: 32px; height: 32px; background: #ECFDF5; color: #059669; display: flex; align-items: center; justify-content: center; border-radius: 8px;"><i class="fas fa-map-pin" style="font-size: 0.85rem;"></i></span>
                     Sites Géographiques
                </h4>
                <div style="display: flex; flex-wrap: wrap; gap: 8px;">
                    <?php if (empty($sites)): ?>
                        <div style="color: #94A3B8; font-size: 0.85rem; font-style: italic;">Non spécifié</div>
                    <?php endif; ?>
                    <?php foreach($sites as $site): ?>
                        <div style="background: #F8FAFC; padding: 8px 14px; border-radius: 12px; border: 1px solid #E2E8F0; font-weight: 700; color: #475569; font-size: 0.8rem; display: flex; align-items: center; gap: 8px;">
                            <?php echo htmlspecialchars($site['site_scpc']); ?>
                            <span style="color: #3B82F6;"><?php echo $site['count']; ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- 4. Top Suppliers -->
    <div style="background: #1E293B; padding: 2rem; border-radius: 24px; color: white;">
        <h4 style="font-size: 1.1rem; font-weight: 800; color: white; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 12px;">
             <i class="fas fa-warehouse" style="color: #FF6B35;"></i> Top Partenaires Fournisseurs
        </h4>
        <div style="display: grid; grid-template-columns: repeat(<?php echo min(3, count($topSuppliers) ?: 1); ?>, 1fr); gap: 1.5rem;">
            <?php foreach($topSuppliers as $idx => $s): ?>
                <div>
                    <div style="display: flex; justify-content: space-between; font-size: 0.75rem; margin-bottom: 6px; font-weight: 600;">
                        <span style="color: #94A3B8;"><?php echo htmlspecialchars($s['fournisseur_nom'] ?? 'N/A'); ?></span>
                        <span style="color: #FF6B35;"><?php echo $s['pct'] ?? 0; ?>%</span>
                    </div>
                    <div style="height: 6px; background: rgba(255,255,255,0.05); border-radius: 10px; overflow: hidden;">
                        <div style="width: <?php echo $s['pct'] ?? 0; ?>%; height: 100%; background: #FF6B35; border-radius: 10px;"></div>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if (empty($topSuppliers)): ?>
                <div style="color: #64748B; font-style: italic;">Aucun fournisseur assigné</div>
            <?php endif; ?>
        </div>
    </div>

</div>

<style>
    .btn-detail:hover { transform: translateX(3px); color: #2563EB !important; }
    .modal-dashboard { font-family: 'Inter', sans-serif; }
</style>
