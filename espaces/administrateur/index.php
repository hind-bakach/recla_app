<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

require_role('administrateur');

$pdo = get_pdo();

// Statistiques Globales
$stats = [];
$stats['total_claims'] = $pdo->query("SELECT COUNT(*) FROM claims")->fetchColumn();
$stats['pending_claims'] = $pdo->query("SELECT COUNT(*) FROM claims WHERE statut = 'en_cours'")->fetchColumn();
$stats['users_count'] = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$stats['categories_count'] = $pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();

// Dernières réclamations
$stmt = $pdo->query("SELECT c.*, u.nom as user_name, cat.nom as category_nom 
    FROM claims c 
    JOIN users u ON c.user_id = u.id 
    JOIN categories cat ON c.category_id = cat.id 
    ORDER BY c.created_at DESC LIMIT 5");
$latest_claims = $stmt->fetchAll();

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
                                        <h6 class="text-uppercase mb-2 opacity-75">Utilisateurs</h6>
                                        <h2 class="display-6 fw-bold mb-0"><?php echo $stats['users_count']; ?></h2>
                                    </div>
                                    <i class="bi bi-people-fill fs-1 opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm rounded-4 bg-info text-white h-100">
                            <div class="card-body p-4">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-uppercase mb-2 opacity-75">Catégories</h6>
                                        <h2 class="display-6 fw-bold mb-0"><?php echo $stats['categories_count']; ?></h2>
                                    </div>
                                    <i class="bi bi-tags-fill fs-1 opacity-50"></i>
                                </div>
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
                                    <?php foreach ($latest_claims as $claim): ?>
                                        <tr>
                                            <td class="ps-4 fw-bold">#<?php echo $claim['id']; ?></td>
                                            <td><?php echo htmlspecialchars($claim['user_name']); ?></td>
                                            <td><?php echo htmlspecialchars($claim['sujet']); ?></td>
                                            <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($claim['category_nom']); ?></span></td>
                                            <td><?php echo format_date($claim['created_at']); ?></td>
                                            <td>
                                                <span class="badge rounded-pill <?php echo get_status_badge($claim['statut']); ?>">
                                                    <?php echo get_status_label($claim['statut']); ?>
                                                </span>
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

    <?php include '../../includes/footer.php'; ?>
</body>
</html>
