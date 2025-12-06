<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/email_config.php';

$success = '';
$error = '';
$step = 'email'; // email, code, password

// Mode de développement (afficher le code) ou production (envoyer l'email)
define('DEV_MODE', false); // Mode PRODUCTION activé - emails envoyés automatiquement

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
            
            // MODE DÉVELOPPEMENT: Afficher le code
            if (DEV_MODE) {
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
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mot de passe oublié - Resolve</title>
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/modern.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #14b8a6 0%, #0ea5e9 100%);
            font-family: 'Inter', sans-serif;
            padding: 2rem 1rem;
        }
        
        .reset-container {
            background: white;
            border-radius: 1.5rem;
            box-shadow: 0 20px 60px rgba(0,0,0,0.15);
            max-width: 480px;
            width: 100%;
            padding: 3rem 2.5rem;
            animation: fadeInUp 0.5s ease-out;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .reset-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .reset-icon {
            width: 64px;
            height: 64px;
            background: linear-gradient(135deg, #14b8a6 0%, #0ea5e9 100%);
            border-radius: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
        }
        
        .reset-icon i {
            font-size: 2rem;
            color: white;
        }
        
        .reset-title {
            font-size: 1.75rem;
            font-weight: 700;
            color: #111827;
            margin-bottom: 0.5rem;
        }
        
        .reset-subtitle {
            color: #6b7280;
            font-size: 0.938rem;
        }
        
        .form-label-modern {
            font-weight: 600;
            color: #374151;
            font-size: 0.875rem;
            margin-bottom: 0.5rem;
            display: block;
        }
        
        .form-control-modern {
            width: 100%;
            padding: 0.875rem 1rem;
            padding-left: 3rem;
            border: 2px solid #e5e7eb;
            border-radius: 0.75rem;
            font-size: 1rem;
            transition: all 0.2s;
        }
        
        .form-control-modern:focus {
            outline: none;
            border-color: #14b8a6;
            box-shadow: 0 0 0 4px rgba(20, 184, 166, 0.1);
        }
        
        .input-with-icon {
            position: relative;
        }
        
        .input-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
            font-size: 1.125rem;
        }
        
        .btn-reset {
            width: 100%;
            padding: 0.875rem;
            background: linear-gradient(135deg, #14b8a6 0%, #0ea5e9 100%);
            color: white;
            border: none;
            border-radius: 0.75rem;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .btn-reset:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(20, 184, 166, 0.3);
        }
        
        .alert-modern {
            padding: 1rem;
            border-radius: 0.75rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .alert-danger-modern {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        
        .alert-success-modern {
            background: #f0fdf4;
            color: #166534;
            border: 1px solid #bbf7d0;
        }
        
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: #6b7280;
            text-decoration: none;
            font-weight: 500;
            margin-top: 1.5rem;
            transition: color 0.2s;
        }
        
        .back-link:hover {
            color: #111827;
        }
        
        .code-input {
            text-align: center;
            font-size: 1.5rem;
            letter-spacing: 0.5rem;
            font-weight: 600;
        }
        
        .success-icon {
            font-size: 3rem;
            color: #10b981;
            margin-bottom: 1rem;
        }
        
        .step-indicator {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            margin-bottom: 2rem;
        }
        
        .step-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #e5e7eb;
            transition: all 0.3s;
        }
        
        .step-dot.active {
            width: 24px;
            border-radius: 4px;
            background: linear-gradient(135deg, #14b8a6 0%, #0ea5e9 100%);
        }
    </style>
</head>
<body>
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
