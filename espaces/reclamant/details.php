<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

require_role('reclamant');

if (!isset($_GET['id'])) {
    redirect('index.php');
}

$claim_id = $_GET['id'];
$user_id = $_SESSION['user_id'];
$pdo = get_pdo();

// Récupérer les détails de la réclamation
$stmt = $pdo->prepare("SELECT c.*, cat.nom as categorie_nom 
    FROM claims c 
    JOIN categories cat ON c.category_id = cat.id 
    WHERE c.id = ? AND c.user_id = ?");
$stmt->execute([$claim_id, $user_id]);
$claim = $stmt->fetch();

if (!$claim) {
    redirect('index.php');
}

// Récupérer les pièces jointes
$stmt = $pdo->prepare("SELECT * FROM attachments WHERE claim_id = ?");
$stmt->execute([$claim_id]);
$attachments = $stmt->fetchAll();

// Récupérer les commentaires (historique)
$stmt = $pdo->prepare("SELECT c.*, u.nom as user_name, u.role as user_role 
    FROM comments c 
    JOIN users u ON c.user_id = u.id 
    WHERE c.claim_id = ? 
    ORDER BY c.created_at ASC");
$stmt->execute([$claim_id]);
$comments = $stmt->fetchAll();

// Traitement du nouveau commentaire
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['comment'])) {
    $comment = sanitize_input($_POST['comment']);
    if (!empty($comment)) {
        $stmt = $pdo->prepare("INSERT INTO comments (claim_id, user_id, comment) VALUES (?, ?, ?)");
        $stmt->execute([$claim_id, $user_id, $comment]);
        
        // Rafraîchir la page
        redirect("details.php?id=$claim_id");
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
        <div class="row g-4">
            <!-- Détails de la réclamation -->
            <div class="col-lg-8">
                <div class="card shadow-sm border-0 rounded-4 mb-4">
                    <div class="card-header bg-white p-4 border-bottom d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fw-bold text-primary">
                            <i class="bi bi-file-text me-2"></i>Détails de la réclamation #<?php echo $claim['id']; ?>
                        </h5>
                        <span class="badge rounded-pill <?php echo get_status_badge($claim['statut']); ?> px-3 py-2">
                            <?php echo get_status_label($claim['statut']); ?>
                        </span>
                    </div>
                    <div class="card-body p-4">
                        <h4 class="fw-bold mb-3"><?php echo htmlspecialchars($claim['sujet']); ?></h4>
                        
                        <div class="mb-4 text-muted small">
                            <span class="me-3"><i class="bi bi-calendar3 me-1"></i> Créé le <?php echo format_date($claim['created_at']); ?></span>
                            <span class="me-3"><i class="bi bi-tag me-1"></i> <?php echo htmlspecialchars($claim['categorie_nom']); ?></span>
                        </div>

                        <div class="p-3 bg-light rounded-3 mb-4 border">
                            <p class="mb-0" style="white-space: pre-line;"><?php echo htmlspecialchars($claim['description']); ?></p>
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
                        <h5 class="mb-0 fw-bold"><i class="bi bi-chat-dots me-2 text-primary"></i>Historique & Échanges</h5>
                    </div>
                    <div class="card-body p-4">
                        <div class="timeline">
                            <?php if (count($comments) > 0): ?>
                                <?php foreach ($comments as $comment): ?>
                                    <div class="d-flex mb-4">
                                        <div class="flex-shrink-0">
                                            <div class="rounded-circle d-flex align-items-center justify-content-center text-white fw-bold <?php echo $comment['user_role'] == 'reclamant' ? 'bg-primary' : 'bg-warning'; ?>" style="width: 40px; height: 40px;">
                                                <?php echo strtoupper(substr($comment['user_name'], 0, 1)); ?>
                                            </div>
                                        </div>
                                        <div class="flex-grow-1 ms-3">
                                            <div class="bg-light p-3 rounded-3">
                                                <div class="d-flex justify-content-between align-items-center mb-1">
                                                    <h6 class="fw-bold mb-0 <?php echo $comment['user_role'] == 'reclamant' ? 'text-primary' : 'text-dark'; ?>">
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
                                <p class="text-center text-muted my-4">Aucun échange pour le moment.</p>
                            <?php endif; ?>
                        </div>

                        <!-- Formulaire de réponse -->
                        <?php if ($claim['statut'] !== 'ferme'): ?>
                            <hr class="my-4">
                            <form method="POST" action="details.php?id=<?php echo $claim_id; ?>">
                                <div class="mb-3">
                                    <label for="comment" class="form-label fw-bold">Ajouter un message</label>
                                    <textarea class="form-control" id="comment" name="comment" rows="3" placeholder="Votre message..." required></textarea>
                                </div>
                                <div class="text-end">
                                    <button type="submit" class="btn btn-primary fw-bold">
                                        <i class="bi bi-send-fill me-2"></i>Envoyer
                                    </button>
                                </div>
                            </form>
                        <?php else: ?>
                            <div class="alert alert-secondary text-center mb-0">
                                <i class="bi bi-lock-fill me-2"></i>Cette réclamation est fermée. Vous ne pouvez plus ajouter de commentaires.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Sidebar Info -->
            <div class="col-lg-4">
                <div class="card shadow-sm border-0 rounded-4 mb-4">
                    <div class="card-body p-4">
                        <h6 class="fw-bold text-uppercase text-muted mb-3 small">Information</h6>
                        <ul class="list-unstyled mb-0">
                            <li class="mb-3 d-flex align-items-center">
                                <i class="bi bi-info-circle text-primary me-3 fs-5"></i>
                                <div>
                                    <small class="d-block text-muted">Statut actuel</small>
                                    <span class="fw-bold"><?php echo get_status_label($claim['statut']); ?></span>
                                </div>
                            </li>
                            <li class="mb-3 d-flex align-items-center">
                                <i class="bi bi-calendar-event text-primary me-3 fs-5"></i>
                                <div>
                                    <small class="d-block text-muted">Date de création</small>
                                    <span class="fw-bold"><?php echo format_date($claim['created_at']); ?></span>
                                </div>
                            </li>
                            <li class="d-flex align-items-center">
                                <i class="bi bi-arrow-repeat text-primary me-3 fs-5"></i>
                                <div>
                                    <small class="d-block text-muted">Dernière mise à jour</small>
                                    <span class="fw-bold"><?php echo format_date($claim['updated_at']); ?></span>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include '../../includes/footer.php'; ?>
</body>
</html>
