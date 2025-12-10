<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/lang.php';

require_role('reclamant');

$user_id = $_SESSION['user_id'];
$pdo = get_pdo();

// Marquer une notification comme lue
if (isset($_GET['mark_read']) && is_numeric($_GET['mark_read'])) {
    $notif_id = $_GET['mark_read'];
    try {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE notification_id = ? AND user_id = ?");
        $stmt->execute([$notif_id, $user_id]);
    } catch (PDOException $e) {
        // Ignorer l'erreur silencieusement
    }
    header("Location: notifications.php");
    exit;
}

// Marquer toutes comme lues
if (isset($_GET['mark_all_read'])) {
    try {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
        $stmt->execute([$user_id]);
    } catch (PDOException $e) {
        // Ignorer l'erreur silencieusement
    }
    header("Location: notifications.php");
    exit;
}

// Récupérer toutes les notifications
$notifications = [];
try {
    // Détecter le nom de la colonne ID dans reclamations
    $reclamCols = $pdo->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='reclamations'")->fetchAll(PDO::FETCH_COLUMN);
    $reclamIdCol = 'id';
    if (in_array('reclam_id', $reclamCols)) {
        $reclamIdCol = 'reclam_id';
    } elseif (in_array('reclamation_id', $reclamCols)) {
        $reclamIdCol = 'reclamation_id';
    }
    
    // Détecter le nom de la colonne sujet
    $reclamSujetCol = 'sujet';
    if (in_array('titre', $reclamCols)) {
        $reclamSujetCol = 'titre';
    } elseif (in_array('objet', $reclamCols)) {
        $reclamSujetCol = 'objet';
    }
    
    $stmt = $pdo->prepare("
        SELECT n.*, r.$reclamSujetCol as reclamation_sujet 
        FROM notifications n
        LEFT JOIN reclamations r ON n.reclamation_id = r.$reclamIdCol
        WHERE n.user_id = ?
        ORDER BY n.created_at DESC
        LIMIT 50
    ");
    $stmt->execute([$user_id]);
    $notifications = $stmt->fetchAll();
} catch (PDOException $e) {
    // Table n'existe pas encore
    $notifications = [];
    error_log("Erreur récupération notifications: " . $e->getMessage());
}
// Compter les non lues
$unread_count = 0;
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch();
    $unread_count = $result['count'] ?? 0;
} catch (PDOException $e) {
    $unread_count = 0;
}

// Fonction pour traduire le titre de notification
function translate_notification_title($title) {
    // Détecter le pattern: "Statut changé: [sujet]"
    if (preg_match('/^Statut changé:\s*(.+)$/i', $title, $matches)) {
        $subject = trim($matches[1]);
        return t('notif_status_changed_title') . ": " . $subject;
    }
    // Détecter le pattern anglais: "Status changed: [subject]"
    if (preg_match('/^Status changed:\s*(.+)$/i', $title, $matches)) {
        $subject = trim($matches[1]);
        return t('notif_status_changed_title') . ": " . $subject;
    }
    // Retourner le titre original si aucun pattern détecté
    return $title;
}

// Fonction pour traduire les messages de notification
function translate_notification_message($message) {
    // Détecter le pattern: "Le statut de votre réclamation est maintenant: [statut]"
    if (preg_match('/^Le statut de votre réclamation est maintenant:\s*(.+)$/i', $message, $matches)) {
        $status = trim($matches[1]);
        return t('notif_status_changed_message') . ": " . get_status_label_from_french($status);
    }
    // Détecter le pattern anglais: "Your claim status is now: [status]"
    if (preg_match('/^Your claim status is now:\s*(.+)$/i', $message, $matches)) {
        $status = trim($matches[1]);
        return t('notif_status_changed_message') . ": " . get_status_label_from_french($status);
    }
    // Retourner le message original si aucun pattern détecté
    return $message;
}

// Fonction pour convertir un statut français en clé et traduire
function get_status_label_from_french($french_status) {
    $status_map = [
        'Soumis' => 'soumis',
        'En cours' => 'en_cours',
        'En attente' => 'en_attente',
        'Résolu' => 'resolu',
        'Fermé' => 'ferme',
        'Rejeté' => 'rejete',
        'Archivé' => 'archive',
        'Traité' => 'traite',
        'Attente d\'info' => 'attente_info',
    ];
    
    $key = $status_map[$french_status] ?? strtolower(str_replace(' ', '_', $french_status));
    return get_status_label($key);
}

include '../../includes/head.php';
?>
<link rel="stylesheet" href="../../css/modern.css">
<link rel="stylesheet" href="../../css/reclamant.css">

<body>
    <!-- Navbar Minimaliste -->
    <nav class="navbar navbar-minimal navbar-expand-lg">
        <div class="container py-2">
            <a class="navbar-brand" href="index.php">
                <i class="bi bi-check-circle-fill me-2" style="color: #14b8a6;"></i>Resolve
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" style="border-color: #e5e7eb;">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-center">
                    <li class="nav-item me-3">
                        <span style="color: #6b7280;"><?php echo t('nav_hello'); ?>, <strong style="color: #111827;"><?php echo htmlspecialchars($_SESSION['user_name']); ?></strong></span>
                    </li>
                    <li class="nav-item me-3">
                        <a href="profil.php" class="text-decoration-none" title="Mon profil">
                            <i class="bi bi-person-circle profile-icon"></i>
                        </a>
                    </li>
                    <li class="nav-item me-3">
                        <a href="notifications.php" class="text-decoration-none position-relative">
                            <i class="bi bi-bell-fill notification-icon" style="color: #14b8a6;"></i>
                            <?php if ($unread_count > 0): ?>
                                <span class="notification-badge"><?php echo $unread_count > 9 ? '9+' : $unread_count; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="btn btn-logout" href="../../frontend/deconnexion.php">
                            <i class="bi bi-box-arrow-right me-1"></i><?php echo t('nav_logout'); ?>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container pb-5">
        <div class="main-content-container">
            <!-- En-tête -->
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4">
                <div>
                    <h6 class="section-title"><?php echo t('dashboard_area_claimant'); ?></h6>
                    <h1 class="main-title"><?php echo t('notifications_title'); ?></h1>
                </div>
                <div class="d-flex gap-2">
                    <?php if ($unread_count > 0): ?>
                        <a href="?mark_all_read=1" class="btn btn-primary-action">
                            <i class="bi bi-check-all me-2"></i><?php echo t('mark_all_read'); ?>
                        </a>
                    <?php endif; ?>
                    <a href="index.php" class="btn btn-secondary-action">
                        <i class="bi bi-arrow-left me-2"></i><?php echo t('back'); ?>
                    </a>
                </div>
            </div>
            
            <!-- Liste des notifications -->
            <?php if (empty($notifications)): ?>
                <div class="empty-state">
                    <i class="bi bi-bell-slash"></i>
                    <h3><?php echo t('no_notifications'); ?></h3>
                    <p><?php echo t('stay_tuned'); ?></p>
                </div>
            <?php else: ?>
                <?php foreach ($notifications as $notif): ?>
                    <div class="notification-card <?php echo $notif['is_read'] == 0 ? 'unread' : ''; ?>">
                        <div class="d-flex gap-3">
                            <div class="notification-icon-type <?php echo $notif['type'] == 'nouveau_commentaire' ? 'comment' : 'status'; ?>">
                                <i class="bi bi-<?php echo $notif['type'] == 'nouveau_commentaire' ? 'chat-dots' : 'arrow-repeat'; ?>"></i>
                            </div>
                            <div class="flex-grow-1">
                                <div class="notification-title"><?php echo htmlspecialchars(translate_notification_title($notif['titre'])); ?></div>
                                <div class="notification-message"><?php echo htmlspecialchars(translate_notification_message($notif['message'])); ?></div>
                                <div class="notification-time">
                                    <i class="bi bi-clock me-1"></i>
                                    <?php 
                                    $time = strtotime($notif['created_at']);
                                    $diff = time() - $time;
                                    if ($diff < 60) echo t('just_now');
                                    elseif ($diff < 3600) echo floor($diff / 60) . ' ' . t('minutes_ago');
                                    elseif ($diff < 86400) echo floor($diff / 3600) . ' ' . t('hours_ago');
                                    else echo floor($diff / 86400) . ' ' . t('days_ago');
                                    ?>
                                </div>
                                <div class="notification-actions">
                                    <a href="details.php?id=<?php echo $notif['reclamation_id']; ?>" class="btn-notification btn-view">
                                        <i class="bi bi-eye me-1"></i><?php echo t('view'); ?> <?php echo t('claim_details'); ?>
                                    </a>
                                    <?php if ($notif['is_read'] == 0): ?>
                                        <a href="?mark_read=<?php echo $notif['notification_id']; ?>" class="btn-notification btn-mark-read">
                                            <i class="bi bi-check me-1"></i><?php echo t('mark_as_read'); ?>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <?php include '../../includes/footer.php'; ?>
</body>
</html>
