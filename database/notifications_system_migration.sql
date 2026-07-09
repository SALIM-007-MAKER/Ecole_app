-- ═══════════════════════════════════════════════════════
--  Système de notifications multi-canaux
--  Exécuter dans phpMyAdmin ou via mysql CLI
-- ═══════════════════════════════════════════════════════

-- Préférences utilisateur (canal × déclencheur)
CREATE TABLE IF NOT EXISTS `notification_preferences` (
    `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id`       INT UNSIGNED NOT NULL,
    `trigger_type`  ENUM('note','absence','paiement','annonce') NOT NULL,
    `canal_interne` TINYINT(1) DEFAULT 1,
    `canal_email`   TINYINT(1) DEFAULT 0,
    `canal_sms`     TINYINT(1) DEFAULT 0,
    `created_at`    DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_user_trigger` (`user_id`, `trigger_type`),
    INDEX `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Journal d'envoi (audit de tous les envois par canal)
CREATE TABLE IF NOT EXISTS `notification_logs` (
    `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id`      INT UNSIGNED NOT NULL,
    `trigger_type` VARCHAR(50) NOT NULL,
    `canal`        ENUM('interne','email','sms') NOT NULL,
    `titre`        VARCHAR(255) NOT NULL,
    `message`      TEXT,
    `destinataire` VARCHAR(255) COMMENT 'email ou numéro de téléphone',
    `statut`       ENUM('envoye','echoue','en_attente') DEFAULT 'envoye',
    `erreur`       TEXT,
    `created_at`   DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_user`    (`user_id`),
    INDEX `idx_trigger` (`trigger_type`),
    INDEX `idx_canal`   (`canal`),
    INDEX `idx_statut`  (`statut`),
    INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
