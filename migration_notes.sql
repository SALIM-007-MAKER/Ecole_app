-- ============================================================
-- Phase 2.4 — Domaine Notes V2
-- CREATE TABLE IF NOT EXISTS — jamais DROP
-- ============================================================

CREATE TABLE IF NOT EXISTS `notes_v2` (
    `id`               INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `evaluation_id`    INT UNSIGNED  NOT NULL,
    `eleve_id`         INT UNSIGNED  NOT NULL,
    `valeur`           DECIMAL(5,2)  NULL     COMMENT 'NULL = absent ou non attribuée',
    `est_absent`       TINYINT(1)    NOT NULL DEFAULT 0,
    `commentaire`      TEXT          NULL,
    `statut`           ENUM('saisie','publiee','verrouillee') NOT NULL DEFAULT 'saisie',
    `verrouille_par`   INT UNSIGNED  NULL,
    `verrouille_le`    DATETIME      NULL,
    `created_by`       INT UNSIGNED  NULL,
    `created_at`       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_note_eval_eleve` (`evaluation_id`, `eleve_id`),
    KEY `idx_note_eleve`      (`eleve_id`),
    KEY `idx_note_statut`     (`statut`),
    KEY `idx_note_created_by` (`created_by`),
    CONSTRAINT `fk_note_eval`
        FOREIGN KEY (`evaluation_id`) REFERENCES `evaluations` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_notev2_eleve`
        FOREIGN KEY (`eleve_id`) REFERENCES `eleves` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Historique des modifications ───────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `notes_historique` (
    `id`            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `note_id`       INT UNSIGNED  NOT NULL,
    `valeur_avant`  DECIMAL(5,2)  NULL,
    `valeur_apres`  DECIMAL(5,2)  NULL,
    `absent_avant`  TINYINT(1)    NOT NULL DEFAULT 0,
    `absent_apres`  TINYINT(1)    NOT NULL DEFAULT 0,
    `commentaire`   TEXT          NULL,
    `modifie_par`   INT UNSIGNED  NULL,
    `modifie_le`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_hist_note` (`note_id`),
    CONSTRAINT `fk_hist_note`
        FOREIGN KEY (`note_id`) REFERENCES `notes_v2` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
