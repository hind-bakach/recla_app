-- Création de la base de données
CREATE DATABASE IF NOT EXISTS reclamation_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE reclamation_db;

-- Table des utilisateurs
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('reclamant', 'gestionnaire', 'administrateur') DEFAULT 'reclamant',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Table des catégories de réclamations
CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    description TEXT
);

-- Insertion des catégories par défaut
INSERT INTO categories (nom, description) VALUES 
('Administrative', 'Problèmes liés aux dossiers administratifs, inscriptions, etc.'),
('Technique', 'Problèmes informatiques, matériel, accès réseau, etc.'),
('Financière', 'Facturation, bourses, paiements, etc.'),
('Service', 'Qualité de service, accueil, etc.'),
('Autre', 'Autres types de réclamations');

-- Table des réclamations
CREATE TABLE IF NOT EXISTS claims (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    category_id INT NOT NULL,
    sujet VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    statut ENUM('en_cours', 'traite', 'ferme', 'attente_info') DEFAULT 'en_cours',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id)
);

-- Table des pièces jointes
CREATE TABLE IF NOT EXISTS attachments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    claim_id INT NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (claim_id) REFERENCES claims(id) ON DELETE CASCADE
);

-- Table des commentaires / historique
CREATE TABLE IF NOT EXISTS comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    claim_id INT NOT NULL,
    user_id INT NOT NULL,
    comment TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (claim_id) REFERENCES claims(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Création d'un administrateur par défaut (Mot de passe: admin123)
-- Le hash doit être généré avec password_hash('admin123', PASSWORD_DEFAULT)
INSERT INTO users (nom, email, password, role) VALUES 
('Administrateur', 'admin@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'administrateur');

-- Création d'un gestionnaire par défaut (Mot de passe: manager123)
INSERT INTO users (nom, email, password, role) VALUES 
('Gestionnaire', 'manager@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'gestionnaire');
