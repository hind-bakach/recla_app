<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/lang.php';

require_role('reclamant');

$user_id = $_SESSION['user_id'];
$pdo = get_pdo();

// Récupérer les informations de l'utilisateur
$stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Vérifier si les colonnes prenom et nom existent
if (!isset($user['prenom'])) {
    $user['prenom'] = '';
}
if (!isset($user['nom'])) {
    $user['nom'] = '';
}

// Détecter le nom de la colonne password
$password_col = 'password';
if (isset($user['mot_de_passe'])) {
    $password_col = 'mot_de_passe';
} elseif (isset($user['pwd'])) {
    $password_col = 'pwd';
}

$success_message = '';
$error_message = '';

// Gérer le message de succès après redirection
if (isset($_GET['success']) && $_GET['success'] == '1') {
    $success_message = t('profile_updated');
}

// DEBUG: Afficher si le formulaire est soumis
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    error_log("Formulaire soumis - Nom: " . $_POST['nom'] . ", Prenom: " . $_POST['prenom']);
}

// Traitement du formulaire de mise à jour du profil
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        $nom = trim($_POST['nom']);
        $prenom = trim($_POST['prenom']);
        $email = trim($_POST['email']);
        
        // DEBUG VISIBLE
        $error_message = "DEBUG: Formulaire reçu - Nom: $nom, Prenom: $prenom, Email: $email, UserID: $user_id";
        
        if (empty($nom) || empty($prenom) || empty($email)) {
            $error_message = t('all_fields_required');
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_message = t('email_invalid');
        } else {
            // Vérifier si l'email existe déjà pour un autre utilisateur
            $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
            $stmt->execute([$email, $user_id]);
            $existing = $stmt->fetch();
            
            if ($existing) {
                $error_message = t('email_already_used');
            } else {
                // Mettre à jour les informations
                try {
                    $stmt = $pdo->prepare("UPDATE users SET nom = ?, prenom = ?, email = ? WHERE user_id = ?");
                    $result = $stmt->execute([$nom, $prenom, $email, $user_id]);
                    
                    if ($result) {
                        $_SESSION['user_name'] = $prenom . ' ' . $nom;
                        $success_message = t('profile_updated');
                        // Recharger les données
                        $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
                        $stmt->execute([$user_id]);
                        $user = $stmt->fetch();
                        
                        // Redirection pour éviter la resoumission du formulaire
                        header("Location: profil.php?success=1");
                        exit;
                    } else {
                        $error_message = t('update_error');
                    }
                } catch (PDOException $e) {
                    $error_message = "Erreur SQL: " . $e->getMessage();
                }
            }
        }
    }
    
    // Traitement du changement de mot de passe
    if (isset($_POST['update_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $error_message = t('all_fields_required');
        } elseif (!password_verify($current_password, $user[$password_col])) {
            $error_message = t('password_incorrect');
        } elseif (strlen($new_password) < 6) {
            $error_message = "Le nouveau mot de passe doit contenir au moins 6 caractères.";
        } elseif ($new_password !== $confirm_password) {
            $error_message = "Les mots de passe ne correspondent pas.";
        } else {
            try {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET $password_col = ? WHERE user_id = ?");
                if ($stmt->execute([$hashed_password, $user_id])) {
                    $success_message = "Mot de passe modifié avec succès !";
                } else {
                    $error_message = "Erreur lors de la modification du mot de passe.";
                }
            } catch (PDOException $e) {
                $error_message = "Erreur SQL: " . $e->getMessage();
            }
        }
    }
}

include '../../includes/head.php';
?>
<link rel="stylesheet" href="../../css/modern.css">
<link rel="stylesheet" href="../../css/reclamant.css">
    
    
    
<body>
    <!-- Barre de statut -->
    <?php if ($success_message): ?>
        <div class="status-bar success"></div>
    <?php endif; ?>
    
    <?php if ($error_message): ?>
        <div class="status-bar error"></div>
    <?php endif; ?>
    
    <!-- Navbar Minimaliste -->
    <nav class="navbar navbar-minimal navbar-expand-lg">
        <div class="container py-2">
            <a class="navbar-brand" href="index.php">
                <i class="bi bi-check-circle-fill me-2" style="color: #14b8a6;"></i>Resolve
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" style="border-color: #e5e7eb;">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-center">
                    <li class="nav-item me-3">
                        <a href="profil.php" class="text-decoration-none d-flex align-items-center gap-2">
                            <i class="bi bi-person-circle profile-icon"></i>
                            <span style="color: #6b7280;"><?php echo t('nav_hello'); ?>, <strong style="color: #111827;"><?php echo htmlspecialchars($_SESSION['user_name']); ?></strong></span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="btn btn-logout" href="../../frontend/deconnexion.php">
                            <i class="bi bi-box-arrow-right me-1"></i><?php echo t('nav_logout'); ?>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container pb-5">
        <div class="main-content-container">
            <!-- En-tête -->
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4">
                <div>
                    <h6 class="section-title"><?php echo t('dashboard_area_claimant'); ?></h6>
                    <h1 class="main-title"><?php echo t('profile_title'); ?></h1>
                </div>
                <a href="index.php" class="btn btn-secondary-action">
                    <i class="bi bi-arrow-left me-2"></i><?php echo t('back_to_dashboard'); ?>
                </a>
            </div>
            
            <!-- Messages -->
            <?php if ($success_message): ?>
                <div class="alert alert-success">
                    <i class="bi bi-check-circle me-2"></i><?php echo $success_message; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-circle me-2"></i><?php echo $error_message; ?>
                </div>
            <?php endif; ?>
            
            <!-- Avatar et info -->
            <div class="profile-card">
                <div class="profile-avatar">
                    <i class="bi bi-person-fill"></i>
                </div>
                <h3 class="text-center mb-2" style="color: var(--gray-900); font-weight: 700;">
                    <?php echo htmlspecialchars($user['prenom'] . ' ' . $user['nom']); ?>
                </h3>
                <p class="text-center mb-0" style="color: var(--gray-500);">
                    <i class="bi bi-envelope me-2"></i><?php echo htmlspecialchars($user['email']); ?>
                </p>
            </div>
            
            <!-- Formulaire de modification du profil -->
            <div class="form-section">
                <h4 class="form-section-title"><?php echo t('personal_info'); ?></h4>
                <form method="POST" action="profil.php">
                    <div class="row mb-3">
                        <div class="col-md-6 mb-3 mb-md-0">
                            <label for="prenom" class="form-label"><?php echo t('first_name'); ?></label>
                            <input type="text" class="form-control" id="prenom" name="prenom" 
                                   value="<?php echo htmlspecialchars($user['prenom']); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="nom" class="form-label"><?php echo t('last_name'); ?></label>
                            <input type="text" class="form-control" id="nom" name="nom" 
                                   value="<?php echo htmlspecialchars($user['nom']); ?>" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label"><?php echo t('email_address'); ?></label>
                        <input type="email" class="form-control" id="email" name="email" 
                               value="<?php echo htmlspecialchars($user['email']); ?>" required>
                    </div>
                    <button type="submit" name="update_profile" value="1" class="btn btn-primary-action">
                        <i class="bi bi-check-circle me-2"></i><?php echo t('save_changes'); ?>
                    </button>
                </form>
            </div>
            
            <!-- Formulaire de changement de mot de passe -->
            <div class="form-section">
                <h4 class="form-section-title"><?php echo t('account_security'); ?></h4>
                <form method="POST">
                    <div class="mb-3">
                        <label for="current_password" class="form-label"><?php echo t('current_password'); ?></label>
                        <input type="password" class="form-control" id="current_password" 
                               name="current_password" required>
                    </div>
                    <div class="mb-3">
                        <label for="new_password" class="form-label"><?php echo t('new_password'); ?></label>
                        <input type="password" class="form-control" id="new_password" 
                               name="new_password" required>
                        <small class="text-muted"><?php echo t('password_hint'); ?></small>
                    </div>
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label"><?php echo t('confirm_new_password'); ?></label>
                        <input type="password" class="form-control" id="confirm_password" 
                               name="confirm_password" required>
                    </div>
                    <button type="submit" name="update_password" class="btn btn-primary-action">
                        <i class="bi bi-shield-lock me-2"></i><?php echo t('update_password'); ?>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <?php include '../../includes/footer.php'; ?>
    
    <script>
        // Script minimal pour tester le formulaire
        console.log('Page chargée');
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form[action="profil.php"]');
            if (form) {
                form.addEventListener('submit', function(e) {
                    console.log('Formulaire soumis !');
                    // Laisser le formulaire se soumettre normalement
                });
            }
        });
    </script>
</body>
</html>
