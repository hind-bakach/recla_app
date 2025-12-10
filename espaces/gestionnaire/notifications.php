<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

require_role('gestionnaire');

$user_id = $_SESSION['user_id'];
$pdo = get_pdo();

// Marquer une notification comme lue
if (isset($_GET['mark_read']) && is_numeric($_GET['mark_read'])) {
    $notif_id = $_GET['mark_read'];
    try {
        // Vérifier si la colonne role_destinataire existe
        $checkCol = $pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='notifications' AND COLUMN_NAME='role_destinataire'");
        $hasRoleCol = $checkCol->fetchColumn() > 0;
        
        if ($hasRoleCol) {
            $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE notification_id = ? AND user_id = ? AND role_destinataire = 'gestionnaire'");
        } else {
            $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE notification_id = ? AND user_id = ?");
        }
        $stmt->execute([$notif_id, $user_id]);
    } catch (PDOException $e) {
        // Ignorer l'erreur silencieusement
    }
    
    // Si on doit rediriger vers la réclamation
    if (isset($_GET['redirect_to'])) {
        header("Location: traitement.php?id=" . (int)$_GET['redirect_to']);
        exit;
    }
    
    header("Location: notifications.php");
    exit;
}

// Marquer toutes comme lues
if (isset($_GET['mark_all_read'])) {
    try {
        $checkCol = $pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='notifications' AND COLUMN_NAME='role_destinataire'");
        $hasRoleCol = $checkCol->fetchColumn() > 0;
        
        if ($hasRoleCol) {
            $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND role_destinataire = 'gestionnaire'");
        } else {
            $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND type = 'commentaire_reclamant'");
        }
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
    } elseif (in_array('title', $reclamCols)) {
        $reclamSujetCol = 'title';
    }
    
    // Vérifier si la colonne role_destinataire existe
    $checkCol = $pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='notifications' AND COLUMN_NAME='role_destinataire'");
    $hasRoleCol = $checkCol->fetchColumn() > 0;
    
    if ($hasRoleCol) {
        $whereClause = "WHERE n.user_id = ? AND n.role_destinataire = 'gestionnaire'";
    } else {
        $whereClause = "WHERE n.user_id = ? AND n.type = 'commentaire_reclamant'";
    }
    
    $stmt = $pdo->prepare("
        SELECT n.*, r.$reclamSujetCol as reclamation_sujet, u.nom, u.prenom
        FROM notifications n
        LEFT JOIN reclamations r ON n.reclamation_id = r.$reclamIdCol
        LEFT JOIN users u ON r.user_id = u.user_id
        $whereClause
        ORDER BY n.created_at DESC
    ");
    $stmt->execute([$user_id]);
    $notifications = $stmt->fetchAll();
} catch (PDOException $e) {
    // Ignorer l'erreur silencieusement
    error_log("Erreur récupération notifications: " . $e->getMessage());
}

include '../../includes/head.php';
?>
<link rel="stylesheet" href="../../css/modern.css">
<link rel="stylesheet" href="../../css/gestionnaire.css">

<body>
    <!-- Navbar -->
    <nav class="navbar navbar-minimal">
        <div class="container-fluid">
            <span class="navbar-brand">
                <i class="bi bi-check-circle-fill me-2" style="color: #14b8a6;"></i>Resolve - Gestionnaire
            </span>
            <div class="d-flex align-items-center gap-3">
                <span class="user-info">Bonjour, <strong><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Gestionnaire'); ?></strong></span>
                <a class="btn-disconnect" href="../../frontend/deconnexion.php">
                    <i class="bi bi-box-arrow-right me-1"></i> Déconnexion
                </a>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        <div class="main-content-container">
            <div class="page-header">
                <div>
                    <p class="section-title mb-1">GESTIONNAIRE</p>
                    <h1 class="main-title">
                        <i class="bi bi-bell me-2"></i>Mes Notifications
                        <?php
                        $unread = count(array_filter($notifications, function($n) { return !$n['is_read']; }));
                        if ($unread > 0):
                        ?>
                            <span class="notification-badge"><?php echo $unread; ?> non lues</span>
                        <?php endif; ?>
                    </h1>
                </div>
                <div class="d-flex gap-2">
                    <a href="index.php" class="btn-back">
                        <i class="bi bi-arrow-left me-1"></i>Retour
                    </a>
                    <?php if ($unread > 0): ?>
                        <a href="notifications.php?mark_all_read" class="btn-mark-all">
                            <i class="bi bi-check-all me-1"></i>Tout marquer comme lu
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="notifications-list">
                <?php if (count($notifications) > 0): ?>
                    <?php foreach ($notifications as $notif): ?>
                        <div class="notification-card <?php echo $notif['is_read'] ? '' : 'unread'; ?>">
                            <div class="notification-header">
                                <div class="d-flex align-items-start">
                                    <div class="notification-icon-type comment">
                                        <i class="bi bi-chat-dots"></i>
                                    </div>
                                    <div class="notification-meta">
                                        <div class="notification-title">
                                            <?php echo htmlspecialchars($notif['titre']); ?>
                                            <?php if (!$notif['is_read']): ?>
                                                <span class="badge bg-primary ms-2" style="font-size: 0.625rem;">Nouveau</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="notification-time">
                                            <i class="bi bi-clock"></i>
                                            <?php echo format_date($notif['created_at']); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="notification-message">
                                <?php echo htmlspecialchars($notif['message']); ?>
                            </div>
                            
                            <?php if ($notif['reclamation_sujet']): ?>
                                <div class="notification-reclamation">
                                    <strong>Réclamation concernée:</strong> <?php echo htmlspecialchars($notif['reclamation_sujet']); ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="notification-actions">
                                <a href="notifications.php?mark_read=<?php echo $notif['notification_id']; ?>&redirect_to=<?php echo $notif['reclamation_id']; ?>" class="btn-notification btn-view">
                                    <i class="bi bi-eye me-1"></i>Voir la réclamation
                                </a>
                                <?php if (!$notif['is_read']): ?>
                                    <a href="notifications.php?mark_read=<?php echo $notif['notification_id']; ?>" class="btn-notification btn-mark-read">
                                        <i class="bi bi-check me-1"></i>Marquer comme lu
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="bi bi-bell-slash"></i>
                        <h3>Aucune notification</h3>
                        <p>Vous n'avez aucune notification pour le moment.</p>
                        <a href="index.php" class="btn-back mt-3">
                            <i class="bi bi-arrow-left me-1"></i>Retour au tableau de bord
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php include '../../includes/footer.php'; ?>
</body>
</html>
