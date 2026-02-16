<?php
/**
 * Statistiques RMQ - Version Premium Unifiée & Enrichie
 * Fichier: pages/rmq/statistiques.php
 */

require_once '../../config/database.php';
require_once '../../api/functions.php';

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'rmq') {
    header('Location: ../agent/login.php?role=rmq');
    exit;
}

$db = getDB();
$userName = $_SESSION['user_prenom'] ?? 'RMQ';

// --- RÉCUPÉRATION DES 5 KPIs PRINCIPAUX (LOGIQUE DASHBOARD) ---
$stats_kpis = [
    'nouveaux' => 0,
    'validation' => 0,
    'encours' => 0,
    'resolus' => 0,
    'total' => 0,
    'fournisseurs' => 0
];

// Nouveaux (Statut 1)
$stats_kpis['nouveaux'] = $db->query("SELECT COUNT(*) FROM tickets WHERE statut_id = 1")->fetchColumn();
// À Valider (Statut 6)
$stats_kpis['validation'] = $db->query("SELECT COUNT(*) FROM tickets WHERE statut_id = 6")->fetchColumn();
// En Cours (Statut 2 + 3: Traitement + Attente Fournisseur)
$stats_kpis['encours'] = $db->query("SELECT COUNT(*) FROM tickets WHERE statut_id IN (2, 3)")->fetchColumn();
// Résolus (Statut 4)
$stats_kpis['resolus'] = $db->query("SELECT COUNT(*) FROM tickets WHERE statut_id = 4")->fetchColumn();
// Total
$stats_kpis['total'] = $db->query("SELECT COUNT(*) FROM tickets")->fetchColumn();

// 1bis. Temps Écoulé (Cycle de Vie Global : Création -> Résolution ou Aujourd'hui)
$avgTotalCycle = round($db->query("SELECT AVG(TIMESTAMPDIFF(DAY, date_creation, IFNULL(date_fin_traitement, NOW()))) FROM tickets")->fetchColumn() ?: 0, 1);

// 2. Durée de Traitement Réel (Temps de travail actif : Début -> Résolution)
// Somme des durées de traitement
$totalTreatmentDuration = round($db->query("SELECT SUM(TIMESTAMPDIFF(DAY, date_debut_traitement, date_fin_traitement)) FROM tickets WHERE date_fin_traitement IS NOT NULL AND date_debut_traitement IS NOT NULL")->fetchColumn() ?: 0, 1);

// Moyenne des durées de traitement (Exclusion des non-résolus)
$avgRealTreatment = round($db->query("SELECT AVG(TIMESTAMPDIFF(DAY, date_debut_traitement, date_fin_traitement)) FROM tickets WHERE date_fin_traitement IS NOT NULL AND date_debut_traitement IS NOT NULL")->fetchColumn() ?: 0, 1);

// 3. Réclamations Fournisseurs (Identifiées par ID, Nom ou Assignation AQF)
$stats_kpis['fournisseurs'] = $db->query("
    SELECT COUNT(*) 
    FROM tickets 
    WHERE (fournisseur_nom IS NOT NULL AND fournisseur_nom != '')
")->fetchColumn();

// Pour les anciens graphiques ou autres usages si besoin
$avgDuree = $avgRealTreatment; 

// 4. Top 5 Clients (par volume réclamations + Motif Dominant)
$stmt = $db->query("
    SELECT CONCAT(IFNULL(u.prenom, ''), ' ', IFNULL(u.nom, '')) as nom_complet, 
           COUNT(t.id) as total,
           (SELECT type_ticket 
            FROM tickets t2 
            WHERE t2.client_id = t.client_id 
            GROUP BY type_ticket 
            ORDER BY COUNT(*) DESC 
            LIMIT 1) as motif_dominant
    FROM tickets t
    LEFT JOIN utilisateurs u ON t.client_id = u.id
    WHERE 1=1
    GROUP BY t.client_id
    ORDER BY total DESC
    LIMIT 5
");
$topClients = $stmt->fetchAll();

// 5. Distribution par Site (Normalisation des libellés pour éviter les doublons sfax/Sfax-Sfax)
$stmt = $db->query("SELECT site_scpc, COUNT(*) as total FROM tickets WHERE site_scpc IS NOT NULL GROUP BY site_scpc");
$rawSites = $stmt->fetchAll(PDO::FETCH_ASSOC);

$siteMapping = [
    'sfax' => 'Sfax-Sfax',
    'Sfax-Sfax' => 'Sfax-Sfax',
    'tunis_mnihla' => 'Tunis - Mnihla',
    'Tunis - Mnihla' => 'Tunis - Mnihla',
    'sousse' => 'Sousse-Sousse',
    'Sousse-Sousse' => 'Sousse-Sousse',
    'tunis_mhamdia' => 'Tunis - Mhamdia',
    'Tunis - Mhamdia' => 'Tunis - Mhamdia',
    'zarzis' => 'Zarzis-Zarzis',
    'Zarzis-Zarzis' => 'Zarzis-Zarzis',
    'site_client' => 'Site client',
    'Site client' => 'Site client'
];

$sitesData = [];
foreach ($rawSites as $row) {
    $rawName = $row['site_scpc'];
    $cleanName = $siteMapping[$rawName] ?? $rawName; // Garde la valeur brute pour "Autre" par exemple
    
    if (!isset($sitesData[$cleanName])) {
        $sitesData[$cleanName] = 0;
    }
    $sitesData[$cleanName] += (int)$row['total'];
}

// 6. Top 5 Motifs (Descriptions les plus fréquentes)
// 6. Top 5 Motifs (Basé sur le Type de Non-Conformité)
$stmt = $db->query("
    SELECT type_nc as motif, COUNT(*) as total 
    FROM tickets 
    WHERE type_nc IS NOT NULL AND type_nc != '' 
    GROUP BY type_nc 
    ORDER BY total DESC 
    LIMIT 5
");
$topMotifs = $stmt->fetchAll();

// 7. Distribution par Statut (pour le graphique)
$stmt = $db->query("SELECT s.nom, COUNT(t.id) as total, s.couleur FROM tickets t JOIN statuts s ON t.statut_id = s.id GROUP BY s.id");
$statutsDistri = $stmt->fetchAll();

// 8. RÉCLAMATIONS PAR CLIENT (TOUS LES CLIENTS - NOUVEAU)
$stmt = $db->query("
    SELECT u.id as client_id,
           CONCAT(IFNULL(u.prenom, ''), ' ', IFNULL(u.nom, '')) as nom_complet, 
           IFNULL(t.code_client, 'N/A') as code_client,
           COUNT(t.id) as total_reclamations
    FROM tickets t
    LEFT JOIN utilisateurs u ON t.client_id = u.id
    GROUP BY t.client_id, t.code_client
    ORDER BY total_reclamations DESC
");
$allClientsClaims = $stmt->fetchAll();

// 9. QUANTITÉ VS QUALITÉ (DEMANDE USER)
$countQualite = $db->query("SELECT COUNT(*) FROM tickets WHERE type_nc LIKE '%Qualité%'")->fetchColumn() ?: 0;
$countQuantite = $db->query("SELECT COUNT(*) FROM tickets WHERE type_nc LIKE '%Quantité%'")->fetchColumn() ?: 0;
$totalQualiQuanti = $countQualite + $countQuantite;
$pctQualite = $totalQualiQuanti > 0 ? round(($countQualite / $totalQualiQuanti) * 100, 1) : 0;
$pctQuantite = $totalQualiQuanti > 0 ? round(($countQuantite / $totalQualiQuanti) * 100, 1) : 0;

// 10. TOP 5 THÈMES RÉCLAMÉS (CALCULÉ STRICTEMENT EN SQL)
$stmt = $db->query("
    SELECT theme, SUM(total) as total
    FROM (
        -- Thèmes extraits par mots-clés dans la description
        SELECT 'Casse/Défaut' as theme, COUNT(*) as total FROM tickets WHERE description REGEXP 'cassé|casse|endommagé|défât|fissure|rayure|brisé|rompu|défaut'
        UNION ALL
        SELECT 'Livraison/Délai' as theme, COUNT(*) as total FROM tickets WHERE description REGEXP 'livraison|retard|délai|tarde|en retard|attendu|tardive|livré|expédition'
        UNION ALL
        SELECT 'Conformité' as theme, COUNT(*) as total FROM tickets WHERE description REGEXP 'conforme|non-conforme|normes|standard|spécifications|réglementaire|certification'
        UNION ALL
        SELECT 'Emballage' as theme, COUNT(*) as total FROM tickets WHERE description REGEXP 'emballage|emballé|conditionnement|carton|paquetage|enveloppe|protégé'
        UNION ALL
        SELECT 'Quantité' as theme, COUNT(*) as total FROM tickets WHERE description REGEXP 'quantité|nombre|qte|pièces manquantes|manque|incomplet|surplus|plus ou moins'
        UNION ALL
        SELECT 'Qualité' as theme, COUNT(*) as total FROM tickets WHERE description REGEXP 'qualité|mauvaise qualité|défaillance|dysfonctionnement|hs|usé|usure|performance'
        UNION ALL
        SELECT 'Facturation' as theme, COUNT(*) as total FROM tickets WHERE description REGEXP 'facture|prix|tarif|montant|devis|invoice|facturation|surcoût'
        UNION ALL
        SELECT 'Documentation' as theme, COUNT(*) as total FROM tickets WHERE description REGEXP 'document|paperasse|attestation|certificat|traçabilité|dossier|bon'
        UNION ALL
        -- Thèmes basés sur le type de ticket (fallback/complément)
        SELECT 'Produit' as theme, COUNT(*) as total FROM tickets WHERE type_ticket = 'produit'
        UNION ALL
        SELECT 'Service' as theme, COUNT(*) as total FROM tickets WHERE type_ticket = 'service'
    ) as combined
    GROUP BY theme
    HAVING total > 0
    ORDER BY total DESC
    LIMIT 5
");
$topThemes = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Statistiques RMQ - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/fontawesome/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary: #FF6B35;
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
        
        /* Layout */
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

        /* KPIs Grid */
        .premium-stat-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 2rem;
            margin-bottom: 4rem;
        }

        .premium-kpi-card {
            background: white;
            padding: 2rem 1.5rem;
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
            transform: translateY(-10px) scale(1.02);
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.12);
            border-color: var(--card-color);
        }

        .premium-kpi-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; width: 100%; height: 6px;
            background: var(--card-color);
            opacity: 0.9;
        }

        .kpi-icon-wrapper {
            width: 64px;
            height: 64px;
            border-radius: 18px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.75rem;
            color: white;
            background: var(--card-color);
            margin-bottom: 1.25rem;
            box-shadow: 0 10px 20px -5px var(--card-color);
        }

        .kpi-title {
            font-size: 0.85rem;
            font-weight: 800;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            margin-bottom: 0.5rem;
        }

        .kpi-number {
            font-size: 2.75rem;
            font-weight: 900;
            color: #0f172a;
            line-height: 1;
        }

        /* Glass Cards for Charts & Lists */
        .glass-card {
            background: white;
            padding: 2.5rem;
            border-radius: var(--card-radius);
            box-shadow: var(--card-shadow);
            border: 1px solid rgba(226, 232, 240, 0.8);
            transition: all 0.3s ease;
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
            width: 48px;
            height: 48px;
            background: #f8fafc;
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 14px;
            font-size: 1.2rem;
            box-shadow: inset 0 2px 4px rgba(0,0,0,0.02);
            border: 1px solid #e2e8f0;
        }

        /* Tables Modern Appearance */
        .data-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0 0.75rem;
        }

        .data-table th {
            text-align: left;
            padding: 0.5rem 1.5rem;
            color: #64748b;
            font-size: 0.85rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }

        .data-table td {
            background: #ffffff;
            padding: 1.25rem 1.5rem;
            font-size: 1rem;
            border-top: 1px solid #f1f5f9;
            border-bottom: 1px solid #f1f5f9;
            transition: background 0.2s ease;
        }

        .data-table tr:hover td {
            background: #fcfcfd;
        }

        .data-table td:first-child { 
            border-radius: 16px 0 0 16px; 
            border-left: 1px solid #f1f5f9;
            font-weight: 700; 
            color: #0f172a; 
        }

        .data-table td:last-child { 
            border-radius: 0 16px 16px 0; 
            border-right: 1px solid #f1f5f9;
            text-align: right; 
        }

        /* Progress Bars */
        .progress-bar-container {
            height: 12px;
            background: #f1f5f9;
            border-radius: 50px;
            overflow: hidden;
            width: 100%;
            border: 1px solid #e2e8f0;
        }

        .progress-bar-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--primary), #FFA500);
            border-radius: 50px;
            transition: width 1s ease-out;
        }

        .badge-count {
            background: #f8fafc;
            color: var(--primary);
            padding: 0.5rem 1rem;
            border-radius: 12px;
            font-weight: 800;
            font-size: 1.1rem;
            border: 1px solid #e2e8f0;
            display: inline-block;
            min-width: 40px;
            text-align: center;
        }

        /* Custom Scrollbar */
        .custom-scrollbar::-webkit-scrollbar { width: 6px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: #f1f5f9; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #94a3b8; }

        /* Responsive Breakpoints */
        @media (max-width: 1400px) {
            .premium-stat-grid { grid-template-columns: repeat(3, 1fr); gap: 1.5rem; }
        }

        @media (max-width: 992px) {
            .premium-stat-grid { grid-template-columns: repeat(2, 1fr); }
            h1 { font-size: 2.2rem; }
        }

        @media (max-width: 600px) {
            .premium-stat-grid { grid-template-columns: 1fr; }
            .analytics-container { padding: 0 1rem; margin: 1.5rem auto; }
            .glass-card { padding: 1.5rem; }
            .data-table { font-size: 0.9rem; }
        }
        /* Modal Overhaul (Ported from AQF) */
        .modal { display: none; position: fixed; z-index: 2000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(30,41,59,0.15); backdrop-filter: blur(12px); border:none; overflow-y: auto; padding: 2rem 0; }
        .modal-content { background: #FAFBFD; margin: 2rem auto; padding: 3.5rem; border-radius: 32px; max-width: 950px; border: 1px solid white; box-shadow: 0 40px 100px -20px rgba(0,0,0,0.1); position: relative; animation: slideUp 0.6s cubic-bezier(0.16, 1, 0.3, 1); margin-bottom: 4rem; }
        @keyframes slideUp { from { transform: translateY(30px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        .close-modal { position: absolute; right: 2.5rem; top: 2.5rem; width: 44px; height: 44px; display: flex; align-items: center; justify-content: center; background: white; border-radius: 50%; box-shadow: var(--card-shadow); cursor: pointer; transition: all 0.3s ease; border: 1px solid #f1f5f9; }
        .close-modal:hover { transform: rotate(90deg); color: var(--primary); }

        /* Row hover visual cue */
        .client-row { transition: all 0.2s ease; }
        .client-row:hover td { background: #fffaf2 !important; }
        .client-row:hover .fas.fa-chevron-right { transform: translateX(3px); color: var(--primary) !important; }
    </style>
</head>
<body>

    <!-- Navbar -->
    <nav class="navbar">
        <div class="container">
            <a class="navbar-brand" href="../../index.php" style="display: flex; align-items: center; gap: 10px;">
                <img src="../../assets/images/logo.png" alt="Logo SCPC" style="height: 40px; width: auto;">
                <div style="display: flex; flex-direction: column; line-height: 1.2;">
                    <span style="font-weight: 700; font-size: 1.1rem; color: #1A1A1A;">SCPC Comptoir Hammami</span>
                    <span style="font-size: 0.75rem; color: #666; font-weight: 500;"><?php echo SITE_NAME; ?></span>
                </div>
                <span class="navbar-brand-badge" style="background: linear-gradient(135deg, #FF6B35 0%, #FFA500 100%);">
                    RMQ
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
                <a href="create_claim.php" class="navbar-link">
                    <i class="fas fa-plus-circle"></i>
                    <span>Nouvelle Réclamation</span>
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
            <i class="fas fa-tasks"></i>
            <strong>RMQ</strong>
            <p class="mobile-menu-subtitle">Espace RMQ</p>
        </div>
        <a href="dashboard.php" class="mobile-menu-link"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a>
        <a href="statistiques.php" class="mobile-menu-link active"><i class="fas fa-chart-bar"></i> <span>Statistiques</span></a>
        <a href="create_claim.php" class="mobile-menu-link"><i class="fas fa-plus-circle"></i> <span>Nouvelle Réclamation</span></a>
        
        <a href="../../pages/logout.php" class="mobile-menu-link" style="color: #ef4444 !important;"><i class="fas fa-sign-out-alt"></i> <span>Déconnexion</span></a>
    </div>

    <div class="analytics-container">
        
        <header class="page-header">
            <h1>Centre Analytique RMQ</h1>
            <p>Pilotage stratégique et indicateurs opérationnels du service réclamations.</p>
        </header>

        <!-- Grille des 5 KPIs Principaux -->
        <div class="premium-stat-grid">
            <div class="premium-kpi-card" style="--card-color: #F59E0B; --card-rgb: 245, 158, 11;">
                <div class="kpi-icon-wrapper"><i class="fas fa-star"></i></div>
                <div class="kpi-title">Nouveaux</div>
                <div class="kpi-number"><?php echo $stats_kpis['nouveaux']; ?></div>
            </div>
            <div class="premium-kpi-card" style="--card-color: #DC2626; --card-rgb: 220, 38, 38;">
                <div class="kpi-icon-wrapper"><i class="fas fa-check-double"></i></div>
                <div class="kpi-title">À Valider</div>
                <div class="kpi-number"><?php echo $stats_kpis['validation']; ?></div>
            </div>
            <div class="premium-kpi-card" style="--card-color: #FF6B35; --card-rgb: 255, 107, 53;">
                <div class="kpi-icon-wrapper"><i class="fas fa-hourglass-half"></i></div>
                <div class="kpi-title">En Cours</div>
                <div class="kpi-number"><?php echo $stats_kpis['encours']; ?></div>
            </div>
            <div class="premium-kpi-card" style="--card-color: #10B981; --card-rgb: 16, 185, 129;">
                <div class="kpi-icon-wrapper"><i class="fas fa-check-circle"></i></div>
                <div class="kpi-title">Résolus</div>
                <div class="kpi-number"><?php echo $stats_kpis['resolus']; ?></div>
            </div>
            <div class="premium-kpi-card" style="--card-color: #6366F1; --card-rgb: 99, 102, 241;">
                <div class="kpi-icon-wrapper"><i class="fas fa-list-check"></i></div>
                <div class="kpi-title">Total</div>
                <div class="kpi-number"><?php echo $stats_kpis['total']; ?></div>
            </div>
            <!-- Global Elapsed -->
            <div class="premium-kpi-card" style="--card-color: #334155; --card-rgb: 51, 65, 85;">
                <div class="kpi-icon-wrapper"><i class="fas fa-calendar-check"></i></div>
                <div class="kpi-title">Temps Écoulé</div>
                <div class="kpi-number"><?php echo $avgTotalCycle; ?><span style="font-size: 1rem; margin-left: 2px;">j</span></div>
            </div>

            <!-- Real treatment (Avg) -->
            <div class="premium-kpi-card" style="--card-color: #0369A1; --card-rgb: 3, 105, 161;">
                <div class="kpi-icon-wrapper"><i class="fas fa-user-clock"></i></div>
                <div class="kpi-title">Moyenne Traitement</div>
                <div class="kpi-number"><?php echo $avgRealTreatment; ?><span style="font-size: 1rem; margin-left: 2px;">j</span></div>
            </div>

            <!-- Total treatment (Sum) -->
            <div class="premium-kpi-card" style="--card-color: #7C3AED; --card-rgb: 124, 58, 237;">
                <div class="kpi-icon-wrapper"><i class="fas fa-calculator"></i></div>
                <div class="kpi-title">Somme Traitement</div>
                <div class="kpi-number"><?php echo $totalTreatmentDuration; ?><span style="font-size: 1rem; margin-left: 2px;">j</span></div>
            </div>

            <!-- Supplier Claims -->
            <div class="premium-kpi-card" style="--card-color: #DB2777; --card-rgb: 219, 39, 119;">
                <div class="kpi-icon-wrapper"><i class="fas fa-truck-field"></i></div>
                <div class="kpi-title">Récl. Fournisseurs</div>
                <div class="kpi-number"><?php echo $stats_kpis['fournisseurs']; ?></div>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 2.5rem; margin-bottom: 2.5rem;">
            <!-- État des Réclamations -->
            <div class="glass-card">
                <h2><i class="fas fa-chart-pie"></i> Distribution par Statut</h2>
                <div style="height: 300px;">
                    <canvas id="statutsChart"></canvas>
                </div>
            </div>

            <!-- Analyse Quantité vs Qualité (NOUVEAU) -->
            <div class="glass-card" style="display: flex; flex-direction: column; justify-content: center;">
                <h2><i class="fas fa-balance-scale"></i> Quantité vs Qualité</h2>
                <div style="margin-top: 1rem;">
                    <div style="margin-bottom: 2rem;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem; font-weight: 700;">
                            <span style="color: #FF6B35;">Qualité</span>
                            <span><?php echo $pctQualite; ?>%</span>
                        </div>
                        <div class="progress-bar-container" style="height: 12px; background: #FFF5F2;">
                            <div class="progress-bar-fill" style="width: <?php echo $pctQualite; ?>%; background: #FF6B35;"></div>
                        </div>
                    </div>
                    <div style="margin-bottom: 2rem;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem; font-weight: 700;">
                            <span style="color: #334155;">Quantité</span>
                            <span><?php echo $pctQuantite; ?>%</span>
                        </div>
                        <div class="progress-bar-container" style="height: 12px; background: #f1f5f9;">
                            <div class="progress-bar-fill" style="width: <?php echo $pctQuantite; ?>%; background: #334155;"></div>
                        </div>
                    </div>
                    
                    <div style="background: rgba(255, 107, 53, 0.05); padding: 1rem; border-radius: 12px; text-align: center; border: 1px dashed rgba(255, 107, 53, 0.2);">
                        <span style="font-size: 0.85rem; color: #64748b; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em;">Tendance Dominante</span>
                        <div style="font-weight: 800; color: <?php echo $countQualite >= $countQuantite ? '#FF6B35' : '#334155'; ?>; font-size: 1.25rem; margin-top: 0.25rem;">
                            <?php echo $countQualite >= $countQuantite ? 'Problèmes Qualité' : 'Problèmes Quantité'; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Temps de Traitement & Insights -->
            <div style="display: flex; flex-direction: column; gap: 2.5rem;">


                <div class="glass-card">
                    <h2><i class="fas fa-map-marker-alt"></i> Distribution par Site</h2>
                    <div style="height: 150px;">
                        <canvas id="sitesChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1.5fr 1fr; gap: 2.5rem; margin-bottom: 2.5rem;">
            <!-- Top 5 Motifs (NOUVEAU) -->
            <div class="glass-card">
                <h2><i class="fas fa-tags"></i> Top 5 Motifs de Réclamation</h2>
                <div style="height: 380px;">
                    <canvas id="motifsChart"></canvas>
                </div>
            </div>

            <!-- Top Clients -->
            <div class="glass-card">
                <h2><i class="fas fa-trophy"></i> Top 5 Clients (Nombre)</h2>
                <div style="overflow-x: auto;">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Client</th>
                                <th style="text-align: center;">Réclamations</th>
                                <th>Motif Dominant</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($topClients as $cli): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($cli['nom_complet']); ?></td>
                                <td style="text-align: center; font-weight: 800; color: #FF6B35; font-size: 1.1rem;">
                                    <?php echo $cli['total']; ?>
                                </td>
                                <td style="font-weight: 600; color: #64748b;">
                                    <span style="background: rgba(255, 107, 53, 0.08); color: #FF6B35; padding: 0.3rem 0.6rem; border-radius: 8px; font-size: 0.85rem;">
                                        <?php echo htmlspecialchars($cli['motif_dominant'] ?: 'Non renseigné'); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- TOP 5 THÈMES RÉCLAMÉS (NOUVEAU) -->
        <div class="glass-card" style="margin-bottom: 2.5rem;">
            <h2><i class="fas fa-lightbulb"></i> Top 5 Thèmes Réclamés</h2>
            <div style="display: grid; grid-template-columns: 1.5fr 1fr; gap: 2.5rem; align-items: start;">
                <!-- Graphique Horizontal -->
                <div style="height: 300px;">
                    <canvas id="themesChart"></canvas>
                </div>
                
                <!-- Tableau Thèmes -->
                <div style="overflow-x: auto;">
                    <table class="data-table" style="margin-top: 0;">
                        <thead>
                            <tr>
                                <th>Thème</th>
                                <th style="text-align: center;">Occurrences</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($topThemes as $idx => $theme): ?>
                            <tr>
                                <td style="display: flex; align-items: center; gap: 0.75rem;">
                                    <span style="background: linear-gradient(135deg, #FF6B35, #FFA500); color: white; width: 28px; height: 28px; display: flex; align-items: center; justify-content: center; border-radius: 8px; font-weight: 800; font-size: 0.85rem;">
                                        <?php echo $idx + 1; ?>
                                    </span>
                                    <span style="font-weight: 600; color: #0f172a;"><?php echo htmlspecialchars(substr($theme['theme'] ?? $theme['motif'] ?? 'N/A', 0, 30)); ?></span>
                                </td>
                                <td style="text-align: center; font-weight: 800; color: #FF6B35; font-size: 1.1rem;">
                                    <?php echo $theme['total'] ?? 0; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- TOUTES LES RÉCLAMATIONS PAR CLIENT (NOUVEAU) -->
        <div class="glass-card" style="margin-bottom: 4rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
                <h2 style="margin: 0;"><i class="fas fa-users"></i> Analyse des Réclamations par Client</h2>
                <div style="display: flex; align-items: center; gap: 1.5rem;">
                    <span style="font-size: 0.8rem; color: #FF6B35; font-weight: 700; background: #FFF5F2; padding: 6px 12px; border-radius: 8px; border: 1px solid rgba(255,107,53,0.1);">
                        <i class="fas fa-mouse-pointer"></i> Cliquez sur une ligne pour voir les détails
                    </span>
                    <div style="position: relative; width: 300px;">
                        <i class="fas fa-filter" style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: #94a3b8;"></i>
                        <input type="text" id="allClientsSearch" placeholder="Rechercher un client ou un code..." style="width: 100%; padding: 0.75rem 1rem 0.75rem 2.75rem; border-radius: 12px; border: 1px solid #e2e8f0; font-size: 0.9rem;">
                    </div>
                </div>
            </div>
            <div style="max-height: 500px; overflow-y: auto; padding-right: 0.5rem;" class="custom-scrollbar">
                <table class="data-table" id="allClientsTable">
                    <thead>
                        <tr>
                            <th>Nom du Client</th>
                            <th>Code Client</th>
                            <th style="text-align: center;">Réclamations</th>
                            <th style="text-align: right;">Détails</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($allClientsClaims as $row): ?>
                        <tr onclick="openClientDetail(<?php echo $row['client_id']; ?>, '<?php echo addslashes($row['nom_complet']); ?>')" style="cursor: pointer;" class="client-row">
                            <td><?php echo htmlspecialchars($row['nom_complet']); ?></td>
                            <td><span style="color: #64748b; font-weight: 600; font-family: monospace;"><?php echo htmlspecialchars($row['code_client']); ?></span></td>
                            <td style="text-align: center;"><span class="badge-count" style="background: #FFF5F2; border: 1px solid rgba(255,107,53,0.1);"><?php echo $row['total_reclamations']; ?></span></td>
                            <td style="text-align: right;"><i class="fas fa-chevron-right" style="color: #cbd5e1; font-size: 0.8rem;"></i></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- MODAL PREMIUM (Drill-down) -->
        <div id="clientModal" class="modal">
            <div class="modal-content">
                <span class="close-modal" onclick="closeModal()"><i class="fas fa-times"></i></span>
                <div id="modalBody">
                    <div style="text-align: center; padding: 4rem;">
                        <i class="fas fa-circle-notch fa-spin" style="font-size: 2.5rem; color: var(--primary);"></i>
                        <p style="margin-top:20px; font-weight:600; color:#94A3B8;">Analyses en cours...</p>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <script>
    // Functions for Modal
    function openClientDetail(clientId, clientName) {
        const modal = document.getElementById('clientModal');
        const modalBody = document.getElementById('modalBody');
        modal.style.display = "block";
        document.body.style.overflow = "hidden"; 
        modalBody.innerHTML = '<div style="text-align: center; padding: 5rem;"><i class="fas fa-circle-notch fa-spin" style="font-size: 3rem; color: var(--primary);"></i><p style="margin-top:20px; font-weight:600; color:#94A3B8;">Chargement des données de ' + clientName + '...</p></div>';

        fetch(`../aqf/get_client_stats.php?id=${clientId}`)
            .then(response => response.text())
            .then(html => { modalBody.innerHTML = html; });
    }

    function closeModal() { 
        document.getElementById('clientModal').style.display = "none"; 
        document.body.style.overflow = "auto"; 
    }
    
    window.onclick = function(event) { 
        if (event.target == document.getElementById('clientModal')) closeModal(); 
    }

    document.addEventListener('DOMContentLoaded', function() {
        // Graphique Statuts (Doughnut - Modernisé)
        const ctxStatuts = document.getElementById('statutsChart').getContext('2d');
        const statData = <?php echo json_encode(array_column($statutsDistri, 'total')); ?>;
        const totalTickets = statData.reduce((a, b) => a + parseInt(b), 0);

        new Chart(ctxStatuts, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode(array_column($statutsDistri, 'nom')); ?>,
                datasets: [{
                    data: statData,
                    backgroundColor: <?php echo json_encode(array_column($statutsDistri, 'couleur')); ?>,
                    borderWidth: 0,
                    hoverOffset: 15,
                    borderRadius: 5
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '70%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            usePointStyle: true,
                            padding: 20,
                            font: { size: 12, weight: '700' },
                            color: '#475569'
                        }
                    },
                    tooltip: {
                        backgroundColor: '#1e293b',
                        padding: 12,
                        cornerRadius: 10,
                        callbacks: {
                            label: function(context) {
                                const value = context.raw;
                                const percentage = ((value / totalTickets) * 100).toFixed(1);
                                return ` ${context.label}: ${value} (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });

        // Graphique Motifs (Horizontal Bar Chart)
        const ctxMotifs = document.getElementById('motifsChart').getContext('2d');
        new Chart(ctxMotifs, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_column($topMotifs, 'motif')); ?>,
                datasets: [{
                    label: 'Fréquence',
                    data: <?php echo json_encode(array_column($topMotifs, 'total')); ?>,
                    backgroundColor: 'rgba(255, 107, 53, 0.85)',
                    hoverBackgroundColor: '#FF6B35',
                    borderRadius: 8,
                    barThickness: 30
                }]
            },
            options: {
                indexAxis: 'y', // Rendu horizontal
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#1e293b',
                        titleFont: { size: 14, weight: '700' },
                        padding: 12,
                        cornerRadius: 10
                    }
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        grid: { color: '#f1f5f9' },
                        ticks: { color: '#94a3b8', font: { weight: '600' } }
                    },
                    y: {
                        grid: { display: false },
                        ticks: { 
                            color: '#1e293b', 
                            font: { weight: '700', size: 12 },
                            callback: function(value) {
                                let label = this.getLabelForValue(value);
                                if (label.length > 25) return label.substr(0, 25) + '...';
                                return label;
                            }
                        }
                    }
                }
            }
        });

        // Graphique Thèmes (Horizontal Bar Chart - NOUVEAU)
        const ctxThemes = document.getElementById('themesChart').getContext('2d');
        new Chart(ctxThemes, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_map(function($t) { return substr($t['theme'] ?? $t['motif'] ?? 'N/A', 0, 35); }, $topThemes)); ?>,
                datasets: [{
                    label: 'Occurrences',
                    data: <?php echo json_encode(array_map(function($t) { return $t['total'] ?? 0; }, $topThemes)); ?>,
                    backgroundColor: [
                        'rgba(255, 107, 53, 0.85)',
                        'rgba(3, 105, 161, 0.85)',
                        'rgba(16, 185, 129, 0.85)',
                        'rgba(168, 85, 247, 0.85)',
                        'rgba(245, 158, 11, 0.85)'
                    ],
                    hoverBackgroundColor: ['#FF6B35', '#0369A1', '#10B981', '#A855F7', '#F59E0B'],
                    borderRadius: 8,
                    barThickness: 30
                }]
            },
            options: {
                indexAxis: 'y', // Rendu horizontal
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#1e293b',
                        titleFont: { size: 14, weight: '700' },
                        padding: 12,
                        cornerRadius: 10
                    }
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        grid: { color: '#f1f5f9' },
                        ticks: { color: '#94a3b8', font: { weight: '600' } }
                    },
                    y: {
                        grid: { display: false },
                        ticks: { 
                            color: '#1e293b', 
                            font: { weight: '700', size: 12 },
                            callback: function(value) {
                                let label = this.getLabelForValue(value);
                                if (label.length > 30) return label.substr(0, 30) + '...';
                                return label;
                            }
                        }
                    }
                }
            }
        });

        // Graphique Sites (Doughnut)
        const ctxSites = document.getElementById('sitesChart').getContext('2d');
        new Chart(ctxSites, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode(array_keys($sitesData)); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_values($sitesData)); ?>,
                    backgroundColor: ['#FF6B35', '#3B82F6', '#10B981', '#F59E0B', '#8B5CF6', '#F43F5E'],
                    borderWidth: 0,
                    hoverOffset: 15
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '80%',
                plugins: {
                    legend: {
                        position: 'right',
                        labels: {
                            usePointStyle: true,
                            padding: 20,
                            font: { size: 12, weight: '700' },
                            color: '#475569'
                        }
                    },
                    tooltip: {
                        backgroundColor: '#1e293b',
                        padding: 12,
                        cornerRadius: 10,
                        callbacks: {
                            label: function(context) {
                                const dataset = context.dataset.data;
                                const total = dataset.reduce((a, b) => a + b, 0);
                                const value = context.raw;
                                const percentage = ((value / total) * 100).toFixed(1);
                                return ` ${context.label}: ${value} (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });



        // Recherche Analyse par Client
        const clientSearch = document.getElementById('allClientsSearch');
        const clientTable = document.getElementById('allClientsTable');
        if (clientSearch && clientTable) {
            clientSearch.addEventListener('keyup', function() {
                const term = this.value.toLowerCase();
                const rows = clientTable.getElementsByTagName('tbody')[0].rows;
                for (let row of rows) {
                    const name = row.cells[0].textContent.toLowerCase();
                    const code = row.cells[1].textContent.toLowerCase();
                    row.style.display = (name.includes(term) || code.includes(term)) ? '' : 'none';
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

    <style>
        .custom-scrollbar::-webkit-scrollbar { width: 6px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 10px; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
    </style>

</body>
</html>
