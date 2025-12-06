<?php
/**
 * EXEMPLE DE CONFIGURATION EMAIL
 * 
 * INSTRUCTIONS:
 * 1. Copiez ce fichier en 'email_config.php' dans le même dossier
 * 2. Remplacez les valeurs par vos vraies credentials
 * 3. Ne committez JAMAIS le fichier email_config.php dans Git
 */

// Charger PHPMailer
require_once __DIR__ . '/../vendor/autoload.php';

// Méthode d'envoi: 'php_mail' (simple) ou 'smtp' (recommandé)
define('EMAIL_METHOD', 'smtp');

// Configuration générale
define('EMAIL_FROM', 'votre-email@gmail.com');
define('EMAIL_FROM_NAME', 'Resolve');

// Configuration SMTP (si EMAIL_METHOD = 'smtp')
define('SMTP_HOST', 'smtp.gmail.com');        // Ex: smtp.gmail.com, smtp.office365.com
define('SMTP_PORT', 587);                      // 587 (TLS) ou 465 (SSL)
define('SMTP_USERNAME', 'votre-email@gmail.com');
define('SMTP_PASSWORD', 'votre-mot-de-passe-application');  // Pour Gmail: utilisez un mot de passe d'application
define('SMTP_SECURE', 'tls');                  // 'tls' ou 'ssl'

/**
 * Fonction pour envoyer un email de réinitialisation
 */
function send_reset_email($to_email, $user_name, $reset_code) {
    $subject = "Code de réinitialisation de mot de passe";
    
    // Template HTML de l'email
    $html_message = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #14b8a6 0%, #0ea5e9 100%); padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
            .header h1 { color: white; margin: 0; }
            .content { background: #f9fafb; padding: 30px; border-radius: 0 0 10px 10px; }
            .code-box { background: white; border: 2px dashed #14b8a6; border-radius: 8px; padding: 20px; text-align: center; margin: 20px 0; }
            .code { font-size: 32px; font-weight: bold; color: #14b8a6; letter-spacing: 8px; }
            .footer { text-align: center; margin-top: 20px; color: #6b7280; font-size: 14px; }
            .warning { background: #fef2f2; border-left: 4px solid #ef4444; padding: 15px; margin: 20px 0; border-radius: 4px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>🔐 Réinitialisation de mot de passe</h1>
            </div>
            <div class='content'>
                <p>Bonjour <strong>" . htmlspecialchars($user_name) . "</strong>,</p>
                <p>Vous avez demandé la réinitialisation de votre mot de passe.</p>
                <p>Voici votre code de vérification :</p>
                
                <div class='code-box'>
                    <div class='code'>" . htmlspecialchars($reset_code) . "</div>
                </div>
                
                <div class='warning'>
                    ⚠️ <strong>Important :</strong> Ce code expire dans <strong>15 minutes</strong>.
                </div>
                
                <p>Si vous n'avez pas demandé cette réinitialisation, ignorez simplement cet email.</p>
                
                <div class='footer'>
                    <p>Cet email a été envoyé automatiquement, merci de ne pas y répondre.</p>
                    <p>&copy; " . date('Y') . " Resolve. Tous droits réservés.</p>
                </div>
            </div>
        </div>
    </body>
    </html>
    ";
    
    // Version texte (fallback)
    $text_message = "
Bonjour $user_name,

Vous avez demandé la réinitialisation de votre mot de passe.

Votre code de vérification : $reset_code

Ce code expire dans 15 minutes.

Si vous n'avez pas demandé cette réinitialisation, ignorez cet email.

---
Resolve
";
    
    if (EMAIL_METHOD === 'smtp') {
        return send_email_smtp($to_email, $subject, $html_message, $text_message);
    } else {
        return send_email_php_mail($to_email, $subject, $html_message, $text_message);
    }
}

/**
 * Méthode 1: PHP mail() - Simple mais limité
 */
function send_email_php_mail($to, $subject, $html_message, $text_message) {
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: " . EMAIL_FROM_NAME . " <" . EMAIL_FROM . ">\r\n";
    $headers .= "Reply-To: " . EMAIL_FROM . "\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();
    
    return mail($to, $subject, $html_message, $headers);
}

/**
 * Méthode 2: SMTP avec PHPMailer - Recommandé
 * Nécessite: composer require phpmailer/phpmailer
 */
function send_email_smtp($to, $subject, $html_message, $text_message) {
    // Vérifier si PHPMailer est installé
    if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        error_log("PHPMailer n'est pas installé. Utilisez: composer require phpmailer/phpmailer");
        return false;
    }
    
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    
    try {
        // Configuration SMTP
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->Port       = SMTP_PORT;
        $mail->CharSet    = 'UTF-8';
        
        // Expéditeur et destinataire
        $mail->setFrom(EMAIL_FROM, EMAIL_FROM_NAME);
        $mail->addAddress($to);
        
        // Contenu
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $html_message;
        $mail->AltBody = $text_message;
        
        $mail->send();
        return true;
        
    } catch (Exception $e) {
        error_log("Erreur d'envoi d'email: {$mail->ErrorInfo}");
        return false;
    }
}

/**
 * Fonction utilitaire pour tester la configuration email
 */
function test_email_config($test_email) {
    $test_code = "123456";
    $result = send_reset_email($test_email, "Test User", $test_code);
    
    if ($result) {
        return "✅ Email envoyé avec succès à $test_email";
    } else {
        return "❌ Échec de l'envoi. Vérifiez les logs.";
    }
}
?>
