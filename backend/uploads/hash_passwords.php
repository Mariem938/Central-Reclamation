<?php
/**
 * Script temporaire pour hasher les mots de passe existants
 * À exécuter UNE SEULE FOIS puis supprimer
 * Fichier: hash_passwords.php (à la racine du projet)
 */

require_once 'config/database.php';

try {
    $db = getDB();
    
    echo "<h2>Hashage des mots de passe...</h2>";
    
    // Hash admin password (1234)
    $adminHash = password_hash('1234', PASSWORD_DEFAULT);
    $stmt = $db->prepare("UPDATE utilisateurs SET mot_de_passe = ? WHERE email = 'admin@reclamation.com'");
    $stmt->execute([$adminHash]);
    echo "✅ Admin password hashé<br>";
    
    // Hash agent password (5678)
    $agentHash = password_hash('5678', PASSWORD_DEFAULT);
    $stmt = $db->prepare("UPDATE utilisateurs SET mot_de_passe = ? WHERE email = 'agent@reclamation.com'");
    $stmt->execute([$agentHash]);
    echo "✅ Agent password hashé<br>";
    
    echo "<br><strong style='color: green;'>✅ Tous les mots de passe ont été hashés avec succès!</strong><br>";
    echo "<br><strong style='color: red;'>⚠️ IMPORTANT: Supprimez ce fichier maintenant pour des raisons de sécurité!</strong>";
    
} catch(PDOException $e) {
    echo "<strong style='color: red;'>❌ Erreur: " . $e->getMessage() . "</strong>";
}
?>