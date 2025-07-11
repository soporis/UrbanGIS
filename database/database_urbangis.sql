-- Base de données UrbanGIS
-- Schéma MySQL pour l'application de gestion d'équipements urbains

CREATE DATABASE IF NOT EXISTS urbangis_db CHARACTER SET utf8 COLLATE utf8_unicode_ci;
USE urbangis_db;

-- Table des utilisateurs
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    name VARCHAR(255) NOT NULL,
    role ENUM('operator', 'manager', 'administrator') DEFAULT 'operator',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Table des projets
CREATE TABLE projects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Table des types d'équipements
CREATE TABLE equipment_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    icon VARCHAR(100) DEFAULT 'package',
    color VARCHAR(7) DEFAULT '#3B82F6',
    schema_json TEXT, -- Stockage des champs personnalisés en JSON
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Table des équipements
CREATE TABLE equipment (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type_id INT NOT NULL,
    project_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    latitude DECIMAL(10, 8) NOT NULL,
    longitude DECIMAL(11, 8) NOT NULL,
    altitude DECIMAL(8, 2) NULL,
    accuracy DECIMAL(8, 2) NULL,
    attributes_json TEXT, -- Stockage des attributs personnalisés en JSON
    status ENUM('active', 'inactive', 'maintenance', 'decommissioned') DEFAULT 'active',
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (type_id) REFERENCES equipment_types(id) ON DELETE CASCADE,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Table des photos d'équipements
CREATE TABLE equipment_photos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    equipment_id INT NOT NULL,
    filename VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    file_size INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (equipment_id) REFERENCES equipment(id) ON DELETE CASCADE
);

-- Table des interventions
CREATE TABLE interventions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    equipment_id INT NOT NULL,
    user_id INT NOT NULL,
    date DATE NOT NULL,
    status ENUM('planned', 'in_progress', 'completed', 'cancelled') DEFAULT 'planned',
    observed_condition VARCHAR(100) NOT NULL,
    actions_performed TEXT, -- JSON array des actions
    comments TEXT,
    time_spent INT DEFAULT 0, -- en minutes
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (equipment_id) REFERENCES equipment(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Table des photos d'interventions
CREATE TABLE intervention_photos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    intervention_id INT NOT NULL,
    filename VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    file_size INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (intervention_id) REFERENCES interventions(id) ON DELETE CASCADE
);

-- Index pour optimiser les performances
CREATE INDEX idx_equipment_coordinates ON equipment(latitude, longitude);
CREATE INDEX idx_equipment_type ON equipment(type_id);
CREATE INDEX idx_equipment_project ON equipment(project_id);
CREATE INDEX idx_interventions_equipment ON interventions(equipment_id);
CREATE INDEX idx_interventions_date ON interventions(date);

-- Données de test
INSERT INTO users (email, password_hash, name, role) VALUES 
('admin@urbangis.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrateur', 'administrator'),
('manager@urbangis.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Gestionnaire', 'manager'),
('operator@urbangis.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Opérateur', 'operator');

INSERT INTO projects (name, description) VALUES 
('Centre Ville', 'Inventaire et maintenance du mobilier urbain du centre ville'),
('Zone Industrielle', 'Gestion des équipements de la zone industrielle');

INSERT INTO equipment_types (name, icon, color, schema_json) VALUES 
('Potelet', 'shield', '#3B82F6', '[{"name":"material","label":"Matériau","type":"select","required":true,"options":["Acier","Fonte","Béton","Plastique"]},{"name":"height","label":"Hauteur (cm)","type":"number","required":true},{"name":"condition","label":"État","type":"select","required":true,"options":["Neuf","Bon","Dégradé","Hors service"]}]'),
('Luminaire', 'lightbulb', '#F59E0B', '[{"name":"type","label":"Type","type":"select","required":true,"options":["LED","Sodium","Halogène","Fluorescent"]},{"name":"power","label":"Puissance (W)","type":"number","required":true},{"name":"height","label":"Hauteur (m)","type":"number","required":true},{"name":"condition","label":"État","type":"select","required":true,"options":["Neuf","Bon","Dégradé","Hors service"]}]'),
('Banc Public', 'armchair', '#10B981', '[{"name":"material","label":"Matériau","type":"select","required":true,"options":["Bois","Métal","Béton","Plastique recyclé"]},{"name":"length","label":"Longueur (cm)","type":"number","required":true},{"name":"has_backrest","label":"Avec dossier","type":"checkbox","required":false},{"name":"condition","label":"État","type":"select","required":true,"options":["Neuf","Bon","Dégradé","Hors service"]}]');