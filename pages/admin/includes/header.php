<?php
// Admin Header - Harmonisé avec le thème global
if (!isset($pageTitle)) {
    $pageTitle = 'Admin - Central Réclamation';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../../assets/fontawesome/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            /* Palette Pastel & Professionnelle */
            --admin-primary: #FF6B35;
            --admin-primary-light: #FF8A65;
            --admin-gradient: linear-gradient(135deg, #FF6B35 0%, #FFA500 100%);
            
            /* Couleurs d'accent pastel */
            --bg-pastel-blue: #f0f9ff;
            --bg-pastel-green: #f0fdf4;
            --bg-pastel-purple: #faf5ff;
            --bg-pastel-gray: #f8fafc;
            
            --success-color: #10b981;
            --success-pastel: #dcfce7;
            --warning-color: #f59e0b;
            --warning-pastel: #fef3c7;
            --danger-color: #ef4444;
            --danger-pastel: #fee2e2;
            
            /* Fond et Surfaces */
            --background-color: #fdfdfd;
            --card-background: #ffffff;
            --text-main: #1e293b;
            --text-muted: #64748b;
            --border-color: #f1f5f9;
            
            /* Échelle de gris enrichie */
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e2e8f0;
            --gray-300: #cbd5e1;
            
            /* Tailles affinées */
            --radius-md: 10px;
            --radius-lg: 14px;
            --radius-xl: 20px;
            --shadow-sm: 0 1px 2px rgba(0,0,0,0.03);
            --shadow-md: 0 4px 15px -3px rgba(0,0,0,0.05);
            --shadow-lg: 0 10px 25px -5px rgba(0,0,0,0.04);
        }

        * {
            box-sizing: border-box;
            -webkit-font-smoothing: antialiased;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--background-color);
            color: var(--text-main);
            margin: 0;
            padding: 0;
            line-height: 1.5;
            font-size: 0.9rem;
        }

        /* Navbar Moderne & Fine - Alignement Horizontal Parfait */
        .navbar {
            background: white;
            border-bottom: 1px solid var(--border-color);
            padding: 0.5rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: var(--shadow-sm);
            flex-wrap: nowrap; /* Force une seule ligne */
            height: 70px;
        }

        .navbar-brand {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            text-decoration: none;
            flex-shrink: 0;
            min-width: 280px;
        }

        .navbar-brand img {
            height: 38px;
            filter: drop-shadow(0 2px 4px rgba(0,0,0,0.05));
        }

        .navbar-brand-info {
            display: flex;
            flex-direction: column;
            justify-content: center;
            line-height: 1.2;
        }

        .navbar-brand-title {
            font-weight: 800;
            font-size: 1.05rem;
            color: #0f172a;
            letter-spacing: -0.02em;
        }

        .navbar-brand-subtitle {
            font-size: 0.75rem;
            color: #475569; /* Gris foncé discret */
            font-weight: 500;
            letter-spacing: 0.01em;
            margin-top: 1px;
        }

        .navbar-menu {
            display: flex;
            gap: 0.25rem;
            align-items: center;
            position: absolute;
            left: 50%;
            transform: translateX(-50%);
            white-space: nowrap;
        }

        .navbar-link {
            display: flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.5rem 0.8rem;
            text-decoration: none;
            color: var(--text-muted);
            font-weight: 600;
            border-radius: 8px;
            transition: all 0.2s ease;
            font-size: 0.8rem;
        }

        .navbar-link i {
            font-size: 0.85rem;
            opacity: 0.7;
        }

        .navbar-link:hover {
            background: var(--bg-pastel-gray);
            color: var(--admin-primary);
        }

        .navbar-link.active {
            background: var(--bg-pastel-blue);
            color: #3b82f6;
        }

        .navbar-actions {
            display: flex;
            align-items: center;
            gap: 1rem;
            flex-shrink: 0;
        }

        .navbar-link.logout {
            color: var(--danger-color);
            background: var(--danger-pastel);
            font-size: 0.8rem;
            padding: 0.4rem 0.8rem;
            border-radius: 6px;
        }

        .navbar-link.logout:hover {
            background: #fecaca;
            color: #991b1b;
        }

        .navbar-toggler {
            display: none;
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--gray-700);
            padding: 0.5rem;
        }


        @media (max-width: 992px) {
            .navbar-menu {
                display: none;
            }
            .navbar-toggler {
                display: block;
            }
        }

        /* Container */
        .container {
            max-width: 1400px;
            margin: 2rem auto;
            padding: 0 1.5rem;
        }

        /* Affinage des Cards */
        .card {
            background: white;
            border-radius: var(--radius-md);
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-sm);
            margin-bottom: 1.75rem;
            overflow: hidden;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .card:hover {
            box-shadow: var(--shadow-md);
        }

        .card-header {
            padding: 0.9rem 1.5rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: var(--bg-pastel-gray);
        }

        .card-title {
            margin: 0;
            font-size: 1.1rem;
            font-weight: 700;
            color: #0f172a;
            display: flex;
            align-items: center;
            gap: 0.6rem;
            letter-spacing: -0.01em;
        }

        .card-title i {
            color: var(--admin-primary);
            font-size: 1rem;
        }

        .card-body {
            padding: 1.5rem;
        }

        /* Stats Grid Compacte */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.25rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: var(--radius-md);
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-sm);
            display: flex;
            flex-direction: row;
            align-items: center;
            text-align: left;
            gap: 1.1rem;
            transition: all 0.2s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-md);
        }

        .stat-icon {
            width: 52px;
            height: 52px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            box-shadow: 0 4px 10px -2px rgba(0,0,0,0.05);
            transition: transform 0.2s;
            flex-shrink: 0;
        }

        .stat-card:hover .stat-icon {
            transform: scale(1.05);
        }

        .stat-info h3 {
            margin: 0;
            font-size: 0.75rem;
            color: var(--text-muted);
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .stat-info p {
            margin: 0.2rem 0 0;
            font-size: 1.75rem;
            font-weight: 800;
            color: #0f172a;
            line-height: 1;
        }

        /* Professional Tables */
        .table-container {
            overflow-x: auto;
            border-radius: var(--radius-md);
            background: white;
        }

        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        th {
            padding: 0.85rem 1.25rem;
            background: var(--bg-pastel-gray);
            font-weight: 800;
            font-size: 0.7rem;
            color: var(--text-muted);
            border-bottom: 1px solid var(--border-color);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        td {
            padding: 0.9rem 1.25rem;
            border-bottom: 1px solid var(--border-color);
            font-size: 0.85rem;
            color: #334155;
            transition: background 0.15s ease;
        }

        tr:hover td {
            background: var(--bg-pastel-blue);
        }

        /* Badges Plus Fins */
        .badge {
            padding: 0.35rem 0.75rem;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            border: 1px solid transparent;
        }

        .badge-success {
            background: var(--success-pastel);
            color: #065f46;
            border-color: rgba(6, 95, 70, 0.1);
        }

        .badge-warning {
            background: var(--warning-pastel);
            color: #92400e;
            border-color: rgba(146, 64, 14, 0.1);
        }

        .badge-danger {
            background: var(--danger-pastel);
            color: #991b1b;
            border-color: rgba(153, 27, 27, 0.1);
        }

        .badge-info {
            background: var(--bg-pastel-blue);
            color: #1e40af;
            border-color: rgba(30, 64, 175, 0.1);
        }

        .badge-secondary {
            background: var(--gray-100);
            color: var(--text-muted);
            border-color: var(--gray-200);
        }

        /* Boutons Affinés */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.6rem 1.125rem;
            border-radius: 8px;
            font-weight: 700;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            border: 1px solid transparent;
            white-space: nowrap;
        }

        .btn-primary {
            background: var(--admin-gradient);
            color: white;
            box-shadow: 0 4px 10px -2px rgba(255, 107, 53, 0.2);
        }

        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 12px -3px rgba(255, 107, 53, 0.3);
        }

        .btn-success {
            background: var(--success-color);
            color: white;
            box-shadow: 0 4px 10px -2px rgba(16, 185, 129, 0.2);
        }

        .btn-success:hover {
            background: #059669;
            transform: translateY(-1px);
        }

        .btn-danger {
            background: var(--danger-color);
            color: white;
            box-shadow: 0 4px 10px -2px rgba(239, 68, 68, 0.2);
        }

        .btn-danger:hover {
            background: #dc2626;
            transform: translateY(-1px);
        }

        .btn-outline {
            border: 1.5px solid var(--gray-200);
            color: var(--text-main);
            background: white;
        }

        .btn-outline:hover {
            background: var(--bg-pastel-gray);
            border-color: var(--admin-primary);
            color: var(--admin-primary);
        }

        .btn-sm {
            padding: 0.4rem 0.8rem;
            font-size: 0.8rem;
            border-radius: 6px;
            gap: 0.35rem;
        }

        .btn-outline-primary {
            border: 1.5px solid var(--admin-primary);
            color: var(--admin-primary);
            background: transparent;
        }

        .btn-outline-primary:hover {
            background: var(--admin-primary);
            color: white;
        }

        .btn-outline-secondary {
            border: 1.5px solid var(--gray-200);
            color: var(--text-muted);
            background: transparent;
        }

        .btn-outline-secondary:hover {
            background: var(--gray-50);
            border-color: var(--gray-300);
            color: var(--text-main);
        }

        .btn-outline-danger {
            border: 1.5px solid var(--danger-pastel);
            color: var(--danger-color);
            background: var(--danger-pastel);
        }

        .btn-outline-danger:hover {
            background: #fecaca;
            border-color: var(--danger-color);
        }

        .btn-outline-success {
            border: 1.5px solid var(--success-pastel);
            color: var(--success-color);
            background: var(--success-pastel);
        }

        .btn-outline-success:hover {
            background: #bbf7d0;
            border-color: var(--success-color);
        }

        /* Alerts */
        .alert {
            padding: 1rem 1.25rem;
            border-radius: var(--radius-md);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .alert-success {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .alert-danger {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .alert-warning {
            background: #fef3c7;
            color: #92400e;
            border: 1px solid #fde68a;
        }

        /* Forms Affinés */
        .form-input {
            width: 100%;
            padding: 0.65rem 1rem;
            border: 1.5px solid var(--gray-200);
            border-radius: 8px;
            font-size: 0.85rem;
            transition: all 0.2s ease;
            background: #fdfdfd;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--admin-primary-light);
            box-shadow: 0 0 0 3px rgba(255, 107, 53, 0.08);
            background: white;
        }

        .form-label {
            display: block;
            font-weight: 700;
            color: #475569;
            margin-bottom: 0.5rem;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.02em;
        }

        /* Dashboard Header */
        .dashboard-header {
            margin-bottom: 2rem;
        }

        .dashboard-header h2 {
            font-size: 1.875rem;
            font-weight: 700;
            color: var(--text-main);
            margin: 0 0 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .dashboard-header p {
            color: var(--text-muted);
            margin: 0;
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <a href="index.php" class="navbar-brand">
            <img src="../../assets/images/logo.png" alt="Logo SCPC">
            <div class="navbar-brand-info">
                <span class="navbar-brand-title">SCPC Comptoir Hammami</span>
                <span class="navbar-brand-subtitle">Central Réclamation</span>
            </div>
        </a>
        
        <div class="navbar-menu" id="mainMenu">
            <a href="index.php" class="navbar-link <?php echo basename($_SERVER['PHP_SELF']) === 'index.php' ? 'active' : ''; ?>">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </a>
            <a href="users.php" class="navbar-link <?php echo basename($_SERVER['PHP_SELF']) === 'users.php' ? 'active' : ''; ?>">
                <i class="fas fa-users"></i>
                <span>Utilisateurs</span>
            </a>
            <a href="reclamations.php" class="navbar-link <?php echo basename($_SERVER['PHP_SELF']) === 'reclamations.php' ? 'active' : ''; ?>">
                <i class="fas fa-clipboard-list"></i>
                <span>Réclamations</span>
            </a>
            <a href="activities.php" class="navbar-link <?php echo basename($_SERVER['PHP_SELF']) === 'activities.php' ? 'active' : ''; ?>">
                <i class="fas fa-history"></i>
                <span>Activités</span>
            </a>
        </div>

        <div class="navbar-actions">
            <!-- SÉLECTEUR D'ANNÉE -->
            <?php 
            require_once '../../api/year_manager.php';
            $activeYear = getActiveYear();
            $years = getAllYears();
            ?>
            <div style="display: flex; align-items: center; gap: 0.4rem; background: var(--gray-50); padding: 0.25rem 0.6rem; border-radius: 8px; border: 1px solid var(--gray-200);">
                <span style="font-size: 0.7rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase;">Année :</span>
                <form action="change_year.php" method="POST" style="margin: 0; display: flex; align-items: center;">
                    <input type="hidden" name="action" value="set_year">
                    <select name="year" onchange="this.form.submit()" style="border: none; background: transparent; font-weight: 800; color: var(--admin-primary); font-size: 0.9rem; cursor: pointer; outline: none; padding-right: 0.2rem;">
                        <?php foreach ($years as $y): ?>
                            <option value="<?php echo $y['annee']; ?>" <?php echo $y['annee'] == $activeYear ? 'selected' : ''; ?>>
                                <?php echo $y['annee']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
                <button onclick="document.getElementById('addYearModal').style.display='block'" title="Ajouter" style="background: white; border: 1px solid var(--gray-300); width: 22px; height: 22px; border-radius: 4px; display: flex; align-items: center; justify-content: center; cursor: pointer; color: var(--success-color);">
                    <i class="fas fa-plus" style="font-size: 0.65rem;"></i>
                </button>
            </div>

            <!-- BOUTON DÉCONNEXION -->
            <a href="../../pages/logout.php" class="navbar-link logout">
                <i class="fas fa-sign-out-alt"></i>
                <span>Déconnexion</span>
            </a>
        </div>
        
        <button id="navbarToggler" class="navbar-toggler">
            <i class="fas fa-bars"></i>
        </button>
    </nav>

    <!-- MODAL AJOUT ANNÉE (Intégrée) -->
    <div id="addYearModal" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); backdrop-filter: blur(4px);">
        <div style="background: white; margin: 15% auto; padding: 2rem; border-radius: 16px; width: 350px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);">
            <h3 style="margin-top: 0; display: flex; align-items: center; gap: 0.75rem; color: var(--text-main);">
                <i class="fas fa-calendar-plus" style="color: var(--success-color);"></i> Nouvelle Année
            </h3>
            <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 1.5rem;">
                Ajoutez une nouvelle année fiscale. Cela basculera automatiquement la plateforme sur cette nouvelle période vierge.
            </p>
            <form action="change_year.php" method="POST">
                <input type="hidden" name="action" value="add_year">
                <div style="margin-bottom: 1.5rem;">
                    <label style="display: block; font-weight: 600; margin-bottom: 0.5rem; font-size: 0.85rem;">Année à créer</label>
                    <input type="number" name="new_year" value="<?php echo date('Y') + 1; ?>" min="2000" max="2100" class="form-input" required>
                </div>
                <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                    <button type="button" onclick="document.getElementById('addYearModal').style.display='none'" class="btn btn-outline-secondary">Annuler</button>
                    <button type="submit" class="btn btn-success">Créer & Basculer</button>
                </div>
            </form>
        </div>
    </div>

    <div class="mobile-menu" id="mobileMenu">
        <div class="mobile-menu-header">
            <i class="fas fa-cog"></i>
            <strong>Admin</strong>
            <p class="mobile-menu-subtitle">Espace Administration</p>
        </div>
        <a href="../../index.php" class="mobile-menu-link"><i class="fas fa-home"></i> <span>Accueil Site</span></a>
        <a href="index.php" class="mobile-menu-link <?php echo basename($_SERVER['PHP_SELF']) === 'index.php' ? 'active' : ''; ?>"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a>
        <a href="users.php" class="mobile-menu-link <?php echo basename($_SERVER['PHP_SELF']) === 'users.php' ? 'active' : ''; ?>"><i class="fas fa-users"></i> <span>Utilisateurs</span></a>
        <a href="reclamations.php" class="mobile-menu-link <?php echo basename($_SERVER['PHP_SELF']) === 'reclamations.php' ? 'active' : ''; ?>"><i class="fas fa-clipboard-list"></i> <span>Réclamations</span></a>
        <a href="activities.php" class="mobile-menu-link <?php echo basename($_SERVER['PHP_SELF']) === 'activities.php' ? 'active' : ''; ?>"><i class="fas fa-history"></i> <span>Activités</span></a>
        <a href="../../pages/logout.php" class="mobile-menu-link" style="color: var(--danger-color) !important;"><i class="fas fa-sign-out-alt"></i> <span>Déconnexion</span></a>
    </div>

    <script>
        // Menu mobile fonctionnel - Version Robuste
        document.addEventListener('DOMContentLoaded', function() {
            const mobileMenu = document.getElementById('mobileMenu');
            const toggler = document.getElementById('navbarToggler');
            const togglerIcon = toggler ? toggler.querySelector('i') : null;

            // Function to toggle menu state
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

            // Toggle button click handler
            if (toggler) {
                toggler.addEventListener('click', function(e) {
                    e.stopPropagation(); 
                    const isCurrentlyOpen = mobileMenu.classList.contains('show');
                    setMenuState(!isCurrentlyOpen);
                });
            }

            // Document click handler
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

    <div class="container">
