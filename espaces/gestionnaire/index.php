<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

require_role('gestionnaire');

$pdo = get_pdo();
$user_id = $_SESSION['user_id'];

// Récupérer le nombre de notifications non lues pour le gestionnaire
$unread_count = 0;
$recent_notifications = [];
try {
    // Détecter les colonnes de reclamations
    $reclamCols = $pdo->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='reclamations'")->fetchAll(PDO::FETCH_COLUMN);
    $reclamIdCol = in_array('reclam_id', $reclamCols) ? 'reclam_id' : (in_array('id', $reclamCols) ? 'id' : 'id');
    $reclamSujetCol = in_array('sujet', $reclamCols) ? 'sujet' : (in_array('objet', $reclamCols) ? 'objet' : (in_array('titre', $reclamCols) ? 'titre' : 'objet'));
    
    // Vérifier si la colonne role_destinataire existe
    $checkCol = $pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='notifications' AND COLUMN_NAME='role_destinataire'");
    $hasRoleCol = $checkCol->fetchColumn() > 0;
    
    if ($hasRoleCol) {
        $whereClause = "WHERE n.user_id = ? AND n.role_destinataire = 'gestionnaire' AND n.is_read = 0";
        $whereClause2 = "WHERE n.user_id = ? AND n.role_destinataire = 'gestionnaire'";
    } else {
        $whereClause = "WHERE n.user_id = ? AND n.type = 'commentaire_reclamant' AND n.is_read = 0";
        $whereClause2 = "WHERE n.user_id = ? AND n.type = 'commentaire_reclamant'";
    }
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications n $whereClause");
    $stmt->execute([$user_id]);
    $unread_count = (int)$stmt->fetchColumn();
    
    // Récupérer les 3 dernières notifications avec le nom du réclamant
    $stmt = $pdo->prepare("
        SELECT n.*, r.$reclamSujetCol as sujet, u.nom, u.prenom
        FROM notifications n
        LEFT JOIN reclamations r ON n.reclamation_id = r.$reclamIdCol
        LEFT JOIN users u ON r.user_id = u.user_id
        $whereClause2
        ORDER BY n.created_at DESC 
        LIMIT 3
    ");
    $stmt->execute([$user_id]);
    $recent_notifications = $stmt->fetchAll();
} catch (PDOException $e) {
    // Ignorer les erreurs silencieusement
    error_log("Erreur notifications gestionnaire: " . $e->getMessage());
}

// Filtrage
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$date_filter = isset($_GET['date']) ? $_GET['date'] : '';
$user_filter = isset($_GET['user']) ? trim($_GET['user']) : '';

$params = [];

// Detect column names dynamically to avoid "unknown column" errors
$getCols = function($table) use ($pdo) {
    $rows = $pdo->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA='".DB_NAME."' AND TABLE_NAME='".addslashes($table)."'")->fetchAll(PDO::FETCH_COLUMN);
    return $rows ?: [];
};

$reclamCols = $getCols('reclamations');
$reclamTable = 'reclamations';
$reclamIdCol = in_array('reclam_id', $reclamCols) ? 'reclam_id' : (in_array('id', $reclamCols) ? 'id' : ($reclamCols[0] ?? 'id'));
$reclamUserCol = in_array('user_id', $reclamCols) ? 'user_id' : (in_array('userId', $reclamCols) ? 'userId' : (in_array('user', $reclamCols) ? 'user' : 'user_id'));
$reclamStatusCol = in_array('statut', $reclamCols) ? 'statut' : (in_array('status', $reclamCols) ? 'status' : 'statut');
$reclamDateCol = in_array('created_at', $reclamCols) ? 'created_at' : (in_array('date_soumission', $reclamCols) ? 'date_soumission' : ($reclamCols[1] ?? $reclamCols[0] ?? 'created_at'));
$reclamCatCol = in_array('categorie_id', $reclamCols) ? 'categorie_id' : (in_array('category_id', $reclamCols) ? 'category_id' : (in_array('cat_id', $reclamCols) ? 'cat_id' : null));
$reclamObjetCol = in_array('objet', $reclamCols) ? 'objet' : (in_array('sujet', $reclamCols) ? 'sujet' : (in_array('title', $reclamCols) ? 'title' : 'objet'));

$userCols = $getCols('users');
$userIdCol = in_array('user_id', $userCols) ? 'user_id' : (in_array('id', $userCols) ? 'id' : ($userCols[0] ?? 'id'));
$userNameCol = in_array('nom', $userCols) ? 'nom' : (in_array('name', $userCols) ? 'name' : ($userCols[1] ?? $userIdCol));

$catCols = $getCols('categories');
$catIdCol = in_array('categorie_id', $catCols) ? 'categorie_id' : (in_array('id', $catCols) ? 'id' : ($catCols[0] ?? 'id'));
$catNameCol = in_array('nom_categorie', $catCols) ? 'nom_categorie' : (in_array('nom', $catCols) ? 'nom' : (in_array('name', $catCols) ? 'name' : ($catCols[1] ?? $catIdCol)));

// Build SQL with stable aliases used by the template
$sql = "SELECT ";
$sql .= "c." . $reclamIdCol . " AS id, ";
$sql .= "c." . $reclamUserCol . " AS user_id, ";
$sql .= "u." . $userNameCol . " AS user_name, ";
$sql .= "c." . $reclamObjetCol . " AS sujet, ";
$sql .= "c.description AS description, ";
$sql .= "cat." . $catNameCol . " AS category_nom, ";
$sql .= "c." . $reclamDateCol . " AS created_at, ";
$sql .= "c." . $reclamStatusCol . " AS statut ";
$sql .= "FROM " . $reclamTable . " c ";
$sql .= "LEFT JOIN users u ON c." . $reclamUserCol . " = u." . $userIdCol . " ";
$sql .= "LEFT JOIN categories cat ON c." . ($reclamCatCol ?? $catIdCol) . " = cat." . $catIdCol . " ";
$sql .= "WHERE 1=1";

if ($status_filter) {
    $sql .= " AND c." . $reclamStatusCol . " = ?";
    $params[] = $status_filter;
}

if ($date_filter) {
    $sql .= " AND DATE(c." . $reclamDateCol . ") = ?";
    $params[] = $date_filter;
}

if ($user_filter) {
    $sql .= " AND (u." . $userNameCol . " LIKE ? OR u.email LIKE ?)";
    $params[] = '%' . $user_filter . '%';
    $params[] = '%' . $user_filter . '%';
}

$sql .= " ORDER BY c." . $reclamDateCol . " DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$claims = $stmt->fetchAll();

// Statistiques rapides
// Statistiques rapides (colonne statut dynamique + valeurs présentes)
$presentStatuses = [];
try {
    $presentStatuses = $pdo->query("SELECT DISTINCT `".$reclamStatusCol."` AS s FROM " . $reclamTable)->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) { $presentStatuses = []; }

$countTotal = (int)$pdo->query("SELECT COUNT(*) FROM " . $reclamTable)->fetchColumn();

// Choisir les libellés compatibles selon ce qui existe
function statusExists($arr, $candidates){ foreach($candidates as $c){ if(in_array($c,$arr)) return $c; } return null; }
$valEnCours = statusExists($presentStatuses, ['en_cours','en cours','encours']);
$valTraite  = statusExists($presentStatuses, ['traite','traité','resolu','résolu']);

$countEnCours = 0; $countTraite = 0;
if ($valEnCours) {
    $q = $pdo->prepare("SELECT COUNT(*) FROM " . $reclamTable . " WHERE `".$reclamStatusCol."` = ?");
    $q->execute([$valEnCours]);
    $countEnCours = (int)$q->fetchColumn();
}
if ($valTraite) {
    $q = $pdo->prepare("SELECT COUNT(*) FROM " . $reclamTable . " WHERE `".$reclamStatusCol."` = ?");
    $q->execute([$valTraite]);
    $countTraite = (int)$q->fetchColumn();
}

$stats = [ 'total' => $countTotal, 'en_cours' => $countEnCours, 'traite' => $countTraite ];

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
    
    .user-info {
        color: var(--gray-700);
        font-size: 0.938rem;
    }
    
    .btn-disconnect {
        color: var(--gray-700) !important;
        border: 2px solid var(--gray-200);
        padding: 0.5rem 1rem;
        border-radius: var(--radius-md);
        font-weight: 600;
        transition: all var(--transition-base);
        background: white;
    }
    
    .btn-disconnect:hover {
        border-color: var(--primary-blue);
        color: var(--primary-blue) !important;
        transform: translateY(-2px);
        box-shadow: var(--shadow-sm);
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
    
    .sidebar-card {
        background: white;
        border-radius: var(--radius-lg);
        padding: 1.5rem;
        box-shadow: var(--shadow-md);
        margin-bottom: 1.5rem;
        animation: fadeInLeft 0.6s ease-out;
    }
    
    @keyframes fadeInLeft {
        from {
            opacity: 0;
            transform: translateX(-20px);
        }
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }
    
    .section-title {
        color: var(--gray-500);
        font-weight: 500;
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin-bottom: 1rem;
    }
    
    .stat-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.75rem 0;
        border-bottom: 1px solid var(--gray-100);
    }
    
    .stat-item:last-child {
        border-bottom: none;
    }
    
    .stat-label {
        color: var(--gray-700);
        font-size: 0.875rem;
        font-weight: 500;
    }
    
    .stat-badge {
        padding: 0.25rem 0.75rem;
        border-radius: var(--radius-full);
        font-weight: 600;
        font-size: 0.813rem;
        white-space: nowrap;
    }
    
    .stat-badge.primary {
        background: linear-gradient(135deg, #14b8a6 0%, #0ea5e9 100%);
        color: white;
    }
    
    .stat-badge.warning {
        background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
        color: white;
    }
    
    .stat-badge.success {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        color: white;
    }
    
    .filter-label {
        color: var(--gray-700);
        font-weight: 600;
        font-size: 0.813rem;
        margin-bottom: 0.5rem;
    }
    
    .form-control, .form-select {
        border: 2px solid var(--gray-200);
        border-radius: var(--radius-md);
        padding: 0.625rem 0.875rem;
        font-size: 0.875rem;
        transition: all var(--transition-base);
        background-color: var(--gray-50);
    }
    
    .form-control:focus, .form-select:focus {
        border-color: var(--primary-blue);
        box-shadow: 0 0 0 4px rgba(20, 184, 166, 0.1);
        background-color: white;
    }
    
    .btn-reset {
        background-color: white;
        color: var(--gray-700);
        border: 2px solid var(--gray-200);
        padding: 0.625rem 1rem;
        border-radius: var(--radius-md);
        font-weight: 600;
        transition: all var(--transition-base);
        font-size: 0.875rem;
        white-space: nowrap;
        text-decoration: none;
        display: block;
        text-align: center;
    }
    
    .btn-reset:hover {
        background-color: var(--gray-100);
        border-color: var(--gray-400);
        transform: translateY(-2px);
    }
    
    .page-header {
        margin-bottom: 2rem;
        animation: fadeIn 0.8s ease-out 0.2s both;
    }
    
    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    .main-title {
        color: var(--gray-900);
        font-weight: 700;
        font-size: 1.75rem;
        margin-bottom: 0.5rem;
    }
    
    .dossier-count {
        color: var(--gray-500);
        font-size: 0.938rem;
        font-weight: 500;
    }
    
    .table-container {
        border-radius: var(--radius-lg);
        overflow: hidden;
        box-shadow: var(--shadow-sm);
    }
    
    .table {
        margin-bottom: 0;
    }
    
    .table thead {
        background: linear-gradient(135deg, var(--gray-50) 0%, var(--gray-100) 100%);
    }
    
    .table thead th {
        color: var(--gray-700);
        font-weight: 600;
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        border-bottom: 2px solid var(--gray-200);
        padding: 1rem;
    }
    
    .table tbody tr {
        transition: all var(--transition-base);
        cursor: pointer;
    }
    
    .table tbody tr:hover {
        background: linear-gradient(135deg, rgba(20, 184, 166, 0.03) 0%, rgba(14, 165, 233, 0.03) 100%);
        transform: translateX(4px);
    }
    
    .table tbody td {
        padding: 1rem;
        vertical-align: middle;
        color: var(--gray-700);
        font-size: 0.875rem;
    }
    
    .user-avatar {
        width: 36px;
        height: 36px;
        border-radius: var(--radius-full);
        background: linear-gradient(135deg, #14b8a6 0%, #0ea5e9 100%);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 0.875rem;
        margin-right: 0.75rem;
    }
    
    .category-badge {
        background-color: var(--gray-100);
        color: var(--gray-700);
        padding: 0.375rem 0.875rem;
        border-radius: var(--radius-full);
        font-size: 0.813rem;
        font-weight: 500;
        border: 1px solid var(--gray-200);
        white-space: nowrap;
    }
    
    .status-badge {
        padding: 0.375rem 0.875rem;
        border-radius: var(--radius-full);
        font-size: 0.813rem;
        font-weight: 600;
        white-space: nowrap;
    }
    
    .status-badge.en-cours {
        background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
        color: #92400e;
    }
    
    .status-badge.traite {
        background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
        color: #065f46;
    }
    
    .status-badge.ferme {
        background: linear-gradient(135deg, #e5e7eb 0%, #d1d5db 100%);
        color: #374151;
    }
    
    .status-badge.attente {
        background: linear-gradient(135deg, #fecaca 0%, #fca5a5 100%);
        color: #7f1d1d;
    }
    
    .btn-action {
        background: var(--gradient-blue);
        color: white;
        border: none;
        padding: 0.5rem 1.25rem;
        border-radius: var(--radius-md);
        font-weight: 600;
        transition: all var(--transition-base);
        font-size: 0.813rem;
        box-shadow: var(--shadow-sm);
        white-space: nowrap;
        text-decoration: none;
    }
    
    .btn-action:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-lg);
        color: white;
    }
    
    .empty-state {
        padding: 4rem 2rem;
        text-align: center;
        color: var(--gray-400);
    }
    
    .empty-state i {
        font-size: 4rem;
        margin-bottom: 1.5rem;
        opacity: 0.5;
        animation: float 3s ease-in-out infinite;
    }
    
    .empty-state small {
        display: block;
        margin-top: 0.5rem;
        font-size: 0.813rem;
    }
    
    @keyframes float {
        0%, 100% { transform: translateY(0); }
        50% { transform: translateY(-10px); }
    }
    
    .btn-action i {
        font-size: 0.875rem;
    }
    
    .status-indicator {
        display: inline-block;
        width: 8px;
        height: 8px;
        border-radius: 50%;
        margin-right: 0.5rem;
        animation: pulse 2s ease-in-out infinite;
    }
    
    .status-indicator.urgent {
        background-color: #ef4444;
    }
    
    .status-indicator.normal {
        background-color: #14b8a6;
    }
    
    @keyframes pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.5; }
    }
    
    /* Notifications Styles */
    .notification-icon-wrapper {
        position: relative;
        display: inline-block;
    }
    
    .notification-icon {
        color: var(--gray-700);
        font-size: 1.25rem;
        position: relative;
        cursor: pointer;
        transition: all var(--transition-base);
        padding: 0.5rem;
        border-radius: var(--radius-md);
    }
    
    .notification-icon:hover {
        color: var(--primary-blue);
        background-color: var(--gray-100);
    }
    
    .notification-badge {
        position: absolute;
        top: 0;
        right: 0;
        background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
        color: white;
        border-radius: var(--radius-full);
        padding: 0.125rem 0.375rem;
        font-size: 0.625rem;
        font-weight: 700;
        min-width: 18px;
        height: 18px;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        animation: notifPulse 2s ease-in-out infinite;
    }
    
    @keyframes notifPulse {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.1); }
    }
    
    /* Notification Dropdown */
    .notification-dropdown-wrapper {
        position: relative;
        display: inline-block;
    }
    
    .notification-dropdown {
        position: absolute;
        top: calc(100% + 15px);
        right: 0;
        background: white;
        border-radius: var(--radius-lg);
        box-shadow: 0 10px 40px rgba(0,0,0,0.15);
        width: 350px;
        max-height: 400px;
        overflow-y: auto;
        z-index: 1000;
        opacity: 0;
        visibility: hidden;
        transform: translateY(-10px);
        transition: all 0.3s ease;
    }
    
    .notification-dropdown-wrapper:hover .notification-dropdown {
        opacity: 1;
        visibility: visible;
        transform: translateY(0);
    }
    
    .notification-dropdown::before {
        content: '';
        position: absolute;
        top: -8px;
        right: 20px;
        width: 16px;
        height: 16px;
        background: white;
        transform: rotate(45deg);
        box-shadow: -3px -3px 5px rgba(0,0,0,0.05);
    }
    
    .notification-dropdown-header {
        padding: 1rem 1.25rem;
        border-bottom: 2px solid var(--gray-100);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .notification-dropdown-header h6 {
        margin: 0;
        font-size: 0.875rem;
        font-weight: 700;
        color: var(--gray-900);
    }
    
    .notification-dropdown-item {
        padding: 1rem 1.25rem;
        border-bottom: 1px solid var(--gray-100);
        transition: all var(--transition-base);
        cursor: pointer;
        text-decoration: none;
        display: block;
        color: inherit;
    }
    
    .notification-dropdown-item:hover {
        background: linear-gradient(135deg, rgba(20, 184, 166, 0.05) 0%, rgba(14, 165, 233, 0.05) 100%);
        transform: translateX(4px);
    }
    
    .notification-dropdown-item.unread {
        background-color: rgba(59, 130, 246, 0.05);
        border-left: 3px solid var(--primary-blue);
    }
    
    .notification-dropdown-item .notification-time {
        font-size: 0.688rem;
        color: var(--gray-500);
        margin-bottom: 0.25rem;
        display: flex;
        align-items: center;
        gap: 0.25rem;
    }
    
    .notification-dropdown-item .notification-title {
        font-size: 0.813rem;
        font-weight: 600;
        color: var(--gray-900);
        margin-bottom: 0.25rem;
    }
    
    .notification-dropdown-item .notification-message {
        font-size: 0.75rem;
        color: var(--gray-600);
        line-height: 1.4;
    }
    
    .notification-dropdown-footer {
        padding: 0.75rem 1.25rem;
        text-align: center;
        border-top: 1px solid var(--gray-100);
    }
    
    .notification-dropdown-footer a {
        color: var(--primary-blue);
        font-size: 0.813rem;
        font-weight: 600;
        text-decoration: none;
        transition: all var(--transition-base);
    }
    
    .notification-dropdown-footer a:hover {
        color: #0891b2;
        text-decoration: underline;
    }
    
    .notification-empty {
        padding: 2rem;
        text-align: center;
        color: var(--gray-400);
    }
    
    .notification-empty i {
        font-size: 2rem;
        margin-bottom: 0.5rem;
        opacity: 0.5;
    }
</style>

<script src="../../js/main.js" defer></script>

<body>
    <!-- Navbar -->
    <nav class="navbar navbar-minimal">
        <div class="container-fluid">
            <span class="navbar-brand">
                <i class="bi bi-person-workspace"></i> Espace Gestionnaire
            </span>
            <div class="d-flex align-items-center gap-3">
                <span class="user-info">Bonjour, <strong><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'manel el moidni'); ?></strong></span>
                
                <!-- Notification Icon with Dropdown -->
                <div class="notification-dropdown-wrapper">
                    <a href="notifications.php" class="notification-icon-wrapper">
                        <i class="bi bi-bell notification-icon"></i>
                        <?php if ($unread_count > 0): ?>
                            <span class="notification-badge"><?php echo $unread_count; ?></span>
                        <?php endif; ?>
                    </a>
                    
                    <div class="notification-dropdown">
                        <div class="notification-dropdown-header">
                            <h6>
                                <i class="bi bi-bell me-1"></i> Notifications
                                <?php if ($unread_count > 0): ?>
                                    <span class="notification-badge" style="position: static; margin-left: 0.5rem;"><?php echo $unread_count; ?></span>
                                <?php endif; ?>
                            </h6>
                        </div>
                        
                        <?php if (count($recent_notifications) > 0): ?>
                            <?php foreach ($recent_notifications as $notif): ?>
                                <?php
                                $created_at = $notif['created_at'];
                                $diff = time() - strtotime($created_at);
                                $time_text = '';
                                if ($diff < 60) {
                                    $time_text = 'À l\'instant';
                                } elseif ($diff < 3600) {
                                    $time_text = floor($diff / 60) . ' min';
                                } elseif ($diff < 86400) {
                                    $time_text = floor($diff / 3600) . ' h';
                                } else {
                                    $time_text = floor($diff / 86400) . ' j';
                                }
                                ?>
                                <a href="notifications.php?mark_read=<?php echo $notif['notification_id']; ?>&redirect_to=<?php echo $notif['reclamation_id']; ?>" 
                                   class="notification-dropdown-item <?php echo $notif['is_read'] ? '' : 'unread'; ?>">
                                    <div class="notification-time">
                                        <i class="bi bi-clock"></i>
                                        <?php echo $time_text; ?>
                                    </div>
                                    <div class="notification-title">
                                        <i class="bi bi-chat-dots me-1"></i>
                                        <?php echo htmlspecialchars($notif['titre']); ?>
                                    </div>
                                    <div class="notification-message">
                                        <?php echo htmlspecialchars($notif['message']); ?>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="notification-empty">
                                <i class="bi bi-bell-slash"></i>
                                <p class="mb-0">Aucune notification</p>
                            </div>
                        <?php endif; ?>
                        
                        <div class="notification-dropdown-footer">
                            <a href="notifications.php">
                                Voir toutes les notifications
                                <i class="bi bi-arrow-right ms-1"></i>
                            </a>
                        </div>
                    </div>
                </div>
                
                <a class="btn-disconnect" href="../../frontend/deconnexion.php">
                    <i class="bi bi-box-arrow-right me-1"></i> Déconnexion
                </a>
            </div>
        </div>
    </nav>

    <div class="container-fluid py-4">
    <div class="container-fluid py-4">
        <div class="row g-4">
            <!-- Sidebar / Filtres -->
            <div class="col-lg-3 col-xl-2">
                <div class="sidebar-card">
                    <h6 class="section-title">Statistiques</h6>
                    <div class="stat-item">
                        <span class="stat-label"><i class="bi bi-files me-2"></i>TOTAL</span>
                        <span class="stat-badge primary"><?php echo $stats['total']; ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label"><i class="bi bi-hourglass-split me-2"></i>EN COURS</span>
                        <span class="stat-badge warning"><?php echo $stats['en_cours']; ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label"><i class="bi bi-check-circle me-2"></i>TRAITÉS</span>
                        <span class="stat-badge success"><?php echo $stats['traite']; ?></span>
                    </div>
                </div>

                <div class="sidebar-card">
                    <h6 class="section-title">Filtres</h6>
                    <form method="GET" action="index.php">
                        <div class="mb-3">
                            <label class="filter-label">Statut</label>
                            <select class="form-select" name="status" onchange="this.form.submit()">
                                <option value="">Tous</option>
                                <?php 
                                // Construire options à partir des valeurs présentes
                                $options = [];
                                foreach ($presentStatuses as $s) {
                                    $label = get_status_label($s);
                                    $options[] = ['value'=>$s,'label'=>$label];
                                }
                                // Si aucune donnée, proposer jeu par défaut compatible
                                if (empty($options)) {
                                    $options = [
                                        ['value'=>'en_cours','label'=>get_status_label('en_cours')],
                                        ['value'=>'traite','label'=>get_status_label('traite')],
                                        ['value'=>'attente_info','label'=>get_status_label('attente_info')],
                                        ['value'=>'ferme','label'=>get_status_label('ferme')],
                                    ];
                                }
                                foreach ($options as $opt) {
                                    echo '<option value="'.htmlspecialchars($opt['value']).'" '.($status_filter==$opt['value']?'selected':'').'>'.htmlspecialchars($opt['label']).'</option>';
                                }
                                ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="filter-label">Réclamant</label>
                            <input type="text" class="form-control" name="user" value="<?php echo htmlspecialchars($user_filter); ?>" placeholder="Nom">
                        </div>
                        <div class="mb-3">
                            <label class="filter-label">Date</label>
                            <input type="date" class="form-control" name="date" value="<?php echo $date_filter; ?>" onchange="this.form.submit()">
                        </div>
                        <button type="submit" class="btn-reset w-100 mb-2">
                            <i class="bi bi-search me-2"></i>Filtrer
                        </button>
                        <a href="index.php" class="btn-reset w-100">
                            <i class="bi bi-arrow-clockwise me-2"></i>Réinitialiser
                        </a>
                    </form>
                </div>
            </div>

            <!-- Liste des réclamations -->
            <div class="col-lg-9 col-xl-10">
                <div class="main-content-container">
                    <div class="page-header d-flex justify-content-between align-items-center">
                        <div>
                            <p class="section-title mb-1">ESPACE GESTIONNAIRE</p>
                            <h1 class="main-title">Réclamations Reçues</h1>
                            <p class="text-muted" style="font-size: 0.875rem; margin-top: 0.5rem;">
                                <i class="bi bi-info-circle me-1"></i>
                                Traitez et suivez les réclamations assignées à votre service
                            </p>
                        </div>
                        <div class="dossier-count">
                            <strong><?php echo count($claims); ?></strong> dossiers
                        </div>
                    </div>

                    <div class="table-container">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Réclamant</th>
                                    <th>Sujet</th>
                                    <th>Catégorie</th>
                                    <th>Date</th>
                                    <th>Statut</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($claims) > 0): ?>
                                    <?php foreach ($claims as $claim): ?>
                                        <tr onclick="window.location.href='traitement.php?id=<?php echo $claim['id']; ?>'">
                                            <td class="fw-bold text-primary">#<?php echo $claim['id']; ?></td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="user-avatar">
                                                        <?php echo strtoupper(substr($claim['user_name'], 0, 1)); ?>
                                                    </div>
                                                    <span><?php echo htmlspecialchars($claim['user_name']); ?></span>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars($claim['sujet']); ?></td>
                                            <td>
                                                <span class="category-badge">
                                                    <?php echo htmlspecialchars($claim['category_nom']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo format_date($claim['created_at']); ?></td>
                                            <td>
                                                <?php 
                                                $statusClass = 'en-cours';
                                                if (in_array($claim['statut'], ['traite','traité','resolu','résolu'])) {
                                                    $statusClass = 'traite';
                                                } elseif (in_array($claim['statut'], ['ferme','fermé'])) {
                                                    $statusClass = 'ferme';
                                                } elseif (in_array($claim['statut'], ['attente_info','en_attente'])) {
                                                    $statusClass = 'attente';
                                                }
                                                ?>
                                                <span class="status-badge <?php echo $statusClass; ?>">
                                                    <?php echo get_status_label($claim['statut']); ?>
                                                </span>
                                            </td>
                                            <td class="text-end">
                                                <a href="traitement.php?id=<?php echo $claim['id']; ?>" class="btn-action" onclick="event.stopPropagation()">
                                                    <i class="bi bi-pencil-square me-1"></i>Traiter
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7">
                                            <div class="empty-state">
                                                <i class="bi bi-inbox"></i>
                                                <p class="mb-0">Aucune réclamation en attente de traitement</p>
                                                <small class="text-muted">Les nouvelles réclamations apparaîtront ici</small>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include '../../includes/footer.php'; ?>
</body>
</html>
