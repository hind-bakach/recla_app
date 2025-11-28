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

<body class="bg-light">
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php"><i class="bi bi-speedometer2 me-2"></i>Espace Réclamant</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-center">
                    <li class="nav-item me-3">
                        <span class="text-white">Bonjour, <strong><?php echo htmlspecialchars($_SESSION['user_name']); ?></strong></span>
                    </li>
                    <li class="nav-item">
                        <a class="btn btn-light btn-sm fw-bold text-primary" href="../../frontend/deconnexion.php">
                            <i class="bi bi-box-arrow-right me-1"></i> Déconnexion
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container py-5">
        <!-- En-tête -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="fw-bold text-dark">Tableau de Bord</h2>
            <a href="soumission.php" class="btn btn-warning fw-bold shadow-sm">
                <i class="bi bi-plus-circle-fill me-2"></i>Nouvelle Réclamation
            </a>
        </div>

        <!-- Cartes Statistiques -->
        <div class="row g-4 mb-5">
            <div class="col-md-3">
                <div class="card border-0 shadow-sm rounded-4 bg-white h-100">
                    <div class="card-body p-4 text-center">
                        <div class="display-4 fw-bold text-primary mb-2"><?php echo $stats['total']; ?></div>
                        <div class="text-muted fw-bold text-uppercase small">Total Réclamations</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm rounded-4 bg-white h-100">
                    <div class="card-body p-4 text-center">
                        <div class="display-4 fw-bold text-warning mb-2"><?php echo $stats['en_cours']; ?></div>
                        <div class="text-muted fw-bold text-uppercase small">En Cours</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm rounded-4 bg-white h-100">
                    <div class="card-body p-4 text-center">
                        <div class="display-4 fw-bold text-success mb-2"><?php echo $stats['traite']; ?></div>
                        <div class="text-muted fw-bold text-uppercase small">Traitées</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm rounded-4 bg-white h-100">
                    <div class="card-body p-4 text-center">
                        <div class="display-4 fw-bold text-secondary mb-2"><?php echo $stats['ferme']; ?></div>
                        <div class="text-muted fw-bold text-uppercase small">Fermées</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Liste des réclamations -->
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
            <div class="card-header bg-white p-4 border-bottom">
                <h5 class="mb-0 fw-bold"><i class="bi bi-list-ul me-2 text-primary"></i>Historique de vos réclamations</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light text-muted text-uppercase small">
                            <tr>
                                <th class="ps-4 py-3">ID</th>
                                <th class="py-3">Sujet</th>
                                <th class="py-3">Catégorie</th>
                                <th class="py-3">Date</th>
                                <th class="py-3">Statut</th>
                                <th class="py-3 text-end pe-4">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($reclamations) > 0): ?>
                                <?php foreach ($reclamations as $reclamation): ?>
                                    <tr>
                                        <td class="ps-4 fw-bold">#<?php echo $reclamation['id']; ?></td>
                                        <td><?php echo htmlspecialchars($reclamation['sujet']); ?></td>
                                        <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($reclamation['categorie_nom'] ?? '—'); ?></span></td>
                                        <td><?php echo format_date($reclamation['created_at']); ?></td>
                                        <td>
                                            <span class="badge rounded-pill <?php echo get_status_badge($reclamation['statut']); ?>">
                                                <?php echo get_status_label($reclamation['statut']); ?>
                                            </span>
                                        </td>
                                        <td class="text-end pe-4">
                                            <a href="details.php?id=<?php echo $reclamation['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="bi bi-eye-fill"></i> Détails
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center py-5 text-muted">
                                        <i class="bi bi-inbox fs-1 d-block mb-3"></i>
                                        Aucune réclamation trouvée.
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
