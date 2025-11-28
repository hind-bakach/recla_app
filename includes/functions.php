<?php
// functions.php

/**
 * Redirige l'utilisateur vers une URL donnée
 */
function redirect($url) {
    header("Location: $url");
    exit();
}

/**
 * Vérifie si l'utilisateur est connecté
 */
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

/**
 * Vérifie si l'utilisateur a un rôle spécifique
 */
function has_role($role) {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === $role;
}

/**
 * Exige que l'utilisateur soit connecté, sinon redirige vers login
 */
function require_login() {
    if (!is_logged_in()) {
        redirect('../../frontend/login.php');
    }
}

/**
 * Exige un rôle spécifique, sinon redirige (ou affiche erreur)
 */
function require_role($role) {
    require_login();
    if (!has_role($role)) {
        // Redirection vers le tableau de bord approprié selon le rôle réel
        if (has_role('administrateur')) {
            redirect('../../espaces/administrateur/index.php');
        } elseif (has_role('gestionnaire')) {
            redirect('../../espaces/gestionnaire/index.php');
        } else {
            redirect('../../espaces/reclamant/index.php');
        }
    }
}

/**
 * Nettoie les entrées utilisateur pour éviter les failles XSS
 */
function sanitize_input($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

/**
 * Formate la date pour l'affichage
 */
function format_date($date) {
    if (empty($date) || $date === '0000-00-00' || $date === '0000-00-00 00:00:00') {
        return '-';
    }
    $ts = strtotime($date);
    if ($ts === false) {
        return '-';
    }
    return date('d/m/Y H:i', $ts);
}

/**
 * Retourne la classe Bootstrap pour le badge de statut
 */
function get_status_badge($status) {
    switch ($status) {
        case 'soumis': return 'bg-info text-white';
        case 'en_cours': return 'bg-warning text-dark';
        case 'en_attente': return 'bg-secondary text-white';
        case 'resolu': return 'bg-success text-white';
        case 'closed': return 'bg-success text-white';
        case 'rejete': return 'bg-danger text-white';
        case 'archive': return 'bg-dark text-white';
        case 'traite': return 'bg-success';
        case 'ferme': return 'bg-secondary';
        case 'attente_info': return 'bg-info text-dark';
        default: return 'bg-light text-dark';
    }
}

/**
 * Retourne le libellé lisible du statut
 */
function get_status_label($status) {
    switch ($status) {
        case 'soumis': return 'Soumis';
        case 'en_cours': return 'En cours';
        case 'en_attente': return 'En attente';
        case 'resolu': return 'Résolu';
        case 'closed': return 'Fermé';
        case 'rejete': return 'Rejeté';
        case 'archive': return 'Archivé';
        case 'traite': return 'Traité';
        case 'ferme': return 'Fermé';
        case 'attente_info': return 'Attente d\'info';
        default: return $status;
    }
}
?>