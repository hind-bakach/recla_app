<?php
// --- Partie PHP de Traitement ---

// Le require_once doit être le premier car il inclut la fonction session_start() dans config.php
// CORRECTION : L'inclusion de head.php doit se faire APRES le traitement (en bas) car head.php contient le début du HTML.
require_once '../includes/config.php'; // Inclut la connexion BDD et DÉMARRE la session

// Vérifier si le formulaire a été soumis
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $pdo = get_pdo();
    
    // 1. Validation de base des champs
    if (empty($email) || empty($password)) {
        // En cas d'erreur, stocker le message d'erreur et rediriger vers la page elle-même
        $_SESSION['error'] = "Veuillez remplir tous les champs.";
        header('Location: login.php');
        exit();
    }

    // 2. Préparation de la requête pour récupérer l'utilisateur par email
    $stmt = $pdo->prepare("SELECT id, nom, email, mot_de_passe, role FROM users WHERE email = :email");
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch();
    
    // 3. Vérification de l'utilisateur et du mot de passe
    if ($user && password_verify($password, $user['mot_de_passe'])) {
        
        // --- Connexion réussie : Création de la session ---
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['nom'];
        $_SESSION['user_role'] = $user['role'];
        
        // 4. Redirection selon le rôle
        $role = $user['role'];
        
        if ($role == 'administrateur') {
            header('Location: ../espaces/administrateur/dashboard.php');
        } elseif ($role == 'gestionnaire') {
            header('Location: ../espaces/gestionnaire/dashboard.php');
        } else { // Réclamant (client, étudiant, etc.)
            header('Location: ../espaces/reclamant/dashboard.php');
        }
        exit(); // Très important d'arrêter l'exécution après la redirection

    } else {
        // Échec de l'authentification
        $_SESSION['error'] = "Email ou mot de passe incorrect.";
        header('Location: login.php');
        exit();
    }
}
?>

<?php include '../includes/head.php'; // Inclut le début du HTML, les balises meta et les liens Bootstrap ?>

<body class="bg-light d-flex align-items-center justify-content-center vh-100">
    
    <main class="form-signin w-100 m-auto p-4 shadow-lg bg-white rounded">
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger text-center" role="alert">
                <?= htmlspecialchars($_SESSION['error']); ?>
            </div>
            <?php unset($_SESSION['error']); // Supprime le message pour qu'il ne s'affiche qu'une fois ?>
        <?php endif; ?>
        
        <form method="POST" action="login.php"> 
            <div class="text-center mb-4">
                 <i class="bi bi-person-circle display-4 text-primary mb-3"></i>
                 <h1 class="h3 fw-bold text-dark">Accès Sécurisé</h1>
                 <p class="text-muted">Veuillez entrer vos identifiants pour continuer.</p>
            </div>

            <div class="form-floating mb-3">
                <input type="email" class="form-control" id="floatingInput" name="email" placeholder="name@example.com" required>
                <label for="floatingInput"><i class="bi bi-envelope me-2"></i>Adresse Email</label>
            </div>

            <div class="form-floating mb-4">
                <input type="password" class="form-control" id="floatingPassword" name="password" placeholder="Mot de passe" required>
                <label for="floatingPassword"><i class="bi bi-lock me-2"></i>Mot de passe</label>
            </div>

            <button class="w-100 btn btn-lg btn-primary" type="submit">
                <i class="bi bi-box-arrow-in-right me-1"></i> Se connecter
            </button>
            
            <hr class="my-4">
            <div class="text-center">
                <a href="soumission.php" class="text-decoration-none text-warning fw-bold">
                    <i class="bi bi-chat-dots me-1"></i> Nouveau ? Déposez une réclamation sans connexion
                </a>
            </div>
            
            <p class="mt-5 mb-3 text-muted text-center">&copy; 2025 Système de Réclamations</p>
        </form>
    </main>

    <?php include '../includes/footer.php'; // Inclut le pied de page HTML et le JS Bootstrap ?>
</body>
</html>