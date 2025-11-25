<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

require_role('gestionnaire');

if (!isset($_GET['id'])) {
    redirect('index.php');
}

$reclamation_id = $_GET['id'];
$user_id = $_SESSION['user_id'];
$pdo = get_pdo();

// Récupérer les détails de la réclamation
$stmt = $pdo->prepare("SELECT c.*, u.nom as user_name, u.email as user_email, cat.nom as categorie_nom 
    FROM reclamations c 
    JOIN users u ON c.user_id = u.id 
    JOIN categories cat ON c.category_id = cat.id 
    WHERE c.id = ?");
$stmt->execute([$reclamation_id]);
$reclamation = $stmt->fetch();

if (!$reclamation) {
    redirect('index.php');
}

// Récupérer les pièces jointes
$stmt = $pdo->prepare("SELECT * FROM pieces_jointes WHERE reclamation_id = ?");
$stmt->execute([$reclamation_id]);
$attachments = $stmt->fetchAll();

// Récupérer les commentaires
$stmt = $pdo->prepare("SELECT c.*, u.nom as user_name, u.role as user_role 
    FROM commentaires c 
    JOIN users u ON c.user_id = u.id 
    WHERE c.reclamation_id = ? 
    ORDER BY c.created_at ASC");
$stmt->execute([$reclamation_id]);
$comments = $stmt->fetchAll();

// Traitement du formulaire (Changement de statut / Commentaire)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['new_status'])) {
        $new_status = $_POST['new_status'];
        $stmt = $pdo->prepare("UPDATE reclamations SET statut = ? WHERE id = ?");
        $stmt->execute([$new_status, $reclamation_id]);
        
        // Ajouter un commentaire système automatique (optionnel)
        $msg = "Le statut a été changé en : " . get_status_label($new_status);
        $stmt = $pdo->prepare("INSERT INTO commentaires (reclamation_id, user_id, comment) VALUES (?, ?, ?)");
        $stmt->execute([$reclamation_id, $user_id, $msg]);
        
        redirect("traitement.php?id=$reclamation_id");
    }
    
    if (isset($_POST['comment'])) {
        $comment = sanitize_input($_POST['comment']);
        if (!empty($comment)) {
            $stmt = $pdo->prepare("INSERT INTO commentaires (reclamation_id, user_id, comment) VALUES (?, ?, ?)");
            $stmt->execute([$reclamation_id, $user_id, $comment]);
            redirect("traitement.php?id=$reclamation_id");
        }
    }
}

include '../../includes/head.php';
?>

<body class="bg-light">
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm">
        <div class="container-fluid">
            <a class="navbar-brand fw-bold" href="index.php"><i class="bi bi-person-workspace me-2"></i>Espace Gestionnaire</a>
            <div class="ms-auto">
                <a class="btn btn-outline-light btn-sm fw-bold" href="index.php">
                    <i class="bi bi-arrow-left me-1"></i> Retour
                </a>
            </div>
        </div>
    </nav>

    <div class="container-fluid py-4">
        <div class="row g-4">
            <!-- Sidebar Actions -->
            <div class="col-lg-3">
                <div class="card shadow-sm border-0 rounded-4 mb-4">
                    <div class="card-header bg-white p-3 border-bottom">
                        <h6 class="mb-0 fw-bold text-uppercase small">Actions</h6>
                    </div>
                    <div class="card-body p-4">
                        <form method="POST" action="traitement.php?id=<?php echo $reclamation_id; ?>">
                            <label class="form-label fw-bold small">Changer le statut</label>
                            <select class="form-select mb-3" name="new_status">
                                <option value="en_cours" <?php echo $reclamation['statut'] == 'en_cours' ? 'selected' : ''; ?>>En cours</option>
                                <option value="attente_info" <?php echo $reclamation['statut'] == 'attente_info' ? 'selected' : ''; ?>>Attente d'info</option>
                                <option value="traite" <?php echo $reclamation['statut'] == 'traite' ? 'selected' : ''; ?>>Traité</option>
                                <option value="ferme" <?php echo $reclamation['statut'] == 'ferme' ? 'selected' : ''; ?>>Fermé</option>
                            </select>
                            <button type="submit" class="btn btn-primary w-100 fw-bold">Mettre à jour</button>
                        </form>
                    </div>
                </div>

                <div class="card shadow-sm border-0 rounded-4">
                    <div class="card-body p-4">
                        <h6 class="fw-bold text-uppercase text-muted mb-3 small">Infos Réclamant</h6>
                        <div class="d-flex align-items-center mb-3">
                            <div class="rounded-circle bg-light text-primary d-flex align-items-center justify-content-center fw-bold me-3" style="width: 40px; height: 40px;">
                                <?php echo strtoupper(substr($reclamation['user_name'], 0, 1)); ?>
                            </div>
                            <div>
                                <div class="fw-bold"><?php echo htmlspecialchars($reclamation['user_name']); ?></div>
                                <div class="small text-muted"><?php echo htmlspecialchars($reclamation['user_email']); ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Détails et Chat -->
            <div class="col-lg-9">
                <div class="card shadow-sm border-0 rounded-4 mb-4">
                    <div class="card-header bg-white p-4 border-bottom d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fw-bold">
                            Réclamation #<?php echo $reclamation['id']; ?> : <?php echo htmlspecialchars($reclamation['sujet']); ?>
                        </h5>
                        <span class="badge rounded-pill <?php echo get_status_badge($reclamation['statut']); ?> px-3 py-2">
                            <?php echo get_status_label($reclamation['statut']); ?>
                        </span>
                    </div>
                    <div class="card-body p-4">
                        <div class="mb-4">
                            <span class="badge bg-light text-dark border me-2"><?php echo htmlspecialchars($reclamation['categorie_nom']); ?></span>
                            <span class="text-muted small"><i class="bi bi-clock me-1"></i> <?php echo format_date($reclamation['created_at']); ?></span>
                        </div>
                        
                        <div class="p-3 bg-light rounded-3 mb-4 border">
                            <p class="mb-0" style="white-space: pre-line;"><?php echo htmlspecialchars($reclamation['description']); ?></p>
                        </div>

                        <?php if (count($attachments) > 0): ?>
                            <h6 class="fw-bold mb-3"><i class="bi bi-paperclip me-2"></i>Pièces jointes</h6>
                            <div class="row g-2">
                                <?php foreach ($attachments as $att): ?>
                                    <div class="col-auto">
                                        <a href="../../uploads/<?php echo $att['file_path']; ?>" target="_blank" class="btn btn-outline-secondary btn-sm">
                                            <i class="bi bi-file-earmark me-1"></i> Voir le fichier
                                        </a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Historique / Commentaires -->
                <div class="card shadow-sm border-0 rounded-4">
                    <div class="card-header bg-white p-4 border-bottom">
                        <h5 class="mb-0 fw-bold"><i class="bi bi-chat-left-text me-2 text-primary"></i>Suivi du dossier</h5>
                    </div>
                    <div class="card-body p-4">
                        <div class="timeline mb-4">
                            <?php if (count($comments) > 0): ?>
                                <?php foreach ($comments as $comment): ?>
                                    <div class="d-flex mb-4">
                                        <div class="flex-shrink-0">
                                            <div class="rounded-circle d-flex align-items-center justify-content-center text-white fw-bold <?php echo $comment['user_role'] == 'reclamant' ? 'bg-secondary' : 'bg-primary'; ?>" style="width: 40px; height: 40px;">
                                                <?php echo strtoupper(substr($comment['user_name'], 0, 1)); ?>
                                            </div>
                                        </div>
                                        <div class="flex-grow-1 ms-3">
                                            <div class="bg-light p-3 rounded-3 <?php echo $comment['user_role'] != 'reclamant' ? 'border-start border-4 border-primary' : ''; ?>">
                                                <div class="d-flex justify-content-between align-items-center mb-1">
                                                    <h6 class="fw-bold mb-0">
                                                        <?php echo htmlspecialchars($comment['user_name']); ?>
                                                        <span class="badge bg-secondary ms-2" style="font-size: 0.7em;"><?php echo $comment['user_role']; ?></span>
                                                    </h6>
                                                    <small class="text-muted"><?php echo format_date($comment['created_at']); ?></small>
                                                </div>
                                                <p class="mb-0 small"><?php echo nl2br(htmlspecialchars($comment['comment'])); ?></p>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="text-center text-muted">Aucun historique.</p>
                            <?php endif; ?>
                        </div>

                        <hr>
                        <form method="POST" action="traitement.php?id=<?php echo $reclamation_id; ?>">
                            <div class="mb-3">
                                <label for="comment" class="form-label fw-bold">Ajouter une note ou une réponse</label>
                                <textarea class="form-control" id="comment" name="comment" rows="3" placeholder="Écrire un message..." required></textarea>
                            </div>
                            <div class="text-end">
                                <button type="submit" class="btn btn-primary fw-bold">
                                    <i class="bi bi-send-fill me-2"></i>Envoyer
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include '../../includes/footer.php'; ?>
</body>
</html>
