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

<style>
    body {
        background: linear-gradient(135deg, #cffafe 0%, #e0f2fe 50%, #e0e7ff 100%);
        min-height: 100vh;
    }
    
    .navbar-minimal {
        background-color: #ffffff;
        border-bottom: none;
        box-shadow: var(--shadow-md);
        transition: var(--transition-base);
        animation: slideDown 0.5s ease-out;
    }
    
    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    .navbar-brand {
        color: var(--gray-900) !important;
        font-weight: 700;
        font-size: 1.25rem;
    }
    
    .btn-logout {
        color: var(--primary-blue) !important;
        font-weight: 500;
        background: transparent;
        border: none;
        transition: var(--transition-base);
    }
    
    .btn-logout:hover {
        color: var(--primary-blue-dark) !important;
    }
    
    .profile-icon {
        color: var(--primary-blue);
        transition: var(--transition-base);
        cursor: pointer;
        font-size: 1.2rem;
    }
    
    .profile-icon:hover {
        color: var(--primary-blue-dark);
        transform: scale(1.1);
    }
    
    .main-content-container {
        background: white;
        border-radius: var(--radius-xl);
        padding: 2.5rem;
        box-shadow: var(--shadow-lg);
        margin-bottom: 2rem;
        margin-top: 2rem;
        animation: fadeInUp 0.6s ease-out;
    }
    
    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(30px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    .section-title {
        color: var(--gray-500);
        font-weight: 500;
        font-size: 0.95rem;
        margin-bottom: 0.5rem;
        animation: fadeIn 0.8s ease-out 0.2s both;
    }
    
    .main-title {
        color: var(--gray-900);
        font-weight: 700;
        font-size: 2rem;
        margin-bottom: 2rem;
        animation: fadeIn 0.8s ease-out 0.3s both;
    }
    
    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    .profile-card {
        background: linear-gradient(135deg, #f9fafb 0%, #ffffff 100%);
        border: 1px solid var(--gray-200);
        border-radius: var(--radius-xl);
        padding: 2rem;
        margin-bottom: 2rem;
        box-shadow: var(--shadow-md);
        animation: scaleIn 0.5s ease-out;
    }
    
    @keyframes scaleIn {
        from {
            opacity: 0;
            transform: scale(0.95);
        }
        to {
            opacity: 1;
            transform: scale(1);
        }
    }
    
    .profile-avatar {
        width: 120px;
        height: 120px;
        border-radius: 50%;
        background: var(--gradient-blue);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 3rem;
        color: white;
        margin: 0 auto 1.5rem;
        box-shadow: var(--shadow-lg);
    }
    
    .form-section {
        background: white;
        border: 1px solid var(--gray-200);
        border-radius: var(--radius-xl);
        padding: 2rem;
        margin-bottom: 2rem;
        box-shadow: var(--shadow-sm);
    }
    
    .form-section-title {
        color: var(--gray-900);
        font-weight: 700;
        font-size: 1.25rem;
        margin-bottom: 1.5rem;
        position: relative;
        padding-left: 1rem;
    }
    
    .form-section-title::before {
        content: '';
        position: absolute;
        left: 0;
        top: 50%;
        transform: translateY(-50%);
        width: 4px;
        height: 70%;
        background: var(--gradient-blue);
        border-radius: var(--radius-sm);
    }
    
    .form-label {
        font-weight: 600;
        color: var(--gray-700);
        margin-bottom: 0.5rem;
    }
    
    .form-control {
        border: 2px solid var(--gray-200);
        border-radius: var(--radius-md);
        padding: 0.75rem 1rem;
        transition: var(--transition-base);
    }
    
    .form-control:focus {
        border-color: var(--primary-blue);
        box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.1);
    }
    
    .btn-primary-action {
        background: var(--gradient-blue);
        color: white;
        border: none;
        padding: 0.75rem 1.75rem;
        border-radius: var(--radius-md);
        font-weight: 600;
        transition: all var(--transition-base);
        box-shadow: var(--shadow-lg);
    }
    
    .btn-primary-action:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-2xl);
    }
    
    .btn-secondary-action {
        background: var(--gray-100);
        color: var(--gray-700);
        border: 2px solid var(--gray-200);
        padding: 0.75rem 1.75rem;
        border-radius: var(--radius-md);
        font-weight: 600;
        transition: all var(--transition-base);
        text-decoration: none;
        display: inline-block;
    }
    
    .btn-secondary-action:hover {
        background: var(--gray-200);
        border-color: var(--gray-300);
        color: var(--gray-900);
        transform: translateY(-2px);
    }
    
    .alert {
        border-radius: var(--radius-md);
        border: none;
        padding: 1rem 1.5rem;
        margin-bottom: 1.5rem;
        animation: slideDown 0.3s ease-out;
    }
    
    .alert-success {
        background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
        color: #065f46;
    }
    
    .alert-danger {
        background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
        color: #991b1b;
    }
    
    .status-bar {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 4px;
        z-index: 9999;
        animation: slideInBar 0.5s ease-out;
    }
    
    .status-bar.success {
        background: linear-gradient(90deg, #10b981 0%, #059669 100%);
        box-shadow: 0 2px 10px rgba(16, 185, 129, 0.5);
    }
    
    .status-bar.error {
        background: linear-gradient(90deg, #ef4444 0%, #dc2626 100%);
        box-shadow: 0 2px 10px rgba(239, 68, 68, 0.5);
    }
    
    @keyframes slideInBar {
        from {
            transform: translateY(-100%);
            opacity: 0;
        }
        to {
            transform: translateY(0);
            opacity: 1;
        }
    }
</style>

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
                            <span style="color: #6b7280;">Bonjour, <strong style="color: #111827;"><?php echo htmlspecialchars($_SESSION['user_name']); ?></strong></span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="btn btn-logout" href="../../frontend/deconnexion.php">
                            <i class="bi bi-box-arrow-right me-1"></i>Déconnexion
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
