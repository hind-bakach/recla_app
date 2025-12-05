<style>
    /* ============================================
       ANIMATIONS ET TRANSITIONS
       ============================================ */
    
    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(30px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }
    
    @keyframes slideInLeft {
        from {
            opacity: 0;
            transform: translateX(-30px);
        }
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }
    
    @keyframes scaleIn {
        from {
            opacity: 0;
            transform: scale(0.9);
        }
        to {
            opacity: 1;
            transform: scale(1);
        }
    }
    
    @keyframes shimmer {
        0% { background-position: -1000px 0; }
        100% { background-position: 1000px 0; }
    }
    
    /* Appliquer les animations */
    .card {
        animation: fadeInUp 0.6s ease-out;
    }
    
    .card:nth-child(1) { animation-delay: 0.1s; }
    .card:nth-child(2) { animation-delay: 0.2s; }
    .card:nth-child(3) { animation-delay: 0.3s; }
    .card:nth-child(4) { animation-delay: 0.4s; }
    
    .table tbody tr {
        animation: fadeIn 0.5s ease-out;
        animation-fill-mode: both;
    }
    
    .table tbody tr:nth-child(1) { animation-delay: 0.05s; }
    .table tbody tr:nth-child(2) { animation-delay: 0.1s; }
    .table tbody tr:nth-child(3) { animation-delay: 0.15s; }
    .table tbody tr:nth-child(4) { animation-delay: 0.2s; }
    .table tbody tr:nth-child(5) { animation-delay: 0.25s; }
    
    /* ============================================
       FORMULAIRES MODERNES
       ============================================ */
    
    .form-control,
    .form-select {
        border: 2px solid #e5e7eb;
        border-radius: 8px;
        padding: 0.65rem 1rem;
        transition: all 0.3s ease;
        font-size: 0.95rem;
    }
    
    .form-control:focus,
    .form-select:focus {
        border-color: #3b82f6;
        box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
        transform: translateY(-1px);
    }
    
    .form-control:hover:not(:focus),
    .form-select:hover:not(:focus) {
        border-color: #cbd5e1;
    }
    
    .form-label {
        color: #374151;
        font-weight: 600;
        margin-bottom: 0.5rem;
        font-size: 0.9rem;
        letter-spacing: 0.3px;
    }
    
    .form-control-sm {
        padding: 0.45rem 0.75rem;
        font-size: 0.875rem;
    }
    
    /* Input avec icône */
    .input-group .form-control {
        border-left: none;
    }
    
    .input-group-text {
        background: white;
        border: 2px solid #e5e7eb;
        border-right: none;
    }
    
    /* ============================================
       TABLEAUX MODERNES
       ============================================ */
    
    .table {
        border-collapse: collapse;
    }
    
    .table thead th {
        background: #f8f9fa;
        color: #2b2d2fff;
        font-weight: 700;
        font-size: 0.75rem;
        letter-spacing: 0.5px;
        text-transform: uppercase;
        padding: 0.75rem 1rem;
        border-bottom: 2px solid #dee2e6;
    }
    
    .table tbody tr {
        transition: background-color 0.2s ease;
        border-bottom: 1px solid #f3f4f6;
    }
    
    .table tbody tr:hover {
        background-color: #f8f9fa;
    }
    
    .table tbody td {
        padding: 0.75rem 1rem;
        vertical-align: middle;
        border-top: 1px solid #dee2e6;
        
    }
    
    /* ============================================
       BADGES MODERNES
       ============================================ */
    
    .badge {
        padding: 0.4rem 0.8rem;
        font-weight: 600;
        font-size: 0.75rem;
        letter-spacing: 0.5px;
        border-radius: 6px;
        transition: all 0.3s ease;
    }
    
    .badge:hover {
        transform: scale(1.05);
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
    }
    
    .badge.bg-primary {
        background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%) !important;
    }
    
    .badge.bg-success {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%) !important;
    }
    
    .badge.bg-warning {
        background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%) !important;
    }
    
    .badge.bg-danger {
        background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%) !important;
    }
    
    .badge.bg-info {
        background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%) !important;
    }
    
    /* ============================================
       BOUTONS MODERNES
       ============================================ */
    
    .btn {
        border-radius: 8px;
        padding: 0.6rem 1.5rem;
        font-weight: 600;
        letter-spacing: 0.3px;
        transition: all 0.3s ease;
        border: none;
        position: relative;
        overflow: hidden;
    }
    
    .btn::before {
        content: '';
        position: absolute;
        top: 50%;
        left: 50%;
        width: 0;
        height: 0;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.3);
        transform: translate(-50%, -50%);
        transition: width 0.6s, height 0.6s;
    }
    
    .btn:hover::before {
        width: 300px;
        height: 300px;
    }
    
    .btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }
    
    .btn:active {
        transform: translateY(0);
    }
    
    .btn-primary {
        background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
    }
    
    .btn-success {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    }
    
    .btn-danger {
        background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    }
    
    .btn-warning {
        background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        color: white;
    }
    
    .btn-sm {
        padding: 0.4rem 1rem;
        font-size: 0.875rem;
    }
    
    /* ============================================
       CARTES STATISTIQUES
       ============================================ */
    
    .card {
        transition: all 0.3s ease;
        border: none;
    }
    
    .card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1) !important;
    }
    
    .card-body {
        position: relative;
        overflow: hidden;
    }
    
    /* Animation de pulsation pour les stats */
    @keyframes pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.8; }
    }
    
    .card .display-4 {
        animation: pulse 2s ease-in-out infinite;
    }
    
    /* ============================================
       ALERTES MODERNES
       ============================================ */
    
    .alert {
        border: none;
        border-radius: 10px;
        padding: 1rem 1.5rem;
        animation: slideInLeft 0.5s ease-out;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    }
    
    .alert-success {
        background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
        color: #065f46;
        border-left: 4px solid #10b981;
    }
    
    .alert-danger {
        background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
        color: #991b1b;
        border-left: 4px solid #ef4444;
    }
    
    /* ============================================
       MODALS MODERNES
       ============================================ */
    
    .modal-content {
        border: none;
        border-radius: 15px;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        animation: scaleIn 0.3s ease-out;
    }
    
    .modal-header {
        border-bottom: 2px solid #f3f4f6;
        padding: 1.5rem;
    }
    
    .modal-body {
        padding: 1.5rem;
    }
    
    .modal-footer {
        border-top: 2px solid #f3f4f6;
        padding: 1.5rem;
    }
    
    /* ============================================
       LOADING STATES
       ============================================ */
    
    .loading {
        position: relative;
        pointer-events: none;
        opacity: 0.6;
    }
    
    .loading::after {
        content: '';
        position: absolute;
        top: 50%;
        left: 50%;
        width: 30px;
        height: 30px;
        margin: -15px 0 0 -15px;
        border: 3px solid #f3f4f6;
        border-top-color: #3b82f6;
        border-radius: 50%;
        animation: spin 0.8s linear infinite;
    }
    
    @keyframes spin {
        to { transform: rotate(360deg); }
    }
    
    /* ============================================
       SCROLLBAR PERSONNALISÉE
       ============================================ */
    
    ::-webkit-scrollbar {
        width: 10px;
        height: 10px;
    }
    
    ::-webkit-scrollbar-track {
        background: #f1f5f9;
        border-radius: 10px;
    }
    
    ::-webkit-scrollbar-thumb {
        background: linear-gradient(135deg, #cbd5e1 0%, #94a3b8 100%);
        border-radius: 10px;
    }
    
    ::-webkit-scrollbar-thumb:hover {
        background: linear-gradient(135deg, #94a3b8 0%, #64748b 100%);
    }
    
    /* ============================================
       RESPONSIVE
       ============================================ */
    
    @media (max-width: 768px) {
        .card:hover {
            transform: none;
        }
        
        .table tbody tr:hover {
            transform: none;
        }
        
        .btn:hover {
            transform: none;
        }
    }
    
    /* ============================================
       TOOLTIPS
       ============================================ */
    
    [data-bs-toggle="tooltip"] {
        cursor: help;
    }
    
    /* ============================================
       SEARCH BAR MODERNE
       ============================================ */
    
    .search-wrapper {
        position: relative;
    }
    
    .search-wrapper input {
        padding-left: 2.5rem;
    }
    
    .search-wrapper i {
        position: absolute;
        left: 0.75rem;
        top: 50%;
        transform: translateY(-50%);
        color: #9ca3af;
    }
    
    /* ============================================
       PAGINATION MODERNE
       ============================================ */
    
    .pagination .page-link {
        border: 2px solid #e5e7eb;
        color: #374151;
        margin: 0 0.2rem;
        border-radius: 8px;
        padding: 0.5rem 1rem;
        transition: all 0.3s ease;
    }
    
    .pagination .page-link:hover {
        background: #3b82f6;
        color: white;
        border-color: #3b82f6;
        transform: translateY(-2px);
    }
    
    .pagination .page-item.active .page-link {
        background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        border-color: #3b82f6;
    }
</style>
