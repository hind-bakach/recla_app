<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

require_role('administrateur');

$pdo = get_pdo();
$error = '';
$success = '';

// Modification d'une catégorie
if (isset($_POST['action']) && $_POST['action'] === 'edit' && isset($_POST['edit_id'])) {
    $edit_id = (int)($_POST['edit_id'] ?? 0);
    $nom_categorie = sanitize_input($_POST['edit_nom_categorie'] ?? '');
    $responsable = sanitize_input($_POST['edit_responsable'] ?? '');
    $delai = is_numeric($_POST['edit_delai_traitement_jours'] ?? null) ? (int)$_POST['edit_delai_traitement_jours'] : null;
    $priorite = sanitize_input($_POST['edit_priorite_defaut'] ?? '');

    if (empty($nom_categorie)) {
        $error = "Le nom de la catégorie est obligatoire.";
    } else {
        try {
            // Detect ID column name
            $colCheck = $pdo->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA='".DB_NAME."' AND TABLE_NAME='categories'")->fetchAll(PDO::FETCH_COLUMN);
            $idCol = in_array('categorie_id', $colCheck) ? 'categorie_id' : (in_array('id', $colCheck) ? 'id' : $colCheck[0] ?? 'id');

            // Build dynamic update based on existing columns
            $fields = [];
            $values = [];
            if (in_array('nom_categorie', $colCheck)) { $fields[] = 'nom_categorie = ?'; $values[] = $nom_categorie; }
            if (in_array('responsable', $colCheck)) { $fields[] = 'responsable = ?'; $values[] = $responsable; }
            if (in_array('delai_traitement_jours', $colCheck)) { $fields[] = 'delai_traitement_jours = ?'; $values[] = $delai; }
            if (in_array('priorite_defaut', $colCheck)) { $fields[] = 'priorite_defaut = ?'; $values[] = $priorite; }

            if (count($fields) === 0) {
                $error = "Aucun champ modifiable détecté dans la table categories.";
            } else {
                $values[] = $edit_id;
                $sql = "UPDATE categories SET " . implode(', ', $fields) . " WHERE " . $idCol . " = ?";
                $stmt = $pdo->prepare($sql);
                if ($stmt->execute($values)) {
                    $success = "Catégorie modifiée avec succès.";
                } else {
                    $error = "Erreur lors de la modification.";
                }
            }
        } catch (PDOException $e) {
            $error = "Erreur lors de la modification : " . $e->getMessage();
        }
    }
}

// Suppression d'une catégorie
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id_to_delete = $_GET['delete'];
    // Détecter le nom de la colonne de catégorie dans reclamations
    $colCheckRecla = $pdo->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA='".DB_NAME."' AND TABLE_NAME='reclamations'")->fetchAll(PDO::FETCH_COLUMN);
    $catCol = in_array('categorie_id', $colCheckRecla) ? 'categorie_id' : (in_array('category_id', $colCheckRecla) ? 'category_id' : 'categorie_id');
    
    // Vérifier si la catégorie est utilisée
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM reclamations WHERE " . $catCol . " = ?");
    $stmt->execute([$id_to_delete]);
    if ($stmt->fetchColumn() > 0) {
        $error = "Impossible de supprimer cette catégorie car elle est liée à des réclamations existantes.";
    } else {
        // Détecter le nom de la colonne ID
        $colCheck = $pdo->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA='".DB_NAME."' AND TABLE_NAME='categories'")->fetchAll(PDO::FETCH_COLUMN);
        $idCol = in_array('categorie_id', $colCheck) ? 'categorie_id' : (in_array('id', $colCheck) ? 'id' : $colCheck[0] ?? 'id');
        
        $stmt = $pdo->prepare("DELETE FROM categories WHERE " . $idCol . " = ?");
        if ($stmt->execute([$id_to_delete])) {
            $success = "Catégorie supprimée avec succès.";
        } else {
            $error = "Erreur lors de la suppression.";
        }
    }
}

// Ajout d'une catégorie (adapté au nouveau schéma)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom_categorie = sanitize_input($_POST['nom_categorie'] ?? '');
    $responsable = sanitize_input($_POST['responsable'] ?? '');
    $delai = is_numeric($_POST['delai_traitement_jours'] ?? null) ? (int)$_POST['delai_traitement_jours'] : null;
    $priorite = sanitize_input($_POST['priorite_defaut'] ?? '');

    if (empty($nom_categorie)) {
        $error = "Le nom de la catégorie est obligatoire.";
    } else {
        $stmt = $pdo->prepare("INSERT INTO categories (nom_categorie, responsable, delai_traitement_jours, priorite_defaut) VALUES (?, ?, ?, ?)");
        if ($stmt->execute([$nom_categorie, $responsable, $delai, $priorite])) {
            $success = "Catégorie ajoutée avec succès.";
        } else {
            $error = "Erreur lors de l'ajout.";
        }
    }
}

// Récupérer toutes les catégories (nouveau schéma)
$catSearch = sanitize_input($_GET['search_cat'] ?? '');
$priorityFilter = sanitize_input($_GET['priority_filter'] ?? '');
$baseCatQuery = "SELECT * FROM categories WHERE 1=1";
$catParams = [];
if ($catSearch !== '') {
    $baseCatQuery .= " AND (nom_categorie LIKE ? OR responsable LIKE ?)";
    $catParams[] = '%' . $catSearch . '%';
    $catParams[] = '%' . $catSearch . '%';
}
if ($priorityFilter !== '' && in_array($priorityFilter, ['Basse','Normale','Haute'])) {
    $baseCatQuery .= " AND priorite_defaut = ?";
    $catParams[] = $priorityFilter;
}
$baseCatQuery .= " ORDER BY nom_categorie ASC";
$stmtCat = $pdo->prepare($baseCatQuery);
$stmtCat->execute($catParams);
$categories = $stmtCat->fetchAll();

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
                    <a href="index.php" class="list-group-item list-group-item-action fw-bold">
                        <i class="bi bi-speedometer2 me-2"></i>Tableau de Bord
                    </a>
                    <a href="users.php" class="list-group-item list-group-item-action fw-bold">
                        <i class="bi bi-people-fill me-2"></i>Utilisateurs
                    </a>
                    <a href="categories.php" class="list-group-item list-group-item-action active fw-bold" aria-current="true">
                        <i class="bi bi-tags-fill me-2"></i>Catégories
                    </a>
                    <a href="reclamations.php" class="list-group-item list-group-item-action fw-bold">
                        <i class="bi bi-inbox-fill me-2"></i>Réclamations
                    </a>
                    
                    
                </div>
            </div>

            <!-- Contenu Principal -->
            <div class="col-lg-10">
                
                <?php if ($error && empty($success)): ?>
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

                <div class="row g-4">
                    <!-- Formulaire Ajout -->
                    <div class="col-md-4">
                        <div class="card shadow-sm border-0 rounded-4">
                            <div class="card-header bg-white p-3 border-bottom">
                                <h5 class="mb-0 fw-bold"><i class="bi bi-plus-circle-fill me-2 text-primary"></i>Ajouter une catégorie</h5>
                            </div>
                            <div class="card-body p-4">
                                <form method="POST" action="categories.php">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold small">Nom de la catégorie</label>
                                        <input type="text" class="form-control" name="nom_categorie" placeholder="Ex: Technique" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label fw-bold small">Responsable</label>
                                        <input type="text" class="form-control" name="responsable" placeholder="Agent ou service responsable">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label fw-bold small">Délai de traitement (jours)</label>
                                        <input type="number" class="form-control" name="delai_traitement_jours" min="0" placeholder="Ex: 5">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label fw-bold small">Priorité par défaut</label>
                                        <select class="form-select" name="priorite_defaut">
                                            <option value="Basse">Basse</option>
                                            <option value="Normale" selected>Normale</option>
                                            <option value="Haute">Haute</option>
                                        </select>
                                    </div>
                                    <div class="d-grid">
                                        <button type="submit" class="btn btn-primary fw-bold">Ajouter</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Liste Catégories -->
                    <div class="col-md-8">
                        <div class="card shadow-sm border-0 rounded-4">
                            <div class="card-header bg-white p-3 border-bottom">
                                <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2">
                                    <h5 class="mb-0 fw-bold"><i class="bi bi-list-ul me-2 text-primary"></i>Liste des catégories</h5>
                                    <form class="d-flex gap-2" method="GET" action="categories.php">
                                        <input type="text" name="search_cat" value="<?php echo htmlspecialchars($catSearch); ?>" class="form-control form-control-sm" placeholder="Recherche nom/responsable">
                                        <select name="priority_filter" class="form-select form-select-sm" style="max-width:140px;">
                                            <option value="">Priorité</option>
                                            <option value="Basse" <?php if($priorityFilter==='Basse') echo 'selected'; ?>>Basse</option>
                                            <option value="Normale" <?php if($priorityFilter==='Normale') echo 'selected'; ?>>Normale</option>
                                            <option value="Haute" <?php if($priorityFilter==='Haute') echo 'selected'; ?>>Haute</option>
                                        </select>
                                        <button type="submit" class="btn btn-sm btn-outline-primary"><i class="bi bi-funnel"></i></button>
                                        <a href="categories.php" class="btn btn-sm btn-outline-secondary" title="Réinitialiser"><i class="bi bi-x-circle"></i></a>
                                    </form>
                                </div>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0">
                                        <thead class="bg-light text-muted text-uppercase small">
                                            <tr>
                                                <th class="ps-4 py-3">ID</th>
                                                <th class="py-3">Nom</th>
                                                <th class="py-3">Responsable</th>
                                                <th class="py-3">Délai (jours)</th>
                                                <th class="py-3">Priorité</th>
                                                <th class="py-3 text-end pe-4">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($categories as $cat): ?>
                                                <tr>
                                                    <td class="ps-4 fw-bold">#<?php echo htmlspecialchars($cat['categorie_id'] ?? $cat['id'] ?? ''); ?></td>
                                                    <td class="fw-bold text-primary"><?php echo htmlspecialchars($cat['nom_categorie'] ?? $cat['name'] ?? $cat['titre'] ?? ''); ?></td>
                                                    <td><?php echo htmlspecialchars($cat['responsable'] ?? ''); ?></td>
                                                    <td><?php echo htmlspecialchars($cat['delai_traitement_jours'] ?? ''); ?></td>
                                                    <td>
                                                        <?php 
                                                            $priorityColor = '#ffc107'; // jaune par défaut
                                                            if (($cat['priorite_defaut'] ?? '') === 'Haute') {
                                                                $priorityColor = '#dc3545'; // rouge
                                                            } elseif (($cat['priorite_defaut'] ?? '') === 'Normale') {
                                                                $priorityColor = '#fd7e14'; // orange
                                                            } elseif (($cat['priorite_defaut'] ?? '') === 'Basse') {
                                                                $priorityColor = '#ffc107'; // jaune
                                                            }
                                                        ?>
                                                        <span class="badge" style="background-color:<?php echo $priorityColor; ?>"><?php echo htmlspecialchars($cat['priorite_defaut'] ?? ''); ?></span>
                                                    </td>
                                                    <td class="text-end pe-4">
                                                        <button type="button" class="btn btn-sm btn-outline-primary me-2" data-bs-toggle="modal" data-bs-target="#editCategoryModal"
                                                            onclick="loadCategoryData('<?php echo htmlspecialchars($cat['categorie_id'] ?? $cat['id'] ?? ''); ?>', '<?php echo htmlspecialchars($cat['nom_categorie'] ?? $cat['name'] ?? $cat['titre'] ?? ''); ?>', '<?php echo htmlspecialchars($cat['responsable'] ?? ''); ?>', '<?php echo htmlspecialchars($cat['delai_traitement_jours'] ?? ''); ?>', '<?php echo htmlspecialchars($cat['priorite_defaut'] ?? ''); ?>')">
                                                            <i class="bi bi-pencil-square"></i>
                                                        </button>
                                                        <a href="categories.php?delete=<?php echo htmlspecialchars($cat['categorie_id'] ?? $cat['id'] ?? ''); ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Êtes-vous sûr de vouloir supprimer cette catégorie ?');">
                                                            <i class="bi bi-trash-fill"></i>
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include '../../includes/footer.php'; ?>
</body>
</html>

<!-- Modal d'édition de catégorie -->
<div class="modal fade" id="editCategoryModal" tabindex="-1" aria-labelledby="editCategoryModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="editCategoryModalLabel"><i class="bi bi-pencil-square me-2"></i>Modifier la catégorie</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="categories.php">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="edit_id" id="edit_id">

                    <div class="mb-3">
                        <label class="form-label fw-bold small">Nom de la catégorie</label>
                        <input type="text" class="form-control" name="edit_nom_categorie" id="edit_nom_categorie" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Responsable</label>
                        <input type="text" class="form-control" name="edit_responsable" id="edit_responsable">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Délai de traitement (jours)</label>
                        <input type="number" class="form-control" name="edit_delai_traitement_jours" id="edit_delai_traitement_jours" min="0">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Priorité par défaut</label>
                        <select class="form-select" name="edit_priorite_defaut" id="edit_priorite_defaut">
                            <option value="Basse">Basse</option>
                            <option value="Normale">Normale</option>
                            <option value="Haute">Haute</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function loadCategoryData(id, nom, responsable, delai, priorite) {
    document.getElementById('edit_id').value = id || '';
    document.getElementById('edit_nom_categorie').value = nom || '';
    document.getElementById('edit_responsable').value = responsable || '';
    document.getElementById('edit_delai_traitement_jours').value = delai || '';
    document.getElementById('edit_priorite_defaut').value = priorite || 'Normale';
}
</script>
