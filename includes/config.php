<?php
// Démarrer la session pour gérer l'état de l'utilisateur après la connexion
session_start();

// --- PARAMÈTRES DE LA BASE DE DONNÉES ---
define('DB_HOST', 'localhost'); // L'hôte de votre base de données
define('DB_USER', 'root');      // Votre nom d'utilisateur MySQL
define('DB_PASS', '');          // Votre mot de passe MySQL (laissez vide si pas de mot de passe)
define('DB_NAME', 'reclamation_db'); // Le nom de la base de données que vous devez créer

// --- CONNEXION PDO ---
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8", DB_USER, DB_PASS);
    
    // Configurer PDO pour lancer des exceptions en cas d'erreur (mode développeur)
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Configurer le mode de récupération par défaut (objets ou tableaux associatifs)
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // En cas d'échec de la connexion, afficher une erreur (à retirer en production)
    die("Erreur de connexion à la base de données: " . $e->getMessage());
}

// Fonction utilitaire pour éviter de répéter le require/include partout
function get_pdo() {
    global $pdo;
    return $pdo;
}