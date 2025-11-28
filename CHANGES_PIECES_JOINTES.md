# Mise à jour: Gestion des Pièces Jointes (pieces_jointes)

## Schéma réel de la table `pieces_jointes`:
```sql
- piece_id (INT, PK)
- reclam_id (INT, FK vers reclamations)
- nom_fichier (VARCHAR) - nom du fichier uploadé
- chemin_acces (VARCHAR) - chemin d'accès au fichier (ex: uploads/file.pdf)
```

## Fichiers modifiés:

### 1. **espaces/reclamant/soumission.php** (Upload de fichier)
**Avant:** Tentait d'insérer avec colonnes non-existent (`reclamation_id`, `file_path`)
**Après:** 
```php
$sql = "INSERT INTO pieces_jointes (reclam_id, nom_fichier, chemin_acces) VALUES (?, ?, ?)";
$stmt = $pdo->prepare($sql);
$stmt->execute([$reclamation_id, $file_name, 'uploads/' . $file_name]);
```
- Utilise la clé étrangère correcte: `reclam_id`
- Stocke le nom de fichier dans `nom_fichier`
- Stocke le chemin dans `chemin_acces`

### 2. **espaces/reclamant/details.php** (Affichage de la réclamation)
**Avant:** Tentait de lire `file_path` (colonne inexistante)
**Après:**
```php
$sql = "SELECT piece_id, reclam_id, nom_fichier, chemin_acces FROM pieces_jointes WHERE reclam_id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$reclamation_id]);
$attachments = $stmt->fetchAll();
```
- SELECT spécifique avec colonnes réelles
- Affichage: Bouton de téléchargement avec `chemin_acces` et étiquette `nom_fichier`
```html
<a href="../../<?php echo $att['chemin_acces']; ?>" download="<?php echo $att['nom_fichier']; ?>" class="btn btn-outline-secondary btn-sm">
    <i class="bi bi-file-earmark-down me-1"></i> <?php echo $att['nom_fichier']; ?>
</a>
```

### 3. **espaces/gestionnaire/traitement.php** (Gestion des réclamations)
**Avant:** Tentait de détecter colonnes avec logique conditionnelle (usage de `file_path`)
**Après:**
```php
$stmt = $pdo->prepare("SELECT piece_id, reclam_id, nom_fichier, chemin_acces FROM pieces_jointes WHERE reclam_id = ?");
$stmt->execute([$reclamation_id]);
$attachments = $stmt->fetchAll();
```
- SELECT simplifié
- Affichage identique à details.php (bouton téléchargement avec `chemin_acces`)

## Fonctionnalités maintenant disponibles:

✅ **Upload de fichier:** Crée automatiquement l'entry dans `pieces_jointes` avec noms corrects
✅ **Affichage sur details.php:** Montre les fichiers avec lien de téléchargement
✅ **Affichage sur gestionnaire/traitement.php:** Gestionnaire voit et peut télécharger les pièces jointes
✅ **Téléchargement direct:** Attribut `download` active le téléchargement au lieu d'ouvrir dans le navigateur

## Chemins de fichiers:
- Les fichiers uploadés vont dans: `/uploads/`
- Le chemin stocké dans `chemin_acces` est: `uploads/nom_du_fichier`
- Le lien HTML utilise: `../../uploads/nom_du_fichier` (remonte 2 niveaux depuis /espaces/reclamant/ ou /espaces/gestionnaire/)

## Zones d'impact:
- **Reclamant:** Peut uploader des fichiers lors de la soumission, voir ses pièces jointes
- **Gestionnaire:** Peut voir et télécharger les pièces jointes des réclamations
- **Admin:** Aucun changement (admin n'affiche pas les pièces jointes)

---
Date: 2025-11-28
