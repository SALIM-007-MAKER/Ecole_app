-- ═══════════════════════════════════════════════════════════════════════════════
-- Module Absences — ecole_app
-- ═══════════════════════════════════════════════════════════════════════════════

-- ─── Table principale des absences ──────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `absences` (
    `id`            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `eleve_id`      INT UNSIGNED  NOT NULL,
    `classe_id`     INT UNSIGNED  NOT NULL,
    `date_absence`  DATE          NOT NULL,
    `session`       ENUM('matin','apres_midi','journee') NOT NULL DEFAULT 'journee',
    `type`          ENUM('absence','retard')              NOT NULL DEFAULT 'absence',
    `duree_retard`  TINYINT UNSIGNED NULL COMMENT 'Durée en minutes (si type=retard)',
    `motif`         VARCHAR(255)  NULL,
    `signale_par`   INT UNSIGNED  NULL COMMENT 'users.id de l auteur du pointage',
    `statut_justif` ENUM('non_justifiee','en_attente','justifiee','refusee')
                    NOT NULL DEFAULT 'non_justifiee',
    `created_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE  KEY `uq_absence`          (`eleve_id`, `date_absence`, `session`),
    INDEX         `idx_abs_date`      (`date_absence`),
    INDEX         `idx_abs_classe`    (`classe_id`, `date_absence`),
    INDEX         `idx_abs_statut`    (`statut_justif`),
    CONSTRAINT `fk_abs_eleve`  FOREIGN KEY (`eleve_id`)   REFERENCES `eleves`(`id`)  ON DELETE CASCADE,
    CONSTRAINT `fk_abs_classe` FOREIGN KEY (`classe_id`)  REFERENCES `classes`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_abs_user`   FOREIGN KEY (`signale_par`)REFERENCES `users`(`id`)   ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Justifications ──────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `justifications` (
    `id`                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `absence_id`         INT UNSIGNED NOT NULL,
    `soumis_par`         INT UNSIGNED NULL COMMENT 'users.id (parent ou admin)',
    `motif`              TEXT         NOT NULL,
    `document_path`      VARCHAR(255) NULL COMMENT 'Chemin relatif depuis public/',
    `statut`             ENUM('en_attente','acceptee','refusee') NOT NULL DEFAULT 'en_attente',
    `commentaire_admin`  VARCHAR(255) NULL,
    `created_at`         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_just_absence` (`absence_id`),
    CONSTRAINT `fk_just_absence` FOREIGN KEY (`absence_id`) REFERENCES `absences`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_just_user`    FOREIGN KEY (`soumis_par`) REFERENCES `users`(`id`)    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Données de test ─────────────────────────────────────────────────────────
-- Absences pour les élèves de la première classe (teste uniquement si les tables existent)
INSERT IGNORE INTO `absences` (`eleve_id`, `classe_id`, `date_absence`, `session`, `type`, `motif`, `signale_par`, `statut_justif`)
SELECT
    e.id,
    e.classe_id,
    DATE_SUB(CURDATE(), INTERVAL 3 DAY),
    'matin',
    'absence',
    NULL,
    (SELECT id FROM users WHERE role = 'enseignant' LIMIT 1),
    'non_justifiee'
FROM eleves e
WHERE e.actif = 1
LIMIT 3;

INSERT IGNORE INTO `absences` (`eleve_id`, `classe_id`, `date_absence`, `session`, `type`, `duree_retard`, `motif`, `signale_par`, `statut_justif`)
SELECT
    e.id,
    e.classe_id,
    DATE_SUB(CURDATE(), INTERVAL 1 DAY),
    'matin',
    'retard',
    20,
    'Transport',
    (SELECT id FROM users WHERE role = 'enseignant' LIMIT 1),
    'non_justifiee'
FROM eleves e
WHERE e.actif = 1
LIMIT 2;

INSERT IGNORE INTO `absences` (`eleve_id`, `classe_id`, `date_absence`, `session`, `type`, `motif`, `signale_par`, `statut_justif`)
SELECT
    e.id,
    e.classe_id,
    DATE_SUB(CURDATE(), INTERVAL 7 DAY),
    'journee',
    'absence',
    NULL,
    (SELECT id FROM users WHERE role IN ('admin','directeur') LIMIT 1),
    'justifiee'
FROM eleves e
WHERE e.actif = 1
LIMIT 2;

-- Justification pour une des absences justifiées
INSERT IGNORE INTO `justifications` (`absence_id`, `soumis_par`, `motif`, `statut`)
SELECT
    a.id,
    (SELECT id FROM users WHERE role = 'parent' LIMIT 1),
    'Maladie — certificat médical fourni',
    'acceptee'
FROM absences a
WHERE a.statut_justif = 'justifiee'
LIMIT 1;
