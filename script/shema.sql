SET NAMES utf8mb4;
SET time_zone = "+00:00";

-- =========================
-- UTILISATEURS
-- =========================
CREATE TABLE IF NOT EXISTS utilisateurs (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  pseudo VARCHAR(50) NOT NULL UNIQUE,
  email VARCHAR(190) NOT NULL UNIQUE,
  mot_de_passe_hash VARCHAR(255) NOT NULL,
  role ENUM('utilisateur','employe','administrateur') NOT NULL DEFAULT 'utilisateur',
  credits INT NOT NULL DEFAULT 20,
  cree_le TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================
-- VEHICULES
-- =========================
CREATE TABLE IF NOT EXISTS vehicules (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  utilisateur_id INT UNSIGNED NOT NULL,
  marque VARCHAR(60) NOT NULL,
  modele VARCHAR(60) NOT NULL,
  couleur VARCHAR(30),
  immatriculation VARCHAR(20) UNIQUE,
  energie ENUM('essence','diesel','hybride','electrique') NOT NULL,
  places TINYINT UNSIGNED NOT NULL,
  date_premiere_immatriculation DATE,
  CONSTRAINT fk_vehicule_utilisateur FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
  INDEX idx_vehicules_utilisateur (utilisateur_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================
-- COVOITURAGES (VOYAGES)
-- =========================
CREATE TABLE IF NOT EXISTS voyages (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  chauffeur_id INT UNSIGNED NOT NULL,
  vehicule_id INT UNSIGNED NOT NULL,
  ville_depart VARCHAR(120) NOT NULL,
  ville_arrivee VARCHAR(120) NOT NULL,
  date_depart DATETIME NOT NULL,
  date_arrivee DATETIME,
  prix DECIMAL(6,2) NOT NULL,
  places_disponibles TINYINT UNSIGNED NOT NULL,
  -- statut aligné aux US : ouvert -> en_cours -> termine / annule
  statut ENUM('ouvert','en_cours','termine','annule') NOT NULL DEFAULT 'ouvert',
  -- flag écolo (optionnel mais pratique pour le filtre)
  ecologique TINYINT(1) NOT NULL DEFAULT 0,
  CONSTRAINT fk_voyage_chauffeur FOREIGN KEY (chauffeur_id) REFERENCES utilisateurs(id),
  CONSTRAINT fk_voyage_vehicule  FOREIGN KEY (vehicule_id)  REFERENCES vehicules(id),
  INDEX idx_recherche (ville_depart, ville_arrivee, date_depart),
  INDEX idx_statut (statut)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================
-- PARTICIPATIONS (passagers sur un voyage)
-- =========================
CREATE TABLE IF NOT EXISTS participations (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  voyage_id INT UNSIGNED NOT NULL,
  passager_id INT UNSIGNED NOT NULL,
  statut ENUM('confirme','annule') NOT NULL DEFAULT 'confirme',
  inscrit_le TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_part_voyage  FOREIGN KEY (voyage_id)  REFERENCES voyages(id) ON DELETE CASCADE,
  CONSTRAINT fk_part_passager FOREIGN KEY (passager_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
  UNIQUE KEY uq_voyage_passager (voyage_id, passager_id) -- un passager une fois par voyage
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================
-- PREFERENCES (chauffeur)
-- =========================
CREATE TABLE IF NOT EXISTS preferences (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  utilisateur_id INT UNSIGNED NOT NULL,
  fumeur TINYINT(1) NOT NULL DEFAULT 0,
  animaux TINYINT(1) NOT NULL DEFAULT 0,
  autre_pref VARCHAR(255),
  CONSTRAINT fk_pref_user FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
  UNIQUE KEY uq_pref_user (utilisateur_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================
-- JOURNAL DES CREDITS (bonus pro pour traçabilité)
-- =========================
CREATE TABLE IF NOT EXISTS journal_credits (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  utilisateur_id INT UNSIGNED NOT NULL,
  delta INT NOT NULL,               -- + ou -
  motif VARCHAR(120) NOT NULL,      -- 'participation','annulation','commission','bonus'
  cree_le TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_journal_user (utilisateur_id),
  CONSTRAINT fk_journal_user FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
