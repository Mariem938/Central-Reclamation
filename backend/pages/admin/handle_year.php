<?php
/**
 * Handler pour la gestion des années (Admin)
 * Fichier: pages/admin/handle_year.php
 */
session_start();
require_once '../../config/database.php';
require_once '../../api/year_manager.php';

// Sécurité: Authentifié requis
if (!isset($_SESSION['user_id'])) {
    die("Accès non autorisé.");
}
$isAdmin = isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';

$action = $_GET['action'] ?? '';
$year = (int)($_GET['year'] ?? 0);

if ($action === 'set' && $year > 0) {
    if (setActiveYear($year)) {
        $_SESSION['success_message'] = "Vous travaillez maintenant sur l'année $year.";
    } else {
        $_SESSION['error_message'] = "Erreur lors du changement d'année.";
    }
} elseif ($action === 'add' && $year > 0 && $isAdmin) {
    if (addNewYear($year)) {
        setActiveYear($year);
        $_SESSION['success_message'] = "Année $year ajoutée et activée avec succès.";
    } else {
        $_SESSION['error_message'] = "L'année $year existe déjà ou une erreur est survenue.";
    }
}

// Redirection vers la page d'où on vient (ou dashboard admin par défaut)
$referer = $_SERVER['HTTP_REFERER'] ?? 'index.php';
header("Location: $referer");
exit;
