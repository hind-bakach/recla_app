<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/lang.php';

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

// Ensure `sujet` key exists to avoid undefined index warnings
if (!isset($reclamation['sujet'])) {
    $sujetCol = detect_column($pdo, 'reclamations', ['sujet', 'subject', 'titre', 'title', 'objet', 'object']);
    if ($sujetCol && isset($reclamation[$sujetCol])) {
        $reclamation['sujet'] = $reclamation[$sujetCol];
    } else {
        $reclamation['sujet'] = 'Sans sujet';
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

        // Créer une notification pour tous les gestionnaires
        try {
            // Récupérer les informations du réclamant
            $reclamantInfo = $pdo->prepare("SELECT nom, prenom FROM users WHERE user_id = ?");
            $reclamantInfo->execute([$user_id]);
            $reclamant = $reclamantInfo->fetch();
            $nom_complet = ($reclamant['prenom'] ?? '') . ' ' . ($reclamant['nom'] ?? 'Réclamant');
            
            // Récupérer tous les utilisateurs avec le rôle gestionnaire
            $gestionnaires = $pdo->query("SELECT user_id FROM users WHERE role = 'gestionnaire'")->fetchAll(PDO::FETCH_COLUMN);
            
            if (count($gestionnaires) > 0) {
                // Préparer les données de notification
                $titre = "Nouveau commentaire de " . trim($nom_complet);
                // Tronquer le commentaire s'il est trop long
                $comment_preview = strlen($comment) > 100 ? substr($comment, 0, 100) . '...' : $comment;
                $message = trim($nom_complet) . " a écrit : \"" . $comment_preview . "\" sur la réclamation #" . $reclamation_id;
                
                // Vérifier si la colonne role_destinataire existe
                $checkCol = $pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='notifications' AND COLUMN_NAME='role_destinataire'");
                $hasRoleCol = $checkCol->fetchColumn() > 0;
                
                // Insérer une notification pour chaque gestionnaire
                if ($hasRoleCol) {
                    $notifStmt = $pdo->prepare("
                        INSERT INTO notifications (user_id, reclamation_id, type, titre, message, role_destinataire, is_read, created_at) 
                        VALUES (?, ?, 'commentaire_reclamant', ?, ?, 'gestionnaire', 0, NOW())
                    ");
                    foreach ($gestionnaires as $gestionnaire_id) {
                        $notifStmt->execute([$gestionnaire_id, $reclamation_id, $titre, $message]);
                    }
                } else {
                    $notifStmt = $pdo->prepare("
                        INSERT INTO notifications (user_id, reclamation_id, type, titre, message, is_read, created_at) 
                        VALUES (?, ?, 'commentaire_reclamant', ?, ?, 0, NOW())
                    ");
                    foreach ($gestionnaires as $gestionnaire_id) {
                        $notifStmt->execute([$gestionnaire_id, $reclamation_id, $titre, $message]);
                    }
                }
            }
        } catch (PDOException $e) {
            // Ignorer les erreurs de notification silencieusement
            error_log("Erreur création notification gestionnaire: " . $e->getMessage());
        }

        // Rafraîchir la page
        redirect("details.php?id=$reclamation_id");
    }
}

include '../../includes/head.php';
?>
<link rel="stylesheet" href="../../css/modern.css">
<link rel="stylesheet" href="../../css/reclamant.css">

<script src="../../js/main.js" defer></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const textarea = document.getElementById('comment');
    if (textarea) {
        textarea.addEventListener('focus', function() {
            this.style.minHeight = '150px';
        });
        
        const counter = document.createElement('small');
        counter.style.float = 'right';
        counter.style.color = 'var(--gray-400)';
        textarea.parentElement.appendChild(counter);
        
        textarea.addEventListener('input', function() {
            counter.textContent = `${this.value.length} caractères`;
        });
    }
    
    const form = document.querySelector('.comment-form');
    if (form) {
        form.addEventListener('submit', function() {
            const btn = this.querySelector('.btn-send');
            if (btn) {
                btn.innerHTML = '<i class="bi bi-arrow-repeat spin"></i> Envoi...';
                btn.disabled = true;
            }
        });
    }
});
</script>

<body>
    <!-- Navbar Minimaliste -->
    <nav class="navbar navbar-minimal navbar-expand-lg">
        <div class="container py-2">
            <a class="navbar-brand" href="index.php">
                <i class="bi bi-check-circle-fill me-2" style="color: #14b8a6;"></i>Resolve
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" style="border-color: #e5e7eb;">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-center">
                    <li class="nav-item me-3">
                        <span style="color: #6b7280;"><?php echo t('nav_hello'); ?>, <strong style="color: #111827;"><?php echo htmlspecialchars($_SESSION['user_name']); ?></strong></span>
                    </li>
                    <li class="nav-item">
                        <a class="btn btn-logout" href="../../frontend/deconnexion.php">
                            <i class="bi bi-box-arrow-right me-1"></i><?php echo t('nav_logout'); ?>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container pb-5">
        <div class="main-content-container">
            <!-- En-tête -->
            <div class="d-flex justify-content-between align-items-start mb-4">
                <div>
                    <h6 class="section-title"><?php echo t('dashboard_area_claimant'); ?></h6>
                    <h1 class="main-title"><?php echo t('claim_detail_title'); ?></h1>
                </div>
                <a href="index.php" class="btn-back">
                    <i class="bi bi-arrow-left me-2"></i><?php echo t('back_to_dashboard'); ?>
                </a>
            </div>

            <!-- Détails de la réclamation -->
            <div class="detail-card">
                <div class="section-header">
                    <i class="bi bi-file-text"></i>
                    <h5 class="mb-0 fw-bold" style="color: var(--gray-900);">
                        <?php echo t('claim_number'); ?> #<?php echo $reclamation['id']; ?>
                    </h5>
                    <div class="ms-auto">
                        <?php 
                            $statut = $reclamation['statut'];
                            $badge_class = '';
                            $statut_label = '';
                            
                            if ($statut === 'ferme' || $statut === 'fermee' || $statut === 'closed') {
                                $badge_class = 'bg-secondary-subtle text-secondary-emphasis';
                                $statut_label = t('status_closed');
                            } elseif ($statut === 'traite' || $statut === 'traitee' || $statut === 'acceptee' || $statut === 'accepted') {
                                $badge_class = 'bg-primary-subtle text-primary-emphasis';
                                $statut_label = t('status_accepted');
                            } elseif (stripos($statut, 'attente') !== false || stripos($statut, 'info') !== false || stripos($statut, 'pending') !== false) {
                                $badge_class = 'bg-danger-subtle text-danger-emphasis';
                                $statut_label = t('status_awaiting_info');
                            } elseif ($statut === 'en_cours' || $statut === 'in_progress') {
                                $badge_class = 'bg-success-subtle text-success-emphasis';
                                $statut_label = t('status_processing');
                            } else {
                                $badge_class = 'bg-success-subtle text-success-emphasis';
                                $statut_label = t('status_processing');
                            }
                        ?>
                        <span class="badge <?php echo $badge_class; ?> badge-status">
                            <?php echo $statut_label; ?>
                        </span>
                    </div>
                </div>
                    
                <h3 class="fw-bold mb-3" style="color: var(--gray-900);"><?php echo htmlspecialchars(html_entity_decode($reclamation['sujet'], ENT_QUOTES, 'UTF-8')); ?></h3>
                
                <div class="mb-4 d-flex gap-4">
                    <span class="info-meta">
                        <i class="bi bi-calendar3"></i>
                        <?php echo t('submitted_on'); ?> <?php echo format_date($reclamation['created_at']); ?>
                    </span>
                    <span class="info-meta">
                        <i class="bi bi-tag"></i>
                        <?php echo htmlspecialchars(html_entity_decode($reclamation['categorie_nom'] ?? t('claim_category'), ENT_QUOTES, 'UTF-8')); ?>
                    </span>
                </div>

                <div class="description-box">
                    <p class="mb-0" style="white-space: pre-line; color: var(--gray-700);"><?php echo htmlspecialchars(html_entity_decode($reclamation['description'], ENT_QUOTES, 'UTF-8')); ?></p>
                </div>

                <?php if (count($attachments) > 0): ?>
                    <div class="section-header mt-4">
                        <i class="bi bi-paperclip"></i>
                        <h6 class="mb-0 fw-bold" style="color: var(--gray-900);"><?php echo t('attached_file'); ?></h6>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <?php foreach ($attachments as $att): ?>
                            <a href="../../<?php echo $att['chemin_acces']; ?>" download="<?php echo $att['nom_fichier']; ?>" class="attachment-btn">
                                <i class="bi bi-file-earmark-arrow-down"></i>
                                <?php echo $att['nom_fichier']; ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Historique / Commentaires -->
            <div class="detail-card">
                <div class="section-header">
                    <i class="bi bi-chat-dots"></i>
                    <h5 class="mb-0 fw-bold" style="color: var(--gray-900);"><?php echo t('comments_history'); ?></h5>
                </div>
                
                <div class="timeline">
                    <?php if (count($comments) > 0): ?>
                        <?php foreach ($comments as $comment): ?>
                            <div class="comment-item d-flex">
                                <div class="flex-shrink-0">
                                    <div class="comment-avatar <?php echo $comment['user_role'] == 'reclamant' ? 'bg-primary' : 'bg-warning'; ?>">
                                        <?php echo strtoupper(substr($comment['user_name'], 0, 1)); ?>
                                    </div>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <div class="comment-bubble">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <div>
                                                <strong style="color: var(--gray-900);"><?php echo htmlspecialchars(html_entity_decode($comment['user_name'], ENT_QUOTES, 'UTF-8')); ?></strong>
                                                <span class="role-badge ms-2"><?php echo $comment['user_role']; ?></span>
                                            </div>
                                            <small class="text-muted"><?php echo format_date($comment['created_at']); ?></small>
                                        </div>
                                        <p class="mb-0" style="color: var(--gray-700);"><?php echo nl2br(htmlspecialchars(html_entity_decode($comment['comment'], ENT_QUOTES, 'UTF-8'))); ?></p>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="bi bi-inbox"></i>
                            <p class="mt-3"><?php echo t('no_comments'); ?></p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Formulaire de réponse -->
                <?php if ($reclamation['statut'] !== 'ferme'): ?>
                    <hr class="my-4" style="border-color: var(--gray-200);">
                    <form method="POST" action="details.php?id=<?php echo $reclamation_id; ?>" class="comment-form">
                        <div class="mb-3">
                            <label for="comment" class="form-label fw-semibold" style="color: var(--gray-700);">
                                <i class="bi bi-chat-left-text me-2" style="color: var(--primary-blue);"></i>
                                <?php echo t('add_comment'); ?>
                            </label>
                            <textarea class="form-control" id="comment" name="comment" 
                                      placeholder="<?php echo t('comment_placeholder'); ?>" 
                                      required></textarea>
                        </div>
                        <div class="text-end">
                            <button type="submit" class="btn-send">
                                <i class="bi bi-send-fill me-2"></i><?php echo t('send'); ?>
                            </button>
                        </div>
                    </form>
                <?php else: ?>
                    <div class="alert mb-0" style="background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%); border: 1px solid #fecaca; color: var(--danger); text-align: center; padding: 1rem; border-radius: var(--radius-lg);">
                        <i class="bi bi-lock-fill me-2"></i><?php echo t('claim_closed_no_comment'); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php include '../../includes/footer.php'; ?>
</body>
</html>
