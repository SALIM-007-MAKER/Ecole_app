-- ============================================================
-- MIGRATION api_001_foundation.sql
-- API Platform V2 — Tables fondation
-- Phase 13.2
-- ============================================================

-- API Keys (intégrations serveur-à-serveur)
CREATE TABLE IF NOT EXISTS api_keys (
    id               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    etablissement_id INT UNSIGNED NOT NULL,
    name             VARCHAR(100) NOT NULL,
    key_prefix       VARCHAR(10)  NOT NULL,
    key_hash         VARCHAR(64)  NOT NULL UNIQUE,
    key_hint         VARCHAR(8)   NOT NULL,
    permissions      JSON         NOT NULL DEFAULT ('[]'),
    rate_limit       INT UNSIGNED DEFAULT 1000,
    allowed_ips      JSON         NULL,
    expires_at       DATETIME     NULL,
    last_used_at     DATETIME     NULL,
    revoked_at       DATETIME     NULL,
    created_by       INT UNSIGNED NOT NULL,
    created_at       DATETIME     DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_etab   (etablissement_id),
    INDEX idx_prefix (key_prefix)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Refresh Tokens JWT
CREATE TABLE IF NOT EXISTS api_refresh_tokens (
    id               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id          INT UNSIGNED NOT NULL,
    token_hash       VARCHAR(64)  NOT NULL UNIQUE,
    etablissement_id INT UNSIGNED NOT NULL,
    expires_at       DATETIME     NOT NULL,
    revoked_at       DATETIME     NULL,
    user_agent       VARCHAR(500) NULL,
    ip_address       VARCHAR(45)  NULL,
    created_at       DATETIME     DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user    (user_id),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Rate Limit — Token Bucket
CREATE TABLE IF NOT EXISTS api_rate_limit_buckets (
    bucket_key   VARCHAR(128) NOT NULL PRIMARY KEY,
    tokens       SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    last_refill  DATETIME(6) NOT NULL,
    expires_at   DATETIME    NOT NULL,
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Journalisation requêtes API (partitionné par année)
CREATE TABLE IF NOT EXISTS api_request_logs (
    id               BIGINT UNSIGNED AUTO_INCREMENT,
    request_id       CHAR(36)     NOT NULL,
    method           VARCHAR(10)  NOT NULL,
    path             VARCHAR(500) NOT NULL,
    query_string     TEXT         NULL,
    status_code      SMALLINT UNSIGNED NOT NULL,
    duration_ms      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    response_bytes   INT UNSIGNED NULL,
    user_id          INT UNSIGNED NULL,
    etablissement_id INT UNSIGNED NULL,
    auth_method      VARCHAR(20)  NULL,
    api_key_id       BIGINT UNSIGNED NULL,
    ip_address       VARCHAR(45)  NOT NULL DEFAULT '',
    user_agent       VARCHAR(500) NULL,
    error_code       VARCHAR(50)  NULL,
    created_at       DATETIME(3)  NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    PRIMARY KEY (id, created_at),
    INDEX idx_user    (user_id, created_at),
    INDEX idx_etab    (etablissement_id, created_at),
    INDEX idx_status  (status_code, created_at),
    INDEX idx_rid     (request_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  PARTITION BY RANGE (YEAR(created_at)) (
    PARTITION p2026 VALUES LESS THAN (2027),
    PARTITION p2027 VALUES LESS THAN (2028),
    PARTITION p2028 VALUES LESS THAN (2029),
    PARTITION pmax  VALUES LESS THAN MAXVALUE
  );

-- Métriques API — agrégats horaires/journaliers
CREATE TABLE IF NOT EXISTS api_metrics_snapshots (
    id               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    etablissement_id INT UNSIGNED NULL,
    snapshot_at      DATETIME     NOT NULL,
    period           ENUM('hour','day','month') NOT NULL,
    requests_total   INT UNSIGNED DEFAULT 0,
    errors_total     INT UNSIGNED DEFAULT 0,
    avg_duration_ms  FLOAT        DEFAULT 0,
    p95_duration_ms  FLOAT        DEFAULT 0,
    webhooks_sent    INT UNSIGNED DEFAULT 0,
    webhooks_failed  INT UNSIGNED DEFAULT 0,
    top_endpoints    JSON         NULL,
    INDEX idx_snapshot (etablissement_id, period, snapshot_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Webhooks — abonnements
CREATE TABLE IF NOT EXISTS webhook_subscriptions (
    id               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    etablissement_id INT UNSIGNED NOT NULL,
    name             VARCHAR(100) NOT NULL,
    url              VARCHAR(2000) NOT NULL,
    secret_hash      VARCHAR(64)  NOT NULL,
    events           JSON         NOT NULL DEFAULT ('[]'),
    active           TINYINT(1)   DEFAULT 1,
    headers          JSON         NULL,
    verify_ssl       TINYINT(1)   DEFAULT 1,
    created_by       INT UNSIGNED NOT NULL,
    created_at       DATETIME     DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_etab   (etablissement_id, active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Webhooks — file de livraison
CREATE TABLE IF NOT EXISTS webhook_deliveries (
    id               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    subscription_id  BIGINT UNSIGNED NOT NULL,
    event_type       VARCHAR(100) NOT NULL,
    payload          JSON         NOT NULL,
    status           ENUM('pending','success','failed') DEFAULT 'pending',
    attempts         TINYINT UNSIGNED DEFAULT 0,
    max_attempts     TINYINT UNSIGNED DEFAULT 5,
    next_attempt_at  DATETIME     NULL,
    last_http_status SMALLINT     NULL,
    last_response    TEXT         NULL,
    duration_ms      SMALLINT     NULL,
    created_at       DATETIME     DEFAULT CURRENT_TIMESTAMP,
    delivered_at     DATETIME     NULL,
    INDEX idx_status (status, next_attempt_at),
    INDEX idx_sub    (subscription_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Uploads transversaux
CREATE TABLE IF NOT EXISTS api_uploads (
    id               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    etablissement_id INT UNSIGNED  NOT NULL,
    user_id          INT UNSIGNED  NOT NULL,
    original_name    VARCHAR(255)  NOT NULL,
    stored_path      VARCHAR(500)  NOT NULL,
    mime_type        VARCHAR(100)  NOT NULL,
    size_bytes       INT UNSIGNED  NOT NULL,
    module           VARCHAR(50)   NULL,
    context_type     VARCHAR(50)   NULL,
    context_id       INT UNSIGNED  NULL,
    status           ENUM('pending','ready','failed') DEFAULT 'pending',
    created_at       DATETIME      DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_etab   (etablissement_id),
    INDEX idx_user   (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
