<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/lang.php';

$page_title = t('register_title');
$include_auth_css = true;

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
        $error = t('fill_all_fields');
    } elseif ($password !== $confirm_password) {
        $error = t('passwords_not_match');
    } elseif (strlen($password) < 6) {
        $error = t('password_min_length');
    } else {
        $pdo = get_pdo();
        
        // Vérifier si l'email existe déjà
        $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = t('email_exists');
        } else {
            // Créer le nouvel utilisateur
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (nom, email, mot_de_passe, role) VALUES (?, ?, ?, 'reclamant')");
            
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
                <i class="bi bi-person-plus-fill"></i>
            </div>
            
            <h1 class="auth-title"><?php echo t('register_title'); ?></h1>
            <p class="auth-subtitle"><?php echo t('register_subtitle'); ?></p>

        <?php if ($error): ?>
            <div class="alert-modern alert-danger-modern">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert-modern alert-success-modern">
                <i class="bi bi-check-circle-fill"></i>
                <div>
                    <div><?php echo htmlspecialchars($success); ?></div>
                    <a href="login.php" class="btn-register" style="margin-top: 1rem; padding: 0.75rem;"><?php echo t('login_now'); ?></a>
                </div>
            </div>
        <?php endif; ?>

        <?php if (empty($success)): ?>
        <form method="POST" action="register.php">
            <div class="form-group-modern">
                <label for="nom" class="form-label-modern"><?php echo t('full_name'); ?></label>
                <div class="input-with-icon">
                    <i class="bi bi-person input-icon"></i>
                    <input type="text" class="form-control-modern" id="nom" name="nom" 
                           placeholder="<?php echo t('full_name'); ?>" required 
                           value="<?php echo isset($_POST['nom']) ? htmlspecialchars($_POST['nom']) : ''; ?>">
                </div>
            </div>
            
            <div class="form-group-modern">
                <label for="email" class="form-label-modern"><?php echo t('email'); ?></label>
                <div class="input-with-icon">
                    <i class="bi bi-envelope input-icon"></i>
                    <input type="email" class="form-control-modern" id="email" name="email" 
                           placeholder="nom@exemple.com" required
                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                </div>
            </div>
            
            <div class="form-group-modern">
                <label for="password" class="form-label-modern"><?php echo t('password'); ?></label>
                <div class="input-with-icon">
                    <i class="bi bi-lock input-icon"></i>
                    <input type="password" class="form-control-modern" id="password" name="password" 
                           placeholder="••••••••" required autocomplete="new-password">
                    <button type="button" class="toggle-visibility" aria-label="<?php echo t('show_hide_password'); ?>" data-target="password">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>
                <div class="form-hint"><?php echo t('password_hint'); ?></div>
                <div class="password-strength" id="pwdStrength">
                    <div class="strength-track"><div class="strength-bar" id="strengthBar"></div></div>
                    <div class="strength-label" id="strengthLabel"><?php echo t('password_strength'); ?></div>
                </div>
            </div>
            
            <div class="form-group-modern">
                <label for="confirm_password" class="form-label-modern"><?php echo t('confirm_password'); ?></label>
                <div class="input-with-icon">
                    <i class="bi bi-lock-fill input-icon"></i>
                    <input type="password" class="form-control-modern" id="confirm_password" name="confirm_password" 
                           placeholder="••••••••" required autocomplete="new-password">
                    <button type="button" class="toggle-visibility" aria-label="<?php echo t('show_hide_password'); ?>" data-target="confirm_password">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>
            </div>
            
            <button type="submit" class="btn-register">
                <?php echo t('register_button'); ?>
            </button>
        </form>
        <?php endif; ?>
        <div class="auth-links">
            <p style="margin-bottom: 0.75rem; color: var(--gray-600);">
                <?php echo t('already_account'); ?> 
                <a href="login.php" class="auth-link"><?php echo t('login_here'); ?></a>
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
                    <?php echo t('showcase_badge_register'); ?>
                </div>
                <h2 class="showcase-title"><?php echo t('showcase_title_register'); ?></h2>
                <ul class="showcase-points">
                    <li>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M9 12l2 2 4-4" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        <?php echo t('showcase_feature_register_1'); ?>
                    </li>
                    <li>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M9 12l2 2 4-4" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        <?php echo t('showcase_feature_register_2'); ?>
                    </li>
                    <li>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M9 12l2 2 4-4" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        <?php echo t('showcase_feature_register_3'); ?>
                    </li>
                </ul>
                <div class="showcase-stats">
                    <span class="chip"><i class="bi bi-person-check"></i> <?php echo t('showcase_chip_user_check'); ?></span>
                    <span class="chip"><i class="bi bi-shield-lock"></i> <?php echo t('showcase_chip_secure'); ?></span>
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

        // Password strength meter (registration)
        const pwd = document.getElementById('password');
        const bar = document.getElementById('strengthBar');
        const label = document.getElementById('strengthLabel');
        if (pwd && bar && label) {
            const evaluate = (v) => {
                let score = 0;
                if (v.length >= 6) score++;
                if (v.length >= 10) score++;
                if (/[A-Z]/.test(v)) score++;
                if (/[0-9]/.test(v)) score++;
                if (/[^A-Za-z0-9]/.test(v)) score++;
                return Math.min(score, 5);
            };
            const render = (s) => {
                const widths = ['0%','25%','40%','60%','80%','100%'];
                const colors = [
                    'linear-gradient(135deg,#ef4444,#f59e0b)',
                    'linear-gradient(135deg,#ef4444,#f59e0b)',
                    'linear-gradient(135deg,#f59e0b,#f59e0b)',
                    'linear-gradient(135deg,#f59e0b,#14b8a6)',
                    'linear-gradient(135deg,#14b8a6,#0ea5e9)',
                    'linear-gradient(135deg,#14b8a6,#0ea5e9)'
                ];
                const labels = ['','Très faible','Faible','Moyen','Bon','Excellent'];
                bar.style.width = widths[s];
                bar.style.background = colors[s];
                label.textContent = labels[s] || 'Force du mot de passe';
            };
            pwd.addEventListener('input', (e) => render(e.target.value ? evaluate(e.target.value) : 0));
        }
    </script>
</body>
</html>
