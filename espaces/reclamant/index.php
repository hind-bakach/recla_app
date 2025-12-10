<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/lang.php';

require_role('reclamant');

$user_id = $_SESSION['user_id'];
$pdo = get_pdo();

// Compter les notifications non lues
$notif_count = 0;
$recent_notifications = [];
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    $notif_result = $stmt->fetch();
    $notif_count = $notif_result['count'] ?? 0;
    
    // Récupérer les 3 dernières notifications pour le dropdown
    $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 3");
    $stmt->execute([$user_id]);
    $recent_notifications = $stmt->fetchAll();
} catch (PDOException $e) {
    // Table notifications n'existe pas encore
    $notif_count = 0;
    $recent_notifications = [];
}

// Fonction pour traduire le titre de notification
function translate_notification_title($title) {
    if (preg_match('/^Statut changé:\s*(.+)$/i', $title, $matches)) {
        return t('notif_status_changed_title') . ": " . trim($matches[1]);
    }
    if (preg_match('/^Status changed:\s*(.+)$/i', $title, $matches)) {
        return t('notif_status_changed_title') . ": " . trim($matches[1]);
    }
    return $title;
}

// Fonction pour traduire le message de notification
function translate_notification_message($message) {
    if (preg_match('/^Le statut de votre réclamation est maintenant:\s*(.+)$/i', $message, $matches)) {
        $status = trim($matches[1]);
        return t('notif_status_changed_message') . ": " . get_status_label_from_french($status);
    }
    if (preg_match('/^Your claim status is now:\s*(.+)$/i', $message, $matches)) {
        $status = trim($matches[1]);
        return t('notif_status_changed_message') . ": " . get_status_label_from_french($status);
    }
    return $message;
}

// Fonction pour convertir un statut français en clé et traduire
function get_status_label_from_french($french_status) {
    $status_map = [
        'Soumis' => 'soumis', 'En cours' => 'en_cours', 'En attente' => 'en_attente',
        'Résolu' => 'resolu', 'Fermé' => 'ferme', 'Rejeté' => 'rejete',
        'Archivé' => 'archive', 'Traité' => 'traite', 'Attente d\'info' => 'attente_info',
    ];
    $key = $status_map[$french_status] ?? strtolower(str_replace(' ', '_', $french_status));
    return get_status_label($key);
}

// Récupérer les statistiques
$stmt = $pdo->prepare("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN statut = 'en_cours' THEN 1 ELSE 0 END) as en_cours,
    SUM(CASE WHEN statut = 'traite' THEN 1 ELSE 0 END) as traite,
    SUM(CASE WHEN statut = 'ferme' THEN 1 ELSE 0 END) as ferme
    FROM reclamations WHERE user_id = ?");
$stmt->execute([$user_id]);
$stats = $stmt->fetch();

// Convertir en entiers pour éviter NaN
$stats['total'] = (int)($stats['total'] ?? 0);
$stats['en_cours'] = (int)($stats['en_cours'] ?? 0);
$stats['traite'] = (int)($stats['traite'] ?? 0);
$stats['ferme'] = (int)($stats['ferme'] ?? 0);

// Helper: détecte la première colonne existante parmi des candidats
function detect_column($pdo, $table, $candidates) {
    $check = $pdo->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?");
    foreach ($candidates as $col) {
        $check->execute([$table, $col]);
        if ($check->fetchColumn() > 0) {
            return $col;
        }
    }
    return null;
}

// Détecter les colonnes utiles dans la table `categories` et `reclamations`
$catNameCol = detect_column($pdo, 'categories', ['nom', 'nom_categorie', 'categorie_nom', 'name', 'libelle']);
$catPk = detect_column($pdo, 'categories', ['id', 'categorie_id', 'category_id', 'cat_id']);
$reclamFk = detect_column($pdo, 'reclamations', ['category_id', 'categorie_id', 'cat_id', 'categorie']);

// Détecter colonnes importantes dans `reclamations` pour aliaser proprement
$reclamIdCol = detect_column($pdo, 'reclamations', ['id', 'reclam_id', 'reclamation_id', 'id_reclamation', 'recl_id']);
$reclamSujetCol = detect_column($pdo, 'reclamations', ['sujet', 'objet', 'title', 'subject']);
$reclamDateCol = detect_column($pdo, 'reclamations', ['created_at', 'date_created', 'date_soumission', 'date_submission', 'date', 'date_creation', 'submitted_at', 'date_submitted']);

// Construire SELECT en incluant c.* puis en aliasant les colonnes usuelles vers les noms attendus par le template
$select = "c.*";
if ($catNameCol && $catPk && $reclamFk) {
    $select .= ", cat.`$catNameCol` as categorie_nom";
}
// Aliases pour garder compatibilité avec le template
if ($reclamIdCol && $reclamIdCol !== 'id') {
    $select .= ", c.`$reclamIdCol` AS id";
}
if ($reclamSujetCol && $reclamSujetCol !== 'sujet') {
    $select .= ", c.`$reclamSujetCol` AS sujet";
}
if ($reclamDateCol && $reclamDateCol !== 'created_at') {
    $select .= ", c.`$reclamDateCol` AS created_at";
}

// Définir la colonne d'ordre — si on a une colonne de date détectée, l'utiliser, sinon tenter 'created_at' ou la PK
if ($reclamDateCol) {
    $orderBy = "c.`$reclamDateCol` DESC";
} elseif (detect_column($pdo, 'reclamations', ['created_at'])) {
    $orderBy = "c.created_at DESC";
} else {
    $orderBy = ($reclamIdCol ? "c.`$reclamIdCol` DESC" : "1");
}

// Construire la requête finale
if ($catNameCol && $catPk && $reclamFk) {
    $sql = "SELECT $select FROM reclamations c LEFT JOIN categories cat ON c.`$reclamFk` = cat.`$catPk` WHERE c.user_id = ? ORDER BY $orderBy";
} else {
    $sql = "SELECT $select FROM reclamations c WHERE c.user_id = ? ORDER BY $orderBy";
}

$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$reclamations = $stmt->fetchAll();

include '../../includes/head.php';
?>
<link rel="stylesheet" href="../../css/modern.css">
<link rel="stylesheet" href="../../css/reclamant.css">
    
    
    
<script src="../../js/main.js" defer></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Animate stat values on load
    const statValues = document.querySelectorAll('.stat-card-value');
    statValues.forEach(stat => {
        const finalValue = parseInt(stat.textContent);
        let currentValue = 0;
        const increment = finalValue / 30;
        const timer = setInterval(() => {
            currentValue += increment;
            if (currentValue >= finalValue) {
                stat.textContent = finalValue;
                clearInterval(timer);
            } else {
                stat.textContent = Math.floor(currentValue);
            }
        }, 30);
    });
});
    
    // Add tooltip to table rows
    const tableRows = document.querySelectorAll('.table-minimal tbody tr');
    tableRows.forEach(row => {
        row.style.cursor = 'pointer';
        row.addEventListener('click', function(e) {
            if (!e.target.closest('.btn-action')) {
                const link = this.querySelector('.btn-action');
                if (link) link.click();
            }
        });
    });
    
    // Add refresh animation
    let isRefreshing = false;
    document.addEventListener('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'r' && !isRefreshing) {
            isRefreshing = true;
            document.querySelectorAll('.stat-card').forEach(card => {
                card.style.animation = 'none';
                setTimeout(() => {
                    card.style.animation = 'scaleIn 0.5s ease-out';
                }, 10);
            });
            setTimeout(() => { isRefreshing = false; }, 1000);
        }
});
</script>

<script>
const searchPlaceholder = <?php echo json_encode(t('search_claim')); ?>;
document.addEventListener('DOMContentLoaded', function() {
    // Real-time search filter
    const searchInput = document.createElement('input');
    searchInput.type = 'text';
    searchInput.placeholder = searchPlaceholder;
    searchInput.className = 'form-control mb-3';
    searchInput.style.maxWidth = '400px';
    searchInput.style.marginLeft = 'auto';
    
    const tableRows = document.querySelectorAll('.table-minimal tbody tr');
    const tableContainer = document.querySelector('.table-container');
    if (tableContainer) {
        tableContainer.parentElement.insertBefore(searchInput, tableContainer);
        
        searchInput.addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            tableRows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        });
    }
});
</script>

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
                        <span style="color: #6b7280;"><?php echo t('hello'); ?>, <strong style="color: #111827;"><?php echo htmlspecialchars($_SESSION['user_name']); ?></strong></span>
                    </li>
                    <li class="nav-item me-3">
                        <a href="profil.php" class="text-decoration-none" title="<?php echo t('my_profile'); ?>">
                            <i class="bi bi-person-circle profile-icon"></i>
                        </a>
                    </li>
                    <li class="nav-item me-3">
                        <div class="notification-dropdown-wrapper">
                            <a href="notifications.php" class="text-decoration-none position-relative">
                                <i class="bi bi-bell notification-icon"></i>
                                <?php if ($notif_count > 0): ?>
                                    <span class="notification-badge"><?php echo $notif_count > 9 ? '9+' : $notif_count; ?></span>
                                <?php endif; ?>
                            </a>
                            
                            <!-- Dropdown des notifications -->
                            <div class="notification-dropdown">
                                <div class="notification-dropdown-header">
                                    <span><?php echo t('notifications'); ?></span>
                                    <?php if ($notif_count > 0): ?>
                                        <span class="badge bg-danger"><?php echo $notif_count; ?></span>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if (empty($recent_notifications)): ?>
                                    <div class="notification-empty">
                                        <i class="bi bi-bell-slash"></i>
                                        <p class="mb-0"><?php echo t('no_notifications'); ?></p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($recent_notifications as $notif): ?>
                                        <a href="details.php?id=<?php echo $notif['reclamation_id']; ?>" 
                                           class="notification-dropdown-item <?php echo $notif['is_read'] == 0 ? 'unread' : ''; ?>">
                                            <div class="notification-dropdown-title">
                                                <i class="bi bi-<?php echo $notif['type'] == 'nouveau_commentaire' ? 'chat-dots' : 'arrow-repeat'; ?> me-1"></i>
                                                <?php echo htmlspecialchars(translate_notification_title($notif['titre'])); ?>
                                            </div>
                                            <div class="notification-dropdown-message">
                                                <?php echo htmlspecialchars(translate_notification_message($notif['message'])); ?>
                                            </div>
                                            <div class="notification-dropdown-time">
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
                                        </a>
                                    <?php endforeach; ?>
                                    
                                    <div class="notification-dropdown-footer">
                                        <a href="notifications.php"><?php echo t('see_all_notifications'); ?> →</a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </li>
                    <li class="nav-item">
                        <a class="btn btn-logout" href="../../frontend/deconnexion.php">
                            <i class="bi bi-box-arrow-right me-1"></i><?php echo t('logout'); ?>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container pb-5">
        <div class="main-content-container">
            <!-- En-tête avec bouton -->
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4">
                <div>
                    <h6 class="section-title"><?php echo t('dashboard_area_claimant'); ?></h6>
                    <h1 class="main-title"><?php echo t('dashboard_title'); ?></h1>
                </div>
                <a href="soumission.php" class="btn btn-primary-action">
                    <i class="bi bi-plus-circle me-2"></i><?php echo t('new_claim'); ?>
                </a>
            </div>
            
            <!-- Cards de Statistiques -->
            <div class="row g-3 mb-5">
            <div class="col-md-6 col-lg-3">
                <div class="stat-card">
                    <div class="d-flex align-items-center gap-3">
                        <div class="stat-card-icon" style="background-color: #e0f2fe;">
                            <i class="bi bi-chat-square-text" style="color: #0891b2;"></i>
                        </div>
                        <div>
                            <div class="stat-card-title"><?php echo t('total_claims'); ?></div>
                            <div class="stat-card-value"><?php echo $stats['total']; ?></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="stat-card">
                    <div class="d-flex align-items-center gap-3">
                        <div class="stat-card-icon" style="background-color: #e0f2fe;">
                            <i class="bi bi-arrow-repeat" style="color: #0891b2;"></i>
                        </div>
                        <div>
                            <div class="stat-card-title"><?php echo t('pending_claims'); ?></div>
                            <div class="stat-card-value"><?php echo $stats['en_cours']; ?></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="stat-card">
                    <div class="d-flex align-items-center gap-3">
                        <div class="stat-card-icon" style="background-color: #d1fae5;">
                            <i class="bi bi-check2-circle" style="color: #10b981;"></i>
                        </div>
                        <div>
                            <div class="stat-card-title"><?php echo t('resolved_claims'); ?></div>
                            <div class="stat-card-value"><?php echo $stats['traite']; ?></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="stat-card">
                    <div class="d-flex align-items-center gap-3">
                        <div class="stat-card-icon" style="background-color: #fed7aa;">
                            <i class="bi bi-slash-circle" style="color: #ea580c;"></i>
                        </div>
                        <div>
                            <div class="stat-card-title"><?php echo t('closed_claims'); ?></div>
                            <div class="stat-card-value"><?php echo $stats['ferme']; ?></div>
                        </div>
                    </div>
                </div>
            </div>
            </div>

            <!-- Section Historique -->
            <div class="mb-4 mt-5">
                <h2 class="table-section-title"><?php echo t('claim_history'); ?></h2>
            </div>

            <!-- Tableau des Réclamations -->
            <div class="table-container">
            <div class="table-responsive">
                <table class="table table-minimal table-striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th><?php echo t('claim_subject'); ?></th>
                            <th><?php echo t('claim_category'); ?></th>
                            <th><?php echo t('claim_date'); ?></th>
                            <th><?php echo t('claim_status'); ?></th>
                            <th class="text-end"><?php echo t('claim_action'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($reclamations) > 0): ?>
                            <?php foreach ($reclamations as $reclamation): ?>
                                <tr>
                                    <td class="fw-semibold" style="color: #111827;">#<?php echo $reclamation['id']; ?></td>
                                    <td><?php echo htmlspecialchars($reclamation['sujet'] ?? t('claim_no_subject')); ?></td>
                                    <td>
                                        <span class="category-badge"><?php echo htmlspecialchars($reclamation['categorie_nom'] ?? '—'); ?></span>
                                    </td>
                                    <td style="color: #6b7280;"><?php echo format_date($reclamation['created_at']); ?></td>
                                    <td>
                                        <?php 
                                            $statut = $reclamation['statut'];
                                            $badge_class = '';
                                            $statut_label = '';
                                            
                                            if ($statut === 'ferme' || $statut === 'fermee' || $statut === 'closed') {
                                                $badge_class = 'bg-secondary-subtle text-secondary-emphasis';
                                                $statut_label = t('status_closed');
                                            } elseif ($statut === 'traite' || $statut === 'traitee' || $statut === 'acceptee' || $statut === 'accepted') {
                                                $badge_class = 'bg-primary-subtle text-primary-emphasis';
                                                $statut_label = t('status_accepted');
                                            } elseif (stripos($statut, 'attente') !== false || stripos($statut, 'info') !== false || stripos($statut, 'pending') !== false) {
                                                $badge_class = 'bg-danger-subtle text-danger-emphasis';
                                                $statut_label = t('status_awaiting_info');
                                            } elseif ($statut === 'en_cours' || $statut === 'in_progress') {
                                                $badge_class = 'bg-success-subtle text-success-emphasis';
                                                $statut_label = t('status_processing');
                                            } else {
                                                $badge_class = 'bg-success-subtle text-success-emphasis';
                                                $statut_label = t('status_processing');
                                            }
                                        ?>
                                        <span class="badge <?php echo $badge_class; ?> badge-custom">
                                            <?php echo $statut_label; ?>
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <a href="details.php?id=<?php echo $reclamation['id']; ?>" class="btn-action">
                                            <i class="bi bi-eye me-1"></i><?php echo t('view'); ?>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center py-5" style="color: #9ca3af;">
                                    <i class="bi bi-inbox fs-1 d-block mb-3" style="color: #d1d5db;"></i>
                                    <div><?php echo t('claim_none_found'); ?></div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            </div>
        </div>
    </div>

    <?php include '../../includes/footer.php'; ?>
</body>
</html>
