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
        $updSql = "UPDATE reclamations SET " . $reclamStatusCol . " = ? WHERE " . $reclamIdCol . " = ?";
        $stmt = $pdo->prepare($updSql);
        $stmt->execute([$new_status, $reclamation_id]);

        // Ajouter un commentaire système automatique (optionnel) si table commentaires existe
        $msg = "Le statut a été changé en : " . get_status_label($new_status);
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
            redirect("traitement.php?id=$reclamation_id");
        }
    }
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
        color: var(--gray-700) !important;
        border: 2px solid var(--gray-200);
        padding: 0.5rem 1rem;
        border-radius: var(--radius-md);
        font-weight: 600;
        transition: all var(--transition-base);
        background: white;
        text-decoration: none;
    }
    
    .btn-back:hover {
        border-color: var(--primary-blue);
        color: var(--primary-blue) !important;
        transform: translateY(-2px);
        box-shadow: var(--shadow-sm);
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
    
    .section-header {
        margin-bottom: 2rem;
        padding-bottom: 1.25rem;
        border-bottom: 2px solid var(--gray-100);
        animation: fadeIn 0.8s ease-out;
    }
    
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    .section-header h4 {
        color: var(--gray-900);
        font-weight: 700;
        font-size: 1.5rem;
        margin-bottom: 0.5rem;
    }
    
    .section-header i {
        color: var(--primary-blue);
        animation: bounce 2s infinite;
    }
    
    @keyframes bounce {
        0%, 100% { transform: translateY(0); }
        50% { transform: translateY(-5px); }
    }
    
    .detail-card {
        background: white;
        border: 1px solid var(--gray-200);
        border-radius: var(--radius-xl);
        padding: 2.5rem;
        box-shadow: var(--shadow-md);
        margin-bottom: 2rem;
        animation: fadeInUp 0.6s ease-out;
        transition: var(--transition-base);
    }
    
    .detail-card:hover {
        box-shadow: var(--shadow-xl);
        transform: translateY(-2px);
    }
    
    .badge-status {
        padding: 0.625rem 1.25rem;
        border-radius: var(--radius-full);
        font-weight: 600;
        font-size: 0.875rem;
        animation: pulse 2s infinite;
        white-space: nowrap;
    }
    
    @keyframes pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.8; }
    }
    
    .info-meta {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        color: var(--gray-500);
        font-size: 0.875rem;
        margin-right: 1.5rem;
    }
    
    .info-meta i {
        color: var(--primary-blue);
    }
    
    .description-box {
        background: linear-gradient(135deg, rgba(20, 184, 166, 0.05) 0%, rgba(14, 165, 233, 0.05) 100%);
        border: 1px solid var(--gray-200);
        border-radius: var(--radius-lg);
        padding: 2rem;
        margin: 2rem 0;
        line-height: 1.8;
        transition: var(--transition-base);
    }
    
    .description-box:hover {
        border-color: var(--primary-blue);
        box-shadow: 0 0 0 4px rgba(20, 184, 166, 0.1);
    }
    
    .attachment-btn {
        background: white;
        border: 1px solid var(--gray-300);
        padding: 0.75rem 1.25rem;
        border-radius: var(--radius-md);
        transition: all var(--transition-base);
        color: var(--gray-700);
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .attachment-btn:hover {
        background: var(--gradient-blue);
        color: white;
        border-color: transparent;
        transform: translateY(-2px);
        box-shadow: var(--shadow-md);
    }
    
    .comment-item {
        margin-bottom: 2rem;
        animation: slideInLeft 0.6s ease-out backwards;
    }
    
    .comment-item:nth-child(1) { animation-delay: 0.1s; }
    .comment-item:nth-child(2) { animation-delay: 0.2s; }
    .comment-item:nth-child(3) { animation-delay: 0.3s; }
    .comment-item:nth-child(4) { animation-delay: 0.4s; }
    .comment-item:nth-child(5) { animation-delay: 0.5s; }
    
    @keyframes slideInLeft {
        from {
            opacity: 0;
            transform: translateX(-30px);
        }
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }
    
    .comment-avatar {
        width: 45px;
        height: 45px;
        border-radius: var(--radius-full);
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 1rem;
        flex-shrink: 0;
        transition: var(--transition-base);
    }
    
    .comment-avatar:hover {
        transform: scale(1.1) rotate(5deg);
    }
    
    .comment-bubble {
        background: white;
        border: 1px solid var(--gray-200);
        border-radius: var(--radius-lg);
        padding: 1.5rem;
        position: relative;
        transition: var(--transition-base);
        line-height: 1.7;
    }
    
    .comment-bubble:hover {
        box-shadow: var(--shadow-lg);
        transform: translateX(4px);
    }
    
    .comment-bubble::before {
        content: '';
        position: absolute;
        left: -8px;
        top: 20px;
        width: 0;
        height: 0;
        border-top: 8px solid transparent;
        border-bottom: 8px solid transparent;
        border-right: 8px solid var(--gray-200);
    }
    
    .role-badge {
        padding: 0.25rem 0.75rem;
        border-radius: var(--radius-full);
        font-size: 0.75rem;
        font-weight: 600;
        white-space: nowrap;
    }
    
    .role-badge.gestionnaire {
        background: linear-gradient(135deg, #14b8a6 0%, #0ea5e9 100%);
        color: white;
    }
    
    .role-badge.reclamant {
        background: linear-gradient(135deg, #9ca3af 0%, #6b7280 100%);
        color: white;
    }
    
    .comment-form textarea {
        border: 2px solid var(--gray-200);
        border-radius: var(--radius-lg);
        padding: 1rem;
        font-size: 0.938rem;
        transition: all var(--transition-base);
        resize: vertical;
        min-height: 100px;
    }
    
    .comment-form textarea:focus {
        border-color: var(--primary-blue);
        box-shadow: 0 0 0 4px rgba(20, 184, 166, 0.1);
        min-height: 140px;
    }
    
    .btn-submit {
        background: var(--gradient-blue);
        color: white;
        border: none;
        padding: 0.875rem 2rem;
        border-radius: var(--radius-md);
        font-weight: 600;
        transition: all var(--transition-base);
        box-shadow: var(--shadow-md);
    }
    
    .btn-submit:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-xl);
        color: white;
    }
    
    .sidebar-card {
        background: white;
        border-radius: var(--radius-lg);
        padding: 1.5rem;
        box-shadow: var(--shadow-md);
        margin-bottom: 1.5rem;
        animation: fadeInLeft 0.6s ease-out;
    }
    
    @keyframes fadeInLeft {
        from {
            opacity: 0;
            transform: translateX(-20px);
        }
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }
    
    .section-title {
        color: var(--gray-500);
        font-weight: 500;
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin-bottom: 1rem;
    }
    
    .form-select, .form-control {
        border: 2px solid var(--gray-200);
        border-radius: var(--radius-md);
        padding: 0.625rem 0.875rem;
        font-size: 0.875rem;
        transition: all var(--transition-base);
        background-color: var(--gray-50);
    }
    
    .form-select:focus, .form-control:focus {
        border-color: var(--primary-blue);
        box-shadow: 0 0 0 4px rgba(20, 184, 166, 0.1);
        background-color: white;
    }
    
    .btn-update {
        background: var(--gradient-blue);
        color: white;
        border: none;
        padding: 0.75rem 1.5rem;
        border-radius: var(--radius-md);
        font-weight: 600;
        transition: all var(--transition-base);
        box-shadow: var(--shadow-sm);
    }
    
    .btn-update:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-lg);
        color: white;
    }
    
    .user-info-box {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 1rem;
        background: linear-gradient(135deg, rgba(20, 184, 166, 0.05) 0%, rgba(14, 165, 233, 0.05) 100%);
        border-radius: var(--radius-lg);
        border: 1px solid var(--gray-200);
    }
    
    .user-avatar-large {
        width: 50px;
        height: 50px;
        border-radius: var(--radius-full);
        background: var(--gradient-blue);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 1.25rem;
    }
</style>

<script src="../../js/main.js" defer></script>

<body>
    <!-- Navbar -->
    <nav class="navbar navbar-minimal">
        <div class="container-fluid">
            <span class="navbar-brand">
                <i class="bi bi-person-workspace"></i> Espace Gestionnaire
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
                            <?php echo nl2br(htmlspecialchars($reclamation['description'])); ?>
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
