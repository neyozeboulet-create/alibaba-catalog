-- =====================================================================
-- BASE DE DONNÉES : CATALOGUE PRODUITS ALIBABA
-- =====================================================================

-- Création de la base de données
CREATE DATABASE IF NOT EXISTS `alibaba_catalog` 
    CHARACTER SET utf8mb4 
    COLLATE utf8mb4_unicode_ci;

USE `alibaba_catalog`;

-- =====================================================================
-- TABLE : produits
-- =====================================================================
CREATE TABLE IF NOT EXISTS `produits` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `sku` VARCHAR(100) NOT NULL COMMENT 'Référence unique Alibaba',
    `titre` VARCHAR(255) NOT NULL COMMENT 'Nom du produit',
    `description` TEXT DEFAULT NULL COMMENT 'Description détaillée',
    `prix_origine` DECIMAL(10, 2) NOT NULL DEFAULT 0.00 COMMENT 'Prix d\'achat brut fournisseur (jamais affiché au client)',
    `image_url` VARCHAR(500) DEFAULT NULL COMMENT 'URL de l\'image produit',
    `date_creation` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Date d\'importation',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_sku` (`sku`),
    INDEX `idx_date_creation` (`date_creation`),
    INDEX `idx_titre` (`titre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Produits importés depuis Alibaba';

-- =====================================================================
-- TABLE : commandes (pour traçabilité)
-- =====================================================================
CREATE TABLE IF NOT EXISTS `commandes` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `reference` VARCHAR(20) NOT NULL COMMENT 'Référence unique de commande',
    `nom_client` VARCHAR(150) NOT NULL,
    `email_client` VARCHAR(150) NOT NULL,
    `telephone_client` VARCHAR(30) NOT NULL COMMENT 'Numéro Mobile Money',
    `total_ht` DECIMAL(10, 2) NOT NULL DEFAULT 0.00 COMMENT 'Total avec marge 10%',
    `statut` ENUM('en_attente', 'confirmee', 'expediee', 'annulee') NOT NULL DEFAULT 'en_attente',
    `date_creation` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_reference` (`reference`),
    INDEX `idx_date_creation` (`date_creation`),
    INDEX `idx_statut` (`statut`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Commandes clients';

-- =====================================================================
-- TABLE : details_commandes (lignes de commande)
-- =====================================================================
CREATE TABLE IF NOT EXISTS `details_commandes` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `commande_id` INT UNSIGNED NOT NULL,
    `produit_id` INT UNSIGNED NOT NULL,
    `sku_produit` VARCHAR(100) NOT NULL COMMENT 'SKU au moment de la commande (historique)',
    `titre_produit` VARCHAR(255) NOT NULL COMMENT 'Titre au moment de la commande',
    `prix_unitaire` DECIMAL(10, 2) NOT NULL COMMENT 'Prix avec marge au moment de la commande',
    `quantite` INT UNSIGNED NOT NULL DEFAULT 1,
    `sous_total` DECIMAL(10, 2) NOT NULL,
    PRIMARY KEY (`id`),
    INDEX `idx_commande` (`commande_id`),
    INDEX `idx_produit` (`produit_id`),
    CONSTRAINT `fk_details_commande` FOREIGN KEY (`commande_id`) REFERENCES `commandes`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_details_produit` FOREIGN KEY (`produit_id`) REFERENCES `produits`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Détails des produits par commande';

-- =====================================================================
-- TABLE : admin_users (pour authentification admin)
-- =====================================================================
CREATE TABLE IF NOT EXISTS `admin_users` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `username` VARCHAR(50) NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `email` VARCHAR(150) DEFAULT NULL,
    `derniere_connexion` TIMESTAMP NULL DEFAULT NULL,
    `date_creation` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Utilisateurs administrateurs';

-- =====================================================================
-- DONNÉES DE TEST (optionnel)
-- =====================================================================
-- Mot de passe par défaut pour admin : "admin123" (hashé avec password_hash)
INSERT IGNORE INTO `admin_users` (`username`, `password_hash`, `email`) VALUES 
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@example.com');

-- Quelques produits de test
INSERT IGNORE INTO `produits` (`sku`, `titre`, `description`, `prix_origine`, `image_url`) VALUES
('ALB-001', 'Écouteurs Bluetooth Sans Fil Pro', 'Écouteurs haute qualité avec réduction de bruit active, autonomie 30h', 15000.00, 'https://via.placeholder.com/300x300?text=Ecouteurs+Bluetooth'),
('ALB-002', 'Montre Connectée Sport GPS', 'Montre intelligente avec GPS, cardiofréquencemètre, étanche 50m', 45000.00, 'https://via.placeholder.com/300x300?text=Montre+Connectee'),
('ALB-003', 'Chargeur Rapide USB-C 65W', 'Chargeur GaN ultra-compact, compatible MacBook, iPhone, Android', 12000.00, 'https://via.placeholder.com/300x300?text=Chargeur+65W'),
('ALB-004', 'Enceinte Portable Waterproof IPX7', 'Enceinte Bluetooth 20W, basses profondes, 24h autonomie', 25000.00, 'https://via.placeholder.com/300x300?text=Enceinte+Portable'),
('ALB-005', 'Support Téléphone Voiture Magnétique', 'Support rotatif 360°, aimants puissants, compatible MagSafe', 8000.00, 'https://via.placeholder.com/300x300?text=Support+Voiture');