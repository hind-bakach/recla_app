<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

require_role('reclamant');

$pdo = get_pdo();
$error = '';
$success = '';

// Récupérer les catégories
$stmt = $pdo->query("SELECT * FROM categories ORDER BY nom ASC");
$categories = $stmt->fetchAll();

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

            // Insérer la réclamation
            $stmt = $pdo->prepare("INSERT INTO reclamations (user_id, category_id, sujet, description) VALUES (?, ?, ?, ?)");
            $stmt->execute([$user_id, $category_id, $sujet, $description]);
            $reclamation_id = $pdo->lastInsertId();

            // Gestion de l'upload de fichier
            if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
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
                        $stmt = $pdo->prepare("INSERT INTO pieces_jointes (reclamation_id, file_path) VALUES (?, ?)");
                        $stmt->execute([$reclamation_id, $file_name]);
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
                // Reset form data if needed or redirect
                // redirect('index.php'); // Optionnel: rediriger vers le dashboard
            } else {
                $pdo->rollBack();
            }

        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Une erreur est survenue: " . $e->getMessage();
        }
    }
}

include '../../includes/head.php';
?>

<body class="bg-light">
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php"><i class="bi bi-speedometer2 me-2"></i>Espace Réclamant</a>
            <div class="ms-auto">
                <a class="btn btn-light btn-sm fw-bold text-primary" href="index.php">
                    <i class="bi bi-arrow-left me-1"></i> Retour au Tableau de Bord
                </a>
            </div>
        </div>
    </nav>

    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card shadow-lg border-0 rounded-4">
                    <div class="card-header bg-white p-4 border-bottom">
                        <h3 class="mb-0 fw-bold text-primary"><i class="bi bi-pencil-square me-2"></i>Nouvelle Réclamation</h3>
                    </div>
                    <div class="card-body p-5">
                        
                        <?php if ($error): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo $error; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>

                        <?php if ($success): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="bi bi-check-circle-fill me-2"></i> <?php echo $success; ?>
                                <div class="mt-2">
                                    <a href="index.php" class="btn btn-sm btn-success fw-bold">Voir mes réclamations</a>
                                </div>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>

                        <?php if (empty($success)): ?>
                        <form method="POST" action="soumission.php" enctype="multipart/form-data">
                            <div class="mb-4">
                                <label for="sujet" class="form-label fw-bold">Objet de la réclamation <span class="text-danger">*</span></label>
                                <input type="text" class="form-control form-control-lg" id="sujet" name="sujet" placeholder="Ex: Problème de connexion..." required>
                            </div>

                            <div class="mb-4">
                                <label for="category_id" class="form-label fw-bold">Catégorie <span class="text-danger">*</span></label>
                                <select class="form-select form-select-lg" id="category_id" name="category_id" required>
                                    <option value="" selected disabled>Choisir une catégorie...</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['nom']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-4">
                                <label for="description" class="form-label fw-bold">Description détaillée <span class="text-danger">*</span></label>
                                <textarea class="form-control" id="description" name="description" rows="6" placeholder="Décrivez votre problème en détail..." required></textarea>
                            </div>

                            <div class="mb-4">
                                <label for="attachment" class="form-label fw-bold">Pièce jointe (Optionnel)</label>
                                <input class="form-control" type="file" id="attachment" name="attachment">
                                <div class="form-text">Formats acceptés: JPG, PNG, PDF, DOC. Max 5Mo.</div>
                            </div>

                            <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-5">
                                <a href="index.php" class="btn btn-light btn-lg px-4 me-md-2 fw-bold">Annuler</a>
                                <button type="submit" class="btn btn-primary btn-lg px-5 fw-bold">Soumettre la réclamation</button>
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
