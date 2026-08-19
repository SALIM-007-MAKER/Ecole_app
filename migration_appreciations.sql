-- ============================================================
-- Bulletin V1 papier (moteur Académique V2) — Appréciations par matière
-- Saisie manuelle par le professeur (eleve, matiere, periode).
-- CREATE TABLE IF NOT EXISTS — jamais DROP
-- ============================================================

CREATE TABLE IF NOT EXISTS `appreciations_matiere` (
    `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `eleve_id`         INT UNSIGNED NOT NULL,
    `matiere_id`       INT UNSIGNED NOT NULL,
    `periode_id`       INT UNSIGNED NOT NULL,
    `classe_id`        INT UNSIGNED NOT NULL,
    `professeur_id`    INT UNSIGNED NULL,
    `texte`            VARCHAR(255) NULL,
    `etablissement_id` INT UNSIGNED NOT NULL,
    `created_by`       INT UNSIGNED NULL,
    `created_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_appreciation_eleve_matiere_periode` (`eleve_id`, `matiere_id`, `periode_id`),
    KEY `idx_appr_classe_matiere_periode` (`classe_id`, `matiere_id`, `periode_id`),
    CONSTRAINT `fk_appr_eleve`
        FOREIGN KEY (`eleve_id`) REFERENCES `eleves` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_appr_matiere`
        FOREIGN KEY (`matiere_id`) REFERENCES `matieres` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_appr_periode`
        FOREIGN KEY (`periode_id`) REFERENCES `periodes_scolaires` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
