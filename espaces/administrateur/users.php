<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

require_role('administrateur');

$pdo = get_pdo();
$error = '';
$success = '';

// Modification d'un utilisateur
if (isset($_POST['action']) && $_POST['action'] === 'edit' && isset($_POST['edit_id'])) {
    $edit_id = (int)$_POST['edit_id'];
    $nom = sanitize_input($_POST['edit_nom'] ?? '');
    $email = sanitize_input($_POST['edit_email'] ?? '');
    $role = $_POST['edit_role'] ?? '';
    $new_password = $_POST['edit_password'] ?? '';

    if (empty($nom) || empty($email) || empty($role)) {
        $error = "Tous les champs obligatoires doivent être remplis.";
    } else {
        try {
            // Vérifier que l'email n'est pas déjà utilisé par un autre utilisateur
            $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
            $stmt->execute([$email, $edit_id]);
            if ($stmt->fetch()) {
                $error = "Cet email est déjà utilisé par un autre utilisateur.";
            } else {
                // Détecter le nom correct de la colonne password
                $passwordCol = 'password';
                $colCheck = $pdo->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA='".DB_NAME."' AND TABLE_NAME='users'")->fetchAll(PDO::FETCH_COLUMN);
                if (in_array('mot_de_passe', $colCheck)) {
                    $passwordCol = 'mot_de_passe';
                } elseif (in_array('pwd', $colCheck)) {
                    $passwordCol = 'pwd';
                }

                // Construire la requête UPDATE
                $updateFields = ['nom = ?', 'email = ?', 'role = ?'];
                $updateValues = [$nom, $email, $role];

                // Si un nouveau mot de passe est fourni, l'inclure
                if (!empty($new_password)) {
                    $updateFields[] = $passwordCol . ' = ?';
                    $updateValues[] = password_hash($new_password, PASSWORD_DEFAULT);
                }

                $updateValues[] = $edit_id;
                $sql = "UPDATE users SET " . implode(', ', $updateFields) . " WHERE user_id = ?";
                $stmt = $pdo->prepare($sql);
                
                if ($stmt->execute($updateValues)) {
                    $success = "Utilisateur modifié avec succès.";
                } else {
                    $error = "Erreur lors de la modification.";
                }
            }
        } catch (PDOException $e) {
            $error = "Erreur lors de la modification : " . $e->getMessage();
        }
    }
}

// Suppression d'un utilisateur
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id_to_delete = $_GET['delete'];
    if ($id_to_delete != $_SESSION['user_id']) { // Empêcher de se supprimer soi-même
        try {
            // Commencer une transaction pour garantir l'intégrité
            $pdo->beginTransaction();
            
            // 1. Supprimer les commentaires de l'utilisateur
            $stmt = $pdo->prepare("DELETE FROM commentaires WHERE user_id = ?");
            $stmt->execute([$id_to_delete]);
            
            // 2. Supprimer les réclamations de l'utilisateur
            $stmt = $pdo->prepare("DELETE FROM reclamations WHERE user_id = ?");
            $stmt->execute([$id_to_delete]);
            
            // 3. Supprimer l'utilisateur
            $stmt = $pdo->prepare("DELETE FROM users WHERE user_id = ?");
            $stmt->execute([$id_to_delete]);
            
            // Valider la transaction
            $pdo->commit();
            $success = "Utilisateur et ses données associées supprimés avec succès.";
        } catch (PDOException $e) {
            // Annuler la transaction en cas d'erreur
            $pdo->rollBack();
            $error = "Erreur lors de la suppression : " . $e->getMessage();
        }
    } else {
        $error = "Vous ne pouvez pas supprimer votre propre compte.";
    }
}

// Ajout d'un utilisateur (Gestionnaire ou Admin)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (!isset($_POST['action']) || $_POST['action'] !== 'edit')) {
    $nom = sanitize_input($_POST['nom'] ?? '');
    $email = sanitize_input($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? '';

    if (empty($nom) || empty($email) || empty($password) || empty($role)) {
        $error = "Tous les champs sont obligatoires.";
    } else {
        // Vérifier email
        $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = "Cet email est déjà utilisé.";
        } else {
            // Détecter le nom correct de la colonne password
            $passwordCol = 'password';
            $colCheck = $pdo->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA='".DB_NAME."' AND TABLE_NAME='users'")->fetchAll(PDO::FETCH_COLUMN);
            if (in_array('mot_de_passe', $colCheck)) {
                $passwordCol = 'mot_de_passe';
            } elseif (in_array('pwd', $colCheck)) {
                $passwordCol = 'pwd';
            }
            
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (nom, email, " . $passwordCol . ", role) VALUES (?, ?, ?, ?)");
            if ($stmt->execute([$nom, $email, $hashed_password, $role])) {
                $success = "Utilisateur ajouté avec succès.";
            } else {
                $error = "Erreur lors de l'ajout.";
            }
        }
    }
}

// Récupérer tous les utilisateurs — détecter la colonne de date et d'ID correctes
$dateCol = 'user_id'; // Fallback par défaut (ordre par ID)
$colCheck = $pdo->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA='".DB_NAME."' AND TABLE_NAME='users'")->fetchAll(PDO::FETCH_COLUMN);
if (in_array('created_at', $colCheck)) {
    $dateCol = 'created_at';
} elseif (in_array('date_creation', $colCheck)) {
    $dateCol = 'date_creation';
} elseif (in_array('created_date', $colCheck)) {
    $dateCol = 'created_date';
}
$search = sanitize_input($_GET['search'] ?? '');
$roleFilter = sanitize_input($_GET['role_filter'] ?? '');

$query = "SELECT * FROM users WHERE role != 'administrateur'";
$params = [];
if ($roleFilter !== '' && in_array($roleFilter, ['reclamant','gestionnaire','administrateur'])) {
    $query .= " AND role = ?";
    $params[] = $roleFilter;
}
if ($search !== '') {
    // Recherche sur nom ou email
    $query .= " AND (nom LIKE ? OR email LIKE ?)";
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}
$query .= " ORDER BY " . $dateCol . " DESC";
$stmtUsers = $pdo->prepare($query);
$stmtUsers->execute($params);
$users = $stmtUsers->fetchAll();

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

                <div class="row g-3">
                    <!-- Formulaire Ajout -->
                    <div class="col-md-4">
                        <div class="card shadow-sm border-0 rounded-3">
                            <div class="card-header bg-white p-3 border-0" style="background-color: #bfc9cf8c !important;">
                                <h6 class="mb-0 fw-bold text-uppercase" style="font-size: 0.90rem; letter-spacing: 0.5px;"><i class="bi bi-person-plus-fill me-2 text-primary"></i>Ajouter un utilisateur</h6>
                            </div>
                            <div class="card-body p-4">
                                <form method="POST" action="users.php">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold small"><i class="bi bi-person-fill text-primary me-1"></i>Nom complet</label>
                                        <input type="text" class="form-control" name="nom" placeholder="Entrez le nom complet" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label fw-bold small"><i class="bi bi-envelope-fill text-primary me-1"></i>Email</label>
                                        <input type="email" class="form-control" name="email" placeholder="exemple@email.com" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label fw-bold small"><i class="bi bi-lock-fill text-primary me-1"></i>Mot de passe</label>
                                        <input type="password" class="form-control" name="password" placeholder="Mot de passe sécurisé" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label fw-bold small"><i class="bi bi-shield-fill-check text-primary me-1"></i>Rôle</label>
                                        <select class="form-select" name="role" required>
                                            <option value="reclamant">👤 Réclamant</option>
                                            <option value="gestionnaire">💼 Gestionnaire</option>
                                            <option value="administrateur">🔑 Administrateur</option>
                                        </select>
                                    </div>
                                    <div class="d-grid">
                                        <button type="submit" class="btn btn-primary fw-bold"><i class="bi bi-plus-circle me-2"></i>Ajouter l'utilisateur</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Liste Utilisateurs -->
                    <div class="col-md-8">
                        <div class="card shadow-sm border-0 rounded-3">
                            <div class="card-header bg-white p-3 border-0" style="background-color: #bfc9cf8c !important;">
                                <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2">
                                    <h6 class="mb-0 fw-bold text-uppercase" style="font-size: 0.90rem; letter-spacing: 0.5px;"><i class="bi bi-list-ul me-2 text-primary"></i>Liste des utilisateurs</h6>
                                    <form class="d-flex gap-2" method="GET" action="users.php">
                                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" class="form-control form-control-sm" placeholder="Recherche nom/email">
                                        <select name="role_filter" class="form-select form-select-sm" style="max-width:140px;">
                                            <option value="">Rôle</option>
                                            <option value="reclamant" <?php if($roleFilter==='reclamant') echo 'selected'; ?>>Réclamant</option>
                                            <option value="gestionnaire" <?php if($roleFilter==='gestionnaire') echo 'selected'; ?>>Gestionnaire</option>
                                            <option value="administrateur" <?php if($roleFilter==='administrateur') echo 'selected'; ?>>Admin</option>
                                        </select>
                                        <button type="submit" class="btn btn-sm btn-outline-primary"><i class="bi bi-funnel"></i></button>
                                        <a href="users.php" class="btn btn-sm btn-outline-secondary" title="Réinitialiser"><i class="bi bi-x-circle"></i></a>
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
                                                <th class="py-3">Email</th>
                                                <th class="py-3">Rôle</th>
                                                <th class="py-3 text-end pe-4">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($users as $user): ?>
                                                <tr>
                                                    <td class="ps-4 fw-bold">#<?php echo htmlspecialchars($user['user_id'] ?? $user['id'] ?? ''); ?></td>
                                                    <td><?php echo htmlspecialchars($user['nom'] ?? ''); ?></td>
                                                    <td><?php echo htmlspecialchars($user['email'] ?? ''); ?></td>
                                                    <td>
                                                        <?php 
                                                        $badge_color = 'bg-secondary';
                                                        if ($user['role'] == 'administrateur') $badge_color = 'bg-danger';
                                                        if ($user['role'] == 'gestionnaire') $badge_color = 'bg-info text-dark';
                                                        if ($user['role'] == 'reclamant') $badge_color = 'bg-success';
                                                        ?>
                                                        <span class="badge <?php echo $badge_color; ?>"><?php echo ucfirst($user['role'] ?? ''); ?></span>
                                                    </td>
                                                    <td class="text-end pe-4">
                                                        <?php $uid = $user['user_id'] ?? $user['id'] ?? null; if ($uid && $uid != $_SESSION['user_id']): ?>
                                                            <button type="button" class="btn btn-sm btn-outline-primary me-2" data-bs-toggle="modal" data-bs-target="#editUserModal" onclick="loadUserData(<?php echo htmlspecialchars($uid); ?>, <?php echo htmlspecialchars(json_encode($user)); ?>)">
                                                                <i class="bi bi-pencil-square"></i>
                                                            </button>
                                                            <a href="users.php?delete=<?php echo htmlspecialchars($uid); ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Êtes-vous sûr de vouloir supprimer cet utilisateur ?');">
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
    <?php include '../../includes/admin_styles.php'; ?>
</body>
</html>

<!-- Modal d'édition d'utilisateur -->
<div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold" id="editUserModalLabel">
                    <i class="bi bi-pencil-square me-2"></i>Modifier un utilisateur
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="editUserForm" method="POST" action="users.php">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="edit_id" id="edit_id" value="">
                    
                    <div class="mb-3">
                        <label for="edit_nom" class="form-label fw-bold">Nom complet</label>
                        <input type="text" class="form-control" id="edit_nom" name="edit_nom" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_email" class="form-label fw-bold">Email</label>
                        <input type="email" class="form-control" id="edit_email" name="edit_email" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_role" class="form-label fw-bold">Rôle</label>
                        <select class="form-select" id="edit_role" name="edit_role" required>
                            <option value="reclamant">Réclamant</option>
                            <option value="gestionnaire">Gestionnaire</option>
                            <option value="administrateur">Administrateur</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_password" class="form-label fw-bold">Nouveau mot de passe <span class="text-muted">(optionnel)</span></label>
                        <input type="password" class="form-control" id="edit_password" name="edit_password" placeholder="Laissez vide pour conserver le mot de passe actuel">
                        <div class="form-text">Si vous souhaitez changer le mot de passe, entrez un nouveau. Sinon, laissez ce champ vide.</div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="submit" form="editUserForm" class="btn btn-primary fw-bold">
                    <i class="bi bi-check-circle me-1"></i>Enregistrer les modifications
                </button>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

<script src="../../js/admin-animations.js"></script>
<script>
    function loadUserData(userId, userData) {
        // userData est un JSON object passed inline
        document.getElementById('edit_id').value = userId;
        document.getElementById('edit_nom').value = userData.nom || '';
        document.getElementById('edit_email').value = userData.email || '';
        document.getElementById('edit_role').value = userData.role || 'reclamant';
        document.getElementById('edit_password').value = ''; // Always empty for security
    }
</script>
