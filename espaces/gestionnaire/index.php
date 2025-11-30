<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

require_role('gestionnaire');

$pdo = get_pdo();

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

<body class="bg-light">
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm">
        <div class="container-fluid">
            <a class="navbar-brand fw-bold" href="index.php"><i class="bi bi-person-workspace me-2"></i>Espace Gestionnaire</a>
            <div class="d-flex align-items-center">
                <span class="text-white me-3">Bonjour, <strong><?php echo htmlspecialchars($_SESSION['user_name']); ?></strong></span>
                <a class="btn btn-outline-light btn-sm fw-bold" href="../../frontend/deconnexion.php">
                    <i class="bi bi-box-arrow-right me-1"></i> Déconnexion
                </a>
            </div>
        </div>
    </nav>

    <div class="container-fluid py-4">
        <div class="row g-4">
            <!-- Sidebar / Filtres -->
            <div class="col-lg-2">
                <div class="card shadow-sm border-0 rounded-4 mb-4">
                    <div class="card-body p-3">
                        <h6 class="fw-bold text-uppercase text-muted mb-3 small">Statistiques</h6>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Total</span>
                            <span class="badge bg-primary rounded-pill"><?php echo $stats['total']; ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>En cours</span>
                            <span class="badge bg-warning text-dark rounded-pill"><?php echo $stats['en_cours']; ?></span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span>Traités</span>
                            <span class="badge bg-success rounded-pill"><?php echo $stats['traite']; ?></span>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm border-0 rounded-4">
                    <div class="card-body p-3">
                        <h6 class="fw-bold text-uppercase text-muted mb-3 small">Filtres</h6>
                        <form method="GET" action="index.php">
                            <div class="mb-3">
                                <label class="form-label small fw-bold">Statut</label>
                                <select class="form-select form-select-sm" name="status" onchange="this.form.submit()">
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
                                <label class="form-label small fw-bold">Réclamant</label>
                                <input type="text" class="form-control form-control-sm" name="user" value="<?php echo htmlspecialchars($user_filter); ?>" placeholder="Nom">
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-bold">Date</label>
                                <input type="date" class="form-control form-control-sm" name="date" value="<?php echo $date_filter; ?>" onchange="this.form.submit()">
                            </div>
                            <div class="d-grid">
                                <a href="index.php" class="btn btn-light btn-sm border">Réinitialiser</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Liste des réclamations -->
            <div class="col-lg-10">
                <div class="card shadow-sm border-0 rounded-4">
                    <div class="card-header bg-white p-3 border-bottom d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fw-bold"><i class="bi bi-inbox-fill me-2 text-primary"></i>Réclamations Reçues</h5>
                        <span class="badge bg-light text-dark border"><?php echo count($claims); ?> dossiers</span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="bg-light text-muted text-uppercase small">
                                    <tr>
                                        <th class="ps-4 py-3">ID</th>
                                        <th class="py-3">Réclamant</th>
                                        <th class="py-3">Sujet</th>
                                        <th class="py-3">Catégorie</th>
                                        <th class="py-3">Date</th>
                                        <th class="py-3">Statut</th>
                                        <th class="py-3 text-end pe-4">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($claims) > 0): ?>
                                        <?php foreach ($claims as $claim): ?>
                                            <tr>
                                                <td class="ps-4 fw-bold">#<?php echo $claim['id']; ?></td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="rounded-circle bg-light text-primary d-flex align-items-center justify-content-center fw-bold me-2" style="width: 30px; height: 30px;">
                                                            <?php echo strtoupper(substr($claim['user_name'], 0, 1)); ?>
                                                        </div>
                                                        <?php echo htmlspecialchars($claim['user_name']); ?>
                                                    </div>
                                                </td>
                                                <td><?php echo htmlspecialchars($claim['sujet']); ?></td>
                                                <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($claim['category_nom']); ?></span></td>
                                                <td><?php echo format_date($claim['created_at']); ?></td>
                                                <td>
                                                    <span class="badge rounded-pill <?php echo get_status_badge($claim['statut']); ?>">
                                                        <?php echo get_status_label($claim['statut']); ?>
                                                    </span>
                                                </td>
                                                <td class="text-end pe-4">
                                                    <a href="traitement.php?id=<?php echo $claim['id']; ?>" class="btn btn-sm btn-primary fw-bold">
                                                        Traiter
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="7" class="text-center py-5 text-muted">
                                                <i class="bi bi-check2-circle fs-1 d-block mb-3"></i>
                                                Aucune réclamation à traiter.
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
    </div>

    <?php include '../../includes/footer.php'; ?>
</body>
</html>
