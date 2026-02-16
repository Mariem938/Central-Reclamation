<?php
/**
 * Gestionnaire d'Année Globale
 * Fichier: api/year_manager.php
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Récupérer l'année active (Session > Base > Courante)
 */
function getActiveYear() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // 1. Session (Priorité haute pour fluidité utilisateur)
    if (isset($_SESSION['active_year'])) {
        return (int)$_SESSION['active_year'];
    }

    // 2. Base de données
    try {
        $db = getDB();
        $stmt = $db->query("SELECT annee FROM gestion_annees WHERE active = 1 LIMIT 1");
        $year = $stmt->fetchColumn();
        if ($year) {
            $_SESSION['active_year'] = (int)$year;
            return (int)$year;
        }
    } catch (Exception $e) {
        // Silently fail to fallback
    }

    // 3. Fallback
    $year = (int)date('Y');
    $_SESSION['active_year'] = $year;
    return $year;
}

/**
 * Définir l'année active
 */
function setActiveYear($year) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $year = (int)$year;
    $_SESSION['active_year'] = $year;

    // Seul l'admin peut changer l'année PAR DÉFAUT du système en base
    if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') {
        try {
            $db = getDB();
            $db->exec("UPDATE gestion_annees SET active = 0");
            $stmt = $db->prepare("UPDATE gestion_annees SET active = 1 WHERE annee = ?");
            $stmt->execute([$year]);
        } catch (Exception $e) {
            return false;
        }
    }

    return true;
}

/**
 * Récupérer toutes les années configurées
 */
function getAllYears() {
    try {
        $db = getDB();
        $stmt = $db->query("SELECT * FROM gestion_annees ORDER BY annee DESC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [['annee' => date('Y'), 'active' => 1]];
    }
}

/**
 * Ajouter une nouvelle année (Admin seulement)
 */
function addNewYear($year) {
    if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
        return false;
    }

    try {
        $db = getDB();
        $stmt = $db->prepare("INSERT INTO gestion_annees (annee, active) VALUES (?, 0)");
        return $stmt->execute([(int)$year]);
    } catch (PDOException $e) {
        return false;
    }
}
