<?php // 1. Inclure la configuration (pour démarrer la session si nécessaire, et avoir la BDD)
require_once '../includes/config.php'; 

// 2. Inclure l'en-tête HTML (qui contient le <head> et le début du <body>)
include '../includes/head.php'; 
?>

<body class="bg-light">

    <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom">
        <div class="container-fluid container-lg">
            <a class="navbar-brand text-muted fw-bold" href="index.php">
                <i class="bi bi-chat-square-text-fill text-primary me-2"></i> 
                Gestion des Réclamations
            </a>
            
            <div class="d-flex ms-auto">
                <a class="btn btn-outline-secondary me-2" href="login.php">
                    <i class="bi bi-box-arrow-in-right me-1"></i> Se connecter
                </a>
                <a class="btn btn-primary" href="soumission.php">
                    Soumettre une Réclamation
                </a>
            </div>
        </div>
    </nav>
    
    <header class="py-5" style="background-color: #f7f3f0;"> <div class="container container-lg py-5">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <h1 class="display-3 fw-bold mb-4">
                        Simplifiez la <span class="text-orange">Soumission</span>. 
                        <br>
                        Assurez la <span class="text-primary">Traçabilité</span>.
                    </h1>
                    <p class="lead mb-4 text-muted">
                        Notre plateforme centralise le dépôt, le suivi et le traitement de toutes vos réclamations (client, étudiant, employé...).
                    </p>
                    
                    <a class="btn btn-warning btn-lg me-3 px-4 py-2 fw-bold" href="soumission.php">
                        Déposer votre Réclamation
                    </a>
                    <a class="btn btn-outline-secondary btn-lg px-4 py-2" href="login.php">
                        Espace Utilisateur/Gestionnaire
                    </a>
                </div>
                
                <div class="col-lg-4 text-center d-none d-lg-block">
                    [Image d'une icône de suivi ou d'un diagramme de workflow]
                </div>
            </div>
        </div>
    </header>

    <section class="container container-lg py-5 my-5">
    
        <h2 class="text-center mb-5 fw-bold text-dark">Les Avantages de Notre Plateforme</h2>

        <div class="row g-4">
            
            <div class="col-md-6 col-lg-3">
                <div class="card h-100 p-4 border-0 shadow-sm rounded-4 text-center">
                    <div class="mx-auto p-3 mb-3 bg-light-warning rounded-circle" style="width: 70px; height: 70px;">
                        <i class="bi bi-send-check-fill text-warning" style="font-size: 1.8rem;"></i>
                    </div>
                    <h5 class="card-title fw-bold">Soumission Simplifiée</h5>
                    <p class="card-text text-muted">
                        Conçu pour être intuitif. Déposez une réclamation en quelques étapes claires, sans friction ni perte de temps.
                    </p>
                </div>
            </div>

            <div class="col-md-6 col-lg-3">
                <div class="card h-100 p-4 border-0 shadow-sm rounded-4 text-center">
                    <div class="mx-auto p-3 mb-3 bg-light-primary rounded-circle" style="width: 70px; height: 70px;">
                        <i class="bi bi-clock-history text-primary" style="font-size: 1.8rem;"></i>
                    </div>
                    <h5 class="card-title fw-bold">Traçabilité Complète</h5>
                    <p class="card-text text-muted">
                        Suivez le statut de votre dossier en temps réel. Chaque étape du traitement est enregistrée et consultable.
                    </p>
                </div>
            </div>

            <div class="col-md-6 col-lg-3">
                <div class="card h-100 p-4 border-0 shadow-sm rounded-4 text-center">
                    <div class="mx-auto p-3 mb-3 bg-light-success rounded-circle" style="width: 70px; height: 70px;">
                        <i class="bi bi-bell-fill text-success" style="font-size: 1.8rem;"></i>
                    </div>
                    <h5 class="card-title fw-bold">Notifications Automatiques</h5>
                    <p class="card-text text-muted">
                        Recevez une alerte immédiate lors d'un changement de statut, d'une demande d'information ou de la clôture.
                    </p>
                </div>
            </div>

            <div class="col-md-6 col-lg-3">
                <div class="card h-100 p-4 border-0 shadow-sm rounded-4 text-center">
                    <div class="mx-auto p-3 mb-3 bg-light-info rounded-circle" style="width: 70px; height: 70px;">
                        <i class="bi bi-person-workspace text-info" style="font-size: 1.8rem;"></i>
                    </div>
                    <h5 class="card-title fw-bold">Espace Gestionnaire</h5>
                    <p class="card-text text-muted">
                        Tableaux de bord dédiés pour les agents de traitement, permettant l'analyse et la gestion efficace des dossiers.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <?php include '../includes/footer.php'; // Inclut le pied de page HTML et le JS Bootstrap ?>
</body>
</html>