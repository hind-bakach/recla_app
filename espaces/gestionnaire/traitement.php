<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/lang.php';

require_role('gestionnaire');

if (!isset($_GET['id'])) {
    redirect('index.php');
}

$reclamation_id = $_GET['id'];
$user_id = $_SESSION['user_id'];
$pdo = get_pdo();

// Détection dynamique des colonnes (pour compatibilité avec différents schémas)
$getCols = function($table) use ($pdo) {
    $rows = $pdo->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA='".DB_NAME."' AND TABLE_NAME='".addslashes($table)."'")->fetchAll(PDO::FETCH_COLUMN);
    return $rows ?: [];
};

$reclamCols = $getCols('reclamations');
$reclamIdCol = in_array('reclam_id', $reclamCols) ? 'reclam_id' : (in_array('id', $reclamCols) ? 'id' : ($reclamCols[0] ?? 'id'));
$reclamUserCol = in_array('user_id', $reclamCols) ? 'user_id' : (in_array('userId', $reclamCols) ? 'userId' : (in_array('user', $reclamCols) ? 'user' : 'user_id'));
$reclamCatCol = in_array('categorie_id', $reclamCols) ? 'categorie_id' : (in_array('category_id', $reclamCols) ? 'category_id' : (in_array('cat_id', $reclamCols) ? 'cat_id' : null));
$reclamObjetCol = in_array('objet', $reclamCols) ? 'objet' : (in_array('sujet', $reclamCols) ? 'sujet' : (in_array('title', $reclamCols) ? 'title' : 'objet'));
$reclamDescCol = in_array('description', $reclamCols) ? 'description' : (in_array('desc', $reclamCols) ? 'desc' : 'description');
$reclamDateCol = in_array('created_at', $reclamCols) ? 'created_at' : (in_array('date_soumission', $reclamCols) ? 'date_soumission' : ($reclamCols[1] ?? $reclamCols[0] ?? 'created_at'));
$reclamStatusCol = in_array('statut', $reclamCols) ? 'statut' : (in_array('status', $reclamCols) ? 'status' : 'statut');

$userCols = $getCols('users');
$userIdCol = in_array('user_id', $userCols) ? 'user_id' : (in_array('id', $userCols) ? 'id' : ($userCols[0] ?? 'id'));
$userNameCol = in_array('nom', $userCols) ? 'nom' : (in_array('name', $userCols) ? 'name' : ($userCols[1] ?? $userIdCol));
$userEmailCol = in_array('email', $userCols) ? 'email' : (in_array('mail', $userCols) ? 'mail' : 'email');

$catCols = $getCols('categories');
$catIdCol = in_array('categorie_id', $catCols) ? 'categorie_id' : (in_array('id', $catCols) ? 'id' : ($catCols[0] ?? 'id'));
$catNameCol = in_array('nom_categorie', $catCols) ? 'nom_categorie' : (in_array('nom', $catCols) ? 'nom' : (in_array('name', $catCols) ? 'name' : ($catCols[1] ?? $catIdCol)));
$catResponsableCol = in_array('responsable', $catCols) ? 'responsable' : (in_array('gestionnaire', $catCols) ? 'gestionnaire' : null);

// Récupérer les détails de la réclamation avec alias stables pour la vue
$sql = "SELECT ";
$sql .= "r." . $reclamIdCol . " AS id, ";
$sql .= "u." . $userNameCol . " AS user_name, ";
$sql .= "u." . $userEmailCol . " AS user_email, ";
$sql .= "r." . $reclamObjetCol . " AS sujet, ";
$sql .= "r." . $reclamDescCol . " AS description, ";
$sql .= "cat." . $catNameCol . " AS categorie_nom, ";
$sql .= "r." . $reclamDateCol . " AS created_at, ";
$sql .= "r." . $reclamStatusCol . " AS statut ";
$sql .= "FROM reclamations r ";
$sql .= "LEFT JOIN users u ON r." . $reclamUserCol . " = u." . $userIdCol . " ";
$sql .= "LEFT JOIN categories cat ON r." . ($reclamCatCol ?? $catIdCol) . " = cat." . $catIdCol . " ";
$sql .= "WHERE r." . $reclamIdCol . " = ? LIMIT 1";

$stmt = $pdo->prepare($sql);
$stmt->execute([$reclamation_id]);
$reclamation = $stmt->fetch();

if (!$reclamation) {
    redirect('index.php');
}

// Récupérer les pièces jointes avec les colonnes réelles
$attachments = [];
if ($pdo->query("SHOW TABLES LIKE 'pieces_jointes'")->fetchColumn()) {
    $stmt = $pdo->prepare("SELECT piece_id, reclam_id, nom_fichier, chemin_acces FROM pieces_jointes WHERE reclam_id = ?");
    $stmt->execute([$reclamation_id]);
    $attachments = $stmt->fetchAll();
}

// Récupérer les commentaires en détectant les colonnes
$comments = [];
if ($pdo->query("SHOW TABLES LIKE 'commentaires'")->fetchColumn()) {
    $commentCols = $getCols('commentaires');
    // FK to reclamation: try common variants (reclam_id, reclamation_id, reclam_id)
    if (in_array('reclam_id', $commentCols)) {
        $commentReclamFk = 'reclam_id';
    } elseif (in_array('reclamation_id', $commentCols)) {
        $commentReclamFk = 'reclamation_id';
    } elseif (in_array('reclamationId', $commentCols)) {
        $commentReclamFk = 'reclamationId';
    } elseif (in_array('reclam', $commentCols)) {
        $commentReclamFk = 'reclam';
    } else {
        $commentReclamFk = $commentCols[0] ?? 'reclamation_id';
    }

    // user FK
    $commentUserFk = in_array('user_id', $commentCols) ? 'user_id' : (in_array('author_id', $commentCols) ? 'author_id' : (in_array('user', $commentCols) ? 'user' : 'user_id'));

    // created/date column
    if (in_array('date_commentaire', $commentCols)) {
        $commentCreated = 'date_commentaire';
    } elseif (in_array('created_at', $commentCols)) {
        $commentCreated = 'created_at';
    } elseif (in_array('date', $commentCols)) {
        $commentCreated = 'date';
    } else {
        $commentCreated = $commentCols[0] ?? 'created_at';
    }

    // text/content column
    if (in_array('contenu_comm', $commentCols)) {
        $commentTextCol = 'contenu_comm';
    } elseif (in_array('contenu_comment', $commentCols)) {
        $commentTextCol = 'contenu_comment';
    } elseif (in_array('comment', $commentCols)) {
        $commentTextCol = 'comment';
    } elseif (in_array('message', $commentCols)) {
        $commentTextCol = 'message';
    } else {
        $commentTextCol = $commentCols[2] ?? 'comment';
    }

    $sqlC = "SELECT c.*, u." . $userNameCol . " AS user_name, u.role AS user_role, c." . $commentTextCol . " AS comment, c." . $commentCreated . " AS created_at ";
    $sqlC .= "FROM commentaires c ";
    $sqlC .= "LEFT JOIN users u ON c." . $commentUserFk . " = u." . $userIdCol . " ";
    $sqlC .= "WHERE c." . $commentReclamFk . " = ? ";
    $sqlC .= "ORDER BY c." . $commentCreated . " ASC";

    $stmt = $pdo->prepare($sqlC);
    $stmt->execute([$reclamation_id]);
    $comments = $stmt->fetchAll();
}

// Traitement du formulaire (Changement de statut / Commentaire)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['new_status'])) {
        $new_status = $_POST['new_status'];
        $old_status = $reclamation['statut'] ?? '';
        
        $updSql = "UPDATE reclamations SET " . $reclamStatusCol . " = ? WHERE " . $reclamIdCol . " = ?";
        $stmt = $pdo->prepare($updSql);
        $stmt->execute([$new_status, $reclamation_id]);

        // Créer une notification de changement de statut
        if ($old_status != $new_status) {
            try {
                // Récupérer l'user_id du propriétaire de la réclamation
                $owner_stmt = $pdo->prepare("SELECT " . $reclamUserCol . " FROM reclamations WHERE " . $reclamIdCol . " = ?");
                $owner_stmt->execute([$reclamation_id]);
                $owner_id = $owner_stmt->fetchColumn();
                
                if ($owner_id) {
                    $notif_stmt = $pdo->prepare("
                        INSERT INTO notifications (user_id, reclamation_id, type, titre, message)
                        VALUES (?, ?, 'changement_statut', ?, ?)
                    ");
                    $titre = t('notif_status_changed_title') . ": " . ($reclamation['sujet'] ?? t('your_claim'));
                    $message = t('notif_status_changed_message') . ": " . get_status_label($new_status);
                    $notif_stmt->execute([$owner_id, $reclamation_id, $titre, $message]);
                }
            } catch (PDOException $e) {
                // Ignorer l'erreur si la table n'existe pas
                error_log("Erreur notification: " . $e->getMessage());
            }
        }

        // Ajouter un commentaire système automatique (optionnel) si table commentaires existe
        $msg = t('system_status_change_comment') . ": " . get_status_label($new_status);
        if (isset($commentReclamFk) && isset($commentUserFk)) {
            $insCols = $commentReclamFk . ", " . $commentUserFk . ", " . $commentTextCol;
            $insSql = "INSERT INTO commentaires (" . $insCols . ") VALUES (?, ?, ?)";
            $stmt = $pdo->prepare($insSql);
            $stmt->execute([$reclamation_id, $user_id, $msg]);
        } else {
            // fallback
            $stmt = $pdo->prepare("INSERT INTO commentaires (reclamation_id, user_id, comment) VALUES (?, ?, ?)");
            $stmt->execute([$reclamation_id, $user_id, $msg]);
        }
        
        redirect("traitement.php?id=$reclamation_id");
    }
    
    if (isset($_POST['comment'])) {
        $comment = sanitize_input($_POST['comment']);
        if (!empty($comment)) {
            if (isset($commentReclamFk) && isset($commentUserFk)) {
                $insCols = $commentReclamFk . ", " . $commentUserFk . ", " . $commentTextCol;
                $insSql = "INSERT INTO commentaires (" . $insCols . ") VALUES (?, ?, ?)";
                $stmt = $pdo->prepare($insSql);
                $stmt->execute([$reclamation_id, $user_id, $comment]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO commentaires (reclamation_id, user_id, comment) VALUES (?, ?, ?)");
                $stmt->execute([$reclamation_id, $user_id, $comment]);
            }
            
            // Créer une notification de nouveau commentaire
            try {
                // Récupérer l'user_id du propriétaire de la réclamation
                $owner_stmt = $pdo->prepare("SELECT " . $reclamUserCol . " FROM reclamations WHERE " . $reclamIdCol . " = ?");
                $owner_stmt->execute([$reclamation_id]);
                $owner_id = $owner_stmt->fetchColumn();
                
                // Ne créer la notification que si ce n'est pas le propriétaire qui commente
                if ($owner_id && $owner_id != $user_id) {
                    $notif_stmt = $pdo->prepare("
                        INSERT INTO notifications (user_id, reclamation_id, type, titre, message)
                        VALUES (?, ?, 'nouveau_commentaire', ?, ?)
                    ");
                    $titre = "Nouveau commentaire sur: " . ($reclamation['sujet'] ?? 'Votre réclamation');
                    $message = "Un gestionnaire a ajouté un commentaire à votre réclamation";
                    $notif_stmt->execute([$owner_id, $reclamation_id, $titre, $message]);
                }
            } catch (PDOException $e) {
                // Ignorer l'erreur si la table n'existe pas
                error_log("Erreur notification: " . $e->getMessage());
            }
            
            redirect("traitement.php?id=$reclamation_id");
        }
    }
}

include '../../includes/head.php';
?>
<link rel="stylesheet" href="../../css/modern.css">
<link rel="stylesheet" href="../../css/gestionnaire.css">

<script src="../../js/main.js" defer></script>

<body>
    <!-- Navbar -->
    <nav class="navbar navbar-minimal">
        <div class="container-fluid">
            <span class="navbar-brand">
                <i class="bi bi-check-circle-fill me-2" style="color: #14b8a6;"></i>Resolve - Gestionnaire
            </span>
            <a href="index.php" class="btn-back">
                <i class="bi bi-arrow-left me-2"></i>Retour
            </a>
        </div>
    </nav>

    <div class="container-fluid py-4">
        <div class="row g-4">
            <!-- Sidebar Actions -->
            <div class="col-lg-3">
                <div class="sidebar-card">
                    <h6 class="section-title">Actions</h6>
                    <form method="POST" action="traitement.php?id=<?php echo $reclamation_id; ?>">
                        <label class="form-label fw-bold small mb-2">Changer le statut</label>
                        <select class="form-select mb-3" name="new_status">
                            <option value="en_cours" <?php echo $reclamation['statut'] == 'en_cours' ? 'selected' : ''; ?>>En cours</option>
                            <option value="attente_info" <?php echo $reclamation['statut'] == 'attente_info' ? 'selected' : ''; ?>>Attente d'info</option>
                            <option value="traite" <?php echo $reclamation['statut'] == 'traite' ? 'selected' : ''; ?>>Traité</option>
                            <option value="ferme" <?php echo $reclamation['statut'] == 'ferme' ? 'selected' : ''; ?>>Fermé</option>
                        </select>
                        <button type="submit" class="btn-update w-100">
                            <i class="bi bi-check-circle me-2"></i>Mettre à jour
                        </button>
                    </form>
                </div>

                <div class="sidebar-card">
                    <h6 class="section-title">Infos Réclamant</h6>
                    <div class="user-info-box">
                        <div class="user-avatar-large">
                            <?php echo strtoupper(substr($reclamation['user_name'], 0, 1)); ?>
                        </div>
                        <div>
                            <div class="fw-bold text-gray-900"><?php echo htmlspecialchars($reclamation['user_name']); ?></div>
                            <div class="small text-muted"><?php echo htmlspecialchars($reclamation['user_email']); ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Détails et Chat -->
            <div class="col-lg-9">
                <div class="main-content-container">
                    <div class="section-header d-flex justify-content-between align-items-start">
                        <div>
                            <h4>
                                <i class="bi bi-file-text me-2"></i>
                                Réclamation #<?php echo $reclamation['id']; ?> : <?php echo htmlspecialchars($reclamation['sujet']); ?>
                            </h4>
                        </div>
                        <span class="badge-status <?php echo get_status_badge($reclamation['statut']); ?>">
                            <?php echo get_status_label($reclamation['statut']); ?>
                        </span>
                    </div>

                    <div class="detail-card">
                        <div class="d-flex flex-wrap gap-3 mb-3">
                            <span class="info-meta">
                                <i class="bi bi-folder"></i>
                                <span><?php echo htmlspecialchars($reclamation['categorie_nom']); ?></span>
                            </span>
                            <span class="info-meta">
                                <i class="bi bi-clock"></i>
                                <span><?php echo format_date($reclamation['created_at']); ?></span>
                            </span>
                        </div>
                        
                        <div class="description-box">
                            <?php echo nl2br(htmlspecialchars(html_entity_decode($reclamation['description'], ENT_QUOTES, 'UTF-8'))); ?>
                        </div>

                        <?php if (count($attachments) > 0): ?>
                            <div class="section-header">
                                <h5><i class="bi bi-paperclip me-2"></i>Pièces jointes</h5>
                            </div>
                            <div class="d-flex flex-wrap gap-2">
                                <?php foreach ($attachments as $att): ?>
                                    <a href="../../<?php echo $att['chemin_acces']; ?>" download="<?php echo $att['nom_fichier']; ?>" class="attachment-btn">
                                        <i class="bi bi-file-earmark-arrow-down"></i>
                                        <span><?php echo $att['nom_fichier']; ?></span>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Historique / Commentaires -->
                    <div class="detail-card">
                        <div class="section-header">
                            <h5><i class="bi bi-chat-left-text me-2"></i>Suivi du dossier</h5>
                        </div>

                        <div class="comments-list mb-4">
                            <?php if (count($comments) > 0): ?>
                                <?php foreach ($comments as $comment): ?>
                                    <div class="comment-item">
                                        <div class="d-flex gap-3">
                                            <div class="comment-avatar <?php echo $comment['user_role'] == 'reclamant' ? 'bg-secondary' : 'bg-primary'; ?>">
                                                <?php echo strtoupper(substr($comment['user_name'], 0, 1)); ?>
                                            </div>
                                            <div class="flex-grow-1">
                                                <div class="comment-bubble">
                                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                                        <div class="d-flex align-items-center gap-2">
                                                            <strong><?php echo htmlspecialchars($comment['user_name']); ?></strong>
                                                            <span class="role-badge <?php echo $comment['user_role']; ?>">
                                                                <?php echo $comment['user_role']; ?>
                                                            </span>
                                                        </div>
                                                        <small class="text-muted"><?php echo format_date($comment['created_at']); ?></small>
                                                    </div>
                                                    <p class="mb-0">
                                                        <?php
                                                            $raw = htmlspecialchars_decode($comment['comment'] ?? '');
                                                            $allowed_tags = '<b><strong><i><em><u><br><a>';
                                                            $safe = strip_tags($raw, $allowed_tags);
                                                            echo nl2br($safe);
                                                        ?>
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="text-center text-muted py-4">
                                    <i class="bi bi-inbox" style="font-size: 2rem; display: block; margin-bottom: 0.5rem;"></i>
                                    Aucun historique disponible
                                </p>
                            <?php endif; ?>
                        </div>

                        <hr class="my-4">

                        <form method="POST" action="traitement.php?id=<?php echo $reclamation_id; ?>" class="comment-form">
                            <div class="mb-3">
                                <label for="comment" class="form-label fw-bold">
                                    <i class="bi bi-pencil-square me-2"></i>Ajouter une note ou une réponse
                                </label>
                                <textarea class="form-control" id="comment" name="comment" rows="4" placeholder="Écrire un message..." required></textarea>
                            </div>
                            <div class="text-end">
                                <button type="submit" class="btn-submit">
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
