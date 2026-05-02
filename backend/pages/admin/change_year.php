<?php
/**
 * Handler for Changing the Global Active Year
 * Fichier: pages/admin/change_year.php
 */

session_start();
require_once '../../config/database.php';
require_once '../../api/year_manager.php';

// Check Admin access
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    $debug = isset($_SESSION['user_role']) ? "Role: " . htmlspecialchars($_SESSION['user_role']) : "Pas de role en session";
    die("Accès refusé. " . $debug);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'set_year') {
        $year = intval($_POST['year'] ?? 0);
        if ($year >= 2000 && $year <= 2100) {
            setActiveYear($year);
            header("Location: " . ($_SERVER['HTTP_REFERER'] ?? 'index.php') . "?year_changed=success&target_year=$year");
            exit;
        }
    }
    
    if ($action === 'add_year') {
        $year = intval($_POST['new_year'] ?? 0);
        if ($year >= 2000 && $year <= 2100) {
            if (addNewYear($year)) {
                setActiveYear($year);
                header("Location: " . ($_SERVER['HTTP_REFERER'] ?? 'index.php') . "?year_added=success&target_year=$year");
                exit;
            }
        }
    }
}

header("Location: index.php");
exit;
