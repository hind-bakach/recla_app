<?php
require_once '../../includes/config.php';
require_once '../../includes/lang.php';
require_once '../../includes/functions.php';

require_role('administrateur');

$pdo = get_pdo();
$error = '';
$success = '';

// Récupérer les noms de colonnes dynamiquement
$getCols = function($table) use ($pdo) {
    $rows = $pdo->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA='".DB_NAME."' AND TABLE_NAME='".addslashes($table)."'")->fetchAll(PDO::FETCH_COLUMN);
    return $rows ?: [];
};

// Détecter les noms de colonnes
$userCols = $getCols('users');
$userIdCol = in_array('user_id', $userCols) ? 'user_id' : (in_array('id', $userCols) ? 'id' : ($userCols[0] ?? 'id'));
$userNameCol = in_array('nom', $userCols) ? 'nom' : (in_array('name', $userCols) ? 'name' : ($userCols[1] ?? $userIdCol));

// Déterminer la table de réclamations
$reclamTable = null;
foreach (['reclamations', 'claims'] as $t) {
    if ($pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA='".DB_NAME."' AND TABLE_NAME='".addslashes($t)."'")->fetchColumn()) {
        $reclamTable = $t;
        break;
    }
}
if (!$reclamTable) $reclamTable = 'reclamations';

$reclamCols = $getCols($reclamTable);
$reclamIdCol = in_array('reclam_id', $reclamCols) ? 'reclam_id' : (in_array('id', $reclamCols) ? 'id' : ($reclamCols[0] ?? 'id'));
$reclamUserCol = in_array('user_id', $reclamCols) ? 'user_id' : (in_array('userId', $reclamCols) ? 'userId' : (in_array('user', $reclamCols) ? 'user' : 'user_id'));
$reclamStatusCol = in_array('statut', $reclamCols) ? 'statut' : (in_array('status', $reclamCols) ? 'status' : 'statut');
$reclamDateCol = in_array('date_soumission', $reclamCols) ? 'date_soumission' : (in_array('created_at', $reclamCols) ? 'created_at' : ($reclamCols[1] ?? $reclamCols[0] ?? 'date_soumission'));
$reclamCatCol = in_array('categorie_id', $reclamCols) ? 'categorie_id' : (in_array('category_id', $reclamCols) ? 'category_id' : (in_array('cat_id', $reclamCols) ? 'cat_id' : null));
$reclamObjetCol = in_array('objet', $reclamCols) ? 'objet' : (in_array('sujet', $reclamCols) ? 'sujet' : (in_array('title', $reclamCols) ? 'title' : 'objet'));

// Modification du statut
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $reclamation_id = $_POST['reclamation_id'] ?? null;
    $new_status = $_POST['new_status'] ?? null;
    
    if ($reclamation_id && $new_status) {
        $stmt = $pdo->prepare("UPDATE " . $reclamTable . " SET " . $reclamStatusCol . " = ? WHERE " . $reclamIdCol . " = ?");
        if ($stmt->execute([$new_status, $reclamation_id])) {
            $success = "Statut de la réclamation mise à jour avec succès.";
        } else {
            $error = "Erreur lors de la mise à jour du statut.";
        }
    }
}

// Récupérer toutes les réclamations avec détails
$catCols = $getCols('categories');
$catIdCol = in_array('categorie_id', $catCols) ? 'categorie_id' : (in_array('id', $catCols) ? 'id' : ($catCols[0] ?? 'id'));
$catNameCol = in_array('nom_categorie', $catCols) ? 'nom_categorie' : (in_array('nom', $catCols) ? 'nom' : (in_array('name', $catCols) ? 'name' : ($catCols[1] ?? $catIdCol)));
$catResponsableCol = in_array('responsable', $catCols) ? 'responsable' : (in_array('gestionnaire', $catCols) ? 'gestionnaire' : null);

$select_obj = 'r.' . $reclamObjetCol . ' AS objet';
$select_cat = ($reclamCatCol ? 'r.' . $reclamCatCol . ' AS category_key,' : "NULL AS category_key,");

// Filtres GET
$fStatus = sanitize_input($_GET['status'] ?? '');
$fSubject = sanitize_input($_GET['subject'] ?? '');
$fUser = sanitize_input($_GET['user'] ?? '');
$fCategory = sanitize_input($_GET['category'] ?? '');
$fDateFrom = sanitize_input($_GET['date_from'] ?? '');

$query = "SELECT 
            r." . $reclamIdCol . " as reclamation_id,
            r." . $reclamUserCol . " as user_id,
            u." . $userNameCol . " as reclamant_name,
            r." . $reclamStatusCol . " as statut,
            r." . $reclamDateCol . " as date_soumission,
            " . $select_obj . ",
            r.description,
            " . $select_cat . "
            cat." . $catNameCol . " as nom_categorie,
            cat." . ($catResponsableCol ?? 'responsable') . " as gestionnaire_name
        FROM " . $reclamTable . " r
        LEFT JOIN users u ON r." . $reclamUserCol . " = u." . $userIdCol . "
        LEFT JOIN categories cat ON (r." . ($reclamCatCol ?? $catIdCol) . " = cat." . $catIdCol . ")
        WHERE 1=1";

$params = [];
if ($fStatus !== '') { $query .= " AND r.`$reclamStatusCol` = ?"; $params[] = $fStatus; }
if ($fSubject !== '') { $query .= " AND r.`$reclamObjetCol` LIKE ?"; $params[] = '%'.$fSubject.'%'; }
if ($fUser !== '') { $query .= " AND (u.`$userNameCol` LIKE ? OR u.email LIKE ?)"; $params[] = '%'.$fUser.'%'; $params[] = '%'.$fUser.'%'; }
if ($fCategory !== '' && ctype_digit($fCategory) && $reclamCatCol) { $query .= " AND r.`$reclamCatCol` = ?"; $params[] = $fCategory; }
if ($fDateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/',$fDateFrom)) { $query .= " AND r.`$reclamDateCol` >= ?"; $params[] = $fDateFrom; }
// Champ 'Au' supprimé: pas de filtre date_to

$query .= " ORDER BY r.`$reclamDateCol` DESC LIMIT 500";
try {
    $stmtRecla = $pdo->prepare($query);
    $stmtRecla->execute($params);
    $reclamations = $stmtRecla->fetchAll();
} catch (Exception $e) {
    $reclamations = [];
}

include '../../includes/head.php';
?>

<body class="bg-light">
    <?php include '../../includes/navbar_admin.php'; ?>
    <?php include '../../includes/sidebar_admin.php'; ?>

    <div class="container-fluid bg-light" style="padding: 2rem;">
                
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $success; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <!-- Titre -->
                <div class="mb-4">
                    <h3 class="fw-bold"><i class="bi bi-inbox-fill me-2 text-primary"></i>Suivi des Réclamations</h3>
                    <p class="text-muted">Gérez et suivez toutes les réclamations du système</p>
                </div>

                <!-- Filtres -->
                <div class="card shadow-sm border-0 rounded-4 mb-4">
                    <div class="card-header bg-white p-3 border-bottom">
                        <div class="d-flex flex-column flex-xl-row gap-3 align-items-xl-center justify-content-between">
                            <h5 class="mb-0 fw-bold"><i class="bi bi-funnel-fill me-2 text-primary"></i>Filtres</h5>
                            <form class="row g-2 align-items-end" method="GET" action="reclamations.php">
                                <div class="col-md-2">
                                    <label class="form-label small fw-bold mb-1">Statut</label>
                                    <select name="status" class="form-select form-select-sm">
                                        <option value="">Tous</option>
                                        <?php foreach(['soumis','en_cours','en_attente','resolu','resolue','rejete','archive'] as $s): ?>
                                            <option value="<?php echo $s; ?>" <?php if(($fStatus ?? '')===$s) echo 'selected'; ?>><?php echo get_status_label($s); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small fw-bold mb-1">Sujet</label>
                                    <input type="text" name="subject" value="<?php echo htmlspecialchars($fSubject); ?>" class="form-control form-control-sm" placeholder="Sujet">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small fw-bold mb-1">Réclamant</label>
                                    <input type="text" name="user" value="<?php echo htmlspecialchars($fUser); ?>" class="form-control form-control-sm" placeholder="Nom ou email">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small fw-bold mb-1">Catégorie</label>
                                    <select name="category" class="form-select form-select-sm">
                                        <option value="">Toutes</option>
                                        <?php 
                                        $catsFilter = $pdo->query("SELECT $catIdCol AS id, $catNameCol AS nom FROM categories ORDER BY $catNameCol ASC")->fetchAll();
                                        foreach($catsFilter as $cf): ?>
                                            <option value="<?php echo htmlspecialchars($cf['id']); ?>" <?php if(($fCategory ?? '')==$cf['id']) echo 'selected'; ?>><?php echo htmlspecialchars($cf['nom']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small fw-bold mb-1">Du</label>
                                    <input type="date" name="date_from" value="<?php echo htmlspecialchars($fDateFrom); ?>" class="form-control form-control-sm">
                                </div>
                                <!-- Champ 'Au' retiré -->
                                <div class="col-12 d-flex gap-2 mt-1">
                                    <button type="submit" class="btn btn-sm btn-primary fw-bold"><i class="bi bi-funnel"></i> Appliquer</button>
                                    <a href="reclamations.php" class="btn btn-sm btn-outline-secondary fw-bold"><i class="bi bi-x-circle"></i> Réinitialiser</a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Tableau des Réclamations -->
                <div class="card shadow-sm border-0 rounded-3">
                    <div class="card-header bg-white p-3 border-0" style="background-color: #bfc9cf8c !important;">
                        <h6 class="mb-0 fw-bold text-uppercase" style="font-size: 0.90rem; letter-spacing: 0.5px;"><i class="bi bi-list-ul me-2 text-primary"></i>Liste des Réclamations (<?php echo count($reclamations); ?>)</h6>
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
                                        <th class="py-3">Gestionnaire</th>
                                        <th class="py-3">Date</th>
                                        <th class="py-3">Statut</th>
                                        <th class="py-3 text-end pe-4">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($reclamations) && is_array($reclamations)): ?>
                                        <?php foreach ($reclamations as $reclamation): ?>
                                            <tr>
                                                <td class="ps-4 fw-bold">#<?php echo htmlspecialchars($reclamation['reclamation_id'] ?? $reclamation['id'] ?? ''); ?></td>
                                                <td><?php echo htmlspecialchars($reclamation['reclamant_name'] ?? $reclamation['nom'] ?? '—'); ?></td>
                                                <td class="text-truncate" title="<?php echo htmlspecialchars($reclamation['objet'] ?? ''); ?>">
                                                    <?php echo htmlspecialchars(substr($reclamation['objet'] ?? '', 0, 30)); ?>...
                                                </td>
                                                <td>
                                                    <span class="badge bg-light text-dark border">
                                                        <?php echo htmlspecialchars($reclamation['nom_categorie'] ?? '—'); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo htmlspecialchars($reclamation['gestionnaire_name'] ?? '—'); ?></td>
                                                <td><?php echo !empty($reclamation['date_soumission']) ? format_date($reclamation['date_soumission']) : '—'; ?></td>
                                                <td>
                                                    <span class="badge rounded-pill <?php echo get_status_badge($reclamation['statut'] ?? ''); ?>">
                                                        <?php echo get_status_label($reclamation['statut'] ?? ''); ?>
                                                    </span>
                                                </td>
                                                <td class="text-end pe-4">
                                                    <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#statusModal" 
                                                        data-reclamation-id="<?php echo htmlspecialchars($reclamation['reclamation_id'] ?? $reclamation['id'] ?? ''); ?>"
                                                        data-current-status="<?php echo htmlspecialchars($reclamation['statut'] ?? ''); ?>"
                                                        data-objet="<?php echo htmlspecialchars($reclamation['objet'] ?? ''); ?>">
                                                        <i class="bi bi-pencil-fill"></i> Modifier
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="8" class="text-center text-muted py-4">Aucune réclamation trouvée.</td>
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

    <link rel="stylesheet" href="../../css/admin.css">

    <!-- Modal Modification Statut -->
    <div class="modal fade" id="statusModal" tabindex="-1" aria-labelledby="statusModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="statusModalLabel">Modifier le Statut</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-3"><strong>Sujet :</strong> <span id="modalObjet"></span></p>
                    <form id="statusForm" method="POST" action="reclamations.php">
                        <input type="hidden" name="action" value="update_status">
                        <input type="hidden" name="reclamation_id" id="modalReclamationId">
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">Nouveau Statut</label>
                            <select class="form-select" name="new_status" id="modalNewStatus" required>
                                <option value="">-- Sélectionner un statut --</option>
                                <option value="soumis">Soumis</option>
                                <option value="en_cours">En Cours</option>
                                <option value="en_attente">En Attente</option>
                                <option value="resolu">Résolu</option>
                                <option value="rejete">Rejeté</option>
                                <option value="archive">Archivé</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Commentaire (optionnel)</label>
                            <textarea class="form-control" name="comment" rows="3" placeholder="Ajouter un commentaire..."></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" form="statusForm" class="btn btn-primary fw-bold">Mettre à jour</button>
                </div>
            </div>
        </div>
    </div>

    <?php include '../../includes/footer.php'; ?>

    <script src="../../js/admin-animations.js"></script>
    <script>
        // Remplir le modal avec les données de la ligne
        document.getElementById('statusModal').addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const reclamationId = button.getAttribute('data-reclamation-id');
            const currentStatus = button.getAttribute('data-current-status');
            const objet = button.getAttribute('data-objet');

            document.getElementById('modalReclamationId').value = reclamationId;
            document.getElementById('modalNewStatus').value = currentStatus;
            document.getElementById('modalObjet').textContent = objet;
        });
    </script>
</body>
</html>
