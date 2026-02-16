<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/functions.php';

$pageTitle = "Statistiques - Central Réclamation";
$db = getDB();

// Récupérer l'année active
$activeYearRow = $db->query("SELECT annee FROM gestion_annees WHERE active = 1 LIMIT 1")->fetch();
$activeYear = $activeYearRow ? (int)$activeYearRow['annee'] : (int)date('Y');

// Données pour les graphiques - Répartition par Statut (avec l'année active)
$statsByStatus = $db->query("
    SELECT s.nom, COUNT(t.id) as count 
    FROM statuts s 
    LEFT JOIN tickets t ON s.id = t.statut_id AND t.annee = $activeYear
    WHERE s.id IN (1, 2, 3, 4, 6, 8)
    GROUP BY s.id, s.nom
    ORDER BY FIELD(s.id, 6, 1, 2, 3, 4, 8)
")->fetchAll();

// Données pour les graphiques - Répartition par Catégorie & Performance (avec l'année active)
$statsByCat = $db->query("
    SELECT 
        c.nom, 
        COUNT(t.id) as count,
        AVG(CASE WHEN t.statut_id IN (4, 8) AND t.date_creation IS NOT NULL AND t.date_fin_traitement IS NOT NULL 
            THEN TIMESTAMPDIFF(HOUR, t.date_creation, t.date_fin_traitement) 
            ELSE NULL END) as avg_resolution_hours,
        AVG(t.satisfaction_client) as avg_satisfaction
    FROM categories c 
    LEFT JOIN tickets t ON c.id = t.categorie_id AND t.annee = $activeYear
    GROUP BY c.id, c.nom
")->fetchAll();

// --- LOGIQUE TOP 5 THÈMES RÉCLAMÉS ---
$ticketsForThemes = $db->query("SELECT type_nc, type_ticket, categorie_id FROM tickets WHERE annee = $activeYear")->fetchAll();
$themesCounts = [];
$themeMapping = [
    'Qualité' => 'Casse/Défaut',
    'Quantité' => 'Écart de quantité',
    'Logistique' => 'Livraison/Délai'
];

foreach ($ticketsForThemes as $tk) {
    $foundThemes = [];
    
    // 1. Extraire de type_nc
    if (!empty($tk['type_nc'])) {
        $parts = explode(',', $tk['type_nc']);
        foreach ($parts as $p) {
            $p = trim($p);
            if (empty($p)) continue;
            // Si c'est "Autre: ...", on peut soit garder "Autre", soit essayer d'extraire
            if (strpos($p, 'Autre:') === 0) {
                $detail = trim(substr($p, 6));
                if (!empty($detail)) $foundThemes[] = $detail;
                else $foundThemes[] = 'Autre';
            } else {
                $foundThemes[] = $themeMapping[$p] ?? $p;
            }
        }
    }
    
    // 2. Si pas de type_nc mais catégorie spécifique
    if (empty($foundThemes)) {
        if ($tk['categorie_id'] == 3) $foundThemes[] = 'Produit';
        elseif ($tk['categorie_id'] == 5) $foundThemes[] = 'Livraison/Délai';
        elseif ($tk['categorie_id'] == 10) $foundThemes[] = 'Service';
    }

    foreach (array_unique($foundThemes) as $th) {
        $themesCounts[$th] = ($themesCounts[$th] ?? 0) + 1;
    }
}

arsort($themesCounts);
$topThemes = array_slice($themesCounts, 0, 5, true);

include 'includes/header.php';
?>

<div class="page-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
    <div>
        <h2 style="font-size: 1.75rem; font-weight: 800; letter-spacing: -0.03em; margin: 0;"><i class="fas fa-chart-pie" style="color: var(--admin-primary); font-size: 1.5rem; margin-right: 0.5rem;"></i> Statistiques & Analyses</h2>
        <p style="color: var(--text-muted); font-size: 0.95rem; margin-top: 0.25rem;">Suivez la performance opérationnelle et la satisfaction client.</p>
    </div>
</div>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 1.5rem;">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fa-solid fa-chart-column"></i>
                Répartition par Statut
            </h3>
        </div>
        <div class="card-body">
            <div style="height: 280px; display: flex; align-items: flex-end; gap: 1rem; padding-top: 1.5rem;">
                <?php 
                $max = 0;
                foreach($statsByStatus as $s) if($s['count'] > $max) $max = $s['count'];
                foreach ($statsByStatus as $s): 
                    $height = $max > 0 ? ($s['count'] / $max) * 100 : 0;
                ?>
                <div style="flex: 1; display: flex; flex-direction: column; align-items: center; gap: 0.5rem;">
                    <div style="width: 100%; max-width: 40px; background: var(--admin-primary); height: <?php echo $height; ?>%; border-radius: 6px 6px 0 0; min-height: 5px; opacity: 0.7; transition: all 0.2s ease;" 
                         onmouseover="this.style.opacity='1';" 
                         onmouseout="this.style.opacity='0.7';"></div>
                    <span style="font-size: 0.65rem; text-align: center; color: var(--text-muted); font-weight: 700; text-transform: uppercase;"><?php echo $s['nom']; ?></span>
                    <span style="font-weight: 800; color: #0f172a; font-size: 0.95rem;"><?php echo $s['count']; ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header" style="display: flex; align-items: center; gap: 0.75rem;">
            <div style="width: 32px; height: 32px; background: rgba(245, 158, 11, 0.1); border-radius: 8px; display: flex; align-items: center; justify-content: center; color: #f59e0b;">
                <i class="fa-solid fa-lightbulb"></i>
            </div>
            <h3 class="card-title" style="margin: 0;">Top 5 Thèmes Réclamés</h3>
        </div>
        <div class="card-body">
            <div style="display: flex; flex-direction: column; gap: 1.25rem;">
                <?php 
                if (empty($topThemes)): 
                    echo '<div style="color: var(--text-muted); text-align: center; padding: 2rem;">Aucune donnée thématique disponible.</div>';
                else:
                    $maxTheme = max($topThemes);
                    $colors = ['#FF7A50', '#3B82F6', '#10B981', '#F59E0B', '#8B5CF6'];
                    $idx = 0;
                    foreach ($topThemes as $theme => $count): 
                        $width = ($count / $maxTheme) * 100;
                        $color = $colors[$idx % count($colors)];
                ?>
                <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div style="display: flex; align-items: center; gap: 0.75rem;">
                            <span style="width: 20px; height: 20px; background: <?php echo ($idx==0)?'#f97316':'#94a3b8'; ?>; color: white; border-radius: 4px; display: flex; align-items: center; justify-content: center; font-size: 0.7rem; font-weight: 700;"><?php echo $idx + 1; ?></span>
                            <span style="font-size: 0.85rem; font-weight: 700; color: #1e293b;"><?php echo htmlspecialchars($theme); ?></span>
                        </div>
                        <span style="font-weight: 800; color: #f97316; font-size: 0.9rem;"><?php echo $count; ?></span>
                    </div>
                    <div style="height: 12px; background: var(--gray-50); border-radius: 6px; overflow: hidden;">
                        <div style="width: <?php echo $width; ?>%; background: <?php echo $color; ?>; height: 100%; border-radius: 6px; transition: width 1s cubic-bezier(0.16, 1, 0.3, 1);"></div>
                    </div>
                </div>
                <?php $idx++; endforeach; endif; ?>
            </div>
        </div>
    </div>
</div>




<div class="card" style="margin-top: 1.5rem;">
    <div class="card-header">
        <h3 class="card-title">
            <i class="fa-solid fa-gauge-high"></i>
            Performance de Résolution
        </h3>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Catégorie</th>
                        <th>Temps Moyen (Heures)</th>
                        <th>Tickets Résolus</th>
                        <th>Taux de Satisfaction</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($statsByCat as $c): ?>
                    <tr>
                        <td style="font-weight: 600;"><?php echo htmlspecialchars($c['nom']); ?></td>
                        <td>
                            <?php 
                            if ($c['avg_resolution_hours'] > 0) {
                                $h = round($c['avg_resolution_hours']);
                                echo $h . 'h';
                            } else {
                                echo '<span style="color: var(--text-muted);">-</span>';
                            }
                            ?>
                        </td>
                        <td style="font-weight: 600;"><?php echo $c['count']; ?></td>
                        <td>
                            <?php if ($c['avg_satisfaction'] > 0): ?>
                                <div style="color: var(--warning-color);">
                                    <?php 
                                    $score = round($c['avg_satisfaction'], 1);
                                    $fullStars = floor($score);
                                    $hasHalf = ($score - $fullStars) >= 0.5;
                                    for($i=1; $i<=5; $i++) {
                                        if($i <= $fullStars) echo '<i class="fa-solid fa-star"></i>';
                                        elseif($i == $fullStars + 1 && $hasHalf) echo '<i class="fa-solid fa-star-half-stroke"></i>';
                                        else echo '<i class="fa-regular fa-star"></i>';
                                    }
                                    ?>
                                    <span style="color: var(--text-main); margin-left: 0.5rem; font-weight: 600;"><?php echo $score; ?></span>
                                </div>
                            <?php else: ?>
                                <span style="color: var(--text-muted);">Pas encore de feedback</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
