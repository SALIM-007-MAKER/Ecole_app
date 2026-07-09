-- ============================================================
-- Migration PORTALS-001 : Tables du Portal Framework
-- Phase 12.1 — SCOLARIS V2
-- ============================================================

-- Préférences utilisateur par portail (widget layout, shortcuts, theme)
CREATE TABLE IF NOT EXISTS portal_preferences (
    id                INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    user_id           INT UNSIGNED     NOT NULL,
    portal            VARCHAR(30)      NOT NULL,
    etablissement_id  INT UNSIGNED     NOT NULL,
    widget_layout     JSON             NULL COMMENT 'Map widget_id -> {ordre, taille, visible}',
    hidden_widgets    JSON             NULL COMMENT 'Liste des IDs de widgets masqués',
    shortcuts         JSON             NULL COMMENT 'Raccourcis personnalisés [{id,label,url,icon,color,ordre}]',
    theme             VARCHAR(20)      NOT NULL DEFAULT 'default',
    default_page      VARCHAR(255)     NOT NULL DEFAULT '',
    notif_prefs       JSON             NULL COMMENT 'Préférences de notification par type',
    lang              VARCHAR(10)      NOT NULL DEFAULT 'fr',
    deleted_at        DATETIME         NULL,
    created_at        DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_portal_prefs (user_id, portal, etablissement_id),
    KEY idx_pp_user   (user_id),
    KEY idx_pp_etab   (etablissement_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cache des données de widgets (TTL géré par expires_at)
CREATE TABLE IF NOT EXISTS portal_widget_cache (
    id                INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    portal            VARCHAR(30)      NOT NULL,
    widget_id         VARCHAR(60)      NOT NULL,
    user_id           INT UNSIGNED     NULL COMMENT 'NULL = cache partagé par établissement',
    etablissement_id  INT UNSIGNED     NOT NULL,
    data              LONGTEXT         NOT NULL COMMENT 'JSON',
    expires_at        DATETIME         NOT NULL,
    created_at        DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_widget_cache (portal, widget_id, etablissement_id, user_id),
    KEY idx_wc_expires (expires_at),
    KEY idx_wc_etab    (etablissement_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Journal d'accès aux portails (analytics)
CREATE TABLE IF NOT EXISTS portal_access_logs (
    id                BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
    user_id           INT UNSIGNED     NOT NULL,
    portal            VARCHAR(30)      NOT NULL,
    page              VARCHAR(255)     NOT NULL DEFAULT '',
    action            VARCHAR(60)      NOT NULL DEFAULT 'access',
    ip                VARCHAR(45)      NOT NULL DEFAULT '',
    user_agent        VARCHAR(255)     NOT NULL DEFAULT '',
    etablissement_id  INT UNSIGNED     NOT NULL,
    created_at        DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_pal_user   (user_id),
    KEY idx_pal_portal (portal),
    KEY idx_pal_etab   (etablissement_id),
    KEY idx_pal_date   (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tokens Bearer pour l'API Portal (clients mobiles / PWA)
CREATE TABLE IF NOT EXISTS portal_api_tokens (
    id                INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    user_id           INT UNSIGNED     NOT NULL,
    token             VARCHAR(64)      NOT NULL,
    portal            VARCHAR(30)      NOT NULL,
    etablissement_id  INT UNSIGNED     NOT NULL,
    expires_at        DATETIME         NOT NULL,
    created_at        DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_api_token   (token),
    KEY idx_at_user    (user_id),
    KEY idx_at_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Données initiales : pas de seed requis pour les tables portail
-- ============================================================
