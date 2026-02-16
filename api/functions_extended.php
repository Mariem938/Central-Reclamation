<?php
/**
 * Fichier de fonctions étendues
 * Contient les fonctions manquantes signalées (getSites, getUsersByRole...)
 */

/**
 * Récupérer la liste des sites
 * @return array
 */
function getSites() {
    $db = getDB();
    // On vérifie d'abord si la table 'sites' existe
    try {
        $db->query("SELECT 1 FROM sites LIMIT 1");
        $stmt = $db->query("SELECT * FROM sites WHERE actif = 1 ORDER BY nom");
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        // Fallback si la table n'existe pas encore
        return [
            ['id' => 1, 'nom' => 'Tunis'],
            ['id' => 2, 'nom' => 'Sfax'],
            ['id' => 3, 'nom' => 'Sousse']
        ];
    }
}

/**
 * Récupérer les utilisateurs par rôle
 * @param string $role
 * @return array
 */
function getUsersByRole($role) {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM utilisateurs WHERE role = ? AND actif = 1 ORDER BY nom, prenom");
    $stmt->execute([$role]);
    return $stmt->fetchAll();
}
?>
