<!-- Overlay pour fermer la sidebar -->
<div id="sidebarOverlay" class="sidebar-overlay" onclick="closeSidebar()"></div>

<!-- Sidebar Menu -->
<div id="sidebar" class="sidebar" onmouseenter="openSidebar()" onmouseleave="closeSidebar()" style="background-color: #34495e;">
    <div class="py-4">
        <!-- Menu Items -->
        <nav class="nav flex-column">
            <a href="index.php" class="nav-link text-white d-flex align-items-center px-3 py-3 sidebar-link <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>">
                <i class="bi bi-speedometer2 me-3" style="font-size: 1.2rem;"></i>
                <span class="fw-semibold">Tableau de Bord</span>
            </a>
            <a href="users.php" class="nav-link text-white-50 d-flex align-items-center px-3 py-3 sidebar-link <?php echo basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : ''; ?>">
                <i class="bi bi-people-fill me-3" style="font-size: 1.2rem;"></i>
                <span class="fw-semibold">Utilisateurs</span>
            </a>
            <a href="categories.php" class="nav-link text-white-50 d-flex align-items-center px-3 py-3 sidebar-link <?php echo basename($_SERVER['PHP_SELF']) == 'categories.php' ? 'active' : ''; ?>">
                <i class="bi bi-tags-fill me-3" style="font-size: 1.2rem;"></i>
                <span class="fw-semibold">Catégories</span>
            </a>
            <a href="reclamations.php" class="nav-link text-white-50 d-flex align-items-center px-3 py-3 sidebar-link <?php echo basename($_SERVER['PHP_SELF']) == 'reclamations.php' ? 'active' : ''; ?>">
                <i class="bi bi-inbox-fill me-3" style="font-size: 1.2rem;"></i>
                <span class="fw-semibold">Réclamations</span>
            </a>
        </nav>
    </div>
</div>

<style>
    body { 
        background-color: #f5f5f5; 
        overflow-x: hidden;
    }
    
    /* Sidebar Styles */
    .sidebar {
        position: fixed;
        left: -315px;
        top: 56px;
        width: 315px;
        height: calc(100vh - 56px);
        background-color: #34495e;
        z-index: 1050;
        transition: left 0.3s ease;
        overflow-y: auto;
        box-shadow: 2px 0 10px rgba(0,0,0,0.1);
    }
    
    .sidebar.active {
        left: 0;
    }
    
    /* Overlay */
    .sidebar-overlay {
        position: fixed;
        top: 56px;
        left: 0;
        width: 100%;
        height: calc(100vh - 56px);
        background-color: rgba(0, 0, 0, 0.5);
        z-index: 1040;
        display: none;
        transition: opacity 0.3s ease;
    }
    
    .sidebar-overlay.active {
        display: block;
    }
    
    /* Sidebar Links */
    .sidebar-link {
        transition: all 0.3s ease;
    }
    
    .sidebar-link:hover {
        background-color: rgba(255, 255, 255, 0.1) !important;
        color: #fff !important;
    }
    
    .sidebar-link.active {
        background-color: #14b8a6 !important;
        color: #fff !important;
    }
    
    /* Hamburger Button Hover */
    #sidebarToggle:hover {
        background: rgba(255,255,255,0.2) !important;
    }
</style>

<script>
    function openSidebar() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        
        sidebar.classList.add('active');
        overlay.classList.add('active');
    }
    
    function closeSidebar() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        
        sidebar.classList.remove('active');
        overlay.classList.remove('active');
    }
    
    // Fermer la sidebar avec la touche Echap
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeSidebar();
        }
    });
</script>
