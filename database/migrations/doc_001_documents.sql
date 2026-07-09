-- ============================================================
-- SCOLARIS V2 — Module Documents V2
-- Migration : doc_001_documents.sql
-- Phase 7.2
-- ============================================================
-- RÈGLE : Ne jamais modifier rh_documents, rh_document_versions,
--         rh_document_historique (tables FROZEN RH V2).
-- ============================================================

-- Catégories documentaires
CREATE TABLE IF NOT EXISTS doc_categories (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code        VARCHAR(50)  NOT NULL,
    libelle     VARCHAR(100) NOT NULL,
    module_hint VARCHAR(30)  NULL,
    icone       VARCHAR(50)  NULL DEFAULT 'file',
    couleur     VARCHAR(20)  NULL DEFAULT 'slate',
    ordre       SMALLINT     NOT NULL DEFAULT 0,
    actif       TINYINT(1)   NOT NULL DEFAULT 1,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Arborescence logique des dossiers
CREATE TABLE IF NOT EXISTS doc_folders (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    parent_id        INT UNSIGNED NULL,
    nom              VARCHAR(200) NOT NULL,
    description      TEXT         NULL,
    module_source    VARCHAR(30)  NOT NULL,
    etablissement_id INT UNSIGNED NOT NULL DEFAULT 1,
    icone            VARCHAR(50)  NULL DEFAULT 'folder',
    couleur          VARCHAR(20)  NULL DEFAULT 'slate',
    ordre            SMALLINT     NOT NULL DEFAULT 0,
    created_by       INT UNSIGNED NOT NULL DEFAULT 0,
    deleted_at       DATETIME     NULL,
    created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY fk_folder_parent (parent_id) REFERENCES doc_folders(id) ON DELETE RESTRICT,
    INDEX idx_parent    (parent_id),
    INDEX idx_module    (module_source),
    INDEX idx_etab      (etablissement_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Registre principal des documents
CREATE TABLE IF NOT EXISTS doc_documents (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    folder_id           INT UNSIGNED NULL,
    categorie_id        INT UNSIGNED NULL,
    titre               VARCHAR(255) NOT NULL,
    description         TEXT         NULL,
    -- Polymorphisme source
    module_source       VARCHAR(30)  NOT NULL,
    entite_type         VARCHAR(60)  NULL,
    entite_id           INT UNSIGNED NULL,
    -- Stockage physique
    chemin_stockage     VARCHAR(500) NOT NULL,
    mime_type           VARCHAR(100) NOT NULL,
    extension           VARCHAR(10)  NOT NULL DEFAULT '',
    taille_octets       INT UNSIGNED NOT NULL DEFAULT 0,
    checksum_sha256     CHAR(64)     NULL,
    -- Métadonnées
    confidentialite     ENUM('public','interne','confidentiel','secret') NOT NULL DEFAULT 'interne',
    statut              ENUM('brouillon','actif','expire','archive','corbeille') NOT NULL DEFAULT 'actif',
    date_emission       DATE         NULL,
    date_expiration     DATE         NULL,
    alerte_jours        SMALLINT     NOT NULL DEFAULT 30,
    reference_externe   VARCHAR(100) NULL,
    emetteur            VARCHAR(100) NULL,
    notes               TEXT         NULL,
    metadata            JSON         NULL,
    -- Versioning
    version_courante    SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    -- SaaS
    etablissement_id    INT UNSIGNED NOT NULL DEFAULT 1,
    -- Signatures (préparation)
    signature_requise   TINYINT(1)   NOT NULL DEFAULT 0,
    signature_statut    ENUM('non_requis','en_attente','partiel','complet') NOT NULL DEFAULT 'non_requis',
    -- Audit
    created_by          INT UNSIGNED NOT NULL DEFAULT 0,
    updated_by          INT UNSIGNED NULL,
    archived_by         INT UNSIGNED NULL,
    archived_at         DATETIME     NULL,
    deleted_at          DATETIME     NULL,
    created_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY fk_doc_folder    (folder_id)    REFERENCES doc_folders(id)     ON DELETE SET NULL,
    FOREIGN KEY fk_doc_categorie (categorie_id) REFERENCES doc_categories(id)  ON DELETE SET NULL,
    INDEX idx_folder        (folder_id),
    INDEX idx_module        (module_source, entite_type, entite_id),
    INDEX idx_statut        (statut),
    INDEX idx_conf          (confidentialite),
    INDEX idx_expiration    (date_expiration, statut),
    INDEX idx_etab          (etablissement_id),
    INDEX idx_created_by    (created_by),
    FULLTEXT INDEX ft_search (titre, description, reference_externe, notes)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Historique des versions
CREATE TABLE IF NOT EXISTS doc_versions (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    document_id     INT UNSIGNED NOT NULL,
    numero          SMALLINT UNSIGNED NOT NULL,
    chemin_stockage VARCHAR(500) NOT NULL,
    taille_octets   INT UNSIGNED NOT NULL DEFAULT 0,
    checksum_sha256 CHAR(64)     NULL,
    mime_type       VARCHAR(100) NOT NULL DEFAULT '',
    notes           TEXT         NULL,
    created_by      INT UNSIGNED NOT NULL DEFAULT 0,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY fk_version_doc (document_id) REFERENCES doc_documents(id) ON DELETE CASCADE,
    UNIQUE KEY uq_doc_version (document_id, numero),
    INDEX idx_doc (document_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Référentiel tags
CREATE TABLE IF NOT EXISTS doc_tags (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nom              VARCHAR(50)  NOT NULL,
    couleur          VARCHAR(20)  NOT NULL DEFAULT 'slate',
    module_source    VARCHAR(30)  NULL,
    etablissement_id INT UNSIGNED NOT NULL DEFAULT 1,
    created_by       INT UNSIGNED NOT NULL DEFAULT 0,
    created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_nom_etab (nom, etablissement_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Pivot document ↔ tag
CREATE TABLE IF NOT EXISTS doc_document_tags (
    document_id INT UNSIGNED NOT NULL,
    tag_id      INT UNSIGNED NOT NULL,
    created_by  INT UNSIGNED NOT NULL DEFAULT 0,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (document_id, tag_id),
    FOREIGN KEY fk_dtag_doc (document_id) REFERENCES doc_documents(id) ON DELETE CASCADE,
    FOREIGN KEY fk_dtag_tag (tag_id)      REFERENCES doc_tags(id)      ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Partages contrôlés
CREATE TABLE IF NOT EXISTS doc_partages (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    document_id         INT UNSIGNED NOT NULL,
    destinataire_type   ENUM('user','role','module','externe') NOT NULL,
    destinataire_id     INT UNSIGNED NULL,
    destinataire_email  VARCHAR(150) NULL,
    permission          ENUM('lecture','telechargement','commentaire') NOT NULL DEFAULT 'lecture',
    token_acces         CHAR(64)     NULL,
    date_expiration     DATETIME     NULL,
    notifie             TINYINT(1)   NOT NULL DEFAULT 0,
    created_by          INT UNSIGNED NOT NULL DEFAULT 0,
    created_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    revoked_at          DATETIME     NULL,
    revoked_by          INT UNSIGNED NULL,
    FOREIGN KEY fk_partage_doc (document_id) REFERENCES doc_documents(id) ON DELETE CASCADE,
    INDEX idx_document  (document_id),
    INDEX idx_dest      (destinataire_type, destinataire_id),
    INDEX idx_token     (token_acces),
    INDEX idx_actif     (revoked_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Journal des actions
CREATE TABLE IF NOT EXISTS doc_historique (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    document_id    INT UNSIGNED NOT NULL,
    action         VARCHAR(50)  NOT NULL,
    details        JSON         NULL,
    ip             VARCHAR(45)  NULL,
    user_agent     VARCHAR(300) NULL,
    created_by     INT UNSIGNED NOT NULL DEFAULT 0,
    created_by_nom VARCHAR(100) NULL,
    created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY fk_historique_doc (document_id) REFERENCES doc_documents(id) ON DELETE CASCADE,
    INDEX idx_document (document_id),
    INDEX idx_action   (action),
    INDEX idx_date     (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Métadonnées corbeille
CREATE TABLE IF NOT EXISTS doc_corbeille (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    document_id INT UNSIGNED NOT NULL,
    raison      TEXT         NULL,
    created_by  INT UNSIGNED NOT NULL DEFAULT 0,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    purge_avant DATETIME     NULL,
    UNIQUE KEY uq_document (document_id),
    FOREIGN KEY fk_corbeille_doc (document_id) REFERENCES doc_documents(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Quotas par module/entité
CREATE TABLE IF NOT EXISTS doc_quotas (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    module_source    VARCHAR(30)     NOT NULL,
    entite_type      VARCHAR(60)     NULL,
    entite_id        INT UNSIGNED    NULL,
    etablissement_id INT UNSIGNED    NOT NULL DEFAULT 1,
    quota_octets     BIGINT UNSIGNED NOT NULL DEFAULT 0,
    utilise_octets   BIGINT UNSIGNED NOT NULL DEFAULT 0,
    updated_at       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_quota (module_source, entite_type, entite_id, etablissement_id),
    INDEX idx_module (module_source)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Préparation signatures électroniques (stub V3)
CREATE TABLE IF NOT EXISTS doc_signatures (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    document_id      INT UNSIGNED NOT NULL,
    version_id       INT UNSIGNED NULL,
    signataire_type  ENUM('user','externe') NOT NULL DEFAULT 'user',
    signataire_id    INT UNSIGNED NULL,
    signataire_email VARCHAR(150) NULL,
    signataire_nom   VARCHAR(100) NULL,
    statut           ENUM('en_attente','signe','refuse','expire') NOT NULL DEFAULT 'en_attente',
    token_signature  CHAR(64)     NULL,
    hash_document    CHAR(64)     NULL,
    signed_at        DATETIME     NULL,
    ip_signataire    VARCHAR(45)  NULL,
    metadata         JSON         NULL,
    created_by       INT UNSIGNED NOT NULL DEFAULT 0,
    created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at       DATETIME     NULL,
    FOREIGN KEY fk_sig_doc     (document_id) REFERENCES doc_documents(id) ON DELETE CASCADE,
    FOREIGN KEY fk_sig_version (version_id)  REFERENCES doc_versions(id)  ON DELETE SET NULL,
    INDEX idx_document (document_id),
    INDEX idx_token    (token_signature),
    INDEX idx_statut   (statut)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Seed : catégories initiales ──────────────────────────────────────────────
INSERT IGNORE INTO doc_categories (code, libelle, module_hint, icone, couleur, ordre) VALUES
('contrat',               'Contrat',                  'rh',          'file-text',   'violet', 1),
('contrat_signe',         'Contrat signé',             'rh',          'file-check',  'violet', 2),
('avenant',               'Avenant',                  'rh',          'file-plus',   'purple', 3),
('diplome',               'Diplôme',                  'rh',          'award',       'blue',   4),
('certificat',            'Certificat',               'rh',          'badge-check', 'cyan',   5),
('attestation',           'Attestation',              'rh',          'stamp',       'teal',   6),
('piece_identite',        'Pièce d\'identité',        'rh',          'id-card',     'slate',  7),
('certificat_medical',    'Certificat médical',       'rh',          'stethoscope', 'rose',   8),
('autorisation',          'Autorisation',             'vie_scolaire','shield-check','amber',  9),
('sanction',              'Sanction disciplinaire',   'vie_scolaire','ban',         'red',    10),
('recompense',            'Récompense',               'vie_scolaire','trophy',      'green',  11),
('justificatif',          'Justificatif d\'absence',  'vie_scolaire','file-search', 'amber',  12),
('facture',               'Facture',                  'finance',     'receipt',     'emerald',13),
('devis',                 'Devis',                    'finance',     'clipboard',   'sky',    14),
('recu_paiement',         'Reçu de paiement',         'finance',     'banknote',    'green',  15),
('contrat_fournisseur',   'Contrat fournisseur',      'finance',     'handshake',   'orange', 16),
('bulletin',              'Bulletin scolaire',        'academique',  'scroll-text', 'indigo', 17),
('feuille_notes',         'Feuille de notes',         'academique',  'clipboard-list','blue', 18),
('attestation_scolarite', 'Attestation de scolarité', 'scolarite',   'graduation-cap','teal',19),
('dossier_inscription',   'Dossier d\'inscription',   'scolarite',   'folder-open', 'violet',20),
('rapport',               'Rapport',                  NULL,          'bar-chart',   'slate',  21),
('circulaire',            'Circulaire',               'communication','megaphone',  'amber',  22),
('pv',                    'Procès-verbal',            'communication','gavel',      'slate',  23),
('autre',                 'Autre',                    NULL,          'file',        'slate',  30);
