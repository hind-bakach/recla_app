# 📧 Guide d'Installation de l'Envoi d'Emails

## 🎯 Vue d'ensemble

Deux méthodes sont disponibles pour envoyer des emails :

1. **PHP mail()** - Simple mais limité
2. **SMTP avec PHPMailer** - Recommandé pour la production

---

## 🚀 Méthode 1 : PHP mail() (Rapide)

### Configuration XAMPP

#### 1. Configurer `php.ini`
```ini
# Fichier: C:\xampp\php\php.ini

[mail function]
SMTP = smtp.gmail.com
smtp_port = 587
sendmail_path = "\"C:\xampp\sendmail\sendmail.exe\" -t"
```

#### 2. Configurer `sendmail.ini`
```ini
# Fichier: C:\xampp\sendmail\sendmail.ini

smtp_server=smtp.gmail.com
smtp_port=587
auth_username=votre-email@gmail.com
auth_password=abcd efgh ijkl mnop
force_sender=votre-email@gmail.com
```

#### 3. Dans `email_config.php`
```php
define('EMAIL_METHOD', 'php_mail');
define('EMAIL_FROM', 'votre-email@gmail.com');
define('EMAIL_FROM_NAME', 'Gestion des Réclamations');
```

#### 4. Redémarrer Apache
```bash
# Dans le panneau XAMPP: Stop puis Start Apache
```

---

## ⭐ Méthode 2 : SMTP avec PHPMailer (Recommandé)

### Installation

#### 1. Installer Composer (si pas déjà fait)
Téléchargez depuis : https://getcomposer.org/download/

#### 2. Installer PHPMailer
```bash
cd C:\xampp\htdocs\recla_app
composer require phpmailer/phpmailer
```

#### 3. Créer un mot de passe d'application Gmail

1. Allez sur : https://myaccount.google.com/security
2. Activez **Validation en 2 étapes**
3. Recherchez **"Mots de passe des applications"**
4. Sélectionnez "Mail" et générez
5. Copiez le mot de passe de 16 caractères (ex: `abcd efgh ijkl mnop`)

#### 4. Configurer `email_config.php`

**Pour Gmail :**
```php
define('EMAIL_METHOD', 'smtp');
define('EMAIL_FROM', 'votre-email@gmail.com');
define('EMAIL_FROM_NAME', 'Gestion des Réclamations');

define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'votre-email@gmail.com');
define('SMTP_PASSWORD', 'abcd efgh ijkl mnop'); // Mot de passe app
define('SMTP_SECURE', 'tls');
```

**Pour Outlook/Office365 :**
```php
define('SMTP_HOST', 'smtp.office365.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'votre-email@outlook.com');
define('SMTP_PASSWORD', 'votre-mot-de-passe');
define('SMTP_SECURE', 'tls');
```

**Pour autres fournisseurs :**
```php
# Yahoo Mail
define('SMTP_HOST', 'smtp.mail.yahoo.com');
define('SMTP_PORT', 587);

# Mailtrap (Test)
define('SMTP_HOST', 'smtp.mailtrap.io');
define('SMTP_PORT', 2525);
```

---

## 🧪 Tester la Configuration

### 1. Via l'interface web
```
http://localhost/recla_app/frontend/test_email.php
```
Entrez votre email et cliquez sur "Envoyer l'email de test"

### 2. Via PHP (script temporaire)
```php
<?php
require_once 'includes/config.php';
require_once 'includes/email_config.php';

$result = send_reset_email('test@example.com', 'Test User', '123456');
echo $result ? "✅ Envoyé" : "❌ Échec";
?>
```

---

## 🔧 Mode Développement vs Production

### En développement (actuel)
```php
# Dans mot-de-passe-oublie.php
define('DEV_MODE', true); // Le code s'affiche à l'écran
```

### En production
```php
# Dans mot-de-passe-oublie.php
define('DEV_MODE', false); // Le code est envoyé par email
```

---

## 🐛 Résolution des Problèmes

### Erreur : "SMTP connect() failed"

**Solution Gmail :**
- Vérifiez que la validation en 2 étapes est activée
- Utilisez un mot de passe d'application (pas votre mot de passe normal)
- Autorisez les applications moins sécurisées : https://myaccount.google.com/lesssecureapps

**Solution générale :**
```php
// Désactiver temporairement SSL (DÉVELOPPEMENT uniquement)
$mail->SMTPOptions = array(
    'ssl' => array(
        'verify_peer' => false,
        'verify_peer_name' => false,
        'allow_self_signed' => true
    )
);
```

### Erreur : "Could not instantiate mail function"
- Vérifiez que `sendmail.exe` existe dans `C:\xampp\sendmail\`
- Redémarrez Apache après modification de `php.ini`

### Les emails vont dans SPAM
- Utilisez un domaine vérifié (pas localhost)
- Ajoutez des enregistrements SPF/DKIM
- Utilisez un service SMTP professionnel (SendGrid, Mailgun, AWS SES)

---

## 📊 Services SMTP Recommandés (Production)

| Service | Emails Gratuits/Mois | Prix | Fiabilité |
|---------|---------------------|------|-----------|
| SendGrid | 100/jour | $15/mois | ⭐⭐⭐⭐⭐ |
| Mailgun | 5,000 | $15/mois | ⭐⭐⭐⭐⭐ |
| AWS SES | 62,000 | $0.10/1000 | ⭐⭐⭐⭐⭐ |
| Mailtrap | Illimité (test) | Gratuit | ⭐⭐⭐⭐ |
| Gmail | 500/jour | Gratuit | ⭐⭐⭐ |

---

## 📝 Checklist de Mise en Production

- [ ] Installer PHPMailer via Composer
- [ ] Configurer les identifiants SMTP dans `email_config.php`
- [ ] Tester avec `test_email.php`
- [ ] Passer `DEV_MODE` à `false` dans `mot-de-passe-oublie.php`
- [ ] Supprimer `test_email.php` en production
- [ ] Vérifier les logs d'erreur PHP
- [ ] Tester la réception d'emails
- [ ] Vérifier que les emails ne vont pas en SPAM

---

## 🔐 Sécurité

### Ne jamais commiter les credentials
```bash
# Ajouter à .gitignore
includes/email_config.php
```

### Utiliser des variables d'environnement
```php
define('SMTP_PASSWORD', getenv('SMTP_PASSWORD'));
```

---

## 📞 Support

En cas de problème :
1. Vérifiez les logs PHP : `C:\xampp\php\logs\php_error_log`
2. Testez avec `test_email.php`
3. Activez le mode debug PHPMailer :
```php
$mail->SMTPDebug = 2; // Affiche les détails SMTP
```

---

**Dernière mise à jour :** 6 décembre 2025
