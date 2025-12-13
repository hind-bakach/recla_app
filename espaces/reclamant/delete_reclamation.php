<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/lang.php';

require_role('reclamant');

$user_id = $_SESSION['user_id'];
$pdo = get_pdo();

if (!isset($_GET['id'])) {
    $_SESSION['error'] = t('error_invalid_request') ?? 'Requête invalide';
    header('Location: index.php');
    exit;
}

$reclamation_id = (int)$_GET['id'];

// Helper pour détecter les colonnes
function detect_column($pdo, $table, $candidates) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?");
    foreach ($candidates as $col) {
        $stmt->execute([$table, $col]);
        if ($stmt->fetchColumn() > 0) { return $col; }
    }
    return null;
}

try {
    // Détecter la colonne ID de reclamations
    $reclamIdCol = detect_column($pdo, 'reclamations', ['reclam_id', 'id', 'reclamation_id']);
    if (!$reclamIdCol) $reclamIdCol = 'reclam_id'; // fallback
    
    // Vérifier que la réclamation appartient bien à l'utilisateur
    $stmt = $pdo->prepare("SELECT user_id FROM reclamations WHERE `$reclamIdCol` = ?");
    $stmt->execute([$reclamation_id]);
    $reclamation = $stmt->fetch();
    
    if (!$reclamation) {
        $_SESSION['error'] = t('error_claim_not_found') ?? 'Réclamation introuvable';
        header('Location: index.php');
        exit;
    }
    
    if ($reclamation['user_id'] != $user_id) {
        $_SESSION['error'] = t('error_unauthorized') ?? 'Vous n\'êtes pas autorisé à supprimer cette réclamation';
        header('Location: index.php');
        exit;
    }
    
    // Vérifier si la table notifications existe et supprimer si oui
    $tableExists = $pdo->query("SHOW TABLES LIKE 'notifications'")->rowCount() > 0;
    if ($tableExists) {
        $notifCol = detect_column($pdo, 'notifications', ['reclamation_id', 'reclam_id', 'claim_id']);
        if ($notifCol) {
            $stmt = $pdo->prepare("DELETE FROM notifications WHERE `$notifCol` = ?");
            $stmt->execute([$reclamation_id]);
        }
    }
    
    // Vérifier si la table commentaires existe et supprimer si oui
    $tableExists = $pdo->query("SHOW TABLES LIKE 'commentaires'")->rowCount() > 0;
    if ($tableExists) {
        $commentCol = detect_column($pdo, 'commentaires', ['reclamation_id', 'reclam_id', 'claim_id']);
        if ($commentCol) {
            $stmt = $pdo->prepare("DELETE FROM commentaires WHERE `$commentCol` = ?");
            $stmt->execute([$reclamation_id]);
        }
    }
    
    // Supprimer la réclamation
    $stmt = $pdo->prepare("DELETE FROM reclamations WHERE `$reclamIdCol` = ? AND user_id = ?");
    $result = $stmt->execute([$reclamation_id, $user_id]);
    
    if ($result && $stmt->rowCount() > 0) {
        $_SESSION['success'] = t('claim_deleted_success') ?? 'Réclamation supprimée avec succès';
    } else {
        $_SESSION['error'] = 'Impossible de supprimer la réclamation';
    }
    
} catch (PDOException $e) {
    error_log("Erreur suppression réclamation: " . $e->getMessage());
    $_SESSION['error'] = 'Erreur: ' . $e->getMessage();
}

header('Location: index.php');
exit;
