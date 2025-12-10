<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/lang.php';

$page_title = t('login_title');
$include_auth_css = true;

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
        $error = t('fill_all_fields');
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
                $error = t('invalid_credentials');
            }
        } else {
            $error = t('invalid_credentials');
        }
    }
}

?>
<?php 
$body_class = 'no-autofocus';
include '../includes/head_frontend.php'; 
?>
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
            <h1 class="auth-title"><?php echo t('login_title'); ?></h1>
            <p class="auth-subtitle"><?php echo t('login_subtitle'); ?></p>

        <?php if ($error): ?>
            <div class="alert-modern alert-danger-modern">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php">
            <div class="form-group-modern">
                <label for="email" class="form-label-modern"><?php echo t('email'); ?></label>
                <div class="input-with-icon">
                    <i class="bi bi-envelope input-icon"></i>
                    <input type="email" class="form-control-modern" id="email" name="email" 
                           placeholder="nom@exemple.com" required autocomplete="email">
                </div>
            </div>
            
            <div class="form-group-modern">
                <label for="password" class="form-label-modern"><?php echo t('password'); ?></label>
                <div class="input-with-icon">
                    <i class="bi bi-lock input-icon"></i>
                    <input type="password" class="form-control-modern" id="password" name="password" 
                           placeholder="••••••••" required autocomplete="current-password">
                    <button type="button" class="toggle-visibility" aria-label="<?php echo t('show_hide_password'); ?>" data-target="password">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>
            </div>
            
            <button type="submit" class="btn-login">
                <?php echo t('login_button'); ?>
            </button>
            
            <div class="text-center mt-3">
                <a href="mot-de-passe-oublie.php" class="forgot-password-link">
                    <i class="bi bi-question-circle me-1"></i><?php echo t('forgot_password'); ?>
                </a>
            </div>
        </form>

        <div class="auth-links">
            <p style="margin-bottom: 0.75rem; color: var(--gray-600);">
                <?php echo t('no_account'); ?> 
                <a href="register.php" class="auth-link"><?php echo t('create_account'); ?></a>
            </p>
            <a href="index.php" class="back-link">
                <i class="bi bi-arrow-left"></i>
                <?php echo t('back_home'); ?>
            </a>
        </div>
        </div>

        <aside class="showcase-panel">
            <div class="showcase-inner">
                <div class="showcase-badge">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M9 12l2 2 4-4" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    <?php echo t('showcase_badge'); ?>
                </div>
                <h2 class="showcase-title"><?php echo t('showcase_title'); ?></h2>
                <ul class="showcase-points">
                    <li>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M9 12l2 2 4-4" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        <?php echo t('showcase_feature_1'); ?>
                    </li>
                    <li>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M9 12l2 2 4-4" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        <?php echo t('showcase_feature_2'); ?>
                    </li>
                    <li>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M9 12l2 2 4-4" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        <?php echo t('showcase_feature_3'); ?>
                    </li>
                </ul>
                <div class="showcase-stats">
                    <span class="chip"><i class="bi bi-shield-lock"></i> <?php echo t('showcase_chip_security'); ?></span>
                    <span class="chip"><i class="bi bi-speedometer2"></i> <?php echo t('showcase_chip_fast'); ?></span>
                    <span class="chip"><i class="bi bi-phone"></i> <?php echo t('showcase_chip_responsive'); ?></span>
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