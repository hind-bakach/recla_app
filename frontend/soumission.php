<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/lang.php';

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

$page_title = 'Soumettre une réclamation';
$extra_head_content = '<link rel="stylesheet" href="../css/frontend.css">';

include '../includes/head_frontend.php';
?>

    <!-- Navbar minimaliste -->
    <nav class="navbar navbar-minimal">
        <div class="container">
            <span class="navbar-brand">
                <i class="bi bi-check-circle-fill"></i> Resolve
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

    <script src="../js/bootstrap.bundle.min.js"></script>
    <script src="../js/main.js"></script>
    <?php include '../includes/footer.php'; ?>
</body>
</html>
    </div>

    <script src="../js/bootstrap.bundle.min.js"></script>
    <script src="../js/main.js"></script>
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
    <?php include '../includes/footer.php'; ?>