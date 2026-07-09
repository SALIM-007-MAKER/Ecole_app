-- ============================================================
-- MIGRATION api_002_oauth_stub.sql
-- API Platform V2 — Tables OAuth2 (stub — implémentation Phase 13.x)
-- Tables créées vides, implémentation différée
-- ============================================================

CREATE TABLE IF NOT EXISTS oauth_clients (
    id               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    etablissement_id INT UNSIGNED  NOT NULL,
    name             VARCHAR(100)  NOT NULL,
    client_id        VARCHAR(80)   NOT NULL UNIQUE,
    client_secret    VARCHAR(80)   NULL,
    redirect_uris    JSON          NOT NULL DEFAULT ('[]'),
    grant_types      JSON          NOT NULL DEFAULT ('[]'),
    scopes           JSON          NOT NULL DEFAULT ('[]'),
    is_confidential  TINYINT(1)    DEFAULT 1,
    active           TINYINT(1)    DEFAULT 1,
    created_at       DATETIME      DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS oauth_auth_codes (
    id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id    VARCHAR(80)  NOT NULL,
    user_id      INT UNSIGNED NOT NULL,
    code         VARCHAR(100) NOT NULL UNIQUE,
    scopes       JSON         NOT NULL DEFAULT ('[]'),
    expires_at   DATETIME     NOT NULL,
    used_at      DATETIME     NULL,
    INDEX idx_code (code),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS oauth_access_tokens (
    id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id    VARCHAR(80)  NOT NULL,
    user_id      INT UNSIGNED NULL,
    token_hash   VARCHAR(64)  NOT NULL UNIQUE,
    scopes       JSON         NOT NULL DEFAULT ('[]'),
    expires_at   DATETIME     NOT NULL,
    revoked_at   DATETIME     NULL,
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS oauth_refresh_tokens (
    id                BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    access_token_id   BIGINT UNSIGNED NOT NULL,
    token_hash        VARCHAR(64)  NOT NULL UNIQUE,
    expires_at        DATETIME     NOT NULL,
    revoked_at        DATETIME     NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS oauth_scopes (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    scope       VARCHAR(100) NOT NULL UNIQUE,
    description VARCHAR(255) NULL,
    permissions JSON         NOT NULL DEFAULT ('[]')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
