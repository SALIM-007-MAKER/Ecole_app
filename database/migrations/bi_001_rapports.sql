-- ═══════════════════════════════════════════════════════════════════════════
-- MODULE RAPPORTS & BI V2 — Migration 001
-- Tables : bi_tableaux_config, bi_rapports_planifies, bi_rapport_executions,
--          bi_kpi_snapshots, bi_exports
-- ═══════════════════════════════════════════════════════════════════════════

-- ─────────────────────────────────────────────────────────────────
-- TABLE : bi_tableaux_config
-- ─────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS bi_tableaux_config (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id          INT UNSIGNED NOT NULL,
    contexte         ENUM('direction','administration','scolarite','academique',
                          'finance','rh','vie_scolaire','bibliotheque','inventaire') NOT NULL,
    widgets          JSON NOT NULL COMMENT 'Ordre et paramètres des widgets',
    etablissement_id INT UNSIGNED NOT NULL DEFAULT 1,
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uq_bi_config_user_ctx (user_id, contexte, etablissement_id),
    INDEX idx_bi_config_etab (etablissement_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────────
-- TABLE : bi_rapports_planifies
-- ─────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS bi_rapports_planifies (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nom              VARCHAR(200) NOT NULL,
    domaine          VARCHAR(60) NOT NULL,
    type_export      ENUM('pdf','excel','csv') NOT NULL DEFAULT 'pdf',
    filtres          JSON NULL,
    frequence        ENUM('quotidien','hebdomadaire','mensuel') NOT NULL,
    jour_execution   TINYINT UNSIGNED NULL COMMENT '0-6 hebdo, 1-28 mensuel',
    heure_execution  TIME NOT NULL DEFAULT '06:00:00',
    destinataires    JSON NULL COMMENT 'Tableau emails',
    actif            TINYINT(1) NOT NULL DEFAULT 1,
    created_by       INT UNSIGNED NOT NULL,
    etablissement_id INT UNSIGNED NOT NULL DEFAULT 1,
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at       DATETIME NULL,

    INDEX idx_bi_plan_actif (actif, frequence),
    INDEX idx_bi_plan_etab  (etablissement_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────────
-- TABLE : bi_rapport_executions
-- ─────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS bi_rapport_executions (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    rapport_planifie_id INT UNSIGNED NULL,
    domaine             VARCHAR(60) NOT NULL,
    type_export         ENUM('pdf','excel','csv') NOT NULL,
    statut              ENUM('pending','en_cours','termine','erreur') NOT NULL DEFAULT 'pending',
    filtres             JSON NULL,
    debut_execution     DATETIME NULL,
    fin_execution       DATETIME NULL,
    fichier_path        VARCHAR(500) NULL,
    nb_lignes           INT UNSIGNED NULL,
    erreur_message      TEXT NULL,
    user_id             INT UNSIGNED NULL,
    etablissement_id    INT UNSIGNED NOT NULL DEFAULT 1,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_bi_exec_plan FOREIGN KEY (rapport_planifie_id)
        REFERENCES bi_rapports_planifies(id) ON DELETE SET NULL,
    INDEX idx_bi_exec_statut (statut),
    INDEX idx_bi_exec_etab   (etablissement_id),
    INDEX idx_bi_exec_date   (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────────
-- TABLE : bi_kpi_snapshots
-- ─────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS bi_kpi_snapshots (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    domaine          VARCHAR(60) NOT NULL,
    metrique         VARCHAR(100) NOT NULL,
    valeur           DECIMAL(15,4) NOT NULL,
    periode          CHAR(7) NOT NULL COMMENT 'YYYY-MM',
    etablissement_id INT UNSIGNED NOT NULL DEFAULT 1,
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uq_bi_snap (domaine, metrique, periode, etablissement_id),
    INDEX idx_bi_snap_domaine (domaine, etablissement_id),
    INDEX idx_bi_snap_periode (periode)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────────
-- TABLE : bi_exports
-- ─────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS bi_exports (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    domaine          VARCHAR(60) NOT NULL,
    type_export      ENUM('pdf','excel','csv') NOT NULL,
    filtres          JSON NULL,
    fichier_path     VARCHAR(500) NOT NULL,
    fichier_nom      VARCHAR(200) NOT NULL,
    nb_lignes        INT UNSIGNED NULL,
    expire_at        DATETIME NULL,
    user_id          INT UNSIGNED NOT NULL,
    etablissement_id INT UNSIGNED NOT NULL DEFAULT 1,
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_bi_exp_user   (user_id),
    INDEX idx_bi_exp_etab   (etablissement_id),
    INDEX idx_bi_exp_expire (expire_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
