<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Si l'utilisateur est déjà connecté, le rediriger
if (is_logged_in()) {
    redirect('../espaces/reclamant/index.php');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = sanitize_input($_POST['nom']);
    $email = sanitize_input($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if (empty($nom) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = "Veuillez remplir tous les champs.";
    } elseif ($password !== $confirm_password) {
        $error = "Les mots de passe ne correspondent pas.";
    } elseif (strlen($password) < 6) {
        $error = "Le mot de passe doit contenir au moins 6 caractères.";
    } else {
        $pdo = get_pdo();
        
        // Vérifier si l'email existe déjà
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = "Cet email est déjà utilisé.";
        } else {
            // Créer le nouvel utilisateur
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (nom, email, password, role) VALUES (?, ?, ?, 'reclamant')");
            
            if ($stmt->execute([$nom, $email, $hashed_password])) {
                // Auto-login the new user
                $pdo = get_pdo();
                $stmt2 = $pdo->prepare("SELECT * FROM users WHERE email = ?");
                $stmt2->execute([$email]);
                $newUser = $stmt2->fetch();
                if ($newUser) {
                    $_SESSION['user_id'] = $newUser['user_id'] ?? $newUser['id'] ?? '';
                    $_SESSION['user_name'] = $newUser['nom'] ?? $newUser['name'] ?? '';
                    $_SESSION['user_email'] = $newUser['email'] ?? '';
                    $_SESSION['user_role'] = $newUser['role'] ?? 'reclamant';

                    // If there is a pending submission, process it
                    if (!empty($_SESSION['pending_submission'])) {
                        redirect('../espaces/reclamant/soumission.php?process_pending=1');
                    }

                    // Otherwise redirect to reclamant dashboard
                    redirect('../espaces/reclamant/index.php');
                } else {
                    $success = "Compte créé avec succès ! Vous pouvez maintenant vous connecter.";
                }
            } else {
                $error = "Une erreur est survenue lors de l'inscription.";
            }
        }
    }
}

include '../includes/head.php';
?>

<body class="bg-light d-flex align-items-center justify-content-center" style="min-height: 100vh;">

    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="card shadow-lg border-0 rounded-4">
                    <div class="card-body p-5">
                        <div class="text-center mb-4">
                            <i class="bi bi-person-plus-fill text-success" style="font-size: 3rem;"></i>
                            <h2 class="fw-bold mt-3">Inscription</h2>
                            <p class="text-muted">Rejoignez notre plateforme</p>
                        </div>

                        <?php if ($error): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo $error; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>

                        <?php if ($success): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="bi bi-check-circle-fill me-2"></i> <?php echo $success; ?>
                                <div class="mt-2">
                                    <a href="login.php" class="btn btn-sm btn-success fw-bold">Se connecter</a>
                                </div>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>

                        <?php if (empty($success)): ?>
                        <form method="POST" action="register.php">
                            <div class="mb-3">
                                <label for="nom" class="form-label fw-bold">Nom complet</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0"><i class="bi bi-person"></i></span>
                                    <input type="text" class="form-control border-start-0 ps-0" id="nom" name="nom" placeholder="Votre nom" required value="<?php echo isset($_POST['nom']) ? htmlspecialchars($_POST['nom']) : ''; ?>">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="email" class="form-label fw-bold">Adresse Email</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0"><i class="bi bi-envelope"></i></span>
                                    <input type="email" class="form-control border-start-0 ps-0" id="email" name="email" placeholder="nom@exemple.com" required value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label fw-bold">Mot de passe</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0"><i class="bi bi-lock"></i></span>
                                    <input type="password" class="form-control border-start-0 ps-0" id="password" name="password" placeholder="********" required>
                                </div>
                                <div class="form-text">Au moins 6 caractères.</div>
                            </div>
                            <div class="mb-4">
                                <label for="confirm_password" class="form-label fw-bold">Confirmer le mot de passe</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0"><i class="bi bi-lock-fill"></i></span>
                                    <input type="password" class="form-control border-start-0 ps-0" id="confirm_password" name="confirm_password" placeholder="********" required>
                                </div>
                            </div>
                            <div class="d-grid mb-3">
                                <button type="submit" class="btn btn-success btn-lg fw-bold">S'inscrire</button>
                            </div>
                        </form>
                        <?php endif; ?>

                        <div class="text-center mt-4">
                            <p class="text-muted mb-0">Déjà un compte ? <a href="login.php" class="text-primary fw-bold text-decoration-none">Se connecter</a></p>
                            <p class="mt-2"><a href="index.php" class="text-secondary text-decoration-none"><i class="bi bi-arrow-left"></i> Retour à l'accueil</a></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>
</body>
</html>
