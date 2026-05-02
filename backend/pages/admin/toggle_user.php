<?php
session_start();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../agent/login.php');
    exit;
}

$db = getDB();
$userId = $_GET['id'] ?? 0;
$action = $_GET['action'] ?? '';

if (!$userId || !in_array($action, ['activer', 'desactiver'])) {
    header('Location: users.php');
    exit;
}

// Récupérer l'utilisateur
$stmt = $db->prepare("SELECT * FROM utilisateurs WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    header('Location: users.php');
    exit;
}

// Ne pas permettre de désactiver son propre compte
if ($userId == $_SESSION['user_id'] && $action === 'desactiver') {
    $_SESSION['error'] = "Vous ne pouvez pas désactiver votre propre compte.";
    header('Location: users.php');
    exit;
}

// Effectuer l'action
$newStatus = ($action === 'activer') ? 1 : 0;
$stmt = $db->prepare("UPDATE utilisateurs SET actif = ? WHERE id = ?");
$stmt->execute([$newStatus, $userId]);

// Logger l'action
$stmt = $db->prepare("INSERT INTO historique_actions (utilisateur_id, action, details, date_action) VALUES (?, ?, ?, NOW())");
$details = "Utilisateur " . $user['prenom'] . " " . $user['nom'] . " " . ($action === 'activer' ? 'activé' : 'désactivé') . " par admin";
$stmt->execute([$_SESSION['user_id'], 'user_' . $action, $details]);

$_SESSION['success'] = "Utilisateur " . ($action === 'activer' ? 'activé' : 'désactivé') . " avec succès.";
header('Location: users.php');
exit;
?>
