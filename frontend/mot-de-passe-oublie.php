<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/email_config.php';
require_once '../includes/lang.php';

$page_title = 'Mot de passe oublié';
$include_auth_css = true;

$success = '';
$error = '';
$step = 'email'; // email, code, password

// DEV_MODE est défini dans .env (true = afficher le code, false = envoyer l'email)

// Étape 1: Vérifier l'email
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email_submit'])) {
    $email = sanitize_input($_POST['email'] ?? '');
    
    if (empty($email)) {
        $error = "Veuillez entrer votre adresse email.";
    } else {
        $pdo = get_pdo();
        $stmt = $pdo->prepare("SELECT user_id, nom, prenom FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user) {
            // Générer un code de 6 chiffres
            $code = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
            $expiry = date('Y-m-d H:i:s', strtotime('+15 minutes'));
            
            // Stocker le code en session
            $_SESSION['reset_email'] = $email;
            $_SESSION['reset_code'] = $code;
            $_SESSION['reset_expiry'] = $expiry;
            $_SESSION['reset_user_id'] = $user['user_id'];
            
            $user_name = $user['prenom'] . ' ' . $user['nom'];
            
            // Vérifier le mode (converti en booléen)
            $dev_mode = (defined('DEV_MODE') && (DEV_MODE === 'true' || DEV_MODE === true || DEV_MODE === '1'));
            
            // MODE DÉVELOPPEMENT: Afficher le code
            if ($dev_mode) {
                $success = "Un code de vérification a été envoyé à votre adresse email.";
                $error = "🔧 MODE DEV - Votre code est: <strong>$code</strong> (valide 15 min)";
                $step = 'code';
            } 
            // MODE PRODUCTION: Envoyer l'email
            else {
                $email_sent = send_reset_email($email, $user_name, $code);
                
                if ($email_sent) {
                    $success = "Un code de vérification a été envoyé à votre adresse email.";
                    $step = 'code';
                } else {
                    $error = "Erreur lors de l'envoi de l'email. Veuillez réessayer.";
                    error_log("Échec d'envoi email de réinitialisation pour: $email");
                }
            }
        } else {
            $error = "Aucun compte n'est associé à cette adresse email.";
        }
    }
}

// Étape 2: Vérifier le code
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['code_submit'])) {
    $code = sanitize_input($_POST['code'] ?? '');
    
    // Enlever tous les espaces du code saisi
    $code = str_replace(' ', '', $code);
    
    if (empty($code)) {
        $error = "Veuillez entrer le code de vérification.";
        $step = 'code';
    } elseif (!isset($_SESSION['reset_code']) || !isset($_SESSION['reset_expiry'])) {
        $error = "Session expirée. Veuillez recommencer.";
        $step = 'email';
    } elseif (strtotime($_SESSION['reset_expiry']) < time()) {
        $error = "Le code a expiré. Veuillez recommencer.";
        unset($_SESSION['reset_code'], $_SESSION['reset_expiry'], $_SESSION['reset_email']);
        $step = 'email';
    } elseif ($code !== $_SESSION['reset_code']) {
        $error = "Code incorrect. Veuillez réessayer. (Code attendu: " . $_SESSION['reset_code'] . ")";
        $step = 'code';
    } else {
        // Code vérifié, passer à l'étape suivante
        $_SESSION['code_verified'] = true;
        $success = "Code vérifié ! Veuillez entrer votre nouveau mot de passe.";
        $step = 'password';
    }
}

// Étape 3: Réinitialiser le mot de passe
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password_submit'])) {
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($new_password) || empty($confirm_password)) {
        $error = "Veuillez remplir tous les champs.";
        $step = 'password';
    } elseif (strlen($new_password) < 6) {
        $error = "Le mot de passe doit contenir au moins 6 caractères.";
        $step = 'password';
    } elseif ($new_password !== $confirm_password) {
        $error = "Les mots de passe ne correspondent pas.";
        $step = 'password';
    } elseif (!isset($_SESSION['reset_user_id'])) {
        $error = "Session expirée. Veuillez recommencer.";
        $step = 'email';
    } else {
        $pdo = get_pdo();
        $hashed = password_hash($new_password, PASSWORD_DEFAULT);
        
        // Détecter la colonne du mot de passe
        $password_col = 'mot_de_passe';
        $cols = $pdo->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users'")->fetchAll(PDO::FETCH_COLUMN);
        if (in_array('password', $cols)) {
            $password_col = 'password';
        } elseif (in_array('pwd', $cols)) {
            $password_col = 'pwd';
        }
        
        $stmt = $pdo->prepare("UPDATE users SET $password_col = ? WHERE user_id = ?");
        $stmt->execute([$hashed, $_SESSION['reset_user_id']]);
        
        // Nettoyer la session
        unset($_SESSION['reset_code'], $_SESSION['reset_expiry'], $_SESSION['reset_email'], $_SESSION['reset_user_id'], $_SESSION['code_verified']);
        
        $success = "Votre mot de passe a été réinitialisé avec succès !";
        $step = 'complete';
    }
}

// Si l'utilisateur revient à l'étape du code
if (isset($_SESSION['reset_code']) && !isset($_POST['email_submit']) && empty($_POST)) {
    $step = 'code';
}

// Si le code a été vérifié, afficher le formulaire de mot de passe
if (isset($_SESSION['code_verified']) && !isset($_POST['code_submit']) && !isset($_POST['password_submit'])) {
    $step = 'password';
}
?>
<?php include '../includes/head_frontend.php'; ?>
    <div class="bokeh-background">
        <div class="bokeh-circle bokeh-1"></div>
        <div class="bokeh-circle bokeh-2"></div>
        <div class="bokeh-circle bokeh-3"></div>
        <div class="bokeh-circle bokeh-4"></div>
    </div>

    <div class="reset-container">
        <?php if ($step === 'email'): ?>
            <div class="reset-header">
                <div class="reset-icon">
                    <i class="bi bi-key"></i>
                </div>
                <h1 class="reset-title">Mot de passe oublié ?</h1>
                <p class="reset-subtitle">Entrez votre email pour recevoir un code de vérification</p>
            </div>
            
            <div class="step-indicator">
                <div class="step-dot active"></div>
                <div class="step-dot"></div>
                <div class="step-dot"></div>
            </div>
            
            <?php if ($error): ?>
                <div class="alert-modern alert-danger-modern">
                    <i class="bi bi-exclamation-circle"></i>
                    <span><?php echo $error; ?></span>
                </div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="mb-4">
                    <label for="email" class="form-label-modern">Adresse email</label>
                    <div class="input-with-icon">
                        <i class="bi bi-envelope input-icon"></i>
                        <input type="email" class="form-control-modern" id="email" name="email" 
                               placeholder="votre@email.com" required>
                    </div>
                </div>
                
                <button type="submit" name="email_submit" class="btn-reset">
                    Envoyer le code
                </button>
            </form>
            
        <?php elseif ($step === 'code'): ?>
            <div class="reset-header">
                <div class="reset-icon">
                    <i class="bi bi-shield-lock"></i>
                </div>
                <h1 class="reset-title">Vérification</h1>
                <p class="reset-subtitle">Entrez le code à 6 chiffres envoyé à <?php echo htmlspecialchars($_SESSION['reset_email'] ?? ''); ?></p>
            </div>
            
            <div class="step-indicator">
                <div class="step-dot"></div>
                <div class="step-dot active"></div>
                <div class="step-dot"></div>
            </div>
            
            <?php if ($error): ?>
                <div class="alert-modern alert-danger-modern">
                    <i class="bi bi-exclamation-circle"></i>
                    <span><?php echo $error; ?></span>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert-modern alert-success-modern">
                    <i class="bi bi-check-circle"></i>
                    <span><?php echo $success; ?></span>
                </div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="mb-4">
                    <label for="code" class="form-label-modern">Code de vérification</label>
                    <input type="text" class="form-control-modern code-input" id="code" name="code" 
                           placeholder="000000" maxlength="6" required pattern="[0-9]{6}" style="padding-left: 1rem;">
                </div>
                
                <button type="submit" name="code_submit" class="btn-reset">
                    Vérifier le code
                </button>
            </form>
            
        <?php elseif ($step === 'password'): ?>
            <div class="reset-header">
                <div class="reset-icon">
                    <i class="bi bi-lock-fill"></i>
                </div>
                <h1 class="reset-title">Nouveau mot de passe</h1>
                <p class="reset-subtitle">Choisissez un mot de passe sécurisé (min. 6 caractères)</p>
            </div>
            
            <div class="step-indicator">
                <div class="step-dot"></div>
                <div class="step-dot"></div>
                <div class="step-dot active"></div>
            </div>
            
            <?php if ($error): ?>
                <div class="alert-modern alert-danger-modern">
                    <i class="bi bi-exclamation-circle"></i>
                    <span><?php echo $error; ?></span>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert-modern alert-success-modern">
                    <i class="bi bi-check-circle"></i>
                    <span><?php echo $success; ?></span>
                </div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="mb-3">
                    <label for="new_password" class="form-label-modern">Nouveau mot de passe</label>
                    <div class="input-with-icon">
                        <i class="bi bi-lock input-icon"></i>
                        <input type="password" class="form-control-modern" id="new_password" name="new_password" 
                               placeholder="••••••••" required minlength="6">
                    </div>
                </div>
                
                <div class="mb-4">
                    <label for="confirm_password" class="form-label-modern">Confirmer le mot de passe</label>
                    <div class="input-with-icon">
                        <i class="bi bi-lock input-icon"></i>
                        <input type="password" class="form-control-modern" id="confirm_password" name="confirm_password" 
                               placeholder="••••••••" required minlength="6">
                    </div>
                </div>
                
                <button type="submit" name="password_submit" class="btn-reset">
                    Réinitialiser le mot de passe
                </button>
            </form>
            
        <?php else: ?>
            <div class="reset-header text-center">
                <i class="bi bi-check-circle-fill success-icon"></i>
                <h1 class="reset-title">Succès !</h1>
                <p class="reset-subtitle">Votre mot de passe a été réinitialisé avec succès.</p>
            </div>
            
            <a href="login.php" class="btn-reset" style="display: block; text-align: center; text-decoration: none;">
                Se connecter
            </a>
        <?php endif; ?>
        
        <div class="text-center">
            <a href="login.php" class="back-link">
                <i class="bi bi-arrow-left"></i>
                Retour à la connexion
            </a>
        </div>
    </div>
</body>
</html>
