<?php
// Système de gestion des langues

// Démarrer la session si pas déjà démarrée
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Définir la langue par défaut
if (!isset($_SESSION['lang'])) {
    $_SESSION['lang'] = 'fr';
}

// Changer la langue si demandé
if (isset($_GET['lang']) && in_array($_GET['lang'], ['fr', 'en'])) {
    $_SESSION['lang'] = $_GET['lang'];
    // Rediriger vers la même page sans le paramètre lang
    $redirect_url = strtok($_SERVER['REQUEST_URI'], '?');
    if (!empty($_SERVER['QUERY_STRING'])) {
        parse_str($_SERVER['QUERY_STRING'], $params);
        unset($params['lang']);
        if (!empty($params)) {
            $redirect_url .= '?' . http_build_query($params);
        }
    }
    header("Location: $redirect_url");
    exit;
}

// Charger le fichier de traduction approprié
$lang_file = __DIR__ . '/lang_' . $_SESSION['lang'] . '.php';
if (file_exists($lang_file)) {
    $translations = include $lang_file;
} else {
    $translations = include __DIR__ . '/lang_fr.php';
}

// Fonction pour obtenir une traduction
function t($key, $default = null) {
    global $translations;
    return isset($translations[$key]) ? $translations[$key] : ($default ?? $key);
}

// Fonction pour obtenir la langue actuelle
function current_lang() {
    return $_SESSION['lang'] ?? 'fr';
}

// Fonction pour générer l'URL de changement de langue
function lang_url($lang) {
    $current_url = $_SERVER['REQUEST_URI'];
    $separator = strpos($current_url, '?') === false ? '?' : '&';
    return $current_url . $separator . 'lang=' . $lang;
}
