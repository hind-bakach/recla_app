<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

require_role('reclamant');

if (!isset($_GET['id'])) {
    redirect('index.php');
}

$reclamation_id = $_GET['id'];
$user_id = $_SESSION['user_id'];
$pdo = get_pdo();

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

// Détecter colonnes pour reclamations/categories
$catNameCol = detect_column($pdo, 'categories', ['nom', 'nom_categorie', 'categorie_nom', 'name', 'libelle']);
$catPk = detect_column($pdo, 'categories', ['id', 'categorie_id', 'category_id', 'cat_id']);
// colonne FK dans reclamations pointant vers categories
$reclamFk = detect_column($pdo, 'reclamations', ['category_id', 'categorie_id', 'cat_id', 'categorie', 'categorie_id', 'category']);
$reclamPk = detect_column($pdo, 'reclamations', ['id', 'reclam_id', 'reclamation_id', 'id_reclamation', 'recl_id']);
$reclamDateCol = detect_column($pdo, 'reclamations', ['created_at', 'date_created', 'date_soumission', 'date_submission', 'date', 'date_creation', 'submitted_at', 'date_submitted']);
$reclamUpdatedCol = detect_column($pdo, 'reclamations', ['updated_at', 'date_updated', 'last_updated', 'modified_at']);

// Construire SELECT principal avec alias pour compatibilité template
$select = "c.*";
// n'ajouter le nom de catégorie que si on pourra faire la jointure (cat existe ET reclamations a la FK)
if ($catNameCol && $catPk && $reclamFk) {
    $select .= ", cat.`$catNameCol` AS categorie_nom";
}
if ($reclamPk && $reclamPk !== 'id') {
    $select .= ", c.`$reclamPk` AS id";
}
if ($reclamDateCol && $reclamDateCol !== 'created_at') {
    $select .= ", c.`$reclamDateCol` AS created_at";
}
if ($reclamUpdatedCol && $reclamUpdatedCol !== 'updated_at') {
    $select .= ", c.`$reclamUpdatedCol` AS updated_at";
}

// Construire la requête principale
// Construire la requête principale — faire la jointure seulement si on a la FK dans reclamations
if ($catNameCol && $catPk && $reclamFk) {
    $sql = "SELECT $select FROM reclamations c LEFT JOIN categories cat ON c.`$reclamFk` = cat.`$catPk` WHERE ";
} else {
    $sql = "SELECT $select FROM reclamations c WHERE ";
}

// Condition sur l'ID de la réclamation et l'utilisateur -> adapter le nom de la PK si nécessaire
if ($reclamPk && $reclamPk !== 'id') {
    $sql .= "c.`$reclamPk` = ? AND c.user_id = ?";
    $params = [$reclamation_id, $user_id];
} else {
    $sql .= "c.id = ? AND c.user_id = ?";
    $params = [$reclamation_id, $user_id];
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$reclamation = $stmt->fetch();

if (!$reclamation) {
    redirect('index.php');
}

// Ensure `created_at` and `updated_at` keys exist to avoid undefined index warnings
if (!isset($reclamation['created_at'])) {
    if (isset($reclamDateCol) && isset($reclamation[$reclamDateCol])) {
        $reclamation['created_at'] = $reclamation[$reclamDateCol];
    } else {
        $reclamation['created_at'] = null;
    }
}
if (!isset($reclamation['updated_at'])) {
    if (isset($reclamUpdatedCol) && isset($reclamation[$reclamUpdatedCol])) {
        $reclamation['updated_at'] = $reclamation[$reclamUpdatedCol];
    } else {
        // fallback to created_at or null
        $reclamation['updated_at'] = $reclamation['created_at'] ?? null;
    }
}

// Récupérer les pièces jointes avec les colonnes réelles
$sql = "SELECT piece_id, reclam_id, nom_fichier, chemin_acces FROM pieces_jointes WHERE reclam_id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$reclamation_id]);
$attachments = $stmt->fetchAll();

// Récupérer les commentaires (historique) — détecter colonnes réelles dans `commentaires`
$commFk = detect_column($pdo, 'commentaires', ['reclamation_id', 'reclam_id', 'reclam_id', 'reclamation']);
$commUserCol = detect_column($pdo, 'commentaires', ['user_id', 'user', 'author_id']);
$commTextCol = detect_column($pdo, 'commentaires', ['comment', 'contenu_comm', 'commentaire', 'message', 'content']);
$commDateCol = detect_column($pdo, 'commentaires', ['created_at', 'date_commentaire', 'date', 'date_creation']);

$selectComm = "c.*";
if ($commTextCol && $commTextCol !== 'comment') {
    $selectComm .= ", c.`$commTextCol` AS comment";
}
if ($commDateCol && $commDateCol !== 'created_at') {
    $selectComm .= ", c.`$commDateCol` AS created_at";
}

// build JOIN to users — assume users.id exists; if comment user col differs, use it
// detect columns in users table to build a safe JOIN and select user display fields
$usersPk = detect_column($pdo, 'users', ['id', 'user_id', 'uid', 'id_user', 'userid']);
$usersNameCol = detect_column($pdo, 'users', ['nom', 'name', 'username', 'user_name', 'full_name']);
$usersRoleCol = detect_column($pdo, 'users', ['role', 'user_role', 'profil', 'profil_role']);

$canJoinUsers = false;
if ($commUserCol && $usersPk) {
    $canJoinUsers = true;
    $userJoinCondition = "c.`$commUserCol` = u.`$usersPk`";
} elseif (!$commUserCol && $usersPk && detect_column($pdo, 'commentaires', ['user_id'])) {
    $canJoinUsers = true;
    $userJoinCondition = "c.user_id = u.`$usersPk`";
} else {
    $userJoinCondition = '';
}

$userSelectName = $usersNameCol ? "u.`$usersNameCol` as user_name" : "'' as user_name";
$userSelectRole = $usersRoleCol ? "u.`$usersRoleCol` as user_role" : "'' as user_role";

if ($commFk) {
    // choisir une colonne sûre pour ORDER BY dans commentaires
    $hasCreatedCol = detect_column($pdo, 'commentaires', ['created_at']);
    if ($commDateCol) {
        $orderCol = "c.`$commDateCol`";
    } elseif ($hasCreatedCol) {
        $orderCol = "c.created_at";
    } else {
        $commentPk = detect_column($pdo, 'commentaires', ['id', 'comm_id', 'commentaire_id', 'id_comment']);
        $orderCol = $commentPk ? "c.`$commentPk`" : "1";
    }
    if ($canJoinUsers && $userJoinCondition) {
        $sql = "SELECT $selectComm, $userSelectName, $userSelectRole FROM commentaires c JOIN users u ON $userJoinCondition WHERE c.`$commFk` = ? ORDER BY $orderCol ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$reclamation_id]);
        $comments = $stmt->fetchAll();
    } else {
        // no safe join possible — select comments without user info
        $sql = "SELECT $selectComm, '' as user_name, '' as user_role FROM commentaires c WHERE c.`$commFk` = ? ORDER BY $orderCol ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$reclamation_id]);
        $comments = $stmt->fetchAll();
    }
} else {
    // fallback to original but only join if safe
    if ($canJoinUsers && $userJoinCondition) {
        $sql = "SELECT c.*, $userSelectName, $userSelectRole FROM commentaires c JOIN users u ON $userJoinCondition WHERE c.reclamation_id = ? ORDER BY c.created_at ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$reclamation_id]);
        $comments = $stmt->fetchAll();
    } else {
        $stmt = $pdo->prepare("SELECT c.*, '' as user_name, '' as user_role FROM commentaires c WHERE c.reclamation_id = ? ORDER BY c.created_at ASC");
        $stmt->execute([$reclamation_id]);
        $comments = $stmt->fetchAll();
    }
}

// Traitement du nouveau commentaire
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['comment'])) {
    $comment = sanitize_input($_POST['comment']);
    if (!empty($comment)) {
        // Construire INSERT en fonction des colonnes détectées
        $insertCols = [];
        $placeholders = [];
        $values = [];

        // FK
        if ($commFk) {
            $insertCols[] = "`$commFk`";
            $placeholders[] = '?';
            $values[] = $reclamation_id;
        } else {
            $insertCols[] = 'reclamation_id';
            $placeholders[] = '?';
            $values[] = $reclamation_id;
        }

        // user
        if ($commUserCol) {
            $insertCols[] = "`$commUserCol`";
            $placeholders[] = '?';
            $values[] = $user_id;
        } else {
            $insertCols[] = 'user_id';
            $placeholders[] = '?';
            $values[] = $user_id;
        }

        // texte
        if ($commTextCol) {
            $insertCols[] = "`$commTextCol`";
            $placeholders[] = '?';
            $values[] = $comment;
        } else {
            $insertCols[] = 'comment';
            $placeholders[] = '?';
            $values[] = $comment;
        }

        $sql = "INSERT INTO commentaires (" . implode(',', $insertCols) . ") VALUES (" . implode(',', $placeholders) . ")";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($values);

        // Rafraîchir la page
        redirect("details.php?id=$reclamation_id");
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
                            <i class="bi bi-file-text me-2"></i>Détails de la réclamation #<?php echo $reclamation['id']; ?>
                        </h5>
                        <span class="badge rounded-pill <?php echo get_status_badge($reclamation['statut']); ?> px-3 py-2">
                            <?php echo get_status_label($reclamation['statut']); ?>
                        </span>
                    </div>
                    <div class="card-body p-4">
                        <h4 class="fw-bold mb-3"><?php echo htmlspecialchars($reclamation['sujet']); ?></h4>
                        
                        <div class="mb-4 text-muted small">
                            <span class="me-3"><i class="bi bi-calendar3 me-1"></i> Créé le <?php echo format_date($reclamation['created_at']); ?></span>
                            <span class="me-3"><i class="bi bi-tag me-1"></i> <?php echo htmlspecialchars($reclamation['categorie_nom']); ?></span>
                        </div>

                        <div class="p-3 bg-light rounded-3 mb-4 border">
                            <p class="mb-0" style="white-space: pre-line;"><?php echo htmlspecialchars($reclamation['description']); ?></p>
                        </div>

                        <?php if (count($attachments) > 0): ?>
                            <h6 class="fw-bold mb-3"><i class="bi bi-paperclip me-2"></i>Pièces jointes</h6>
                            <div class="row g-2">
                                <?php foreach ($attachments as $att): ?>
                                    <div class="col-auto">
                                        <a href="../../<?php echo $att['chemin_acces']; ?>" download="<?php echo $att['nom_fichier']; ?>" class="btn btn-outline-secondary btn-sm">
                                            <i class="bi bi-file-earmark-down me-1"></i> <?php echo $att['nom_fichier']; ?>
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
                        <?php if ($reclamation['statut'] !== 'ferme'): ?>
                            <hr class="my-4">
                            <form method="POST" action="details.php?id=<?php echo $reclamation_id; ?>">
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
                                    <span class="fw-bold"><?php echo get_status_label($reclamation['statut']); ?></span>
                                </div>
                            </li>
                            <li class="mb-3 d-flex align-items-center">
                                <i class="bi bi-calendar-event text-primary me-3 fs-5"></i>
                                <div>
                                    <small class="d-block text-muted">Date de création</small>
                                    <span class="fw-bold"><?php echo format_date($reclamation['created_at']); ?></span>
                                </div>
                            </li>
                            <li class="d-flex align-items-center">
                                <i class="bi bi-arrow-repeat text-primary me-3 fs-5"></i>
                                <div>
                                    <small class="d-block text-muted">Dernière mise à jour</small>
                                    <span class="fw-bold"><?php echo format_date($reclamation['updated_at']); ?></span>
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
