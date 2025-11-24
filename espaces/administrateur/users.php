<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

require_role('administrateur');

$pdo = get_pdo();
$error = '';
$success = '';

// Suppression d'un utilisateur
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id_to_delete = $_GET['delete'];
    if ($id_to_delete != $_SESSION['user_id']) { // Empêcher de se supprimer soi-même
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        if ($stmt->execute([$id_to_delete])) {
            $success = "Utilisateur supprimé avec succès.";
        } else {
            $error = "Erreur lors de la suppression.";
        }
    } else {
        $error = "Vous ne pouvez pas supprimer votre propre compte.";
    }
}

// Ajout d'un utilisateur (Gestionnaire ou Admin)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = sanitize_input($_POST['nom']);
    $email = sanitize_input($_POST['email']);
    $password = $_POST['password'];
    $role = $_POST['role'];

    if (empty($nom) || empty($email) || empty($password) || empty($role)) {
        $error = "Tous les champs sont obligatoires.";
    } else {
        // Vérifier email
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = "Cet email est déjà utilisé.";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (nom, email, password, role) VALUES (?, ?, ?, ?)");
            if ($stmt->execute([$nom, $email, $hashed_password, $role])) {
                $success = "Utilisateur ajouté avec succès.";
            } else {
                $error = "Erreur lors de l'ajout.";
            }
        }
    }
}

// Récupérer tous les utilisateurs
$users = $pdo->query("SELECT * FROM users ORDER BY created_at DESC")->fetchAll();

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
                    <a href="users.php" class="list-group-item list-group-item-action active fw-bold" aria-current="true">
                        <i class="bi bi-people-fill me-2"></i>Utilisateurs
                    </a>
                    <a href="categories.php" class="list-group-item list-group-item-action fw-bold">
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
                                <h5 class="mb-0 fw-bold"><i class="bi bi-person-plus-fill me-2 text-primary"></i>Ajouter un utilisateur</h5>
                            </div>
                            <div class="card-body p-4">
                                <form method="POST" action="users.php">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold small">Nom complet</label>
                                        <input type="text" class="form-control" name="nom" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label fw-bold small">Email</label>
                                        <input type="email" class="form-control" name="email" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label fw-bold small">Mot de passe</label>
                                        <input type="password" class="form-control" name="password" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label fw-bold small">Rôle</label>
                                        <select class="form-select" name="role" required>
                                            <option value="reclamant">Réclamant</option>
                                            <option value="gestionnaire">Gestionnaire</option>
                                            <option value="administrateur">Administrateur</option>
                                        </select>
                                    </div>
                                    <div class="d-grid">
                                        <button type="submit" class="btn btn-primary fw-bold">Ajouter</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Liste Utilisateurs -->
                    <div class="col-md-8">
                        <div class="card shadow-sm border-0 rounded-4">
                            <div class="card-header bg-white p-3 border-bottom">
                                <h5 class="mb-0 fw-bold"><i class="bi bi-list-ul me-2 text-primary"></i>Liste des utilisateurs</h5>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0">
                                        <thead class="bg-light text-muted text-uppercase small">
                                            <tr>
                                                <th class="ps-4 py-3">ID</th>
                                                <th class="py-3">Nom</th>
                                                <th class="py-3">Email</th>
                                                <th class="py-3">Rôle</th>
                                                <th class="py-3 text-end pe-4">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($users as $user): ?>
                                                <tr>
                                                    <td class="ps-4 fw-bold">#<?php echo $user['id']; ?></td>
                                                    <td><?php echo htmlspecialchars($user['nom']); ?></td>
                                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                                    <td>
                                                        <?php 
                                                        $badge_color = 'bg-secondary';
                                                        if ($user['role'] == 'administrateur') $badge_color = 'bg-danger';
                                                        if ($user['role'] == 'gestionnaire') $badge_color = 'bg-info text-dark';
                                                        if ($user['role'] == 'reclamant') $badge_color = 'bg-success';
                                                        ?>
                                                        <span class="badge <?php echo $badge_color; ?>"><?php echo ucfirst($user['role']); ?></span>
                                                    </td>
                                                    <td class="text-end pe-4">
                                                        <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                                            <a href="users.php?delete=<?php echo $user['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Êtes-vous sûr de vouloir supprimer cet utilisateur ?');">
                                                                <i class="bi bi-trash-fill"></i>
                                                            </a>
                                                        <?php endif; ?>
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
