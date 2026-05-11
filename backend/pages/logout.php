<?php
/**
 * Déconnexion universelle (tous les rôles)
 * Fichier: pages/logout.php
 */

session_start();
session_unset();
session_destroy();

// Rediriger vers la page d'accueil
header('Location: ../index.php');
exit;
?>
