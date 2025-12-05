<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

$error = '';
$success = '';

// Handle public submission: save into session and require login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sujet = sanitize_input($_POST['sujet'] ?? '');
    $category_id = $_POST['category_id'] ?? '';
    $description = sanitize_input($_POST['description'] ?? '');

    if (empty($sujet) || empty($category_id) || empty($description)) {
        $error = 'Veuillez remplir tous les champs obligatoires.';
    } else {
        // Prepare pending submission in session
        $pending = [
            'sujet' => $sujet,
            'category_id' => $category_id,
            'description' => $description,
            'file' => null,
        ];

        // Handle optional file: move to uploads/temp
        if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = __DIR__ . '/../uploads/temp/';
            if (!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);
            $orig_name = basename($_FILES['attachment']['name']);
            $uniq = time() . '_' . bin2hex(random_bytes(6));
            $tmp_name = $uniq . '_' . $orig_name;
            $target = $upload_dir . $tmp_name;
            if (move_uploaded_file($_FILES['attachment']['tmp_name'], $target)) {
                // store relative path for processing later
                $pending['file'] = [
                    'tmp_path' => 'uploads/temp/' . $tmp_name,
                    'orig_name' => $orig_name,
                ];
            }
        }

        $_SESSION['pending_submission'] = $pending;

        // If user already logged in, send to reclamant handler to process
        if (is_logged_in()) {
            redirect('../espaces/reclamant/soumission.php?process_pending=1');
        }

        // Otherwise require login first
        redirect('login.php');
    }
}

// Lightweight categories list for public form
$pdo = get_pdo();
$cats = $pdo->query("SELECT * FROM categories ORDER BY nom_categorie ASC")->fetchAll();

include '../includes/head.php';
?>
<link rel="stylesheet" href="../css/modern.css">

<style>
    body {
        background: linear-gradient(135deg, #cffafe 0%, #e0f2fe 50%, #e0e7ff 100%);
        min-height: 100vh;
    }
    
    .navbar-minimal {
        background-color: #ffffff;
        border-bottom: none;
        box-shadow: var(--shadow-md);
        transition: var(--transition-base);
        animation: slideDown 0.5s ease-out;
    }
    
    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    .navbar-brand {
        color: var(--gray-900) !important;
        font-weight: 700;
        font-size: 1.25rem;
    }
    
    .btn-back {
        color: var(--primary-blue) !important;
        font-weight: 500;
        background: transparent;
        border: none;
        transition: var(--transition-base);
        text-decoration: none;
    }
    
    .btn-back:hover {
        color: var(--primary-blue-dark) !important;
    }
    
    .main-content-container {
        background: white;
        border-radius: var(--radius-xl);
        padding: 2.5rem;
        box-shadow: var(--shadow-lg);
        margin-bottom: 2rem;
        margin-top: 2rem;
        animation: fadeInUp 0.6s ease-out;
    }
    
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
    
    .section-title {
        color: var(--gray-500);
        font-weight: 500;
        font-size: 0.95rem;
        margin-bottom: 0.5rem;
        animation: fadeIn 0.8s ease-out 0.2s both;
    }
    
    .main-title {
        color: var(--gray-900);
        font-weight: 700;
        font-size: 2rem;
        margin-bottom: 2rem;
        animation: fadeIn 0.8s ease-out 0.3s both;
    }
    
    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    .form-group-animated {
        animation: fadeInUp 0.6s ease-out backwards;
    }
    
    .form-group-animated:nth-child(1) { animation-delay: 0.1s; }
    .form-group-animated:nth-child(2) { animation-delay: 0.2s; }
    .form-group-animated:nth-child(3) { animation-delay: 0.3s; }
    .form-group-animated:nth-child(4) { animation-delay: 0.4s; }
    .form-group-animated:nth-child(5) { animation-delay: 0.5s; }
    
    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    .form-label {
        color: var(--gray-700);
        font-weight: 600;
        font-size: 0.875rem;
        margin-bottom: 0.5rem;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .form-label i {
        color: var(--primary-blue);
        font-size: 1rem;
    }
    
    .form-control, .form-select {
        border: 2px solid var(--gray-200);
        border-radius: var(--radius-md);
        padding: 0.875rem 1rem;
        font-size: 0.938rem;
        transition: all var(--transition-base);
        background-color: var(--gray-50);
    }
    
    .form-control:focus, .form-select:focus {
        border-color: var(--primary-blue);
        box-shadow: 0 0 0 4px rgba(20, 184, 166, 0.1);
        background-color: white;
        transform: translateY(-2px);
    }
    
    .form-control:hover, .form-select:hover {
        border-color: var(--gray-300);
    }
    
    textarea.form-control {
        resize: vertical;
        min-height: 120px;
    }
    
    .file-upload-wrapper {
        position: relative;
        overflow: hidden;
        border: 2px dashed var(--gray-300);
        border-radius: var(--radius-md);
        padding: 2rem;
        text-align: center;
        transition: all var(--transition-base);
        background: var(--gray-50);
        cursor: pointer;
    }
    
    .file-upload-wrapper:hover {
        border-color: var(--primary-blue);
        background: rgba(20, 184, 166, 0.05);
    }
    
    .file-upload-wrapper input[type="file"] {
        position: absolute;
        opacity: 0;
        width: 100%;
        height: 100%;
        top: 0;
        left: 0;
        cursor: pointer;
    }
    
    .file-upload-icon {
        font-size: 3rem;
        color: var(--primary-blue);
        margin-bottom: 1rem;
        animation: float 3s ease-in-out infinite;
    }
    
    @keyframes float {
        0%, 100% { transform: translateY(0); }
        50% { transform: translateY(-10px); }
    }
    
    .btn-primary-action {
        background: var(--gradient-blue);
        color: white;
        border: none;
        padding: 0.875rem 2.5rem;
        border-radius: var(--radius-md);
        font-weight: 600;
        transition: all var(--transition-base);
        font-size: 0.938rem;
        box-shadow: var(--shadow-lg);
        position: relative;
        overflow: hidden;
    }
    
    .btn-primary-action::before {
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
    
    .btn-primary-action:hover::before {
        width: 300px;
        height: 300px;
    }
    
    .btn-primary-action:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-xl);
    }
    
    .btn-primary-action:active {
        transform: translateY(0);
    }
    
    .btn-secondary-action {
        background-color: white;
        color: var(--gray-700);
        border: 2px solid var(--gray-300);
        padding: 0.875rem 2rem;
        border-radius: var(--radius-md);
        font-weight: 600;
        transition: all var(--transition-base);
        font-size: 0.938rem;
    }
    
    .btn-secondary-action:hover {
        background-color: var(--gray-100);
        border-color: var(--gray-400);
        transform: translateY(-2px);
    }
    
    .alert {
        border-radius: var(--radius-lg);
        border: none;
        padding: 1.25rem 1.5rem;
        animation: slideInDown 0.5s ease-out;
        box-shadow: var(--shadow-md);
    }
    
    @keyframes slideInDown {
        from {
            opacity: 0;
            transform: translateY(-20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    .alert-danger {
        background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
        color: #991b1b;
        border-left: 4px solid #ef4444;
    }
    
    .form-text {
        color: var(--gray-400);
        font-size: 0.813rem;
        margin-top: 0.5rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .required-star {
        color: #ef4444;
        font-weight: 700;
    }
</style>

<script src="../js/main.js" defer></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // File upload feedback
    const fileInput = document.querySelector('input[type="file"]');
    const fileLabel = document.querySelector('.file-upload-text');
    
    if (fileInput) {
        fileInput.addEventListener('change', function(e) {
            if (this.files && this.files[0]) {
                const fileName = this.files[0].name;
                const fileSize = (this.files[0].size / 1024 / 1024).toFixed(2);
                fileLabel.innerHTML = `<i class="bi bi-file-earmark-check"></i> ${fileName} (${fileSize} MB)`;
                document.querySelector('.file-upload-wrapper').style.borderColor = 'var(--success)';
                document.querySelector('.file-upload-wrapper').style.background = 'rgba(16, 185, 129, 0.05)';
            }
        });
    }
    
    // Form validation animation
    const form = document.querySelector('form');
    if (form) {
        form.addEventListener('submit', function(e) {
            const submitBtn = this.querySelector('.btn-primary-action');
            if (submitBtn) {
                submitBtn.innerHTML = '<i class="bi bi-arrow-repeat spin"></i> Envoi en cours...';
            }
        });
    }
    
    // Character counter for textarea
    const textarea = document.querySelector('textarea[name="description"]');
    if (textarea) {
        const counter = document.createElement('small');
        counter.className = 'form-text';
        counter.style.float = 'right';
        textarea.parentElement.appendChild(counter);
        
        textarea.addEventListener('input', function() {
            const length = this.value.length;
            counter.textContent = `${length} caractères`;
            if (length > 500) {
                counter.style.color = 'var(--warning)';
            } else {
                counter.style.color = 'var(--gray-400)';
            }
        });
    }
});
</script>

<style>
@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}
.spin {
    animation: spin 1s linear infinite;
}
</style>

<body>
    <!-- Navbar minimaliste -->
    <nav class="navbar navbar-minimal">
        <div class="container">
            <span class="navbar-brand">
                <i class="bi bi-app-indicator"></i> Recla App
            </span>
            <a href="index.php" class="btn-back">
                <i class="bi bi-arrow-left me-2"></i>Retour
            </a>
        </div>
    </nav>

    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-lg-9">
                <div class="main-content-container">
                    <div class="section-title">NOUVELLE DEMANDE</div>
                    <h1 class="main-title">
                        <i class="bi bi-pencil-square me-2" style="color: var(--primary-blue);"></i>
                        Soumettre une réclamation
                    </h1>

                    <?php if ($error): ?>
                        <div class="alert alert-danger mb-4">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo $error; ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="soumission.php" enctype="multipart/form-data">
                        <div class="form-group-animated mb-4">
                            <label class="form-label">
                                <i class="bi bi-card-text"></i>
                                Objet <span class="required-star">*</span>
                            </label>
                            <input type="text" name="sujet" class="form-control" placeholder="Décrivez brièvement votre demande" required>
                        </div>

                        <div class="form-group-animated mb-4">
                            <label class="form-label">
                                <i class="bi bi-folder"></i>
                                Catégorie <span class="required-star">*</span>
                            </label>
                            <select name="category_id" class="form-select" required>
                                <option value="" disabled selected>Sélectionnez une catégorie</option>
                                <?php foreach ($cats as $c): ?>
                                    <option value="<?php echo $c['categorie_id'] ?? $c['id'] ?? ''; ?>">
                                        <?php echo htmlspecialchars($c['nom_categorie'] ?? $c['nom'] ?? $c['name'] ?? ''); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group-animated mb-4">
                            <label class="form-label">
                                <i class="bi bi-text-paragraph"></i>
                                Description <span class="required-star">*</span>
                            </label>
                            <textarea name="description" class="form-control" rows="6" placeholder="Décrivez en détail votre demande..." required></textarea>
                        </div>

                        <div class="form-group-animated mb-5">
                            <label class="form-label">
                                <i class="bi bi-paperclip"></i>
                                Pièce jointe <span class="form-text">(optionnel)</span>
                            </label>
                            <div class="file-upload-wrapper">
                                <div class="file-upload-icon">
                                    <i class="bi bi-cloud-upload"></i>
                                </div>
                                <div class="file-upload-text">
                                    <strong>Cliquez pour ajouter un fichier</strong><br>
                                    <span class="form-text">ou glissez-déposez un fichier ici</span>
                                </div>
                                <input type="file" name="attachment">
                            </div>
                        </div>

                        <div class="d-flex justify-content-end gap-3">
                            <a href="index.php" class="btn-secondary-action">
                                <i class="bi bi-x-circle me-2"></i>Annuler
                            </a>
                            <button class="btn-primary-action" type="submit">
                                <i class="bi bi-check-circle me-2"></i>Valider et se connecter
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>
</body>
</html>
