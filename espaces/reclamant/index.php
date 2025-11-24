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
    FROM claims WHERE user_id = ?");
$stmt->execute([$user_id]);
$stats = $stmt->fetch();

// Récupérer les dernières réclamations
$stmt = $pdo->prepare("SELECT c.*, cat.nom as categorie_nom 
    FROM claims c 
    JOIN categories cat ON c.category_id = cat.id 
    WHERE c.user_id = ? 
    ORDER BY c.created_at DESC");
$stmt->execute([$user_id]);
$claims = $stmt->fetchAll();

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
                            <?php if (count($claims) > 0): ?>
                                <?php foreach ($claims as $claim): ?>
                                    <tr>
                                        <td class="ps-4 fw-bold">#<?php echo $claim['id']; ?></td>
                                        <td><?php echo htmlspecialchars($claim['sujet']); ?></td>
                                        <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($claim['categorie_nom']); ?></span></td>
                                        <td><?php echo format_date($claim['created_at']); ?></td>
                                        <td>
                                            <span class="badge rounded-pill <?php echo get_status_badge($claim['statut']); ?>">
                                                <?php echo get_status_label($claim['statut']); ?>
                                            </span>
                                        </td>
                                        <td class="text-end pe-4">
                                            <a href="details.php?id=<?php echo $claim['id']; ?>" class="btn btn-sm btn-outline-primary">
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
