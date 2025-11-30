<?php
require_once '../includes/config.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Réclamations - Plateforme Intelligente</title>
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/modern.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
</head>
<body>

    <!-- Modern Navigation -->
    <nav class="navbar-modern" id="mainNav">
        <div class="navbar-container">
            <a href="index.php" class="navbar-logo">
                <i class="bi bi-chat-square-dots-fill" style="color: #14b8a6;"></i>
                <span>Gestion des <span class="text-gradient">Réclamations</span></span>
            </a>
            
            <ul class="navbar-menu">
                <li><a href="login.php" class="btn-outline-modern btn-sm-modern">
                    <i class="bi bi-box-arrow-in-right"></i>
                    Se connecter
                </a></li>
                <li><a href="soumission.php" class="btn-primary-modern btn-sm-modern">
                    Soumettre une Réclamation
                    <i class="bi bi-arrow-right icon-arrow"></i>
                </a></li>
            </ul>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-modern">
        <div class="hero-background">
            <div class="hero-shape hero-shape-1"></div>
            <div class="hero-shape hero-shape-2"></div>
            <div class="hero-shape hero-shape-3"></div>
        </div>
        
        <div class="hero-container">
            <div class="hero-content">
                <div class="hero-badge">
                    <i class="bi bi-stars" style="color: #f59e0b;"></i>
                    <span>Plateforme Nouvelle Génération</span>
                </div>
                
                <h1 class="hero-title">
                    Simplifiez la Soumission.<br>
                    Assurez la <span class="text-gradient">Traçabilité.</span>
                </h1>
                
                <p class="hero-description">
                    Optimisez la journée du patient du début à la fin avec le pouvoir de l'IA, 
                    proporciondant une expérience personnalisée qui fidélise patients et impulsiona 
                    le crescimento da sua clínica.
                </p>
                
                <div class="hero-cta">
                    <a href="soumission.php" class="btn-modern btn-gold-modern btn-lg-modern">
                        <i class="bi bi-send-fill"></i>
                        Déposer votre Réclamation
                        <i class="bi bi-arrow-right icon-arrow"></i>
                    </a>
                    <a href="login.php" class="btn-modern btn-outline-modern btn-lg-modern">
                        <i class="bi bi-person-circle"></i>
                        Espace Utilisateur
                    </a>
                </div>
            </div>
            
            <div class="hero-visual">
                <svg viewBox="0 0 600 600" xmlns="http://www.w3.org/2000/svg" class="hero-illustration">
                    <!-- 3D Isometric Illustration -->
                    <!-- Dashboard Device -->
                    <g id="dashboard">
                        <rect x="200" y="150" width="300" height="200" rx="15" fill="#fff" stroke="#e5e7eb" stroke-width="3"/>
                        <rect x="200" y="150" width="300" height="40" rx="15" fill="#2563eb"/>
                        <circle cx="225" cy="170" r="8" fill="#fff" opacity="0.5"/>
                        <circle cx="250" cy="170" r="8" fill="#fff" opacity="0.5"/>
                        <circle cx="275" cy="170" r="8" fill="#fff" opacity="0.5"/>
                        
                        <!-- Charts -->
                        <rect x="220" y="210" width="120" height="80" rx="8" fill="#eff6ff"/>
                        <path d="M230,270 L260,240 L290,260 L320,230" stroke="#2563eb" stroke-width="3" fill="none"/>
                        
                        <rect x="360" y="210" width="120" height="80" rx="8" fill="#fef3c7"/>
                        <circle cx="420" cy="250" r="25" fill="none" stroke="#f59e0b" stroke-width="8" stroke-dasharray="80 40"/>
                    </g>
                    
                    <!-- User submitting form -->
                    <g id="user">
                        <circle cx="120" cy="300" r="40" fill="#dbeafe"/>
                        <circle cx="120" cy="285" r="20" fill="#2563eb"/>
                        <path d="M90,320 Q120,310 150,320" fill="#2563eb"/>
                        <rect x="70" y="350" width="100" height="120" rx="10" fill="#fff" stroke="#e5e7eb" stroke-width="2"/>
                        <line x1="85" y1="370" x2="145" y2="370" stroke="#9ca3af" stroke-width="2"/>
                        <line x1="85" y1="390" x2="145" y2="390" stroke="#9ca3af" stroke-width="2"/>
                        <line x1="85" y1="410" x2="125" y2="410" stroke="#9ca3af" stroke-width="2"/>
                        <rect x="85" y="435" width="60" height="20" rx="5" fill="#f59e0b"/>
                    </g>
                    
                    <!-- Cloud connection -->
                    <g id="cloud">
                        <ellipse cx="300" cy="100" rx="50" ry="30" fill="#eff6ff"/>
                        <ellipse cx="280" cy="110" rx="35" ry="25" fill="#dbeafe"/>
                        <ellipse cx="320" cy="110" rx="40" ry="25" fill="#dbeafe"/>
                        <circle cx="300" cy="95" r="5" fill="#2563eb"/>
                        <path d="M120,300 L120,280 Q150,100 280,110" stroke="#93c5fd" stroke-width="2" stroke-dasharray="5,5" fill="none" opacity="0.6"/>
                        <path d="M320,110 Q400,120 450,160" stroke="#93c5fd" stroke-width="2" stroke-dasharray="5,5" fill="none" opacity="0.6"/>
                    </g>
                    
                    <!-- Success check -->
                    <g id="success">
                        <circle cx="500" cy="400" r="50" fill="#10b981"/>
                        <path d="M475,400 L490,415 L525,380" stroke="#fff" stroke-width="8" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
                        <circle cx="500" cy="400" r="55" fill="none" stroke="#10b981" stroke-width="3" opacity="0.3">
                            <animate attributeName="r" from="55" to="70" dur="2s" repeatCount="indefinite"/>
                            <animate attributeName="opacity" from="0.3" to="0" dur="2s" repeatCount="indefinite"/>
                        </circle>
                    </g>
                </svg>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="features-section">
        <div class="features-container">
            <div class="text-center mb-6">
                <h2 class="display-2 mb-4">Les Avantages de Notre <span class="text-gradient">Plateforme</span></h2>
                <p style="font-size: 1.25rem; color: var(--gray-600); max-width: 700px; margin: 0 auto;">
                    Une solution complète pour simplifier la gestion de vos réclamations avec traçabilité totale
                </p>
            </div>
            
            <div class="features-grid">
            <div class="features-grid">
                <div class="feature-card animate-on-scroll">
                    <div class="feature-icon">
                        <i class="bi bi-send-check-fill"></i>
                    </div>
                    <h3 class="feature-title">Soumission Simplifiée</h3>
                    <p class="feature-description">
                        Interface intuitive permettant de déposer une réclamation en quelques clics. 
                        Guidage pas à pas pour ne rien oublier.
                    </p>
                </div>

                <div class="feature-card animate-on-scroll">
                    <div class="feature-icon" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">
                        <i class="bi bi-clock-history"></i>
                    </div>
                    <h3 class="feature-title">Traçabilité Complète</h3>
                    <p class="feature-description">
                        Suivez chaque étape du traitement en temps réel. Historique complet et transparent 
                        de toutes les actions effectuées.
                    </p>
                </div>

                <div class="feature-card animate-on-scroll">
                    <div class="feature-icon" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);">
                        <i class="bi bi-bell-fill"></i>
                    </div>
                    <h3 class="feature-title">Notifications Intelligentes</h3>
                    <p class="feature-description">
                        Recevez des alertes automatiques à chaque changement de statut. 
                        Ne manquez aucune mise à jour importante.
                    </p>
                </div>

                <div class="feature-card animate-on-scroll">
                    <div class="feature-icon" style="background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);">
                        <i class="bi bi-person-workspace"></i>
                    </div>
                    <h3 class="feature-title">Espace Gestionnaire</h3>
                    <p class="feature-description">
                        Tableaux de bord avancés avec analytics en temps réel pour une gestion 
                        efficace et des prises de décisions éclairées.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="section-modern" style="background: linear-gradient(135deg, #2563eb 0%, #7c3aed 100%); color: white;">
        <div class="container-modern text-center">
            <h2 class="display-2 mb-4" style="color: white;">Prêt à Commencer ?</h2>
            <p class="mb-6" style="font-size: 1.25rem; opacity: 0.9; max-width: 600px; margin: 0 auto 2rem;">
                Rejoignez des centaines d'organisations qui font confiance à notre plateforme 
                pour gérer leurs réclamations efficacement.
            </p>
            <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
                <a href="soumission.php" class="btn-modern btn-gold-modern btn-lg-modern">
                    <i class="bi bi-send-fill"></i>
                    Soumettre une Réclamation
                    <i class="bi bi-arrow-right icon-arrow"></i>
                </a>
                <a href="register.php" class="btn-modern btn-outline-modern btn-lg-modern" style="background: rgba(255,255,255,0.1); color: white; border-color: white;">
                    <i class="bi bi-person-plus-fill"></i>
                    Créer un Compte
                </a>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer style="background: var(--gray-900); color: var(--gray-400); padding: 3rem 0 1.5rem;">
        <div class="container-modern">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 2rem; margin-bottom: 2rem;">
                <div>
                    <h4 style="color: white; font-weight: 700; margin-bottom: 1rem;">
                        <i class="bi bi-chat-square-dots-fill" style="color: #2563eb;"></i>
                        Gestion des Réclamations
                    </h4>
                    <p>Plateforme intelligente de gestion et traçabilité des réclamations pour organisations modernes.</p>
                </div>
                <div>
                    <h5 style="color: white; font-weight: 600; margin-bottom: 1rem;">Liens Rapides</h5>
                    <ul style="list-style: none; padding: 0;">
                        <li style="margin-bottom: 0.5rem;"><a href="soumission.php" style="color: var(--gray-400); text-decoration: none;">Soumettre Réclamation</a></li>
                        <li style="margin-bottom: 0.5rem;"><a href="login.php" style="color: var(--gray-400); text-decoration: none;">Se Connecter</a></li>
                        <li style="margin-bottom: 0.5rem;"><a href="register.php" style="color: var(--gray-400); text-decoration: none;">S'Inscrire</a></li>
                    </ul>
                </div>
                <div>
                    <h5 style="color: white; font-weight: 600; margin-bottom: 1rem;">Support</h5>
                    <ul style="list-style: none; padding: 0;">
                        <li style="margin-bottom: 0.5rem;"><i class="bi bi-envelope"></i> support@reclamations.app</li>
                        <li style="margin-bottom: 0.5rem;"><i class="bi bi-telephone"></i> +212 XXX-XXXX</li>
                    </ul>
                </div>
            </div>
            <div style="border-top: 1px solid var(--gray-800); padding-top: 1.5rem; text-align: center;">
                <p style="margin: 0; font-size: 0.875rem;">© <?php echo date('Y'); ?> Gestion des Réclamations. Tous droits réservés.</p>
            </div>
        </div>
    </footer>

    <script src="../js/bootstrap.bundle.min.js"></script>
    <script src="../js/main.js"></script>
</body>
</html>