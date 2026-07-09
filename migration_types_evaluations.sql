-- ============================================================
-- Phase 2.2 — Types d'évaluations V2
-- Migration : CREATE TABLE IF NOT EXISTS (jamais DROP)
-- Seed      : 5 types système issus de la V1 (INSERT IGNORE)
-- ============================================================

CREATE TABLE IF NOT EXISTS `types_evaluations` (
    `id`                    INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `code`                  VARCHAR(30) NOT NULL COMMENT 'Clé immuable, correspond au type V1 pour les types système',
    `nom`                   VARCHAR(80) NOT NULL,
    `description`           TEXT NULL,
    `coefficient_defaut`    DECIMAL(4,2) NOT NULL DEFAULT 1.00 COMMENT 'Coefficient suggéré lors de la création d\'une évaluation',
    `note_max_defaut`       DECIMAL(5,2) NOT NULL DEFAULT 20.00 COMMENT 'Barème suggéré (note maximale)',
    `est_eliminatoire`      TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = note en dessous du seuil entraîne élimination automatique',
    `seuil_eliminatoire`    DECIMAL(5,2) NULL COMMENT 'Note sur note_max_defaut déclenchant l\'élimination',
    `couleur`               VARCHAR(7) NULL COMMENT 'Couleur hex #RRGGBB pour l\'UI',
    `icone`                 VARCHAR(50) NULL COMMENT 'Nom icône Lucide',
    `ordre`                 TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Ordre d\'affichage dans les sélecteurs',
    `actif`                 TINYINT(1) NOT NULL DEFAULT 1 COMMENT '0 = désactivé (n\'apparaît plus dans les listes)',
    `est_archive`           TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = archivé définitivement, non réactivable',
    `est_systeme`           TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = type V1 protégé, requiert rôle admin pour modifications majeures',
    `created_by`            INT UNSIGNED NULL,
    `created_at`            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_te_code` (`code`),
    INDEX `idx_te_actif`    (`actif`),
    INDEX `idx_te_archive`  (`est_archive`),
    INDEX `idx_te_ordre`    (`ordre`),
    INDEX `idx_te_systeme`  (`est_systeme`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ──────────────────────────────────────────────────────────────────────────────
-- Seed : 5 types système correspondant aux TYPES V1 (ControleModel::TYPES)
-- ON DUPLICATE KEY UPDATE : idempotent, peut être relancé sans risque
-- ──────────────────────────────────────────────────────────────────────────────
INSERT INTO `types_evaluations`
    (`code`, `nom`, `description`, `coefficient_defaut`, `note_max_defaut`,
     `couleur`, `icone`, `ordre`, `actif`, `est_archive`, `est_systeme`)
VALUES
    ('controle', 'Contrôle',          'Contrôle de cours classique',              1.00, 20.00, '#6366f1', 'file-text',      1, 1, 0, 1),
    ('devoir',   'Devoir surveillé',  'Devoir en classe sous surveillance',        1.00, 20.00, '#8b5cf6', 'pencil',         2, 1, 0, 1),
    ('examen',   'Examen',            'Examen de fin de période ou semestriel',    2.00, 20.00, '#ef4444', 'graduation-cap', 3, 1, 0, 1),
    ('tp',       'Travaux Pratiques', 'TP en salle de TP ou laboratoire',          1.00, 20.00, '#10b981', 'flask-conical',  4, 1, 0, 1),
    ('oral',     'Oral',              'Évaluation orale individuelle',             1.00, 20.00, '#f59e0b', 'mic',            5, 1, 0, 1)
ON DUPLICATE KEY UPDATE
    `nom`         = VALUES(`nom`),
    `est_systeme` = 1;
