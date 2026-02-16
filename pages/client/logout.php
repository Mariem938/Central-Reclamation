<?php
/**
 * Déconnexion Client
 * Fichier: pages/client/logout.php
 */

session_start();
session_unset();
session_destroy();

header('Location: ../../index.php');
exit;
?>
