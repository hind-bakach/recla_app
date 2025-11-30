<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

require_role('administrateur');

$pdo = get_pdo();

// Helper de détection générique de colonnes (évite les erreurs Unknown column)
if (!function_exists('detect_column')) {
    function detect_column($pdo, $table, $candidates) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?");
        foreach ($candidates as $col) {
            $stmt->execute([$table, $col]);
            if ($stmt->fetchColumn() > 0) { return $col; }
        }
        return null;
    }
}

// Statistiques Globales (détection dynamique des colonnes de statut)
$stats = [];
$stats['total_claims'] = $pdo->query("SELECT COUNT(*) FROM reclamations")->fetchColumn();
// Détecter la colonne de statut réelle
$statusCol = detect_column($pdo, 'reclamations', ['statut','status','etat']);
if ($statusCol) {
    $pendingValues = ["en_cours","en_attente","pending"];
    $resolvedValues = ["resolu","closed","traite"];
    $archivedValues = ["archive","archived"];
    $inPending = "'" . implode("','", $pendingValues) . "'";
    $inResolved = "'" . implode("','", $resolvedValues) . "'";
    $inArchived = "'" . implode("','", $archivedValues) . "'";
    $stats['pending_claims'] = $pdo->query("SELECT COUNT(*) FROM reclamations WHERE `$statusCol` IN ($inPending)")->fetchColumn();
    $stats['resolved_claims'] = $pdo->query("SELECT COUNT(*) FROM reclamations WHERE `$statusCol` IN ($inResolved)")->fetchColumn();
    $stats['archived_claims'] = $pdo->query("SELECT COUNT(*) FROM reclamations WHERE `$statusCol` IN ($inArchived)")->fetchColumn();
} else {
    // Si aucune colonne de statut détectée, mettre les compteurs à 0
    $stats['pending_claims'] = 0;
    $stats['resolved_claims'] = 0;
    $stats['archived_claims'] = 0;
}
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

// Colonnes dynamiques pour reclamations
$reclamIdCol = detect_column($pdo, $reclamTable, ['id','reclam_id','reclamation_id','id_reclamation','claim_id']) ?: 'id';
$reclamUserCol = detect_column($pdo, $reclamTable, ['user_id','userId','utilisateur_id']) ?: 'user_id';
$reclamCatCol = detect_column($pdo, $reclamTable, ['category_id','categorie_id','cat_id','categorie']) ?: 'category_id';
$reclamCreatedCol = detect_column($pdo, $reclamTable, ['created_at','date_soumission','date_creation','submitted_at','date']) ?: 'created_at';
$reclamStatusCol = detect_column($pdo, $reclamTable, ['statut','status','etat']) ?: 'statut';
$reclamManagerCol = detect_column($pdo, $reclamTable, ['gestionnaire_id','manager_id','assigned_to','traitant_id']) ?: $reclamUserCol; // fallback
$reclamSubmitDateCol = $reclamCreatedCol; // Pour calcul des délais

// Construire la requête en échappant les noms de colonnes (utilisation basique)
$query = "SELECT c.*, u.".$userNameCol." AS user_name, cat.".$catNameCol." AS category_nom "
    ."FROM `".$reclamTable."` c "
    ."LEFT JOIN `users` u ON c.`$reclamUserCol` = u.`$userIdCol` "
    ."LEFT JOIN `categories` cat ON c.`$reclamCatCol` = cat.`$catIdCol` "
    ."ORDER BY c.`$reclamCreatedCol` DESC LIMIT 5";

try {
    $stmt = $pdo->query($query);
    $latest_reclamations = $stmt->fetchAll();
} catch (PDOException $e) {
    // En cas d'échec, retomber sur une requête basique (sans nom de catégorie)
    $stmt = $pdo->query("SELECT c.* FROM " . $reclamTable . " c ORDER BY c." . $reclamCreatedCol . " DESC LIMIT 5");
    $latest_reclamations = $stmt->fetchAll();
}

// ===== ANALYSE DES TENDANCES PAR CATÉGORIE (dynamiques) =====
$categAnalysis = [];
try {
    $catQuery = "SELECT cat.`$catNameCol` AS cat_name, 
                        COUNT(c.`$reclamIdCol`) AS total,
                        SUM(CASE WHEN c.`$reclamStatusCol` IN ('resolu','closed','traite') THEN 1 ELSE 0 END) AS resolved,
                        SUM(CASE WHEN c.`$reclamStatusCol` IN ('en_cours','en_attente','pending') THEN 1 ELSE 0 END) AS pending
                 FROM `".$reclamTable."` c
                 LEFT JOIN `categories` cat ON c.`$reclamCatCol` = cat.`$catIdCol`
                 GROUP BY cat.`$catIdCol`
                 ORDER BY total DESC";
    $categAnalysis = $pdo->query($catQuery)->fetchAll();
} catch (Exception $e) { $categAnalysis = []; }

// ===== ANALYSE DE L'EFFICACITÉ PAR GESTIONNAIRE (dynamiques) =====
$managerAnalysis = [];
try {
    $managerQuery = "SELECT u.`$userNameCol` AS manager_name, u.`$userIdCol` AS user_id,
                            COUNT(DISTINCT c.`$reclamIdCol`) AS handled_claims,
                            SUM(CASE WHEN c.`$reclamStatusCol` IN ('resolu','closed','traite') THEN 1 ELSE 0 END) AS resolved_claims,
                            ROUND(AVG(CASE WHEN c.`$reclamStatusCol` IN ('resolu','closed','traite') THEN TIMESTAMPDIFF(DAY, c.`$reclamSubmitDateCol`, NOW()) END),1) AS avg_days_pending
                     FROM `users` u
                     LEFT JOIN `".$reclamTable."` c ON u.`$userIdCol` = c.`$reclamManagerCol`
                     WHERE u.role = 'gestionnaire'
                     GROUP BY u.`$userIdCol`
                     ORDER BY handled_claims DESC";
    $managerAnalysis = $pdo->query($managerQuery)->fetchAll();
} catch (Exception $e) { $managerAnalysis = []; }

// ===== DÉLAI MOYEN DE TRAITEMENT ===== (utilise colonnes dynamiques si possible)
$avgProcessingTime = 0;
try {
    $dateCol = detect_column($pdo, 'reclamations', ['date_soumission','created_at','date_creation','submitted_at','date']) ?: 'date_soumission';
    $statusCond = $statusCol ? "`$statusCol` IN ('resolu','closed','traite')" : "1=0"; // 1=0 si pas de statut -> pas de calcul
    $timeQuery = "SELECT ROUND(AVG(TIMESTAMPDIFF(DAY, `$dateCol`, NOW())), 1) AS avg_days FROM reclamations WHERE $statusCond";
    $result = $pdo->query($timeQuery)->fetch();
    $avgProcessingTime = $result['avg_days'] ?? 0;
} catch (Exception $e) { $avgProcessingTime = 0; }

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
                    <a href="reclamations.php" class="list-group-item list-group-item-action fw-bold">
                        <i class="bi bi-inbox-fill me-2"></i>Réclamations
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
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
    (function(){
        // Données Catégories (échelle minimisée)
        const categData = <?php echo json_encode($categAnalysis, JSON_UNESCAPED_UNICODE); ?>;
        if (categData && categData.length > 0) {
            const labels = categData.map(r => r.cat_name || '—');
            const totals = categData.map(r => parseInt(r.total || 0,10));
            const resolved = categData.map(r => parseInt(r.resolved || 0,10));
            const pending = categData.map(r => parseInt(r.pending || 0,10));
            const maxCat = Math.max(1, ...totals, ...resolved, ...pending);
            const stepCat = Math.max(1, Math.ceil(maxCat / 5));
            const catCanvas = document.createElement('canvas');
            catCanvas.id = 'categoryChart';
            catCanvas.style.maxHeight = '240px';
            const target = document.querySelector('.card:has(h5 i.bi-graph-up) .card-body');
            if (target) {
                target.insertBefore(catCanvas, target.firstChild);
            }
            new Chart(catCanvas, {
                type: 'bar',
                data: { labels, datasets: [
                    { label: 'Total', data: totals, backgroundColor: 'rgba(54,162,235,0.6)' },
                    { label: 'Résolues', data: resolved, backgroundColor: 'rgba(75,192,192,0.7)' },
                    { label: 'En cours', data: pending, backgroundColor: 'rgba(255,206,86,0.7)' }
                ]},
                options: {
                    responsive:true,
                    maintainAspectRatio:false,
                    layout:{ padding:{ top:4, right:8, bottom:4, left:8 } },
                    plugins:{ legend:{ position:'bottom' } },
                    scales:{
                        y:{
                            beginAtZero:true,
                            suggestedMax: maxCat,
                            ticks:{ stepSize: stepCat }
                        },
                        x:{ ticks:{ maxRotation:0 } }
                    }
                }
            });
        }

        // Données Gestionnaires (échelle minimisée)
        const managerData = <?php echo json_encode($managerAnalysis, JSON_UNESCAPED_UNICODE); ?>;
        if (managerData && managerData.length > 0) {
            const labels = managerData.map(r => r.manager_name || '—');
            const handled = managerData.map(r => parseInt(r.handled_claims || 0,10));
            const resolvedMgr = managerData.map(r => parseInt(r.resolved_claims || 0,10));
            const maxMgr = Math.max(1, ...handled, ...resolvedMgr);
            const stepMgr = Math.max(1, Math.ceil(maxMgr / 5));
            const mgrCanvas = document.createElement('canvas');
            mgrCanvas.id = 'managerChart';
            mgrCanvas.style.maxHeight = '240px';
            const target2 = document.querySelector('.card:has(h5 i.bi-person-check-fill) .card-body');
            if (target2) {
                target2.insertBefore(mgrCanvas, target2.firstChild);
            }
            new Chart(mgrCanvas, {
                type: 'bar',
                data: { labels, datasets: [
                    { label: 'Dossiers', data: handled, backgroundColor: 'rgba(54,162,235,0.6)' },
                    { label: 'Résolus', data: resolvedMgr, backgroundColor: 'rgba(34,197,94,0.7)' }
                ]},
                options: {
                    indexAxis:'y',
                    responsive:true,
                    maintainAspectRatio:false,
                    layout:{ padding:{ top:4, right:8, bottom:4, left:8 } },
                    plugins:{ legend:{ position:'bottom' } },
                    scales:{
                        x:{
                            beginAtZero:true,
                            suggestedMax: maxMgr,
                            ticks:{ stepSize: stepMgr }
                        },
                        y:{ ticks:{ autoSkip:false } }
                    }
                }
            });
        }
    })();
    </script>
</body>
</html>
