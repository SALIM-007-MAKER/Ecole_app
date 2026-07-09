-- ============================================================
-- Phase 2.7 — Table bulletins_v2
-- Exécuter UNE SEULE FOIS. Ne jamais DROP.
-- ============================================================

CREATE TABLE IF NOT EXISTS `bulletins_v2` (
    `id`                     INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    `eleve_id`               INT UNSIGNED     NOT NULL,
    `classe_id`              INT UNSIGNED     NOT NULL,
    `periode_id`             INT UNSIGNED     NOT NULL,
    `verification_token`     VARCHAR(64)      NOT NULL,
    `moyenne`                DECIMAL(5,2)     NULL,
    `rang`                   SMALLINT UNSIGNED NULL,
    `nb_eleves`              SMALLINT UNSIGNED NULL,
    `mention_code`           VARCHAR(10)      NULL,
    `decision`               ENUM('admis','rattrapage','refuse','indeterminate') NOT NULL DEFAULT 'indeterminate',
    `appreciation_pp`        TEXT             NULL,
    `appreciation_directeur` TEXT             NULL,
    `statut`                 ENUM('brouillon','publie','archive') NOT NULL DEFAULT 'brouillon',
    `data_json`              LONGTEXT         NULL COMMENT 'Snapshot BulletinData sérialisé',
    `generated_by`           INT UNSIGNED     NULL,
    `generated_at`           DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `published_at`           DATETIME         NULL,
    `published_by`           INT UNSIGNED     NULL,
    `archived_at`            DATETIME         NULL,
    `archived_by`            INT UNSIGNED     NULL,
    `created_at`             DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`             DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_token`               (`verification_token`),
    UNIQUE KEY `uq_bulletin_eleve_per`  (`eleve_id`, `periode_id`),
    KEY `idx_bul_classe_periode`        (`classe_id`, `periode_id`),
    KEY `idx_bul_statut`                (`statut`),
    CONSTRAINT `fk_bul_eleve`   FOREIGN KEY (`eleve_id`)   REFERENCES `eleves` (`id`)             ON DELETE RESTRICT,
    CONSTRAINT `fk_bul_classe`  FOREIGN KEY (`classe_id`)  REFERENCES `classes` (`id`)             ON DELETE RESTRICT,
    CONSTRAINT `fk_bul_periode` FOREIGN KEY (`periode_id`) REFERENCES `periodes_scolaires` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
