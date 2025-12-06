<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

require_role('reclamant');

$pdo = get_pdo();
$error = '';
$success = '';

// Récupérer les catégories
// Helper: détecte la première colonne existante parmi des candidats
function detect_column($pdo, $table, $candidates) {
    $check = $pdo->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?");
    foreach ($candidates as $col) {
        $check->execute([$table, $col]);
        if ($check->fetchColumn() > 0) {
            return $col;
        }
    }
    return null;
}

$catNameCol = detect_column($pdo, 'categories', ['nom', 'nom_categorie', 'categorie_nom', 'name', 'libelle']);

$catPk = detect_column($pdo, 'categories', ['id', 'categorie_id', 'category_id', 'cat_id']);

// Construire SELECT sûr en aliasant les colonnes attendues vers 'id' et 'nom'
$selectParts = [];
if ($catPk) {
    $selectParts[] = "cat.`$catPk` AS id";
} else {
    $selectParts[] = "0 AS id";
}
if ($catNameCol) {
    $selectParts[] = "cat.`$catNameCol` AS nom";
} else {
    $selectParts[] = "'' AS nom";
}
$selectParts[] = "cat.*";

$sql = "SELECT " . implode(', ', $selectParts) . " FROM categories cat ORDER BY nom ASC";
$stmt = $pdo->query($sql);
$categories = $stmt->fetchAll();

// If redirected to process a pending submission, prepare POST data first
$processing_pending = false;
if (isset($_GET['process_pending']) && $_GET['process_pending'] == 1 && !empty($_SESSION['pending_submission'])) {
    $processing_pending = true;
    $pending = $_SESSION['pending_submission'];
    // Populate POST-like variables for reuse of existing logic
    $_POST['sujet'] = $pending['sujet'] ?? '';
    $_POST['category_id'] = $pending['category_id'] ?? '';
    $_POST['description'] = $pending['description'] ?? '';

    // If a temp file exists, move it into expected place and simulate $_FILES
    if (!empty($pending['file']) && !empty($pending['file']['tmp_path'])) {
        $tempRel = $pending['file']['tmp_path']; // e.g. uploads/temp/uniq_name
        $tempFull = realpath(__DIR__ . '/../../' . $tempRel);
        if ($tempFull && file_exists($tempFull)) {
            $orig = $pending['file']['orig_name'] ?? basename($tempRel);
            $_SESSION['pending_submission']['moved_temp_full'] = $tempFull;
            $_SESSION['pending_submission']['moved_orig_name'] = $orig;
        }
    }

    // Simulate POST so the existing handler runs below
    $_SERVER['REQUEST_METHOD'] = 'POST';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sujet = sanitize_input($_POST['sujet']);
    $category_id = $_POST['category_id'];
    $description = sanitize_input($_POST['description']);
    $user_id = $_SESSION['user_id'];

    if (empty($sujet) || empty($category_id) || empty($description)) {
        $error = "Veuillez remplir tous les champs obligatoires.";
    } else {
        try {
            $pdo->beginTransaction();

            // Insérer la réclamation — construire INSERT dynamique selon les noms réels des colonnes
            $reclamCatCol = detect_column($pdo, 'reclamations', ['category_id','categorie_id','cat_id','categorie','categorieId','category']);
            $reclamSujetCol = detect_column($pdo, 'reclamations', ['sujet','objet','title','subject']);
            $reclamDescCol = detect_column($pdo, 'reclamations', ['description','desc','contenu','details']);

            $insertCols = ['`user_id`'];
            $placeholders = ['?'];
            $values = [$user_id];

            // Insérer la colonne catégorie à la position souhaitée (après user_id)
            if ($reclamCatCol) {
                $insertCols[] = "`$reclamCatCol`";
                $placeholders[] = '?';
                $values[] = $category_id;
            } else {
                // fallback to category_id (legacy) if not detected
                $insertCols[] = '`category_id`';
                $placeholders[] = '?';
                $values[] = $category_id;
            }

            // Ajouter la colonne sujet (ou son équivalent réel)
            if ($reclamSujetCol) {
                $insertCols[] = "`$reclamSujetCol`";
            } else {
                $insertCols[] = '`sujet`';
            }
            $placeholders[] = '?';
            $values[] = $sujet;

            // Ajouter la colonne description (ou son équivalent réel)
            if ($reclamDescCol) {
                $insertCols[] = "`$reclamDescCol`";
            } else {
                $insertCols[] = '`description`';
            }
            $placeholders[] = '?';
            $values[] = $description;

            $sql = 'INSERT INTO reclamations (' . implode(', ', $insertCols) . ') VALUES (' . implode(', ', $placeholders) . ')';
            $stmt = $pdo->prepare($sql);
            $stmt->execute($values);
            $reclamation_id = $pdo->lastInsertId();

            // Gestion de l'upload de fichier
            // First, handle file moved from frontend pending_submission (temp file)
            $handled_file = false;
            if (empty($_FILES['attachment']) && !empty($_SESSION['pending_submission']['moved_temp_full'])) {
                $tempFull = $_SESSION['pending_submission']['moved_temp_full'];
                $orig_name = $_SESSION['pending_submission']['moved_orig_name'] ?? basename($tempFull);
                if (file_exists($tempFull)) {
                    $upload_dir = '../../uploads/';
                    if (!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);

                    $file_name = time() . '_' . basename($orig_name);
                    $target_path = $upload_dir . $file_name;
                    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                    $allowed_ext = ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx'];

                    if (in_array($file_ext, $allowed_ext)) {
                        if (@rename($tempFull, $target_path) || @copy($tempFull, $target_path)) {
                            // Remove temp file if copy used
                            if (file_exists($tempFull) && realpath($tempFull) !== realpath($target_path)) {
                                @unlink($tempFull);
                            }
                            $sql = "INSERT INTO pieces_jointes (reclam_id, nom_fichier, chemin_acces) VALUES (?, ?, ?)";
                            $stmt = $pdo->prepare($sql);
                            $stmt->execute([$reclamation_id, $file_name, 'uploads/' . $file_name]);
                            $handled_file = true;
                        } else {
                            $error = "Erreur lors du déplacement du fichier temporaire.";
                        }
                    } else {
                        $error = "Format de fichier non supporté. (Autorisé: jpg, png, pdf, doc)";
                    }
                }
            }

            if (!$handled_file && isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = '../../uploads/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }

                $file_name = time() . '_' . basename($_FILES['attachment']['name']);
                $target_path = $upload_dir . $file_name;
                $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                $allowed_ext = ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx'];

                if (in_array($file_ext, $allowed_ext)) {
                    if (move_uploaded_file($_FILES['attachment']['tmp_name'], $target_path)) {
                        // Insérer dans pieces_jointes avec les colonnes réelles: reclam_id, nom_fichier, chemin_acces
                        $sql = "INSERT INTO pieces_jointes (reclam_id, nom_fichier, chemin_acces) VALUES (?, ?, ?)";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute([$reclamation_id, $file_name, 'uploads/' . $file_name]);
                    } else {
                        $error = "Erreur lors du téléchargement du fichier.";
                    }
                } else {
                    $error = "Format de fichier non supporté. (Autorisé: jpg, png, pdf, doc)";
                }
            }

            if (empty($error)) {
                $pdo->commit();
                $success = "Votre réclamation a été soumise avec succès.";
                // Clear pending submission if any
                if (!empty($_SESSION['pending_submission'])) {
                    unset($_SESSION['pending_submission']);
                }
                // If we were processing a pending submission, redirect to dashboard
                if (!empty($processing_pending)) {
                    redirect('index.php');
                }
                // Otherwise keep showing the success message on this page
            } else {
                $pdo->rollBack();
            }

        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Une erreur est survenue: " . $e->getMessage();
        }
    }
}

// Process pending submission if redirected from frontend after login
if (isset($_GET['process_pending']) && $_GET['process_pending'] == 1 && !empty($_SESSION['pending_submission'])) {
    $pending = $_SESSION['pending_submission'];
    // Populate POST-like variables for reuse of existing logic
    $_POST['sujet'] = $pending['sujet'] ?? '';
    $_POST['category_id'] = $pending['category_id'] ?? '';
    $_POST['description'] = $pending['description'] ?? '';

    // If a temp file exists, move it into expected place and simulate $_FILES
    if (!empty($pending['file']) && !empty($pending['file']['tmp_path'])) {
        $tempRel = $pending['file']['tmp_path']; // e.g. uploads/temp/uniq_name
        $tempFull = realpath(__DIR__ . '/../../' . $tempRel);
        if ($tempFull && file_exists($tempFull)) {
            // create a new fake file array to be handled by existing upload code
            $orig = $pending['file']['orig_name'] ?? basename($tempRel);
            // move to a temporary PHP upload-like tmp file location won't be necessary; we'll move directly later
            // store info in a helper variable that upload code below will use
            $_SESSION['pending_submission']['moved_temp_full'] = $tempFull;
            $_SESSION['pending_submission']['moved_orig_name'] = $orig;
        }
    }

    // Submit by reusing POST handling code path: we'll call the same block by performing a POST-like process
    // To avoid duplicating code, we call submit logic by simulating a POST request: set a flag
    $_SERVER['REQUEST_METHOD'] = 'POST';
}

include '../../includes/head.php';
?>
<link rel="stylesheet" href="../../css/modern.css">

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
        animation: slideUp 0.6s ease-out;
    }
    
    @keyframes slideUp {
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
    
    .alert-success {
        background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
        color: #166534;
        border-left: 4px solid #10b981;
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
    
    /* Progress Steps */
    .form-steps {
        display: flex;
        justify-content: space-between;
        margin-bottom: 2rem;
        position: relative;
    }
    
    .form-steps::before {
        content: '';
        position: absolute;
        top: 20px;
        left: 0;
        right: 0;
        height: 2px;
        background: var(--gray-200);
        z-index: 0;
    }
    
    .step {
        background: white;
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        border: 2px solid var(--gray-300);
        position: relative;
        z-index: 1;
        transition: all var(--transition-base);
    }
    
    .step.active {
        background: var(--gradient-blue);
        color: white;
        border-color: var(--primary-blue);
        box-shadow: 0 0 0 4px rgba(20, 184, 166, 0.2);
    }
    
    .step.completed {
        background: var(--primary-blue);
        color: white;
        border-color: var(--primary-blue);
    }
</style>

<script src="../../js/main.js" defer></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // File upload feedback
    const fileInput = document.getElementById('attachment');
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
    const textarea = document.getElementById('description');
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
    <!-- Navbar Minimaliste -->
    <nav class="navbar navbar-minimal navbar-expand-lg">
        <div class="container py-2">
            <a class="navbar-brand" href="index.php">
                <i class="bi bi-check-circle-fill me-2" style="color: #14b8a6;"></i>Resolve
            </a>
            <div class="ms-auto">
                <a class="btn btn-back" href="index.php">
                    <i class="bi bi-arrow-left me-1"></i>Retour au Tableau de Bord
                </a>
            </div>
        </div>
    </nav>

    <div class="container pb-5">
        <div class="main-content-container">
            <div class="mb-4">
                <h6 class="section-title">Espace Réclamant</h6>
                <h1 class="main-title">Nouvelle Réclamation</h1>
            </div>
                        
                        <?php if ($error): ?>
                            <div class="alert alert-danger" role="alert">
                                <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo $error; ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($success): ?>
                            <div class="alert alert-success" role="alert">
                                <i class="bi bi-check-circle-fill me-2"></i><?php echo $success; ?>
                                <div class="mt-3">
                                    <a href="index.php" class="btn btn-primary-action">Voir mes réclamations</a>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if (empty($success)): ?>
                        <form method="POST" action="soumission.php" enctype="multipart/form-data">
                            <div class="mb-4 form-group-animated">
                                <label for="sujet" class="form-label">
                                    <i class="bi bi-pencil"></i>
                                    Objet de la réclamation <span class="required-star">*</span>
                                </label>
                                <input type="text" class="form-control" id="sujet" name="sujet" placeholder="Ex: Problème de connexion..." required>
                            </div>

                            <div class="mb-4 form-group-animated">
                                <label for="category_id" class="form-label">
                                    <i class="bi bi-folder"></i>
                                    Catégorie <span class="required-star">*</span>
                                </label>
                                <select class="form-select" id="category_id" name="category_id" required>
                                    <option value="" selected disabled>Choisir une catégorie...</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['nom']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-4 form-group-animated">
                                <label for="description" class="form-label">
                                    <i class="bi bi-text-paragraph"></i>
                                    Description détaillée <span class="required-star">*</span>
                                </label>
                                <textarea class="form-control" id="description" name="description" rows="6" placeholder="Décrivez votre problème en détail..." required></textarea>
                            </div>

                            <div class="mb-4 form-group-animated">
                                <label for="attachment" class="form-label">
                                    <i class="bi bi-paperclip"></i>
                                    Pièce jointe (Optionnel)
                                </label>
                                <div class="file-upload-wrapper">
                                    <div class="file-upload-icon">
                                        <i class="bi bi-cloud-arrow-up"></i>
                                    </div>
                                    <div class="file-upload-text">
                                        <strong>Cliquez pour télécharger</strong> ou glissez-déposez
                                    </div>
                                    <small class="form-text mt-2">JPG, PNG, PDF, DOC (Max 5 Mo)</small>
                                    <input type="file" id="attachment" name="attachment">
                                </div>
                            </div>

                            <div class="d-flex gap-3 justify-content-end mt-5 form-group-animated">
                                <a href="index.php" class="btn btn-secondary-action">
                                    <i class="bi bi-x-circle me-2"></i>Annuler
                                </a>
                                <button type="submit" class="btn btn-primary-action">
                                    <i class="bi bi-send me-2"></i>Soumettre la réclamation
                                </button>
                            </div>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include '../../includes/footer.php'; ?>
</body>
</html>
