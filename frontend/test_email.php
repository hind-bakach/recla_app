<?php
/**
 * Script de test pour la configuration email
 * Usage: Accédez à ce fichier dans votre navigateur
 */

require_once '../includes/config.php';
require_once '../includes/email_config.php';

// Sécurité: Limiter l'accès en développement uniquement
if (!defined('DEV_MODE') || !DEV_MODE) {
    die('Ce script n\'est accessible qu\'en mode développement.');
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Configuration Email</title>
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body { padding: 40px; background: #f3f4f6; }
        .test-container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .config-item { padding: 15px; margin: 10px 0; background: #f9fafb; border-left: 4px solid #14b8a6; border-radius: 4px; }
        .config-label { font-weight: 600; color: #374151; }
        .config-value { color: #6b7280; font-family: monospace; }
        .test-form { margin-top: 30px; padding-top: 30px; border-top: 2px solid #e5e7eb; }
        .alert { padding: 15px; border-radius: 8px; margin: 20px 0; }
        .alert-success { background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; }
        .alert-danger { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
        .alert-info { background: #eff6ff; color: #1e40af; border: 1px solid #bfdbfe; }
        .btn-test { background: linear-gradient(135deg, #14b8a6 0%, #0ea5e9 100%); color: white; padding: 12px 24px; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; }
        .btn-test:hover { opacity: 0.9; }
        pre { background: #1f2937; color: #f9fafb; padding: 15px; border-radius: 8px; overflow-x: auto; }
        code { color: #14b8a6; }
    </style>
</head>
<body>
    <div class="test-container">
        <h1><i class="bi bi-envelope-check"></i> Test Configuration Email</h1>
        <p class="text-muted">Vérifiez votre configuration d'envoi d'emails</p>
        
        <h3 class="mt-4">📋 Configuration Actuelle</h3>
        
        <div class="config-item">
            <div class="config-label">Méthode d'envoi:</div>
            <div class="config-value"><?php echo EMAIL_METHOD; ?></div>
        </div>
        
        <div class="config-item">
            <div class="config-label">Email expéditeur:</div>
            <div class="config-value"><?php echo EMAIL_FROM; ?></div>
        </div>
        
        <div class="config-item">
            <div class="config-label">Nom expéditeur:</div>
            <div class="config-value"><?php echo EMAIL_FROM_NAME; ?></div>
        </div>
        
        <?php if (EMAIL_METHOD === 'smtp'): ?>
            <div class="config-item">
                <div class="config-label">Serveur SMTP:</div>
                <div class="config-value"><?php echo SMTP_HOST . ':' . SMTP_PORT; ?></div>
            </div>
            
            <div class="config-item">
                <div class="config-label">Sécurité:</div>
                <div class="config-value"><?php echo strtoupper(SMTP_SECURE); ?></div>
            </div>
            
            <div class="config-item">
                <div class="config-label">PHPMailer installé:</div>
                <div class="config-value">
                    <?php 
                    if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
                        echo '✅ Oui';
                    } else {
                        echo '❌ Non - Installez avec: <code>composer require phpmailer/phpmailer</code>';
                    }
                    ?>
                </div>
            </div>
        <?php endif; ?>
        
        <?php
        $result = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_email'])) {
            $test_email = filter_var($_POST['test_email'], FILTER_SANITIZE_EMAIL);
            
            if (filter_var($test_email, FILTER_VALIDATE_EMAIL)) {
                $result = test_email_config($test_email);
            } else {
                $result = '❌ Adresse email invalide';
            }
        }
        ?>
        
        <?php if ($result): ?>
            <div class="alert <?php echo strpos($result, '✅') !== false ? 'alert-success' : 'alert-danger'; ?>">
                <?php echo $result; ?>
            </div>
        <?php endif; ?>
        
        <div class="test-form">
            <h3>🧪 Envoyer un email de test</h3>
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label">Adresse email de test:</label>
                    <input type="email" name="test_email" class="form-control" 
                           placeholder="test@example.com" required>
                    <small class="text-muted">Un email de test sera envoyé à cette adresse</small>
                </div>
                <button type="submit" class="btn-test">
                    <i class="bi bi-send"></i> Envoyer l'email de test
                </button>
            </form>
        </div>
        
        <div class="mt-5 pt-4" style="border-top: 2px solid #e5e7eb;">
            <h3>📚 Guide de Configuration</h3>
            
            <div class="alert alert-info">
                <strong><i class="bi bi-info-circle"></i> Méthode 1: PHP mail()</strong>
                <p class="mb-0 mt-2">Simple mais nécessite un serveur avec <code>sendmail</code> configuré.</p>
            </div>
            
            <h5 class="mt-4">Activer PHP mail() dans XAMPP:</h5>
            <pre>1. Ouvrir: C:\xampp\php\php.ini
2. Rechercher: [mail function]
3. Configurer:
   SMTP = smtp.gmail.com
   smtp_port = 587
   sendmail_path = "\"C:\xampp\sendmail\sendmail.exe\" -t"
   
4. Ouvrir: C:\xampp\sendmail\sendmail.ini
5. Configurer:
   smtp_server=smtp.gmail.com
   smtp_port=587
   auth_username=votre-email@gmail.com
   auth_password=votre-mot-de-passe-app
   force_sender=votre-email@gmail.com
   
6. Redémarrer Apache</pre>
            
            <div class="alert alert-info mt-4">
                <strong><i class="bi bi-info-circle"></i> Méthode 2: SMTP avec PHPMailer (Recommandé)</strong>
                <p class="mb-0 mt-2">Plus fiable, fonctionne avec Gmail, Office365, etc.</p>
            </div>
            
            <h5 class="mt-4">Installer PHPMailer:</h5>
            <pre>cd C:\xampp\htdocs\recla_app
composer require phpmailer/phpmailer</pre>
            
            <h5 class="mt-4">Configuration Gmail (Mot de passe d'application):</h5>
            <ol>
                <li>Allez sur: <a href="https://myaccount.google.com/security" target="_blank">https://myaccount.google.com/security</a></li>
                <li>Activez la validation en 2 étapes</li>
                <li>Recherchez "Mots de passe des applications"</li>
                <li>Créez un nouveau mot de passe pour "Mail"</li>
                <li>Copiez le mot de passe de 16 caractères</li>
                <li>Utilisez-le dans <code>email_config.php</code></li>
            </ol>
            
            <h5 class="mt-4">Exemple de configuration Gmail:</h5>
            <pre>define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'votre-email@gmail.com');
define('SMTP_PASSWORD', 'abcd efgh ijkl mnop'); // Mot de passe app
define('SMTP_SECURE', 'tls');</pre>
            
            <h5 class="mt-4">Exemple pour Office365/Outlook:</h5>
            <pre>define('SMTP_HOST', 'smtp.office365.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'votre-email@outlook.com');
define('SMTP_PASSWORD', 'votre-mot-de-passe');
define('SMTP_SECURE', 'tls');</pre>
        </div>
    </div>
</body>
</html>
