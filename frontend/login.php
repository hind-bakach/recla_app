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
                // If there is a pending submission, process it in the reclamant area
                if (!empty($_SESSION['pending_submission'])) {
                    // Redirect to reclamant soumission processor which will require the user to be reclamant
                    redirect('../espaces/reclamant/soumission.php?process_pending=1');
                }

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

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - Resolve</title>
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/modern.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body {
            min-height: 100vh;
            display: block;
            background: linear-gradient(135deg, #14b8a6 0%, #0ea5e9 100%);
            position: relative;
            overflow-y: auto;
            -webkit-overflow-scrolling: touch;
            font-family: 'Inter', sans-serif;
        }
        
        /* Bokeh Background */
        .bokeh-background {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 0;
            overflow: hidden;
        }
        
        .bokeh-circle {
            position: absolute;
            border-radius: 50%;
            filter: blur(80px);
            opacity: 0.4;
            animation: float-bokeh 15s ease-in-out infinite;
        }
        
        .bokeh-1 { width: 300px; height: 300px; background: rgba(20, 184, 166, 0.6); top: 10%; left: 10%; animation-delay: 0s; }
        .bokeh-2 { width: 400px; height: 400px; background: rgba(14, 165, 233, 0.5); bottom: 15%; right: 10%; animation-delay: 3s; }
        .bokeh-3 { width: 350px; height: 350px; background: rgba(34, 197, 94, 0.35); top: 40%; right: 20%; animation-delay: 6s; }
        .bokeh-4 { width: 250px; height: 250px; background: rgba(255, 255, 255, 0.3); bottom: 30%; left: 15%; animation-delay: 9s; }
        
        @keyframes float-bokeh {
            0%, 100% { transform: translate(0, 0) scale(1); }
            33% { transform: translate(30px, -40px) scale(1.1); }
            66% { transform: translate(-30px, 30px) scale(0.9); }
        }
        
        /* Auth Card */
        .auth-card {
            position: relative;
            z-index: 10;
            background: white;
            border-radius: 2rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.35);
            padding: 2rem;
            max-width: 440px;
            width: calc(100% - 2rem);
            margin: 2rem auto;
            animation: slideUp 0.6s ease-out;
        }

        /* Grid layout for larger screens */
        @media (min-width: 992px) {
            .auth-card {
                display: grid;
                grid-template-columns: 1.05fr 0.95fr;
                padding: 0;
                max-width: 980px;
                overflow: hidden;
                min-height: 640px;
            }
            .auth-form { padding: 2rem 2rem 2rem 2.25rem; }
            .showcase-panel { display: block; }
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .auth-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #14b8a6 0%, #0ea5e9 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            box-shadow: 0 10px 20px rgba(37, 99, 235, 0.3);
        }
        
        .auth-icon i {
            font-size: 2.5rem;
            color: white;
        }
        
        .auth-title {
            font-size: 2rem;
            font-weight: 800;
            text-align: center;
            color: var(--gray-900);
            margin-bottom: 0.5rem;
        }
        
        .auth-subtitle {
            text-align: center;
            color: var(--gray-600);
            margin-bottom: 2rem;
            font-size: 1rem;
        }
        
        .form-group-modern {
            margin-bottom: 1rem;
            position: relative;
        }
        
        .form-label-modern {
            font-weight: 600;
            font-size: 0.875rem;
            color: var(--gray-700);
            margin-bottom: 0.5rem;
            display: block;
        }
        
        .input-with-icon {
            position: relative;
        }
        
        .input-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray-400);
            font-size: 1.125rem;
            z-index: 1;
        }
        
        .form-control-modern {
            width: 100%;
            padding: 0.875rem 1rem 0.875rem 3rem;
            border: 1.5px solid var(--gray-300);
            border-radius: 0.75rem;
            font-size: 1rem;
            transition: all var(--transition-base);
            background: white;
        }

        .toggle-visibility {
            position: absolute;
            right: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            background: transparent;
            border: none;
            color: var(--gray-400);
            font-size: 1.1rem;
            padding: 0.25rem;
            cursor: pointer;
        }
        .toggle-visibility:hover { color: var(--gray-600); }
        
        .form-control-modern:focus {
            outline: none;
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1);
        }
        
        .btn-login {
            width: 100%;
            padding: 0.875rem;
            background: linear-gradient(135deg, #14b8a6 0%, #0ea5e9 100%);
            color: white;
            border: none;
            border-radius: 0.75rem;
            font-weight: 700;
            font-size: 1rem;
            cursor: pointer;
            transition: all var(--transition-base);
            box-shadow: 0 10px 20px rgba(37, 99, 235, 0.3);
            margin-top: 0.5rem;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 30px rgba(37, 99, 235, 0.4);
        }
        
        .btn-login:active {
            transform: translateY(0);
        }
        
        .auth-links {
            text-align: center;
            margin-top: 1rem;
        }
        
        .auth-link {
            color: var(--primary-blue);
            text-decoration: none;
            font-weight: 600;
            transition: color var(--transition-fast);
        }
        
        .auth-link:hover {
            color: var(--primary-blue-dark);
        }
        
        .forgot-password-link {
            color: var(--gray-600);
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 500;
            transition: color var(--transition-fast);
            display: inline-flex;
            align-items: center;
        }
        
        .forgot-password-link:hover {
            color: var(--primary-blue);
        }
        
        .back-link {
            color: var(--gray-600);
            text-decoration: none;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: color var(--transition-fast);
        }
        
        .back-link:hover {
            color: var(--gray-800);
        }
        
        .alert-modern {
            padding: 1rem 1.25rem;
            border-radius: 0.75rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            animation: slideDown 0.3s ease-out;
        }
        
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .alert-danger-modern {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        /* Showcase panel (hidden on mobile) */
        .showcase-panel {
            position: relative;
            background: radial-gradient(1200px 600px at 80% -20%, rgba(255,255,255,0.18) 0%, rgba(255,255,255,0) 60%),
                        linear-gradient(135deg, #0ea5e9 0%, #14b8a6 100%);
            color: white;
            padding: 2rem 2.25rem;
            height: 100%;
        }
        .showcase-inner { max-width: 420px; margin: 0 auto; position: relative; z-index: 2; display: flex; flex-direction: column; height: 100%; justify-content: center; }
        .showcase-panel::before { content: ""; position: absolute; right: -120px; bottom: -120px; width: 360px; height: 360px; background: radial-gradient(circle at center, rgba(255,255,255,0.28), rgba(255,255,255,0) 60%); filter: blur(8px); z-index: 1; }
        .showcase-panel::after { content: ""; position: absolute; left: -100px; top: -80px; width: 260px; height: 260px; background: radial-gradient(circle at center, rgba(255,255,255,0.2), rgba(255,255,255,0) 60%); filter: blur(6px); z-index: 1; }
        .showcase-badge { display:inline-flex; align-items:center; gap:0.5rem; background: rgba(255,255,255,0.15); padding:0.5rem 0.75rem; border-radius:999px; font-weight:600; }
        .showcase-title { font-weight: 800; font-size: 1.75rem; line-height: 1.2; margin: 1rem 0; }
        .showcase-points { list-style:none; padding:0; margin:1rem 0 0; display:grid; gap:0.75rem; }
        .showcase-points li { display:flex; align-items:flex-start; gap:0.5rem; }
        .showcase-points svg { flex:0 0 auto; }
        .showcase-stats { margin-top: auto; display:flex; gap:0.5rem; flex-wrap: wrap; }
        .chip { display:inline-flex; align-items:center; gap:0.4rem; background: rgba(255,255,255,0.16); border: 1px solid rgba(255,255,255,0.2); color: #fff; padding: 0.4rem 0.6rem; border-radius: 999px; font-weight: 600; font-size: 0.85rem; }

        /* Responsive adjustments */
        @media (max-width: 576px) {
            .auth-card { border-radius: 1.25rem; padding: 1.25rem; }
            .auth-icon { width: 64px; height: 64px; }
            .auth-title { font-size: 1.5rem; }
            .form-control-modern { padding: 0.75rem 0.75rem 0.75rem 2.5rem; }
            .input-icon { left: 0.75rem; }
        }
    </style>
</head>
<body class="no-autofocus">
    <div class="bokeh-background">
        <div class="bokeh-circle bokeh-1"></div>
        <div class="bokeh-circle bokeh-2"></div>
        <div class="bokeh-circle bokeh-3"></div>
        <div class="bokeh-circle bokeh-4"></div>
    </div>

    <div class="auth-card">
        <div class="auth-form">
            <div class="auth-icon">
                <i class="bi bi-person-circle"></i>
            </div>
            <h1 class="auth-title">Connexion</h1>
            <p class="auth-subtitle">Accédez à votre espace</p>

        <?php if ($error): ?>
            <div class="alert-modern alert-danger-modern">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php">
            <div class="form-group-modern">
                <label for="email" class="form-label-modern">Adresse Email</label>
                <div class="input-with-icon">
                    <i class="bi bi-envelope input-icon"></i>
                    <input type="email" class="form-control-modern" id="email" name="email" 
                           placeholder="nom@exemple.com" required autocomplete="email">
                </div>
            </div>
            
            <div class="form-group-modern">
                <label for="password" class="form-label-modern">Mot de passe</label>
                <div class="input-with-icon">
                    <i class="bi bi-lock input-icon"></i>
                    <input type="password" class="form-control-modern" id="password" name="password" 
                           placeholder="••••••••" required autocomplete="current-password">
                    <button type="button" class="toggle-visibility" aria-label="Afficher / masquer le mot de passe" data-target="password">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>
            </div>
            
            <button type="submit" class="btn-login">
                Se connecter
            </button>
            
            <div class="text-center mt-3">
                <a href="mot-de-passe-oublie.php" class="forgot-password-link">
                    <i class="bi bi-question-circle me-1"></i>Mot de passe oublié ?
                </a>
            </div>
        </form>

        <div class="auth-links">
            <p style="margin-bottom: 0.75rem; color: var(--gray-600);">
                Pas encore de compte ? 
                <a href="register.php" class="auth-link">Créer un compte</a>
            </p>
            <a href="index.php" class="back-link">
                <i class="bi bi-arrow-left"></i>
                Retour à l'accueil
            </a>
        </div>
        </div>

        <aside class="showcase-panel">
            <div class="showcase-inner">
                <div class="showcase-badge">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M9 12l2 2 4-4" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    Sécurisé & Moderne
                </div>
                <h2 class="showcase-title">Bienvenue sur Resolve</h2>
                <ul class="showcase-points">
                    <li>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M9 12l2 2 4-4" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        Suivez vos réclamations en temps réel et recevez des mises à jour.
                    </li>
                    <li>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M9 12l2 2 4-4" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        Interface épurée, rapide et accessible sur tous les appareils.
                    </li>
                    <li>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M9 12l2 2 4-4" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        Données protégées avec chiffrement et bonnes pratiques.
                    </li>
                </ul>
                <div class="showcase-stats">
                    <span class="chip"><i class="bi bi-shield-lock"></i> Sécurité</span>
                    <span class="chip"><i class="bi bi-speedometer2"></i> Rapide</span>
                    <span class="chip"><i class="bi bi-phone"></i> Responsive</span>
                </div>
            </div>
        </aside>
    </div>

    <script src="../js/bootstrap.bundle.min.js"></script>
    <script src="../js/main.js"></script>
    <script>
        // Password visibility toggle
        document.querySelectorAll('.toggle-visibility').forEach(btn => {
            btn.addEventListener('click', () => {
                const id = btn.getAttribute('data-target');
                const input = document.getElementById(id);
                if (!input) return;
                const isPwd = input.getAttribute('type') === 'password';
                input.setAttribute('type', isPwd ? 'text' : 'password');
                btn.innerHTML = isPwd ? '<i class="bi bi-eye-slash"></i>' : '<i class="bi bi-eye"></i>';
            });
        });
    </script>
</body>
</html>