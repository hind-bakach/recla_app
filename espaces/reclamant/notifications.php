<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

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

include '../../includes/head.php';
?>
<link rel="stylesheet" href="../../css/modern.css">

<style>
    body {
        background: linear-gradient(135deg, #cffafe 0%, #e0f2fe 50%, #e0e7ff 100%);
        min-height: 100vh;
    }
    
    .navbar-minimal {
        background-color: #ffffff;
        border-bottom: none;
        box-shadow: var(--shadow-md);
        transition: var(--transition-base);
        animation: slideDown 0.5s ease-out;
    }
    
    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    .navbar-brand {
        color: var(--gray-900) !important;
        font-weight: 700;
        font-size: 1.25rem;
    }
    
    .btn-logout {
        color: var(--primary-blue) !important;
        font-weight: 500;
        background: transparent;
        border: none;
        transition: var(--transition-base);
    }
    
    .btn-logout:hover {
        color: var(--primary-blue-dark) !important;
    }
    
    .profile-icon {
        color: var(--primary-blue);
        transition: var(--transition-base);
        cursor: pointer;
        font-size: 1.2rem;
    }
    
    .profile-icon:hover {
        color: var(--primary-blue-dark);
        transform: scale(1.1);
    }
    
    .notification-icon {
        position: relative;
        color: var(--primary-blue);
        font-size: 1.3rem;
        transition: var(--transition-base);
        cursor: pointer;
    }
    
    .notification-icon:hover {
        color: var(--primary-blue-dark);
        transform: scale(1.1);
    }
    
    .notification-badge {
        position: absolute;
        top: -8px;
        right: -8px;
        background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
        color: white;
        border-radius: 50%;
        width: 20px;
        height: 20px;
        font-size: 0.7rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 2px 8px rgba(239, 68, 68, 0.4);
    }
    
    .main-content-container {
        background: white;
        border-radius: var(--radius-xl);
        padding: 2.5rem;
        box-shadow: var(--shadow-lg);
        margin-bottom: 2rem;
        margin-top: 2rem;
        animation: fadeInUp 0.6s ease-out;
    }
    
    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(30px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    .section-title {
        color: var(--gray-500);
        font-weight: 500;
        font-size: 0.95rem;
        margin-bottom: 0.5rem;
    }
    
    .main-title {
        color: var(--gray-900);
        font-weight: 700;
        font-size: 2rem;
        margin-bottom: 2rem;
    }
    
    .notification-card {
        background: white;
        border: 2px solid var(--gray-200);
        border-radius: var(--radius-lg);
        padding: 1.5rem;
        margin-bottom: 1rem;
        transition: all var(--transition-base);
        position: relative;
        overflow: hidden;
    }
    
    .notification-card.unread {
        background: linear-gradient(135deg, #e0f2fe 0%, #f0f9ff 100%);
        border-color: var(--primary-blue);
    }
    
    .notification-card.unread::before {
        content: '';
        position: absolute;
        left: 0;
        top: 0;
        width: 4px;
        height: 100%;
        background: var(--gradient-blue);
    }
    
    .notification-card:hover {
        transform: translateX(5px);
        box-shadow: var(--shadow-lg);
    }
    
    .notification-icon-type {
        width: 48px;
        height: 48px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        flex-shrink: 0;
    }
    
    .notification-icon-type.status {
        background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
        color: #1e40af;
    }
    
    .notification-icon-type.comment {
        background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
        color: #065f46;
    }
    
    .notification-title {
        font-weight: 700;
        color: var(--gray-900);
        margin-bottom: 0.25rem;
    }
    
    .notification-message {
        color: var(--gray-600);
        font-size: 0.95rem;
        margin-bottom: 0.5rem;
    }
    
    .notification-time {
        color: var(--gray-400);
        font-size: 0.85rem;
    }
    
    .notification-actions {
        display: flex;
        gap: 0.5rem;
        margin-top: 1rem;
    }
    
    .btn-notification {
        padding: 0.5rem 1rem;
        border-radius: var(--radius-md);
        font-size: 0.875rem;
        font-weight: 600;
        text-decoration: none;
        transition: var(--transition-base);
    }
    
    .btn-view {
        background: var(--gradient-blue);
        color: white;
        border: none;
    }
    
    .btn-view:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-lg);
    }
    
    .btn-mark-read {
        background: var(--gray-100);
        color: var(--gray-700);
        border: 2px solid var(--gray-200);
    }
    
    .btn-mark-read:hover {
        background: var(--gray-200);
    }
    
    .empty-state {
        text-align: center;
        padding: 4rem 2rem;
        color: var(--gray-400);
    }
    
    .empty-state i {
        font-size: 5rem;
        color: var(--gray-300);
        margin-bottom: 1rem;
    }
    
    .btn-secondary-action {
        background: var(--gray-100);
        color: var(--gray-700);
        border: 2px solid var(--gray-200);
        padding: 0.75rem 1.75rem;
        border-radius: var(--radius-md);
        font-weight: 600;
        transition: all var(--transition-base);
        text-decoration: none;
        display: inline-block;
    }
    
    .btn-secondary-action:hover {
        background: var(--gray-200);
        border-color: var(--gray-300);
        color: var(--gray-900);
        transform: translateY(-2px);
    }
    
    .btn-primary-action {
        background: var(--gradient-blue);
        color: white;
        border: none;
        padding: 0.75rem 1.75rem;
        border-radius: var(--radius-md);
        font-weight: 600;
        transition: all var(--transition-base);
        box-shadow: var(--shadow-lg);
        text-decoration: none;
        display: inline-block;
    }
    
    .btn-primary-action:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-2xl);
    }
</style>

<body>
    <!-- Navbar Minimaliste -->
    <nav class="navbar navbar-minimal navbar-expand-lg">
        <div class="container py-2">
            <a class="navbar-brand" href="index.php">
                <i class="bi bi-chat-square-text me-2" style="color: #0891b2;"></i>Gestion des Réclamations
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" style="border-color: #e5e7eb;">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-center">
                    <li class="nav-item me-3">
                        <a href="notifications.php" class="text-decoration-none position-relative">
                            <i class="bi bi-bell-fill notification-icon"></i>
                            <?php if ($unread_count > 0): ?>
                                <span class="notification-badge"><?php echo $unread_count > 9 ? '9+' : $unread_count; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item me-3">
                        <a href="profil.php" class="text-decoration-none d-flex align-items-center gap-2">
                            <i class="bi bi-person-circle profile-icon"></i>
                            <span style="color: #6b7280;">Bonjour, <strong style="color: #111827;"><?php echo htmlspecialchars($_SESSION['user_name']); ?></strong></span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="btn btn-logout" href="../../frontend/deconnexion.php">
                            <i class="bi bi-box-arrow-right me-1"></i>Déconnexion
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
                    <h6 class="section-title">Espace Réclamant</h6>
                    <h1 class="main-title">Notifications</h1>
                </div>
                <div class="d-flex gap-2">
                    <?php if ($unread_count > 0): ?>
                        <a href="?mark_all_read=1" class="btn btn-primary-action">
                            <i class="bi bi-check-all me-2"></i>Tout marquer comme lu
                        </a>
                    <?php endif; ?>
                    <a href="index.php" class="btn btn-secondary-action">
                        <i class="bi bi-arrow-left me-2"></i>Retour
                    </a>
                </div>
            </div>
            
            <!-- Liste des notifications -->
            <?php if (empty($notifications)): ?>
                <div class="empty-state">
                    <i class="bi bi-bell-slash"></i>
                    <h3>Aucune notification</h3>
                    <p>Vous n'avez aucune notification pour le moment.</p>
                </div>
            <?php else: ?>
                <?php foreach ($notifications as $notif): ?>
                    <div class="notification-card <?php echo $notif['is_read'] == 0 ? 'unread' : ''; ?>">
                        <div class="d-flex gap-3">
                            <div class="notification-icon-type <?php echo $notif['type'] == 'nouveau_commentaire' ? 'comment' : 'status'; ?>">
                                <i class="bi bi-<?php echo $notif['type'] == 'nouveau_commentaire' ? 'chat-dots' : 'arrow-repeat'; ?>"></i>
                            </div>
                            <div class="flex-grow-1">
                                <div class="notification-title"><?php echo htmlspecialchars($notif['titre']); ?></div>
                                <div class="notification-message"><?php echo htmlspecialchars($notif['message']); ?></div>
                                <div class="notification-time">
                                    <i class="bi bi-clock me-1"></i>
                                    <?php 
                                    $time = strtotime($notif['created_at']);
                                    $diff = time() - $time;
                                    if ($diff < 60) echo 'À l\'instant';
                                    elseif ($diff < 3600) echo floor($diff / 60) . ' min';
                                    elseif ($diff < 86400) echo floor($diff / 3600) . ' h';
                                    else echo floor($diff / 86400) . ' j';
                                    ?>
                                </div>
                                <div class="notification-actions">
                                    <a href="details.php?id=<?php echo $notif['reclamation_id']; ?>" class="btn-notification btn-view">
                                        <i class="bi bi-eye me-1"></i>Voir la réclamation
                                    </a>
                                    <?php if ($notif['is_read'] == 0): ?>
                                        <a href="?mark_read=<?php echo $notif['notification_id']; ?>" class="btn-notification btn-mark-read">
                                            <i class="bi bi-check me-1"></i>Marquer comme lu
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
