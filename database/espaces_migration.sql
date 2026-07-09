-- ═══════════════════════════════════════════════════════════════════════
-- Espace Parent & Élève — notifications + annonces
-- ═══════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `notifications` (
    `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id`    INT UNSIGNED NOT NULL,
    `type`       ENUM('note','absence','paiement','annonce','info') DEFAULT 'info',
    `titre`      VARCHAR(255) NOT NULL,
    `message`    TEXT,
    `lien`       VARCHAR(500) DEFAULT '',
    `lu`         TINYINT(1) DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_user_lu`  (`user_id`, `lu`),
    INDEX `idx_created`  (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `annonces` (
    `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `titre`        VARCHAR(255) NOT NULL,
    `contenu`      TEXT NOT NULL,
    `audience`     ENUM('tous','parents','eleves','enseignants') DEFAULT 'tous',
    `publie_par`   INT UNSIGNED DEFAULT NULL,
    `published_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `actif`        TINYINT(1) DEFAULT 1,
    `created_at`   DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_actif_aud` (`actif`, `audience`),
    INDEX `idx_published` (`published_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Exemples d'annonces
INSERT INTO `annonces` (`titre`, `contenu`, `audience`, `published_at`) VALUES
('Rentrée scolaire 2025-2026', 'La rentrée scolaire est fixée au 2 septembre 2025. Les élèves sont priés d\'arriver ponctuellement.', 'tous', NOW()),
('Réunion parents d''élèves', 'Une réunion parents-professeurs aura lieu le vendredi 10 octobre à 17h en salle polyvalente.', 'parents', NOW()),
('Examens du premier trimestre', 'Les examens du premier trimestre se dérouleront du 15 au 19 décembre. Consultez le planning sur le tableau d''affichage.', 'eleves', NOW());
