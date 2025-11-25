<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Si l'utilisateur est déjà connecté et tente d'accéder au formulaire en GET, le rediriger
// (Ne pas rediriger si POST pour permettre la soumission du formulaire)
if (is_logged_in() && $_SERVER['REQUEST_METHOD'] === 'GET') {
    if (has_role('administrateur')) {
        redirect('../espaces/administrateur/index.php');
    } elseif (has_role('gestionnaire')) {
        redirect('../espaces/gestionnaire/index.php');
    } else {
        redirect('../espaces/reclamant/index.php');
    }
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize_input($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = "Veuillez remplir tous les champs.";
    } else {
        $pdo = get_pdo();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            $stored = $user['mot_de_passe'] ?? $user['password'] ?? '';
            $authenticated = false;

            if ($stored !== '') {
                if (password_verify($password, $stored)) {
                    $authenticated = true;
                } elseif (hash_equals($stored, $password)) {
                    // Fallback for plain-text passwords stored in the DB (not recommended)
                    $authenticated = true;
                }
            }

            if ($authenticated) {
            // Authentification réussie
                $_SESSION['user_id'] = $user['user_id'] ?? $user['id'] ?? '';
                $_SESSION['user_name'] = $user['nom'] ?? $user['name'] ?? '';
                $_SESSION['user_email'] = $user['email'] ?? '';
                // Do NOT store the password in session
                $_SESSION['user_role'] = $user['role'] ?? '';

            // Redirection selon le rôle
            if ($user['role'] === 'administrateur') {
                redirect('../espaces/administrateur/index.php');
            } elseif ($user['role'] === 'gestionnaire') {
                redirect('../espaces/gestionnaire/index.php');
            } else {
                redirect('../espaces/reclamant/index.php');
            }
            } else {
                $error = "Email ou mot de passe incorrect.";
            }
        } else {
            $error = "Email ou mot de passe incorrect.";
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
                            <i class="bi bi-person-circle text-primary" style="font-size: 3rem;"></i>
                            <h2 class="fw-bold mt-3">Connexion</h2>
                            <p class="text-muted">Accédez à votre espace</p>
                        </div>

                        <?php if ($error): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo $error; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="login.php">
                            <div class="mb-3">
                                <label for="email" class="form-label fw-bold">Adresse Email</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0"><i class="bi bi-envelope"></i></span>
                                    <input type="email" class="form-control border-start-0 ps-0" id="email" name="email" placeholder="nom@exemple.com" required>
                                </div>
                            </div>
                            <div class="mb-4">
                                <label for="password" class="form-label fw-bold">Mot de passe</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0"><i class="bi bi-lock"></i></span>
                                    <input type="password" class="form-control border-start-0 ps-0" id="password" name="password" placeholder="********" required>
                                </div>
                            </div>
                            <div class="d-grid mb-3">
                                <button type="submit" class="btn btn-primary btn-lg fw-bold">Se connecter</button>
                            </div>
                        </form>

                        <div class="text-center mt-4">
                            <p class="text-muted mb-0">Pas encore de compte ? <a href="register.php" class="text-primary fw-bold text-decoration-none">Créer un compte</a></p>
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