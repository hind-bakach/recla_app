<?php
require_once '../includes/config.php';
require_once '../includes/lang.php';

$page_title = t('home_title') . ' - ' . t('home_subtitle');
$extra_head_content = '
<style>
    <style>
        .lang-switcher {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }
        .lang-btn {
            padding: 0.375rem 0.75rem;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }
        .lang-btn.active {
            background: linear-gradient(135deg, #14b8a6 0%, #0ea5e9 100%);
            color: white;
        }
        .lang-btn:not(.active) {
            color: #6b7280;
            border-color: #e5e7eb;
            background: white;
        }
        .lang-btn:not(.active):hover {
            border-color: #14b8a6;
            color: #14b8a6;
        }
</style>
';
?>
<?php include '../includes/head_frontend.php'; ?>

    <!-- Modern Navigation -->
    <nav class="navbar-modern" id="mainNav">
        <div class="navbar-container">
            <a href="index.php" class="navbar-logo">
                <i class="bi bi-check-circle-fill" style="color: #14b8a6;"></i>
                <span class="text-gradient"><?php echo t('home_title'); ?></span>
            </a>
            
            <ul class="navbar-menu">
                <li class="lang-switcher">
                    <a href="<?php echo lang_url('fr'); ?>" class="lang-btn <?php echo current_lang() == 'fr' ? 'active' : ''; ?>">
                        🇫🇷 FR
                    </a>
                    <a href="<?php echo lang_url('en'); ?>" class="lang-btn <?php echo current_lang() == 'en' ? 'active' : ''; ?>">
                        🇬🇧 EN
                    </a>
                </li>
                <li><a href="login.php" class="btn-outline-modern btn-sm-modern">
                    <i class="bi bi-box-arrow-in-right"></i>
                    <?php echo t('nav_login'); ?>
                </a></li>
                <li><a href="soumission.php" class="btn-primary-modern btn-sm-modern">
                    <?php echo t('nav_submit_claim'); ?>
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
                    <span><?php echo t('home_hero_badge'); ?></span>
                </div>
                
                <h1 class="hero-title">
                    <?php echo t('home_hero_title_1'); ?><br>
                    <?php echo t('home_hero_title_2'); ?> <span class="text-gradient"><?php echo t('home_hero_title_highlight'); ?></span>
                </h1>
                
                <p class="hero-description">
                    <?php echo t('home_hero_description'); ?>
                </p>
                
                <div class="hero-cta">
                    <a href="soumission.php" class="btn-modern btn-gold-modern btn-lg-modern">
                        <i class="bi bi-send-fill"></i>
                        <?php echo t('home_hero_cta_submit'); ?>
                        <i class="bi bi-arrow-right icon-arrow"></i>
                    </a>
                    <a href="login.php" class="btn-modern btn-outline-modern btn-lg-modern">
                        <i class="bi bi-person-circle"></i>
                        <?php echo t('home_hero_cta_login'); ?>
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
                <h2 class="display-2 mb-4"><?php echo t('home_features_title'); ?> <span class="text-gradient"><?php echo t('home_features_title_highlight'); ?></span></h2>
                <p style="font-size: 1.25rem; color: var(--gray-600); max-width: 700px; margin: 0 auto;">
                    <?php echo t('home_features_subtitle'); ?>
                </p>
            </div>
            
            <div class="features-grid">
            <div class="features-grid">
                <div class="feature-card animate-on-scroll">
                    <div class="feature-icon">
                        <i class="bi bi-send-check-fill"></i>
                    </div>
                    <h3 class="feature-title"><?php echo t('home_feature_1_title'); ?></h3>
                    <p class="feature-description">
                        <?php echo t('home_feature_1_desc'); ?>
                    </p>
                </div>

                <div class="feature-card animate-on-scroll">
                    <div class="feature-icon" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">
                        <i class="bi bi-clock-history"></i>
                    </div>
                    <h3 class="feature-title"><?php echo t('home_feature_2_title'); ?></h3>
                    <p class="feature-description">
                        <?php echo t('home_feature_2_desc'); ?>
                    </p>
                </div>

                <div class="feature-card animate-on-scroll">
                    <div class="feature-icon" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);">
                        <i class="bi bi-bell-fill"></i>
                    </div>
                    <h3 class="feature-title"><?php echo t('home_feature_3_title'); ?></h3>
                    <p class="feature-description">
                        <?php echo t('home_feature_3_desc'); ?>
                    </p>
                </div>

                <div class="feature-card animate-on-scroll">
                    <div class="feature-icon" style="background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);">
                        <i class="bi bi-person-workspace"></i>
                    </div>
                    <h3 class="feature-title"><?php echo t('home_feature_4_title'); ?></h3>
                    <p class="feature-description">
                        <?php echo t('home_feature_4_desc'); ?>
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="section-modern" style="background: linear-gradient(135deg, #2563eb 0%, #7c3aed 100%); color: white;">
        <div class="container-modern text-center">
            <h2 class="display-2 mb-4" style="color: white;"><?php echo t('home_cta_title'); ?></h2>
            <p class="mb-6" style="font-size: 1.25rem; opacity: 0.9; max-width: 600px; margin: 0 auto 2rem;">
                <?php echo t('home_cta_description'); ?>
            </p>
            <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
                <a href="soumission.php" class="btn-modern btn-gold-modern btn-lg-modern">
                    <i class="bi bi-send-fill"></i>
                    <?php echo t('home_cta_submit'); ?>
                    <i class="bi bi-arrow-right icon-arrow"></i>
                </a>
                <a href="register.php" class="btn-modern btn-outline-modern btn-lg-modern" style="background: rgba(255,255,255,0.1); color: white; border-color: white;">
                    <i class="bi bi-person-plus-fill"></i>
                    <?php echo t('home_cta_register'); ?>
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
                        <i class="bi bi-check-circle-fill" style="color: #2563eb;"></i>
                        <?php echo t('home_title'); ?>
                    </h4>
                    <p><?php echo t('home_footer_description'); ?></p>
                </div>
                <div>
                    <h5 style="color: white; font-weight: 600; margin-bottom: 1rem;"><?php echo t('home_footer_quick_links'); ?></h5>
                    <ul style="list-style: none; padding: 0;">
                        <li style="margin-bottom: 0.5rem;"><a href="soumission.php" style="color: var(--gray-400); text-decoration: none;"><?php echo t('home_footer_submit_claim'); ?></a></li>
                        <li style="margin-bottom: 0.5rem;"><a href="login.php" style="color: var(--gray-400); text-decoration: none;"><?php echo t('home_footer_login'); ?></a></li>
                        <li style="margin-bottom: 0.5rem;"><a href="register.php" style="color: var(--gray-400); text-decoration: none;"><?php echo t('home_footer_register'); ?></a></li>
                    </ul>
                </div>
                <div>
                    <h5 style="color: white; font-weight: 600; margin-bottom: 1rem;"><?php echo t('home_footer_support'); ?></h5>
                    <ul style="list-style: none; padding: 0;">
                        <li style="margin-bottom: 0.5rem;"><i class="bi bi-envelope"></i> <?php echo t('home_footer_email'); ?></li>
                        <li style="margin-bottom: 0.5rem;"><i class="bi bi-telephone"></i> <?php echo t('home_footer_phone'); ?></li>
                    </ul>
                </div>
            </div>
            <div style="border-top: 1px solid var(--gray-800); padding-top: 1.5rem; text-align: center;">
                <p style="margin: 0; font-size: 0.875rem;">© <?php echo date('Y'); ?> <?php echo t('home_title'); ?>. <?php echo t('home_footer_copyright'); ?>.</p>
            </div>
        </div>
    </footer>

    <script src="../js/bootstrap.bundle.min.js"></script>
    <script src="../js/main.js"></script>
</body>
</html>