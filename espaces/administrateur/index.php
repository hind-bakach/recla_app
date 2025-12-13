<?php
require_once '../../includes/config.php';
require_once '../../includes/lang.php';
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
$reclamManagerCol = detect_column($pdo, $reclamTable, ['gestionnaire_id','manager_id','assigned_to','traitant_id']);
$reclamSubmitDateCol = $reclamCreatedCol; // Pour calcul des délais
$reclamResolvedDateCol = detect_column($pdo, $reclamTable, ['date_resolution','resolved_at','date_traitement','date_cloture']);

// DEBUG: Vérifier la détection (commenter après vérification)
// echo "<!-- DEBUG: reclamManagerCol = " . ($reclamManagerCol ?: 'NULL') . " -->";

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
$managerColumnExists = !empty($reclamManagerCol);

// DEBUG: Afficher si colonne détectée
// echo "<!-- DEBUG: managerColumnExists = " . ($managerColumnExists ? 'OUI' : 'NON') . " -->";

try {
    if ($managerColumnExists) {
        // Calcul du délai : si date_resolution existe, utiliser (date_resolution - date_soumission)
        // sinon pour réclamations résolues, approximer avec NOW() - date_soumission
        $delaiCalcul = $reclamResolvedDateCol 
            ? "TIMESTAMPDIFF(DAY, c.`$reclamSubmitDateCol`, c.`$reclamResolvedDateCol`)"
            : "TIMESTAMPDIFF(DAY, c.`$reclamSubmitDateCol`, NOW())";
        
        // Joindre avec gestionnaire_id (la colonne qui lie réclamations aux gestionnaires)
        $managerQuery = "SELECT u.`$userNameCol` AS manager_name, u.`$userIdCol` AS user_id,
                                COUNT(DISTINCT c.`$reclamIdCol`) AS handled_claims,
                                SUM(CASE WHEN c.`$reclamStatusCol` IN ('resolu','closed','traite') THEN 1 ELSE 0 END) AS resolved_claims,
                                ROUND(AVG(CASE WHEN c.`$reclamStatusCol` IN ('resolu','closed','traite') THEN $delaiCalcul END),1) AS avg_days_resolution
                         FROM `users` u
                         LEFT JOIN `".$reclamTable."` c ON u.`$userIdCol` = c.`$reclamManagerCol`
                         WHERE u.role = 'gestionnaire'
                         GROUP BY u.`$userIdCol`
                         ORDER BY handled_claims DESC";
        
        // DEBUG: Afficher la requête
        // echo "<!-- DEBUG SQL: " . htmlspecialchars($managerQuery) . " -->";
        
        $managerAnalysis = $pdo->query($managerQuery)->fetchAll();
        
        // DEBUG: Afficher résultat
        // echo "<!-- DEBUG: Résultats trouvés = " . count($managerAnalysis) . " -->";
    } else {
        // Si pas de colonne gestionnaire, afficher message
        $managerAnalysis = [];
    }
} catch (Exception $e) { 
    // En cas d'erreur SQL, afficher l'erreur en commentaire HTML
    echo "<!-- ERREUR SQL: " . htmlspecialchars($e->getMessage()) . " -->";
    error_log("Erreur requête gestionnaires: " . $e->getMessage());
    $managerAnalysis = []; 
}

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
    <?php include '../../includes/navbar_admin.php'; ?>
    <?php include '../../includes/sidebar_admin.php'; ?>

    <!-- Contenu Principal -->
    <div class="container-fluid bg-light" style="padding: 2rem;">
                <!-- Cartes Stats -->
                <div class="row g-3 mb-4">
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm rounded-3 h-100" style="background: linear-gradient(135deg, #2c5f7f 0%, #1e4563 100%);">
                            <div class="card-body p-3 text-white">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="text-uppercase mb-2 fw-bold" style="font-size: 0.7rem; letter-spacing: 0.5px; opacity: 0.9;">RÉCLAMATIONS</h6>
                                        <h2 class="display-4 fw-bold mb-0"><?php echo $stats['total_claims']; ?></h2>
                                    </div>
                                    <i class="bi bi-inbox-fill" style="font-size: 2rem; opacity: 0.4;"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm rounded-3 h-100" style="background: linear-gradient(135deg, #d97706 0%, #b45309 100%);">
                            <div class="card-body p-3 text-white">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="text-uppercase mb-2 fw-bold" style="font-size: 0.7rem; letter-spacing: 0.5px; opacity: 0.9;">EN ATTENTE</h6>
                                        <h2 class="display-4 fw-bold mb-0"><?php echo $stats['pending_claims']; ?></h2>
                                    </div>
                                    <i class="bi bi-hourglass-split" style="font-size: 2rem; opacity: 0.4;"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm rounded-3 h-100" style="background: linear-gradient(135deg, #14b8a6 0%, #0d9488 100%);">
                            <div class="card-body p-3 text-white">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="text-uppercase mb-2 fw-bold" style="font-size: 0.7rem; letter-spacing: 0.5px; opacity: 0.9;">RÉSOLUES</h6>
                                        <h2 class="display-4 fw-bold mb-0"><?php echo $stats['resolved_claims']; ?></h2>
                                        <small class="opacity-90 fw-semibold"><?php echo $stats['resolution_rate']; ?>% taux</small>
                                    </div>
                                    <i class="bi bi-check-circle-fill" style="font-size: 2rem; opacity: 0.4;"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm rounded-3 h-100" style="background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%);">
                            <div class="card-body p-3 text-white">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="text-uppercase mb-2 fw-bold" style="font-size: 0.7rem; letter-spacing: 0.5px; opacity: 0.9;">DÉLAI MOYEN</h6>
                                        <h2 class="display-4 fw-bold mb-0"><?php echo $avgProcessingTime; ?></h2>
                                        <small class="opacity-90 fw-semibold">Jours</small>
                                    </div>
                                    <i class="bi bi-calendar-event" style="font-size: 2rem; opacity: 0.4;"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Analyse des Tendances par Catégorie -->
                <div class="row g-3 mb-4">
                    <div class="col-lg-6">
                        <div class="card shadow-sm border-0 rounded-3">
                            <div class="card-header bg-white p-3 border-0" style="background-color: #bfc9cf8c !important;">
                                <h6 class="mb-0 fw-bold text-uppercase" style="font-size: 0.90rem; letter-spacing: 0.5px;"><i class="bi bi-bar-chart-fill me-2 text-primary"></i>Tendances par Catégorie</h6>
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
                        <div class="card shadow-sm border-0 rounded-3">
                            <div class="card-header bg-white p-3 border-0" style="background-color: #bfc9cf8c !important;">
                                <h6 class="mb-0 fw-bold text-uppercase" style="font-size: 0.90rem; letter-spacing: 0.5px;"><i class="bi bi-people-fill me-2 text-primary"></i>Efficacité des Gestionnaires</h6>
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
                                                <?php foreach ($managerAnalysis as $manager): 
                                                    $efficiency = $manager['handled_claims'] > 0 ? round(($manager['resolved_claims'] / $manager['handled_claims']) * 100) : 0;
                                                ?>
                                                    <tr>
                                                        <td class="ps-4 fw-bold"><?php echo htmlspecialchars($manager['manager_name'] ?? '—'); ?></td>
                                                        <td><span class="badge bg-info"><?php echo $manager['handled_claims']; ?></span></td>
                                                        <td>
                                                            <span class="badge bg-success"><?php echo $manager['resolved_claims']; ?></span>
                                                            <small class="text-muted ms-1">(<?php echo $efficiency; ?>%)</small>
                                                        </td>
                                                        <td class="text-end pe-4">
                                                            <?php 
                                                                $delai = $manager['avg_days_resolution'] ?? $manager['avg_days_pending'] ?? null;
                                                                if ($delai !== null && $delai > 0): 
                                                            ?>
                                                                <span class="badge <?php echo $delai < 7 ? 'bg-success' : ($delai < 14 ? 'bg-warning text-dark' : 'bg-danger'); ?>">
                                                                    <?php echo number_format($delai, 1); ?> j
                                                                </span>
                                                            <?php else: ?>
                                                                <span class="text-muted">—</span>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <p class="text-center text-muted p-4">Aucun gestionnaire n'a encore de réclamations assignées.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Dernières activités -->
                <div class="card shadow-sm border-0 rounded-3">
                    <div class="card-header bg-white p-3 border-0" style="background-color: #bfc9cf8c !important;">
                        <h6 class="mb-0 fw-bold text-uppercase" style="font-size: 0.90rem; letter-spacing: 0.5px;"><i class="bi bi-list-check me-2 text-primary"></i>Dernières Réclamations</h6>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead style="background-color: #343a40; color: white;">
                                    <tr>
                                        <th class="ps-4 py-3 text-uppercase fw-semibold" style="font-size: 0.80rem;">ID</th>
                                        <th class="py-3 text-uppercase fw-semibold" style="font-size: 0.80rem;">RÉCLAMANT</th>
                                        <th class="py-3 text-uppercase fw-semibold" style="font-size: 0.80rem;">SUJET</th>
                                        <th class="py-3 text-uppercase fw-semibold" style="font-size: 0.80rem;">CATÉGORIE</th>
                                        <th class="py-3 text-uppercase fw-semibold" style="font-size: 0.80rem;">DATE</th>
                                        <th class="py-3 text-uppercase fw-semibold" style="font-size: 0.80rem;">STATUT</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($latest_reclamations) && is_array($latest_reclamations)): ?>
                                        <?php foreach ($latest_reclamations as $reclamation): ?>
                                            <tr style="border-bottom: 1px solid #e5e7eb;">
                                                <td class="ps-4 fw-bold" style="color: #374151;">#<?php echo htmlspecialchars($reclamation['reclam_id'] ?? $reclamation['id'] ?? $reclamation['reclamation_id'] ?? ''); ?></td>
                                                <td style="color: #111827;"><?php echo htmlspecialchars(html_entity_decode($reclamation['user_name'] ?? $reclamation['nom'] ?? $reclamation['name'] ?? '—', ENT_QUOTES, 'UTF-8')); ?></td>
                                                <td style="color: #374151;"><?php echo htmlspecialchars(html_entity_decode($reclamation['objet'] ?? $reclamation['sujet'] ?? '', ENT_QUOTES, 'UTF-8')); ?></td>
                                                <td><span class="badge" style="background-color: #f3f4f6; color: #374151; font-weight: 500;"><?php echo htmlspecialchars(html_entity_decode($reclamation['category_nom'] ?? $reclamation['categorie_nom'] ?? $reclamation['categorie'] ?? '—', ENT_QUOTES, 'UTF-8')); ?></span></td>
                                                <td style="color: #6b7280;"><?php echo !empty($reclamation['date_soumission'] ?? $reclamation['created_at'] ?? null) ? format_date($reclamation['date_soumission'] ?? $reclamation['created_at']) : ''; ?></td>
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
    <link rel="stylesheet" href="../../css/admin.css">

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="../../js/admin-animations.js"></script>
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
            catCanvas.style.maxHeight = '280px';
            const target = document.querySelector('.card:has(h6 i.bi-bar-chart-fill) .card-body');
            if (target) {
                target.insertBefore(catCanvas, target.firstChild);
            }
            new Chart(catCanvas, {
                type: 'bar',
                data: { labels, datasets: [
                    { label: 'Total', data: totals, backgroundColor: '#3b82f6', borderRadius: 6 },
                    { label: 'Résolues', data: resolved, backgroundColor: '#14b8a6', borderRadius: 6 },
                    { label: 'En cours', data: pending, backgroundColor: '#06b6d4', borderRadius: 6 }
                ]},
                options: {
                    responsive:true,
                    maintainAspectRatio:false,
                    layout:{ padding:{ top:10, right:10, bottom:10, left:10 } },
                    plugins:{ 
                        legend:{ 
                            position:'bottom',
                            labels: {
                                boxWidth: 12,
                                padding: 15,
                                font: { size: 11, weight: '500' }
                            }
                        } 
                    },
                    scales:{
                        y:{
                            beginAtZero:true,
                            suggestedMax: maxCat,
                            ticks:{ stepSize: stepCat, font: { size: 11 } },
                            grid: { color: '#f3f4f6' }
                        },
                        x:{ 
                            ticks:{ maxRotation:0, font: { size: 11 } },
                            grid: { display: false }
                        }
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
            mgrCanvas.style.maxHeight = '280px';
            const target2 = document.querySelector('.card:has(h6 i.bi-people-fill) .card-body');
            if (target2) {
                target2.insertBefore(mgrCanvas, target2.firstChild);
            }
            new Chart(mgrCanvas, {
                type: 'bar',
                data: { labels, datasets: [
                    { label: 'Dossiers', data: handled, backgroundColor: '#3b82f6', borderRadius: 6 },
                    { label: 'Résolus', data: resolvedMgr, backgroundColor: '#14b8a6', borderRadius: 6 }
                ]},
                options: {
                    indexAxis:'y',
                    responsive:true,
                    maintainAspectRatio:false,
                    layout:{ padding:{ top:10, right:10, bottom:10, left:10 } },
                    plugins:{ 
                        legend:{ 
                            position:'bottom',
                            labels: {
                                boxWidth: 12,
                                padding: 15,
                                font: { size: 11, weight: '500' }
                            }
                        } 
                    },
                    scales:{
                        x:{
                            beginAtZero:true,
                            suggestedMax: maxMgr,
                            ticks:{ stepSize: stepMgr, font: { size: 11 } },
                            grid: { color: '#f3f4f6' }
                        },
                        y:{ 
                            ticks:{ autoSkip:false, font: { size: 11 } },
                            grid: { display: false }
                        }
                    }
                }
            });
        }
    })();
    </script>
</body>
</html>
