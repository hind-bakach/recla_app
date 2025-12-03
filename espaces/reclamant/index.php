<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

require_role('reclamant');

$user_id = $_SESSION['user_id'];
$pdo = get_pdo();
// Récupérer les statistiques
$stmt = $pdo->prepare("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN statut = 'en_cours' THEN 1 ELSE 0 END) as en_cours,
    SUM(CASE WHEN statut = 'traite' THEN 1 ELSE 0 END) as traite,
    SUM(CASE WHEN statut = 'ferme' THEN 1 ELSE 0 END) as ferme
    FROM reclamations WHERE user_id = ?");
$stmt->execute([$user_id]);
$stats = $stmt->fetch();

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
        animation: fadeIn 0.8s ease-out 0.2s both;
    }
    
    .main-title {
        color: var(--gray-900);
        font-weight: 700;
        font-size: 2rem;
        margin-bottom: 2rem;
        animation: fadeIn 0.8s ease-out 0.3s both;
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
    
    .stat-card {
        background: linear-gradient(135deg, #f9fafb 0%, #ffffff 100%);
        border: 1px solid var(--gray-200);
        border-radius: var(--radius-xl);
        padding: 1.75rem 1.5rem;
        text-align: left;
        transition: all var(--transition-base);
        box-shadow: var(--shadow-sm);
        height: 100%;
        position: relative;
        overflow: hidden;
        animation: scaleIn 0.5s ease-out backwards;
    }
    
    .stat-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: linear-gradient(135deg, rgba(20, 184, 166, 0.05) 0%, transparent 100%);
        opacity: 0;
        transition: var(--transition-base);
    }
    
    .stat-card:hover::before {
        opacity: 1;
    }
    
    .stat-card:hover {
        transform: translateY(-4px) scale(1.02);
        box-shadow: var(--shadow-xl);
        border-color: var(--primary-blue);
    }
    
    @keyframes scaleIn {
        from {
            opacity: 0;
            transform: scale(0.9);
        }
        to {
            opacity: 1;
            transform: scale(1);
        }
    }
    
    .stat-card:nth-child(1) { animation-delay: 0.1s; }
    .stat-card:nth-child(2) { animation-delay: 0.2s; }
    .stat-card:nth-child(3) { animation-delay: 0.3s; }
    .stat-card:nth-child(4) { animation-delay: 0.4s; }
    
    .stat-card-icon {
        width: 48px;
        height: 48px;
        margin: 0;
        border-radius: var(--radius-lg);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        flex-shrink: 0;
        transition: var(--transition-base);
    }
    
    .stat-card:hover .stat-card-icon {
        transform: rotate(5deg) scale(1.1);
    }
    
    .stat-card-title {
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--gray-400);
        margin-bottom: 0.5rem;
        line-height: 1.2;
    }
    
    .stat-card-value {
        font-size: 2rem;
        font-weight: 700;
        line-height: 1;
        color: var(--gray-900);
        transition: var(--transition-base);
    }
    
    .stat-card:hover .stat-card-value {
        color: var(--primary-blue);
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
        position: relative;
        overflow: hidden;
    }
    
    .btn-primary-action::before {
        content: '';
        position: absolute;
        top: 50%;
        left: 50%;
        width: 0;
        height: 0;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.3);
        transform: translate(-50%, -50%);
        transition: width 0.6s, height 0.6s;
    }
    
    .btn-primary-action:hover::before {
        width: 300px;
        height: 300px;
    }
    
    .btn-primary-action:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-2xl);
    }
    
    .table-container {
        background: white;
        border: 1px solid var(--gray-200);
        border-radius: var(--radius-xl);
        overflow: hidden;
        box-shadow: var(--shadow-md);
        animation: fadeInUp 0.6s ease-out 0.5s backwards;
    }
    
    .table-minimal {
        margin-bottom: 0;
    }
    
    .table-minimal thead {
        background: linear-gradient(135deg, #f9fafb 0%, #f3f4f6 100%);
    }
    
    .table-minimal thead th {
        font-size: 0.75rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--gray-500);
        border-bottom: 2px solid var(--gray-200);
        padding: 1rem;
    }
    
    .table-minimal tbody td {
        padding: 1rem;
        vertical-align: middle;
        color: var(--gray-700);
        border-bottom: 1px solid var(--gray-100);
        transition: var(--transition-fast);
    }
    
    .table-minimal tbody tr {
        transition: var(--transition-base);
    }
    
    .table-minimal tbody tr:hover {
        background: linear-gradient(135deg, rgba(20, 184, 166, 0.03) 0%, rgba(14, 165, 233, 0.03) 100%);
        transform: scale(1.01);
    }
    
    .table-minimal tbody tr:last-child td {
        border-bottom: none;
    }
    
    .badge-custom {
        padding: 0.5rem 1rem;
        border-radius: var(--radius-full);
        font-size: 0.813rem;
        font-weight: 600;
        transition: var(--transition-base);
    }
    
    .badge-custom:hover {
        transform: scale(1.05);
    }
    
    .category-badge {
        background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
        color: #1e40af;
        border: none;
        padding: 0.375rem 0.875rem;
        border-radius: var(--radius-md);
        font-size: 0.875rem;
        font-weight: 600;
        transition: var(--transition-base);
    }
    
    .category-badge:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-md);
    }
    
    .btn-action {
        background: var(--gradient-blue);
        color: white;
        border: none;
        padding: 0.5rem 1.25rem;
        border-radius: var(--radius-md);
        font-size: 0.875rem;
        font-weight: 600;
        transition: all var(--transition-base);
        text-decoration: none;
        display: inline-block;
        position: relative;
        overflow: hidden;
    }
    
    .btn-action::after {
        content: '→';
        position: absolute;
        right: -20px;
        opacity: 0;
        transition: var(--transition-base);
    }
    
    .btn-action:hover::after {
        right: 10px;
        opacity: 1;
    }
    
    .btn-action:hover {
        padding-right: 2rem;
        transform: translateY(-2px);
        box-shadow: var(--shadow-lg);
    }
    
    .table-section-title {
        color: var(--gray-900);
        font-weight: 700;
        font-size: 1.25rem;
        margin-bottom: 1.5rem;
        position: relative;
        padding-left: 1rem;
    }
    
    .table-section-title::before {
        content: '';
        position: absolute;
        left: 0;
        top: 50%;
        transform: translateY(-50%);
        width: 4px;
        height: 70%;
        background: var(--gradient-blue);
        border-radius: var(--radius-sm);
    }
    
    /* Loading skeleton animation */
    @keyframes shimmer {
        0% { background-position: -1000px 0; }
        100% { background-position: 1000px 0; }
    }
    
    .skeleton {
        animation: shimmer 2s infinite;
        background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
        background-size: 1000px 100%;
    }
    
    /* Pulse animation for new items */
    @keyframes pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.5; }
    }
    
    .pulse {
        animation: pulse 2s ease-in-out infinite;
    }
    
    /* Empty state styling */
    .empty-state {
        text-align: center;
        padding: 3rem;
        color: var(--gray-400);
    }
    
    .empty-state i {
        font-size: 4rem;
        color: var(--gray-300);
        animation: float 3s ease-in-out infinite;
    }
    
    @keyframes float {
        0%, 100% { transform: translateY(0); }
        50% { transform: translateY(-10px); }
    }
</style>

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
    
    // Real-time search filter
    const searchInput = document.createElement('input');
    searchInput.type = 'text';
    searchInput.placeholder = '🔍 Rechercher une réclamation...';
    searchInput.className = 'form-control mb-3';
    searchInput.style.maxWidth = '400px';
    searchInput.style.marginLeft = 'auto';
    
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
                <i class="bi bi-chat-square-text me-2" style="color: #0891b2;"></i>Gestion des Réclamations
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" style="border-color: #e5e7eb;">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-center">
                    <li class="nav-item me-3">
                        <span style="color: #6b7280;">Bonjour, <strong style="color: #111827;"><?php echo htmlspecialchars($_SESSION['user_name']); ?></strong></span>
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
            <!-- En-tête avec bouton -->
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4">
                <div>
                    <h6 class="section-title">Espace Réclamant</h6>
                    <h1 class="main-title">Tableau de Bord</h1>
                </div>
                <a href="soumission.php" class="btn btn-primary-action">
                    <i class="bi bi-plus-circle me-2"></i>Nouvelle Réclamation
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
                            <div class="stat-card-title">Total Réclamations</div>
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
                            <div class="stat-card-title">En Cours</div>
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
                            <div class="stat-card-title">Traitées</div>
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
                            <div class="stat-card-title">Fermées</div>
                            <div class="stat-card-value"><?php echo $stats['ferme']; ?></div>
                        </div>
                    </div>
                </div>
            </div>
            </div>

            <!-- Section Historique -->
            <div class="mb-4 mt-5">
                <h2 class="table-section-title">Historique des Réclamations</h2>
            </div>

            <!-- Tableau des Réclamations -->
            <div class="table-container">
            <div class="table-responsive">
                <table class="table table-minimal table-striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Sujet</th>
                            <th>Catégorie</th>
                            <th>Date</th>
                            <th>Statut</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($reclamations) > 0): ?>
                            <?php foreach ($reclamations as $reclamation): ?>
                                <tr>
                                    <td class="fw-semibold" style="color: #111827;">#<?php echo $reclamation['id']; ?></td>
                                    <td><?php echo htmlspecialchars($reclamation['sujet'] ?? 'Sans sujet'); ?></td>
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
                                                $statut_label = 'Fermée';
                                            } elseif ($statut === 'traite' || $statut === 'traitee' || $statut === 'acceptee' || $statut === 'accepted') {
                                                $badge_class = 'bg-primary-subtle text-primary-emphasis';
                                                $statut_label = 'Acceptée';
                                            } elseif (stripos($statut, 'attente') !== false || stripos($statut, 'info') !== false || stripos($statut, 'pending') !== false) {
                                                $badge_class = 'bg-danger-subtle text-danger-emphasis';
                                                $statut_label = 'En attente info';
                                            } elseif ($statut === 'en_cours' || $statut === 'in_progress') {
                                                $badge_class = 'bg-success-subtle text-success-emphasis';
                                                $statut_label = 'En cours de traitement';
                                            } else {
                                                $badge_class = 'bg-success-subtle text-success-emphasis';
                                                $statut_label = 'En cours de traitement';
                                            }
                                        ?>
                                        <span class="badge <?php echo $badge_class; ?> badge-custom">
                                            <?php echo $statut_label; ?>
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <a href="details.php?id=<?php echo $reclamation['id']; ?>" class="btn-action">
                                            <i class="bi bi-eye me-1"></i>Voir
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center py-5" style="color: #9ca3af;">
                                    <i class="bi bi-inbox fs-1 d-block mb-3" style="color: #d1d5db;"></i>
                                    <div>Aucune réclamation trouvée</div>
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
