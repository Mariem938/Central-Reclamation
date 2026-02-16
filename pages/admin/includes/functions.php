<?php
require_once __DIR__ . '/../../../api/functions.php';


if (!function_exists('getStatutBadge')) {
    function getStatutBadge($statut) {
        return "<span class='badge' style='background-color: #e2e8f0; color: #475569;'>" . htmlspecialchars($statut) . "</span>";
    }
}

if (!function_exists('formatDateTime')) {
    function formatDateTime($date) {
        if (!$date) return 'N/A';
        return date('d/m/Y H:i', strtotime($date));
    }
}
?>
