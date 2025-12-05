<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark shadow-sm" style="background-color: #34495e;">
    <div class="container-fluid">
        <button class="btn btn-dark me-3" id="sidebarToggle" onmouseenter="openSidebar()" style="border: none; background: rgba(255,255,255,0.1); padding: 0.5rem 0.75rem; border-radius: 4px;">
            <i class="bi bi-list" style="font-size: 1.5rem; color: white;"></i>
        </button>
        <a class="navbar-brand fw-bold" href="index.php"><i class="bi bi-shield-lock-fill me-2"></i>Espace Administrateur</a>
        <div class="d-flex align-items-center">
            <span class="text-white me-3">Admin: <strong><?php echo htmlspecialchars($_SESSION['user_name']); ?></strong></span>
            <a class="btn btn-outline-light btn-sm fw-bold" href="../../frontend/deconnexion.php">
                <i class="bi bi-box-arrow-right me-1"></i> Déconnexion
            </a>
        </div>
    </div>
</nav>
