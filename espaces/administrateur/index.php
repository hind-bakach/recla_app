<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

require_role('administrateur');

$pdo = get_pdo();

// Statistiques Globales
$stats = [];
$stats['total_claims'] = $pdo->query("SELECT COUNT(*) FROM reclamations")->fetchColumn();
$stats['pending_claims'] = $pdo->query("SELECT COUNT(*) FROM reclamations WHERE statut = 'en_cours'")->fetchColumn();
$stats['resolved_claims'] = $pdo->query("SELECT COUNT(*) FROM reclamations WHERE statut = 'resolu' OR statut = 'closed'")->fetchColumn();
$stats['archived_claims'] = $pdo->query("SELECT COUNT(*) FROM reclamations WHERE statut = 'archive' OR statut = 'archived'")->fetchColumn();
$stats['users_count'] = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$stats['categories_count'] = $pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();
$stats['resolution_rate'] = $stats['total_claims'] > 0 ? round(($stats['resolved_claims'] / $stats['total_claims']) * 100, 1) : 0;

// Dernières réclamations — construire la requête en détectant les noms de tables/colonnes
// (évite les erreurs "Unknown column" si le schéma diffère)
// Détecter le nom de la table de réclamations (possibilités courantes)
$possibleClaimTables = ['reclamations', 'claims'];
$reclamTable = null;
foreach ($possibleClaimTables as $t) {
    $exists = $pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA='".DB_NAME."' AND TABLE_NAME='".addslashes($t)."'")->fetchColumn();
    if ($exists) { $reclamTable = $t; break; }
}
if (!$reclamTable) { $reclamTable = 'reclamations'; }

// Récupérer colonnes importantes pour chaque table
$getCols = function($table) use ($pdo) {
    $rows = $pdo->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA='".DB_NAME."' AND TABLE_NAME='".addslashes($table)."'")->fetchAll(PDO::FETCH_COLUMN);
    return $rows ?: [];
};

$userCols = $getCols('users');
$catCols = $getCols('categories');
$reclamCols = $getCols($reclamTable);

$userIdCol = in_array('user_id', $userCols) ? 'user_id' : (in_array('id', $userCols) ? 'id' : ($userCols[0] ?? 'id'));
$userNameCol = in_array('nom', $userCols) ? 'nom' : (in_array('name', $userCols) ? 'name' : ($userCols[1] ?? $userIdCol));

$catIdCol = in_array('id', $catCols) ? 'id' : ($catCols[0] ?? 'id');
$catNameCol = in_array('nom', $catCols) ? 'nom' : (in_array('name', $catCols) ? 'name' : ($catCols[1] ?? $catIdCol));

$reclamUserCol = in_array('user_id', $reclamCols) ? 'user_id' : (in_array('userId', $reclamCols) ? 'userId' : ($reclamCols[0] ?? 'user_id'));
$reclamCatCol = in_array('category_id', $reclamCols) ? 'category_id' : (in_array('categoryId', $reclamCols) ? 'categoryId' : ($reclamCols[1] ?? 'category_id'));
$reclamCreatedCol = in_array('created_at', $reclamCols) ? 'created_at' : ($reclamCols[2] ?? $reclamCols[0] ?? 'created_at');

// Construire la requête en échappant les noms de colonnes (utilisation basique)
$query = "SELECT c.*, u." . $userNameCol . " AS user_name, cat." . $catNameCol . " AS category_nom\n";
$query .= "    FROM " . $reclamTable . " c\n";
$query .= "    LEFT JOIN users u ON c." . $reclamUserCol . " = u." . $userIdCol . "\n";
$query .= "    LEFT JOIN categories cat ON c." . $reclamCatCol . " = cat." . $catIdCol . "\n";
$query .= "    ORDER BY c." . $reclamCreatedCol . " DESC LIMIT 5";

try {
    $stmt = $pdo->query($query);
    $latest_reclamations = $stmt->fetchAll();
} catch (PDOException $e) {
    // En cas d'échec, retomber sur une requête basique (sans nom de catégorie)
    $stmt = $pdo->query("SELECT c.* FROM " . $reclamTable . " c ORDER BY c." . $reclamCreatedCol . " DESC LIMIT 5");
    $latest_reclamations = $stmt->fetchAll();
}

// ===== ANALYSE DES TENDANCES PAR CATÉGORIE =====
$categAnalysis = [];
try {
    $catQuery = "SELECT 
                    cat." . $catNameCol . " as cat_name,
                    COUNT(c.id) as total,
                    SUM(CASE WHEN c.statut IN ('resolu', 'closed') THEN 1 ELSE 0 END) as resolved,
                    SUM(CASE WHEN c.statut = 'en_cours' THEN 1 ELSE 0 END) as pending
                FROM " . $reclamTable . " c
                LEFT JOIN categories cat ON c." . $reclamCatCol . " = cat." . $catIdCol . "
                GROUP BY cat." . $catIdCol . "
                ORDER BY total DESC";
    $categAnalysis = $pdo->query($catQuery)->fetchAll();
} catch (Exception $e) {
    $categAnalysis = [];
}

// ===== ANALYSE DE L'EFFICACITÉ PAR GESTIONNAIRE =====
$managerAnalysis = [];
try {
    // Récupérer les gestionnaires et leurs statistiques
    $managerQuery = "SELECT 
                        u.nom as manager_name,
                        u.user_id,
                        COUNT(DISTINCT c.id) as handled_claims,
                        SUM(CASE WHEN c.statut IN ('resolu', 'closed') THEN 1 ELSE 0 END) as resolved_claims,
                        ROUND(AVG(TIMESTAMPDIFF(DAY, c.date_soumission, NOW())), 1) as avg_days_pending
                    FROM users u
                    LEFT JOIN reclamations c ON u.user_id = c.user_id AND c.statut = 'en_cours'
                    WHERE u.role = 'gestionnaire'
                    GROUP BY u.user_id
                    ORDER BY handled_claims DESC";
    $managerAnalysis = $pdo->query($managerQuery)->fetchAll();
} catch (Exception $e) {
    $managerAnalysis = [];
}

// ===== DÉLAI MOYEN DE TRAITEMENT =====
$avgProcessingTime = 0;
try {
    $timeQuery = "SELECT ROUND(AVG(TIMESTAMPDIFF(DAY, c.date_soumission, NOW())), 1) as avg_days
                  FROM reclamations c
                  WHERE c.statut IN ('resolu', 'closed')";
    $result = $pdo->query($timeQuery)->fetch();
    $avgProcessingTime = $result['avg_days'] ?? 0;
} catch (Exception $e) {
    $avgProcessingTime = 0;
}

include '../../includes/head.php';
?>

<body class="bg-light">
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm">
        <div class="container-fluid">
            <a class="navbar-brand fw-bold" href="index.php"><i class="bi bi-shield-lock-fill me-2"></i>Espace Administrateur</a>
            <div class="d-flex align-items-center">
                <span class="text-white me-3">Admin: <strong><?php echo htmlspecialchars($_SESSION['user_name']); ?></strong></span>
                <a class="btn btn-outline-light btn-sm fw-bold" href="../../frontend/deconnexion.php">
                    <i class="bi bi-box-arrow-right me-1"></i> Déconnexion
                </a>
            </div>
        </div>
    </nav>

    <div class="container-fluid py-4">
        <div class="row g-4">
            <!-- Sidebar Menu -->
            <div class="col-lg-2">
                <div class="list-group shadow-sm rounded-4 border-0">
                    <a href="index.php" class="list-group-item list-group-item-action active fw-bold" aria-current="true">
                        <i class="bi bi-speedometer2 me-2"></i>Tableau de Bord
                    </a>
                    <a href="users.php" class="list-group-item list-group-item-action fw-bold">
                        <i class="bi bi-people-fill me-2"></i>Utilisateurs
                    </a>
                    <a href="categories.php" class="list-group-item list-group-item-action fw-bold">
                        <i class="bi bi-tags-fill me-2"></i>Catégories
                    </a>
                </div>
            </div>

            <!-- Contenu Principal -->
            <div class="col-lg-10">
                <!-- Cartes Stats -->
                <div class="row g-4 mb-4">
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm rounded-4 bg-primary text-white h-100">
                            <div class="card-body p-4">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-uppercase mb-2 opacity-75">Réclamations</h6>
                                        <h2 class="display-6 fw-bold mb-0"><?php echo $stats['total_claims']; ?></h2>
                                    </div>
                                    <i class="bi bi-inbox-fill fs-1 opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm rounded-4 bg-warning text-dark h-100">
                            <div class="card-body p-4">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-uppercase mb-2 opacity-75">En Attente</h6>
                                        <h2 class="display-6 fw-bold mb-0"><?php echo $stats['pending_claims']; ?></h2>
                                    </div>
                                    <i class="bi bi-hourglass-split fs-1 opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm rounded-4 bg-success text-white h-100">
                            <div class="card-body p-4">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-uppercase mb-2 opacity-75">Résolues</h6>
                                        <h2 class="display-6 fw-bold mb-0"><?php echo $stats['resolved_claims']; ?></h2>
                                        <small class="opacity-75"><?php echo $stats['resolution_rate']; ?>% taux</small>
                                    </div>
                                    <i class="bi bi-check-circle-fill fs-1 opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm rounded-4 bg-info text-white h-100">
                            <div class="card-body p-4">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-uppercase mb-2 opacity-75">Délai Moyen</h6>
                                        <h2 class="display-6 fw-bold mb-0"><?php echo $avgProcessingTime; ?></h2>
                                        <small class="opacity-75">jours</small>
                                    </div>
                                    <i class="bi bi-calendar-event fs-1 opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Analyse des Tendances par Catégorie -->
                <div class="row g-4 mb-4 mt-2">
                    <div class="col-lg-6">
                        <div class="card shadow-sm border-0 rounded-4">
                            <div class="card-header bg-white p-3 border-bottom">
                                <h5 class="mb-0 fw-bold"><i class="bi bi-graph-up me-2 text-primary"></i>Tendances par Catégorie</h5>
                            </div>
                            <div class="card-body p-0">
                                <?php if (!empty($categAnalysis)): ?>
                                    <div class="table-responsive">
                                        <table class="table table-sm align-middle mb-0">
                                            <thead class="bg-light text-muted text-uppercase small">
                                                <tr>
                                                    <th class="ps-4 py-2">Catégorie</th>
                                                    <th class="py-2">Total</th>
                                                    <th class="py-2">Résolues</th>
                                                    <th class="py-2">En Cours</th>
                                                    <th class="py-2 text-end pe-4">Taux</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($categAnalysis as $cat): 
                                                    $rate = ($cat['total'] > 0) ? round(($cat['resolved'] / $cat['total']) * 100) : 0;
                                                    $rateClass = $rate >= 80 ? 'bg-success' : ($rate >= 50 ? 'bg-warning' : 'bg-danger');
                                                ?>
                                                    <tr>
                                                        <td class="ps-4 fw-bold"><?php echo htmlspecialchars($cat['cat_name'] ?? '—'); ?></td>
                                                        <td><span class="badge bg-primary"><?php echo $cat['total']; ?></span></td>
                                                        <td><span class="badge bg-success"><?php echo $cat['resolved']; ?></span></td>
                                                        <td><span class="badge bg-warning text-dark"><?php echo $cat['pending']; ?></span></td>
                                                        <td class="text-end pe-4"><span class="badge <?php echo $rateClass; ?>"><?php echo $rate; ?>%</span></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <p class="text-center text-muted p-4">Pas de données disponibles.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Efficacité des Gestionnaires -->
                    <div class="col-lg-6">
                        <div class="card shadow-sm border-0 rounded-4">
                            <div class="card-header bg-white p-3 border-bottom">
                                <h5 class="mb-0 fw-bold"><i class="bi bi-person-check-fill me-2 text-primary"></i>Efficacité des Gestionnaires</h5>
                            </div>
                            <div class="card-body p-0">
                                <?php if (!empty($managerAnalysis)): ?>
                                    <div class="table-responsive">
                                        <table class="table table-sm align-middle mb-0">
                                            <thead class="bg-light text-muted text-uppercase small">
                                                <tr>
                                                    <th class="ps-4 py-2">Gestionnaire</th>
                                                    <th class="py-2">Dossiers</th>
                                                    <th class="py-2">Résolus</th>
                                                    <th class="py-2 text-end pe-4">Délai Moy</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($managerAnalysis as $manager): ?>
                                                    <tr>
                                                        <td class="ps-4 fw-bold"><?php echo htmlspecialchars($manager['manager_name'] ?? '—'); ?></td>
                                                        <td><span class="badge bg-info"><?php echo $manager['handled_claims']; ?></span></td>
                                                        <td><span class="badge bg-success"><?php echo $manager['resolved_claims']; ?></span></td>
                                                        <td class="text-end pe-4"><?php echo $manager['avg_days_pending'] ?? '—'; ?> j</td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <p class="text-center text-muted p-4">Pas de données disponibles.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Dernières activités -->
                <div class="card shadow-sm border-0 rounded-4">
                    <div class="card-header bg-white p-3 border-bottom">
                        <h5 class="mb-0 fw-bold"><i class="bi bi-activity me-2 text-primary"></i>Dernières Réclamations</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="bg-light text-muted text-uppercase small">
                                    <tr>
                                        <th class="ps-4 py-3">ID</th>
                                        <th class="py-3">Utilisateur</th>
                                        <th class="py-3">Sujet</th>
                                        <th class="py-3">Catégorie</th>
                                        <th class="py-3">Date</th>
                                        <th class="py-3">Statut</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($latest_reclamations) && is_array($latest_reclamations)): ?>
                                        <?php foreach ($latest_reclamations as $reclamation): ?>
                                            <tr>
                                                <td class="ps-4 fw-bold">#<?php echo htmlspecialchars($reclamation['reclam_id'] ?? $reclamation['id'] ?? $reclamation['reclamation_id'] ?? ''); ?></td>
                                                <td><?php echo htmlspecialchars($reclamation['user_name'] ?? $reclamation['nom'] ?? $reclamation['name'] ?? '—'); ?></td>
                                                <td><?php echo htmlspecialchars($reclamation['objet'] ?? $reclamation['sujet'] ?? ''); ?></td>
                                                <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($reclamation['category_nom'] ?? $reclamation['categorie_nom'] ?? $reclamation['categorie'] ?? '—'); ?></span></td>
                                                <td><?php echo !empty($reclamation['date_soumission'] ?? $reclamation['created_at'] ?? null) ? format_date($reclamation['date_soumission'] ?? $reclamation['created_at']) : ''; ?></td>
                                                <td>
                                                    <span class="badge rounded-pill <?php echo get_status_badge($reclamation['statut'] ?? $reclamation['status'] ?? ''); ?>">
                                                        <?php echo get_status_label($reclamation['statut'] ?? $reclamation['status'] ?? ''); ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="text-center text-muted py-4">Aucune réclamation trouvée.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include '../../includes/footer.php'; ?>
</body>
</html>
