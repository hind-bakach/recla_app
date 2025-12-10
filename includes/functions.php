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
    // Essayer de trouver une clé de traduction
    $key = 'status_' . $status;
    $translated = t($key);
    
    // Si la traduction existe (différente de la clé), la retourner
    if ($translated !== $key) {
        return $translated;
    }
    
    // Fallback pour les statuts standards
    switch ($status) {
        case 'soumis': return t('status_soumis');
        case 'en_cours': return t('status_en_cours');
        case 'en_attente': return t('status_en_attente');
        case 'resolu': return t('status_resolu');
        case 'closed': return t('status_closed');
        case 'rejete': return t('status_rejete');
        case 'archive': return t('status_archive');
        case 'traite': return t('status_traite');
        case 'ferme': return t('status_ferme');
        case 'attente_info': return t('status_attente_info');
        default: return ucfirst(str_replace('_', ' ', $status));
    }
}
?>