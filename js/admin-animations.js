/**
 * Animations et interactions modernes pour l'interface d'administration
 */

document.addEventListener('DOMContentLoaded', function() {
    
    // ===== ANIMATION AU SCROLL =====
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };
    
    const observer = new IntersectionObserver(function(entries) {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
            }
        });
    }, observerOptions);
    
    // Observer les cartes et tableaux
    document.querySelectorAll('.card, .table-responsive').forEach(el => {
        el.style.opacity = '0';
        el.style.transform = 'translateY(20px)';
        el.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
        observer.observe(el);
    });
    
    // ===== EFFET RIPPLE SUR LES BOUTONS =====
    document.querySelectorAll('.btn').forEach(button => {
        button.addEventListener('click', function(e) {
            const ripple = document.createElement('span');
            const rect = this.getBoundingClientRect();
            const size = Math.max(rect.width, rect.height);
            const x = e.clientX - rect.left - size / 2;
            const y = e.clientY - rect.top - size / 2;
            
            ripple.style.width = ripple.style.height = size + 'px';
            ripple.style.left = x + 'px';
            ripple.style.top = y + 'px';
            ripple.classList.add('ripple');
            
            this.appendChild(ripple);
            
            setTimeout(() => ripple.remove(), 600);
        });
    });
    
    // ===== ANIMATIONS DES LIGNES DE TABLEAU =====
    const tableRows = document.querySelectorAll('.table tbody tr');
    tableRows.forEach((row, index) => {
        row.style.animationDelay = (index * 0.05) + 's';
    });
    
    // ===== EFFET DE FOCUS AMÉLIIORÉ SUR LES INPUTS =====
    document.querySelectorAll('.form-control, .form-select').forEach(input => {
        input.addEventListener('focus', function() {
            this.parentElement.classList.add('input-focused');
        });
        
        input.addEventListener('blur', function() {
            this.parentElement.classList.remove('input-focused');
        });
    });
    
    // ===== ANIMATION DES BADGES AU HOVER =====
    document.querySelectorAll('.badge').forEach(badge => {
        badge.addEventListener('mouseenter', function() {
            this.style.transform = 'scale(1.1) rotate(-2deg)';
        });
        
        badge.addEventListener('mouseleave', function() {
            this.style.transform = 'scale(1) rotate(0deg)';
        });
    });
    
    // ===== TOOLTIP PERSONNALISÉ =====
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl, {
            animation: true,
            delay: { show: 300, hide: 100 }
        });
    });
    
    // ===== ANIMATION DES CARTES STATISTIQUES =====
    const statCards = document.querySelectorAll('.col-md-3 .card');
    statCards.forEach((card, index) => {
        card.style.animationDelay = (index * 0.1) + 's';
        
        // Animation des chiffres (compteur)
        const displayNumber = card.querySelector('.display-4, h2, h3');
        if (displayNumber) {
            const finalNumber = parseInt(displayNumber.textContent) || 0;
            if (finalNumber > 0 && finalNumber < 10000) {
                animateCounter(displayNumber, 0, finalNumber, 1500);
            }
        }
    });
    
    // ===== FONCTION COMPTEUR ANIMÉ =====
    function animateCounter(element, start, end, duration) {
        const range = end - start;
        const increment = range / (duration / 16);
        let current = start;
        
        const timer = setInterval(() => {
            current += increment;
            if (current >= end) {
                element.textContent = end;
                clearInterval(timer);
            } else {
                element.textContent = Math.round(current);
            }
        }, 16);
    }
    
    // ===== EFFET DE CHARGEMENT SUR LES FORMULAIRES =====
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', function() {
            const submitBtn = this.querySelector('button[type="submit"]');
            if (submitBtn && !submitBtn.disabled) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Traitement...';
                submitBtn.classList.add('loading');
            }
        });
    });
    
    // ===== ANIMATION DES ALERTES =====
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        // Auto-dismiss après 5 secondes
        setTimeout(() => {
            alert.style.opacity = '0';
            alert.style.transform = 'translateX(100%)';
            setTimeout(() => {
                if (alert.parentElement) {
                    alert.remove();
                }
            }, 500);
        }, 5000);
    });
    
    // ===== SMOOTH SCROLL POUR LES ANCRES =====
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                e.preventDefault();
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });
    
    // ===== ANIMATION DES MODALS =====
    document.querySelectorAll('.modal').forEach(modal => {
        modal.addEventListener('show.bs.modal', function () {
            this.querySelector('.modal-dialog').style.animation = 'scaleIn 0.3s ease-out';
        });
    });
    
    // ===== EFFET SHAKE SUR LES ERREURS DE VALIDATION =====
    document.querySelectorAll('.is-invalid').forEach(input => {
        input.style.animation = 'shake 0.5s ease';
    });
    
    // ===== PRÉCHARGEMENT DES IMAGES =====
    const images = document.querySelectorAll('img[data-src]');
    const imageObserver = new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const img = entry.target;
                img.src = img.dataset.src;
                img.removeAttribute('data-src');
                imageObserver.unobserve(img);
            }
        });
    });
    
    images.forEach(img => imageObserver.observe(img));
    
    // ===== CONFIRMATION DE SUPPRESSION STYLISÉE =====
    document.querySelectorAll('.btn-danger[href*="delete"]').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const url = this.href;
            
            // Créer une modale de confirmation personnalisée
            const confirmed = confirm('⚠️ Êtes-vous sûr de vouloir supprimer cet élément ? Cette action est irréversible.');
            
            if (confirmed) {
                // Animation de sortie
                const row = this.closest('tr');
                if (row) {
                    row.style.transition = 'all 0.3s ease';
                    row.style.opacity = '0';
                    row.style.transform = 'translateX(-50px)';
                    setTimeout(() => {
                        window.location.href = url;
                    }, 300);
                } else {
                    window.location.href = url;
                }
            }
        });
    });
    
    // ===== INDICATEUR DE PROGRESSION DE SCROLL =====
    const progressBar = document.createElement('div');
    progressBar.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        height: 3px;
        background: linear-gradient(90deg, #3b82f6, #14b8a6);
        width: 0%;
        z-index: 9999;
        transition: width 0.1s ease;
    `;
    document.body.appendChild(progressBar);
    
    window.addEventListener('scroll', () => {
        const scrolled = (window.scrollY / (document.documentElement.scrollHeight - window.innerHeight)) * 100;
        progressBar.style.width = scrolled + '%';
    });
    
    // ===== ANIMATION DES STATISTIQUES DANS LES CARTES =====
    document.querySelectorAll('.card-body .text-white').forEach(stat => {
        stat.style.textShadow = '0 2px 4px rgba(0,0,0,0.2)';
    });
    
});

// ===== KEYFRAME POUR SHAKE =====
const shakeKeyframes = `
@keyframes shake {
    0%, 100% { transform: translateX(0); }
    10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
    20%, 40%, 60%, 80% { transform: translateX(5px); }
}
`;

const styleSheet = document.createElement('style');
styleSheet.textContent = shakeKeyframes;
document.head.appendChild(styleSheet);

// ===== STYLE POUR L'EFFET RIPPLE =====
const rippleStyle = `
.ripple {
    position: absolute;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.6);
    transform: scale(0);
    animation: ripple-animation 0.6s ease-out;
    pointer-events: none;
}

@keyframes ripple-animation {
    to {
        transform: scale(4);
        opacity: 0;
    }
}

.input-focused {
    transform: scale(1.02);
    transition: transform 0.2s ease;
}
`;

const rippleStyleSheet = document.createElement('style');
rippleStyleSheet.textContent = rippleStyle;
document.head.appendChild(rippleStyleSheet);
