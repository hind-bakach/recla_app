<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

require_role('administrateur');

$pdo = get_pdo();
$error = '';
$success = '';

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
$categories = $pdo->query("SELECT * FROM categories ORDER BY nom_categorie ASC")->fetchAll();

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
                </div>
            </div>

            <!-- Contenu Principal -->
            <div class="col-lg-10">
                
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
                                <h5 class="mb-0 fw-bold"><i class="bi bi-list-ul me-2 text-primary"></i>Liste des catégories</h5>
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
                                                <th class="py-3 text-end pe-4">Action</th>
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
