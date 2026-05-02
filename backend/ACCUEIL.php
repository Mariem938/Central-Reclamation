<?php
/**
 * INDEX PAGE - Tous les liens d'accès
 */
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Central Réclamation - Accueil</title>
    <link rel="stylesheet" href="./assets/fontawesome/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #FF6B35 0%, #FFA500 100%);
            min-height: 100vh;
            padding: 2rem;
        }
        .container { max-width: 1000px; margin: 0 auto; }
        .header {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.2);
            margin-bottom: 2rem;
            text-align: center;
        }
        .header h1 { color: #FF6B35; font-size: 2.5rem; margin-bottom: 0.5rem; }
        .header p { color: #666; }
        .main-cta {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            padding: 2.5rem;
            border-radius: 12px;
            text-align: center;
            margin-bottom: 2rem;
            box-shadow: 0 8px 32px rgba(0,0,0,0.2);
        }
        .main-cta h2 { font-size: 2rem; margin-bottom: 1rem; }
        .main-cta a {
            display: inline-block;
            background: white;
            color: #059669;
            padding: 1rem 2.5rem;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            font-size: 1.1rem;
            transition: all 0.3s;
        }
        .main-cta a:hover { transform: translateY(-3px); box-shadow: 0 8px 24px rgba(0,0,0,0.2); }
        .section {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        .section h2 { color: #333; margin-bottom: 1.5rem; border-bottom: 3px solid #FF6B35; padding-bottom: 0.5rem; }
        .links-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem; }
        .link-box {
            padding: 1.5rem;
            background: #f5f5f5;
            border-radius: 8px;
            border-left: 4px solid #FF6B35;
            transition: all 0.3s;
        }
        .link-box:hover { transform: translateY(-3px); box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .link-box a {
            display: block;
            color: #FF6B35;
            text-decoration: none;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        .link-box a:hover { text-decoration: underline; }
        .link-box p { color: #666; font-size: 0.9rem; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🎯 Central de Réclamation</h1>
            <p>Plateforme de gestion des réclamations clients</p>
        </div>

        <div class="main-cta">
            <h2>✅ Corrections Complètes Effectuées!</h2>
            <p style="margin-bottom: 1.5rem;">Tous les problèmes ont été résolus. Commencez par le setup.</p>
            <a href="MASTER_SETUP_V3.php">🚀 Exécuter le Setup (3 min)</a>
        </div>

        <div class="section">
            <h2>📚 Documentation & Guides</h2>
            <div class="links-grid">
                <div class="link-box">
                    <a href="BIENVENUE_LIRE_DABORD.php"><i class="fas fa-book"></i> Bienvenue - Lire d'abord</a>
                    <p>Page d'accueil avec toutes les instructions</p>
                </div>
                <div class="link-box">
                    <a href="MASTER_SETUP_V3.php"><i class="fas fa-cog"></i> Setup Base de Données</a>
                    <p>Exécute la configuration automatique</p>
                </div>
                <div class="link-box">
                    <a href="MISSION_ACCOMPLIE.php"><i class="fas fa-check-circle"></i> Mission Accomplie</a>
                    <p>Visualisation des corrections effectuées</p>
                </div>
                <div class="link-box">
                    <a href="RESUME_FINAL.php"><i class="fas fa-file-alt"></i> Résumé Final</a>
                    <p>Détail complet de tous les changements</p>
                </div>
            </div>
        </div>

        <div class="section">
            <h2>👨‍💼 Accès Commercial</h2>
            <div class="links-grid">
                <div class="link-box">
                    <a href="pages/commercial/dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                    <p>Tableau de bord commercial</p>
                </div>
                <div class="link-box">
                    <a href="pages/commercial/create_claim.php"><i class="fas fa-plus-circle"></i> Nouvelle Réclamation</a>
                    <p>Créer une réclamation client</p>
                </div>
            </div>
        </div>

        <div class="section">
            <h2>👨‍⚖️ Accès RMQ</h2>
            <div class="links-grid">
                <div class="link-box">
                    <a href="pages/rmq/dashboard.php"><i class="fas fa-chart-line"></i> Dashboard</a>
                    <p>Tableau de bord RMQ (Code Client, Durée)</p>
                </div>
                <div class="link-box">
                    <a href="pages/rmq/detail_ticket.php?id=1"><i class="fas fa-eye"></i> Détail Ticket</a>
                    <p>Consulter les détails d'une réclamation</p>
                </div>
                <div class="link-box">
                    <a href="pages/rmq/edit_claim.php?id=1"><i class="fas fa-edit"></i> Modifier</a>
                    <p>Modifier une réclamation</p>
                </div>
                <div class="link-box">
                    <a href="pages/rmq/refuse_claim.php?id=1"><i class="fas fa-ban"></i> Refuser</a>
                    <p>Refuser une réclamation</p>
                </div>
            </div>
        </div>

        <div class="section">
            <h2>👤 Accès Client</h2>
            <div class="links-grid">
                <div class="link-box">
                    <a href="pages/client/dashboard.php"><i class="fas fa-inbox"></i> Dashboard</a>
                    <p>Consulter mes réclamations</p>
                </div>
                <div class="link-box">
                    <a href="pages/client/create_claim.php"><i class="fas fa-pencil-alt"></i> Nouvelle Réclamation</a>
                    <p>Soumettre une réclamation</p>
                </div>
            </div>
        </div>

        <div class="section">
            <h2>📖 Fichiers de Référence</h2>
            <div class="links-grid">
                <div class="link-box">
                    <a href="README_FINAL_3MINUTES.md"><i class="fas fa-file"></i> Guide 3 Minutes</a>
                    <p>Guide rapide d'exécution</p>
                </div>
                <div class="link-box">
                    <a href="ACTION_REQUISE_3_ETAPES.md"><i class="fas fa-list"></i> 3 Étapes</a>
                    <p>Actions détaillées étape par étape</p>
                </div>
                <div class="link-box">
                    <a href="URLS_PRINCIPALES.md"><i class="fas fa-link"></i> URLs Principales</a>
                    <p>Référence de tous les liens</p>
                </div>
                <div class="link-box">
                    <a href="RESUME_COMPLET_FINAL.md"><i class="fas fa-book-open"></i> Résumé Complet</a>
                    <p>Documentation technique complète</p>
                </div>
            </div>
        </div>

        <div style="background: white; padding: 2rem; border-radius: 12px; box-shadow: 0 8px 32px rgba(0,0,0,0.1); text-align: center;">
            <h3 style="color: #333; margin-bottom: 1rem;">✨ Prêt à Démarrer?</h3>
            <p style="color: #666; margin-bottom: 1.5rem;">Commencez par le setup (MASTER_SETUP_V3.php), puis testez les 5 scénarios.</p>
            <a href="MASTER_SETUP_V3.php" style="display: inline-block; background: linear-gradient(135deg, #FF6B35 0%, #FFA500 100%); color: white; padding: 1rem 2rem; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 1.1rem;">
                <i class="fas fa-rocket"></i> Commencer Maintenant
            </a>
        </div>
    </div>
</body>
</html>
