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

<body class="bg-light">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card shadow-lg border-0 rounded-4">
                    <div class="card-header bg-white p-4 border-bottom">
                        <h3 class="mb-0 fw-bold text-primary"><i class="bi bi-pencil-square me-2"></i>Soumettre une demande</h3>
                    </div>
                    <div class="card-body p-5">
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>

                        <form method="POST" action="soumission.php" enctype="multipart/form-data">
                            <div class="mb-4">
                                <label class="form-label fw-bold">Objet <span class="text-danger">*</span></label>
                                <input type="text" name="sujet" class="form-control" required>
                            </div>
                            <div class="mb-4">
                                <label class="form-label fw-bold">Catégorie <span class="text-danger">*</span></label>
                                <select name="category_id" class="form-select" required>
                                    <option value="" disabled selected>Choisir une catégorie</option>
                                    <?php foreach ($cats as $c): ?>
                                        <option value="<?php echo $c['categorie_id'] ?? $c['id'] ?? ''; ?>"><?php echo htmlspecialchars($c['nom_categorie'] ?? $c['nom'] ?? $c['name'] ?? ''); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-4">
                                <label class="form-label fw-bold">Description <span class="text-danger">*</span></label>
                                <textarea name="description" class="form-control" rows="6" required></textarea>
                            </div>
                            <div class="mb-4">
                                <label class="form-label fw-bold">Pièce jointe (optionnel)</label>
                                <input type="file" name="attachment" class="form-control">
                            </div>
                            <div class="d-flex justify-content-end">
                                <a href="index.php" class="btn btn-light me-2">Annuler</a>
                                <button class="btn btn-primary" type="submit">Valider et se connecter</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>
</body>
</html>
