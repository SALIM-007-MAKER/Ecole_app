-- ============================================================
-- Phase 6.11 — Domaine Documents RH V2
-- Tables : rh_documents, rh_document_versions, rh_document_historique
-- Stockage physique hors périmètre — références et métadonnées uniquement
-- ============================================================

-- ------------------------------------------------------------
-- rh_documents — table principale
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS rh_documents (
    id                      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employe_id              INT UNSIGNED NOT NULL,
    type                    ENUM(
                                'contrat_signe','avenant','diplome','certificat',
                                'attestation','piece_identite','certificat_medical',
                                'autorisation','sanction','recompense',
                                'evaluation_signee','certificat_formation'
                            ) NOT NULL,
    titre                   VARCHAR(255) NOT NULL,
    reference_externe       VARCHAR(150) NULL          COMMENT 'Référence DocumentService V2/V3',
    statut                  ENUM('actif','expire','archive','en_attente','refuse')
                            NOT NULL DEFAULT 'actif',
    confidentialite         ENUM('public','confidentiel','secret')
                            NOT NULL DEFAULT 'confidentiel',
    version_courante        TINYINT UNSIGNED NOT NULL DEFAULT 1,
    date_emission           DATE NOT NULL,
    date_expiration         DATE NULL,
    alerte_jours            INT UNSIGNED NULL DEFAULT 30 COMMENT 'Alerte N jours avant expiration',
    emetteur                VARCHAR(255) NULL,
    notes                   TEXT NULL,
    metadata                JSON NULL                  COMMENT 'Données libres pour intégration V3/API',

    -- Liens optionnels vers entités RH
    contrat_id              INT UNSIGNED NULL,
    formation_session_id    INT UNSIGNED NULL,
    evaluation_id           INT UNSIGNED NULL,

    -- Traçabilité
    created_by              INT UNSIGNED NOT NULL,
    archived_by             INT UNSIGNED NULL,
    archived_at             TIMESTAMP NULL,
    deleted_at              TIMESTAMP NULL,
    created_at              TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at              TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_rdoc_employe    FOREIGN KEY (employe_id)           REFERENCES rh_employes(id)             ON DELETE RESTRICT,
    CONSTRAINT fk_rdoc_contrat    FOREIGN KEY (contrat_id)           REFERENCES rh_contrats(id)             ON DELETE SET NULL,
    CONSTRAINT fk_rdoc_session    FOREIGN KEY (formation_session_id) REFERENCES rh_formations_sessions(id)  ON DELETE SET NULL,
    CONSTRAINT fk_rdoc_eval       FOREIGN KEY (evaluation_id)        REFERENCES rh_evaluations(id)          ON DELETE SET NULL,

    INDEX idx_rdoc_employe      (employe_id),
    INDEX idx_rdoc_type         (type),
    INDEX idx_rdoc_statut       (statut),
    INDEX idx_rdoc_expiration   (date_expiration),
    INDEX idx_rdoc_confidentialite (confidentialite),
    INDEX idx_rdoc_deleted      (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Références documentaires RH — métadonnées uniquement';

-- ------------------------------------------------------------
-- rh_document_versions — historique des versions
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS rh_document_versions (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    document_id         INT UNSIGNED NOT NULL,
    version             TINYINT UNSIGNED NOT NULL,
    reference_externe   VARCHAR(150) NULL      COMMENT 'Référence du fichier à cette version',
    notes_version       VARCHAR(500) NULL      COMMENT 'Motif de la nouvelle version',
    created_by          INT UNSIGNED NOT NULL,
    created_by_nom      VARCHAR(100) NULL,
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_rdocv_document FOREIGN KEY (document_id) REFERENCES rh_documents(id) ON DELETE CASCADE,

    UNIQUE KEY uk_doc_version (document_id, version),
    INDEX idx_rdocv_document (document_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Historique des versions d\'un document RH';

-- ------------------------------------------------------------
-- rh_document_historique — journal des changements de statut
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS rh_document_historique (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    document_id     INT UNSIGNED NOT NULL,
    action          VARCHAR(60) NOT NULL  COMMENT 'creer|mettre_a_jour|archiver|restaurer|expirer|refuser',
    ancien_statut   VARCHAR(50) NULL,
    nouveau_statut  VARCHAR(50) NULL,
    notes           VARCHAR(500) NULL,
    created_by      INT UNSIGNED NOT NULL,
    created_by_nom  VARCHAR(100) NULL,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_rdoch_document FOREIGN KEY (document_id) REFERENCES rh_documents(id) ON DELETE CASCADE,

    INDEX idx_rdoch_document (document_id),
    INDEX idx_rdoch_action   (action)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Journal des actions sur les documents RH';
