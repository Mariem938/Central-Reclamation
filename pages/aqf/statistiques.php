<?php
/**
 * Statistiques AQF - Version Premium Coordonnée (Original Data + RMQ Style)
 * Fichier: pages/aqf/statistiques.php
 */

require_once '../../config/database.php';
require_once '../../api/functions.php';

// Vérifier que l'utilisateur est AQF
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'aqf') {
    header('Location: ../agent/login.php?role=aqf');
    exit;
}

$db = getDB();
$userName = $_SESSION['user_prenom'] ?? 'AQF';

// --- 1. INDICATEURS GLOBAUX (SCOPE AQF: AVEC FOURNISSEUR) ---
$aqfFilter = "(fournisseur_nom IS NOT NULL AND fournisseur_nom != '')";

$totalClients = $db->query("SELECT COUNT(DISTINCT client_id) FROM tickets WHERE $aqfFilter")->fetchColumn();
$totalFournisseurs = $db->query("SELECT COUNT(DISTINCT fournisseur_nom, fournisseur_prenom) FROM tickets WHERE $aqfFilter")->fetchColumn();
$totalClaims = $db->query("SELECT COUNT(*) FROM tickets WHERE $aqfFilter")->fetchColumn();
$avgDuréeQuery = $db->query("SELECT ROUND(AVG(DATEDIFF(date_traitement_aqf, date_creation)), 1) FROM tickets WHERE $aqfFilter AND date_traitement_aqf IS NOT NULL");
$avgDuration = $avgDuréeQuery->fetchColumn() ?: 0;

// --- 2. RÉPARTITION DES STATUTS (ALIGNÉ DASHBOARD) ---
$stmt = $db->query("
    SELECT 
        CASE 
            WHEN t.statut_id = 2 THEN 'En traitement'
            WHEN t.statut_id = 4 THEN 'Résolu'
            ELSE 'En attente'
        END as nom,
        COUNT(*) as total,
        CASE 
            WHEN t.statut_id = 2 THEN '#FFA500'
            WHEN t.statut_id = 4 THEN '#4CAF50'
            ELSE '#8B5CF6'
        END as couleur
    FROM tickets t
    WHERE $aqfFilter
    GROUP BY nom
");
$statusDistri = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- 3. TOP 5 FOURNISSEURS (ORIGINAL) ---
$stmt = $db->query("
    SELECT fournisseur_nom, fournisseur_prenom, COUNT(*) as count,
           ROUND((COUNT(*) * 100 / (SELECT COUNT(*) FROM tickets WHERE $aqfFilter)), 1) as pct
    FROM tickets 
    WHERE $aqfFilter
    GROUP BY fournisseur_nom, fournisseur_prenom
    ORDER BY count DESC 
    LIMIT 5
");
$topSuppliersTable = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- 4. RÉPARTITION PAR SITE (ORIGINAL) ---
$stmt = $db->query("
    SELECT site_scpc, COUNT(*) as count,
           ROUND((COUNT(*) * 100 / (SELECT COUNT(*) FROM tickets WHERE $aqfFilter)), 1) as pct
    FROM tickets 
    WHERE $aqfFilter AND site_scpc IS NOT NULL
    GROUP BY site_scpc
    ORDER BY count DESC
");
$siteDistri = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- 5. TABLEAU CLIENTS (ORIGINAL + INTERACTIF) ---
$stmt = $db->query("
    SELECT u.id as client_id, u.nom, u.prenom, u.code_client, COUNT(t.id) as total_claims,
           ROUND((COUNT(t.id) * 100 / (SELECT COUNT(*) FROM tickets WHERE $aqfFilter)), 1) as pct
    FROM tickets t
    LEFT JOIN utilisateurs u ON t.client_id = u.id
    WHERE $aqfFilter
    GROUP BY u.id
    ORDER BY total_claims DESC
");
$clientClaims = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- 6. DURÉE DE TRAITEMENT (MIN, MAX, AVG) ---
$durationMetrics = $db->query("
    SELECT 
        MIN(DATEDIFF(date_traitement_aqf, date_creation)) as min_days,
        MAX(DATEDIFF(date_traitement_aqf, date_creation)) as max_days,
        AVG(DATEDIFF(date_traitement_aqf, date_creation)) as avg_days
    FROM tickets 
    WHERE $aqfFilter AND date_traitement_aqf IS NOT NULL
")->fetch(PDO::FETCH_ASSOC);

// --- 7. RÉPARTITION PAR INTERVALLES (CHART) ---
$intervals = $db->query("
    SELECT 
        SUM(CASE WHEN DATEDIFF(date_traitement_aqf, date_creation) <= 3 THEN 1 ELSE 0 END) as '0_3',
        SUM(CASE WHEN DATEDIFF(date_traitement_aqf, date_creation) > 3 AND DATEDIFF(date_traitement_aqf, date_creation) <= 7 THEN 1 ELSE 0 END) as '4_7',
        SUM(CASE WHEN DATEDIFF(date_traitement_aqf, date_creation) > 7 THEN 1 ELSE 0 END) as 'over_7'
    FROM tickets 
    WHERE $aqfFilter AND date_traitement_aqf IS NOT NULL
")->fetch(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Statistiques AQF - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/fontawesome/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary: #3B82F6;
            --secondary: #334155;
            --success: #10B981;
            --bg-page: #f8fafc;
            --card-shadow: 0 10px 40px -10px rgba(0,0,0,0.08);
            --card-radius: 24px;
        }

        body { 
            background: var(--bg-page); 
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            color: #334155;
            line-height: 1.6;
        }
        
        .analytics-container {
            max-width: 1400px;
            margin: 3rem auto;
            padding: 0 2rem;
        }

        .page-header {
            margin-bottom: 4rem;
            position: relative;
        }

        .page-header h1 {
            font-size: 2.8rem;
            font-weight: 900;
            color: #0f172a;
            letter-spacing: -0.03em;
            margin-bottom: 0.5rem;
        }

        .page-header p {
            font-size: 1.15rem;
            color: #64748b;
            font-weight: 500;
        }

        /* KPIs Grid (RMQ Style) */
        .premium-stat-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 2rem;
            margin-bottom: 4rem;
        }

        .premium-kpi-card {
            background: white;
            padding: 2.2rem 1.75rem;
            border-radius: var(--card-radius);
            text-align: center;
            box-shadow: var(--card-shadow);
            border: 1px solid rgba(226, 232, 240, 0.6);
            transition: all 0.4s cubic-bezier(0.23, 1, 0.32, 1);
            position: relative;
            overflow: hidden;
            z-index: 1;
        }

        .premium-kpi-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.12);
            border-color: var(--card-color);
        }

        .premium-kpi-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; width: 100%; height: 6px;
            background: var(--card-color);
        }

        .kpi-icon-wrapper {
            width: 60px;
            height: 60px;
            border-radius: 18px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.6rem;
            color: white;
            background: var(--card-color);
            margin-bottom: 1.5rem;
            box-shadow: 0 10px 20px -5px var(--card-color);
        }

        .kpi-title { font-size: 0.8rem; font-weight: 800; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.15em; margin-bottom: 0.75rem; }
        .kpi-number { font-size: 2.8rem; font-weight: 900; color: #0f172a; line-height: 1; }

        .glass-card {
            background: white;
            padding: 2.5rem;
            border-radius: var(--card-radius);
            box-shadow: var(--card-shadow);
            border: 1px solid rgba(226, 232, 240, 0.8);
            margin-bottom: 2.5rem;
        }

        h2 {
            font-size: 1.5rem;
            color: #0f172a;
            margin-bottom: 2.5rem;
            display: flex;
            align-items: center;
            gap: 1.25rem;
            font-weight: 850;
        }

        h2 i {
            width: 44px;
            height: 44px;
            background: #f8fafc;
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            font-size: 1.1rem;
            border: 1px solid #e2e8f0;
        }

        .data-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0 0.8rem;
        }

        .data-table th { text-align: left; padding: 0 1.5rem 1rem; color: #94a3b8; font-size: 0.8rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.1em; }
        .data-table td { background: #ffffff; padding: 1.5rem; font-size: 1rem; border-top: 1px solid #f1f5f9; border-bottom: 1px solid #f1f5f9; transition: all 0.3s; }
        .data-table tr:hover td { background: #f8fbff; }
        .data-table td:first-child { border-radius: 18px 0 0 18px; border-left: 1px solid #f1f5f9; font-weight: 700; color: #0f172a; }
        .data-table td:last-child { border-radius: 0 18px 18px 0; border-right: 1px solid #f1f5f9; text-align: right; }

        .count-pill { background: #f0f7ff; color: var(--primary); padding: 0.5rem 1rem; border-radius: 12px; font-weight: 800; font-size: 1rem; border: 1px solid rgba(59,130,246,0.1); }

        /* Row hover visual cue */
        .client-row { transition: all 0.2s ease; }
        .client-row:hover td { background: #f0f7ff !important; }
        .client-row:hover .fas.fa-chevron-right { transform: translateX(3px); color: var(--primary) !important; }

        /* Modal RMQ Style */
        .modal { display: none; position: fixed; z-index: 3000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(15,23,42,0.15); backdrop-filter: blur(12px); overflow-y: auto; padding: 2rem 0; }
        .modal-content { background: #FAFBFD; margin: 2rem auto; padding: 3.5rem; border-radius: 32px; max-width: 950px; border: 1px solid white; box-shadow: 0 40px 100px -20px rgba(0,0,0,0.1); position: relative; animation: slideUp 0.6s cubic-bezier(0.16, 1, 0.3, 1); }
        @keyframes slideUp { from { transform: translateY(30px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        .close-modal { position: absolute; right: 2.5rem; top: 2.5rem; width: 44px; height: 44px; display: flex; align-items: center; justify-content: center; background: white; border-radius: 50%; box-shadow: 0 4px 10px rgba(0,0,0,0.05); cursor: pointer; transition: all 0.3s; border: 1px solid #f1f5f9; }
        .close-modal:hover { transform: rotate(90deg); color: var(--primary); }

        .dur-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem; }
        .dur-item { padding: 2rem; border-radius: 24px; text-align: center; border: 1px solid #F1F5F9; background: white; transition: all 0.3s; }
        .dur-item:hover { border-color: var(--primary); transform: translateY(-3px); }
        .dur-val { font-size: 2.4rem; font-weight: 900; color: #0f172a; line-height: 1; }
        .dur-label { font-size: 0.75rem; color: #94a3b8; text-transform: uppercase; font-weight: 700; margin-top: 1rem; letter-spacing: 0.05em; }

        .custom-scrollbar::-webkit-scrollbar { width: 6px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: #f1f5f9; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }

        @media (max-width: 1024px) { .premium-stat-grid { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 600px) { .premium-stat-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>

    <nav class="navbar" style="background: white; border-bottom: 1px solid #F1F5F9;">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php" style="display: flex; align-items: center; gap: 10px;">
                <img src="../../assets/images/logo.png" alt="Logo SCPC" style="height: 40px; width: auto;">
                <div style="display: flex; flex-direction: column; line-height: 1.2;">
                    <span style="font-weight: 700; font-size: 1.1rem; color: #1A1A1A;">SCPC Comptoir Hammami</span>
                    <span style="font-size: 0.75rem; color: #666; font-weight: 500;"><?php echo SITE_NAME; ?></span>
                </div>
                <!-- Badge spécifique AQF -->
                <span class="navbar-brand-badge" style="background: #2563EB;">
                    AQF
                </span>
            </a>
            
            <div class="navbar-menu" id="mainMenu">
                <a href="dashboard.php" class="navbar-link">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
                <a href="statistiques.php" class="navbar-link active">
                    <i class="fas fa-chart-bar"></i>
                    <span>Statistiques</span>
                </a>
                <a href="../../pages/logout.php" class="navbar-link">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Déconnexion</span>
                </a>
            </div>
            
            <button id="navbarToggler" class="navbar-toggler">
                <i class="fas fa-bars"></i>
            </button>
        </div>
    </nav>

    <!-- Mobile Menu -->
    <div class="mobile-menu" id="mobileMenu">
        <div class="mobile-menu-header">
            <i class="fas fa-clipboard-check"></i>
            <strong>AQF</strong>
            <p class="mobile-menu-subtitle">Responsable Qualité</p>
        </div>
        <a href="dashboard.php" class="mobile-menu-link"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a>
        <a href="statistiques.php" class="mobile-menu-link active"><i class="fas fa-chart-bar"></i> <span>Statistiques</span></a>
        <a href="../../pages/logout.php" class="mobile-menu-link" style="color: var(--danger-color) !important;"><i class="fas fa-sign-out-alt"></i> <span>Déconnexion</span></a>
    </div>

    <div class="analytics-container">
        
        <header class="page-header">
            <h1>Expertise Qualité & Data</h1>
            <p>Vue panoramique sur la performance opérationnelle et les flux de réclamations.</p>
        </header>

        <!-- KPI GRID (4 ORIGINALS - RMQ STYLE) -->
        <div class="premium-stat-grid">
            <div class="premium-kpi-card" style="--card-color: #FF8A65;">
                <div class="kpi-icon-wrapper"><i class="fas fa-users"></i></div>
                <div class="kpi-title">Clients Réclamants</div>
                <div class="kpi-number"><?php echo $totalClients; ?></div>
            </div>
            <div class="premium-kpi-card" style="--card-color: #3B82F6;">
                <div class="kpi-icon-wrapper"><i class="fas fa-warehouse"></i></div>
                <div class="kpi-title">Fournisseurs AQF</div>
                <div class="kpi-number"><?php echo $totalFournisseurs; ?></div>
            </div>
            <div class="premium-kpi-card" style="--card-color: #10B981;">
                <div class="kpi-icon-wrapper"><i class="fas fa-file-invoice"></i></div>
                <div class="kpi-title">Nombre de Réclamations</div>
                <div class="kpi-number"><?php echo $totalClaims; ?></div>
            </div>
            <div class="premium-kpi-card" style="--card-color: #9575CD;">
                <div class="kpi-icon-wrapper"><i class="fas fa-clock"></i></div>
                <div class="kpi-title">Délai Moyen AQF</div>
                <div class="kpi-number"><?php echo $avgDuration; ?><span style="font-size: 1.25rem; margin-left: 4px;">j</span></div>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1.4fr 1fr; gap: 2.5rem; margin-bottom: 2.5rem;">
            <!-- CLIENTS TABLE -->
            <div class="glass-card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2.5rem;">
                    <h2 style="margin:0;"><i class="fas fa-user-circle"></i> Réclamations par Client</h2>
                    <div style="display: flex; align-items: center; gap: 1.5rem;">
                        <span style="font-size: 0.8rem; color: #3B82F6; font-weight: 700; background: #f0f7ff; padding: 6px 12px; border-radius: 8px; border: 1px solid rgba(59,130,246,0.1);">
                            <i class="fas fa-mouse-pointer"></i> Cliquez pour détails
                        </span>
                        <div style="position: relative; width: 300px;">
                            <i class="fas fa-filter" style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: #94a3b8;"></i>
                            <input type="text" id="clientSearch" placeholder="Chercher un client..." style="width: 100%; padding: 0.7rem 1rem 0.7rem 2.8rem; border-radius: 12px; border: 1px solid #e2e8f0; font-size: 0.9rem;">
                        </div>
                    </div>
                </div>
                <div style="max-height: 480px; overflow-y: auto;" class="custom-scrollbar">
                    <table class="data-table" id="clientsTable">
                        <thead>
                            <tr><th>Client / Entité</th><th>Nombre</th><th style="text-align: center;">Impact</th><th style="text-align: right;">Détails</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach($clientClaims as $c): ?>
                            <tr onclick="openClientDetail(<?php echo $c['client_id']; ?>, '<?php echo addslashes($c['prenom'] . ' ' . $c['nom']); ?>')" style="cursor: pointer;" class="client-row">
                                <td>
                                    <div style="font-weight: 700; color: #1e293b;"><?php echo htmlspecialchars($c['prenom'] . ' ' . $c['nom']); ?></div>
                                    <div style="font-size: 0.75rem; color: #94a3b8; font-family: monospace; font-weight: 600;"><?php echo htmlspecialchars($c['code_client'] ?: 'N/A'); ?></div>
                                </td>
                                <td><span class="count-pill"><?php echo $c['total_claims']; ?></span></td>
                                <td style="text-align: center; color: #94A3B8; font-weight: 600;"><?php echo $c['pct']; ?>%</td>
                                <td style="text-align: right;"><i class="fas fa-chevron-right" style="color: #cbd5e1; font-size: 0.8rem;"></i></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- DECISIONS CHART -->
            <div class="glass-card">
                <h2><i class="fas fa-chart-pie"></i> État des expertises AQF</h2>
                <div style="height: 380px;">
                    <canvas id="statusChart"></canvas>
                </div>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2.5rem; margin-bottom: 2.5rem;">
            <!-- TOP SUPPLIERS -->
            <div class="glass-card">
                <h2><i class="fas fa-box"></i> Top 5 Fournisseurs (Volume)</h2>
                <table class="data-table">
                    <thead><tr><th>Fournisseur</th><th>Nombre</th><th style="text-align: right;">Part</th></tr></thead>
                    <tbody>
                        <?php foreach($topSuppliersTable as $idx => $s): ?>
                        <tr>
                            <td>
                                <span style="font-weight: 850; color: #FF8A65; margin-right: 12px; font-size: 0.85rem;">#<?php echo $idx+1; ?></span>
                                <?php echo htmlspecialchars($s['fournisseur_prenom'] . ' ' . $s['fournisseur_nom']); ?>
                            </td>
                            <td><span class="count-pill"><?php echo $s['count']; ?></span></td>
                            <td style="text-align: right; font-weight: 700; color: #64748b;"><?php echo $s['pct']; ?>%</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- SITE DISTRIBUTION -->
            <div class="glass-card">
                <h2><i class="fas fa-map-pin"></i> Distribution par Site SCPC</h2>
                <table class="data-table">
                    <thead><tr><th>Site géographique</th><th style="text-align: right;">Réclamations</th></tr></thead>
                    <tbody>
                        <?php foreach($siteDistri as $sd): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($sd['site_scpc'] ?: 'Non spécifié'); ?></td>
                            <td style="text-align: right;"><span class="count-pill"><?php echo $sd['count']; ?></span> <span style="color: #94A3B8; margin-left:12px; font-weight:600; font-size:0.85rem;"><?php echo $sd['pct']; ?>%</span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- TEMPORAL ANALYSIS -->
        <div class="glass-card">
            <h2><i class="fas fa-chart-line"></i> Analyse temporelle (Réponse AQF)</h2>
            <div style="display: grid; grid-template-columns: 1fr 1.2fr; gap: 4rem;">
                <div class="dur-row">
                    <div class="dur-item">
                        <div class="dur-val"><?php echo $durationMetrics['avg_days'] !== null ? round($durationMetrics['avg_days'], 1) : '--'; ?></div>
                        <div class="dur-label">Moyenne</div>
                    </div>
                    <div class="dur-item">
                        <div class="dur-val"><?php echo $durationMetrics['min_days'] !== null ? $durationMetrics['min_days'] : '--'; ?></div>
                        <div class="dur-label">Minimum</div>
                    </div>
                    <div class="dur-item">
                        <div class="dur-val"><?php echo $durationMetrics['max_days'] !== null ? $durationMetrics['max_days'] : '--'; ?></div>
                        <div class="dur-label">Maximum</div>
                    </div>
                </div>
                <div style="height: 250px; position: relative;">
                    <?php if ((int)$intervals['0_3'] + (int)$intervals['4_7'] + (int)$intervals['over_7'] === 0): ?>
                        <div style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; background: rgba(255,255,255,0.7); z-index: 10; border-radius: 12px; border: 1px dashed #cbd5e1;">
                            <p style="color: #94a3b8; font-weight: 600; font-size: 0.9rem;"> <i class="fas fa-info-circle"></i> Données en attente d'expertise</p>
                        </div>
                    <?php endif; ?>
                    <canvas id="durationChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL PREMIUM (Drill-down) -->
    <div id="clientModal" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeModal()"><i class="fas fa-times"></i></span>
            <div id="modalBody">
                <div style="text-align: center; padding: 4rem;">
                    <i class="fas fa-circle-notch fa-spin" style="font-size: 2.5rem; color: var(--primary);"></i>
                    <p style="margin-top:20px; font-weight:600; color:#94A3B8;">Analyses détaillées en cours...</p>
                </div>
            </div>
        </div>
    </div>

    <script>
    function openClientDetail(clientId, clientName) {
        const modal = document.getElementById('clientModal');
        const modalBody = document.getElementById('modalBody');
        modal.style.display = "block";
        document.body.style.overflow = "hidden"; 
        modalBody.innerHTML = '<div style="text-align: center; padding: 6rem;"><i class="fas fa-spinner fa-spin" style="font-size: 3rem; color: #3B82F6;"></i><p style="margin-top:25px; font-weight:700; color:#94A3B8; font-size:1.1rem;">Extraction des diagnostics pour ' + clientName + '...</p></div>';
        fetch(`get_client_stats.php?id=${clientId}`).then(r => r.text()).then(html => { modalBody.innerHTML = html; });
    }
    function closeModal() { document.getElementById('clientModal').style.display = "none"; document.body.style.overflow = "auto"; }
    window.onclick = function(e) { if (e.target == document.getElementById('clientModal')) closeModal(); }

    document.addEventListener('DOMContentLoaded', function() {
        // Doughnut Chart (Decisions)
        new Chart(document.getElementById('statusChart').getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode(array_column($statusDistri, 'nom')); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_column($statusDistri, 'total')); ?>,
                    backgroundColor: <?php echo json_encode(array_column($statusDistri, 'couleur')); ?>,
                    borderWidth: 0, hoverOffset: 25, borderRadius: 8
                }]
            },
            options: { cutout: '75%', plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, padding: 30, font: { weight: '700', size: 13 }, color: '#64748B' } } } }
        });

        // Bar Chart (Intervals)
        new Chart(document.getElementById('durationChart').getContext('2d'), {
            type: 'bar',
            data: {
                labels: ['0-3 jours', '4-7 jours', '> 7 jours'],
                datasets: [{
                    data: [<?php echo (int)$intervals['0_3']; ?>, <?php echo (int)$intervals['4_7']; ?>, <?php echo (int)$intervals['over_7']; ?>],
                    backgroundColor: ['#10B981', '#3B82F6', '#FF8A65'], borderRadius: 12, barThickness: 50
                }]
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, grid: { color: '#f1f5f9' }, ticks: { color: '#94a3b8' } }, x: { grid: { display: false }, ticks: { color: '#64748B', font: { weight: '700' } } } } }
        });

        // Search Logic
        const search = document.getElementById('clientSearch');
        if (search) {
            search.addEventListener('keyup', function() {
                const term = this.value.toLowerCase();
                const rows = document.getElementById('clientsTable').getElementsByTagName('tbody')[0].rows;
                for (let row of rows) { 
                    const text = row.cells[0].textContent.toLowerCase();
                    row.style.display = text.includes(term) ? '' : 'none'; 
                }
            });
        }

        // --- GESTION MENU MOBILE ---
        const mobileMenu = document.getElementById('mobileMenu');
        const toggler = document.getElementById('navbarToggler');
        const togglerIcon = toggler ? toggler.querySelector('i') : null;

        function setMenuState(isOpen) {
            if (isOpen) {
                mobileMenu.classList.add('show');
                if (togglerIcon) {
                    togglerIcon.classList.remove('fa-bars');
                    togglerIcon.classList.add('fa-times');
                }
            } else {
                mobileMenu.classList.remove('show');
                if (togglerIcon) {
                    togglerIcon.classList.remove('fa-times');
                    togglerIcon.classList.add('fa-bars');
                }
            }
        }

        if (toggler) {
            toggler.addEventListener('click', function(e) {
                e.stopPropagation();
                const isCurrentlyOpen = mobileMenu.classList.contains('show');
                setMenuState(!isCurrentlyOpen);
            });
        }

        document.addEventListener('click', function(event) {
            if (mobileMenu && mobileMenu.classList.contains('show')) {
                const isClickOnOverlay = (event.target === mobileMenu);
                const isClickOutside = !mobileMenu.contains(event.target);
                const isClickOnToggler = (toggler && toggler.contains(event.target));

                if ((isClickOutside || isClickOnOverlay) && !isClickOnToggler) {
                    setMenuState(false);
                }
            }
        });
    });
    </script>
</body>
</html>
