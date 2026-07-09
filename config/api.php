<?php

/**
 * Configuration de la plateforme API V2
 */

return [

    'version' => '1.0.0',

    // ── JWT ───────────────────────────────────────────────────────────────────
    'jwt' => [
        'secret'          => $_ENV['JWT_SECRET'] ?? ($_ENV['APP_KEY'] ?? 'scolaris-default-jwt-secret-change-in-production'),
        'algo'            => 'HS256',
        'access_ttl'      => (int)($_ENV['JWT_ACCESS_TTL']  ?? 900),     // 15 minutes
        'refresh_ttl'     => (int)($_ENV['JWT_REFRESH_TTL'] ?? 2592000), // 30 jours
        'issuer'          => 'scolaris-v2',
        'audience'        => 'api-v1',
    ],

    // ── API Keys ──────────────────────────────────────────────────────────────
    'api_keys' => [
        'prefix_live' => 'sk_live_',
        'prefix_test' => 'sk_test_',
        'default_rate_limit' => 1000,
    ],

    // ── Rate Limiting ─────────────────────────────────────────────────────────
    'rate_limit' => [
        'default'         => ['requests' => 300, 'window' => 60],   // 300 req/min
        'auth'            => ['requests' => 5,   'window' => 60],   // 5 req/min (brute-force)
        'api_key_read'    => ['requests' => 1000,'window' => 3600], // 1000 req/h
        'api_key_write'   => ['requests' => 200, 'window' => 3600], // 200 req/h
        'upload'          => ['requests' => 10,  'window' => 3600], // 10 req/h
    ],

    // ── CORS ──────────────────────────────────────────────────────────────────
    'cors' => [
        'allowed_origins' => array_filter(explode(',', $_ENV['CORS_ORIGINS'] ?? 'http://localhost:3000,http://localhost:8080')),
        'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
        'allowed_headers' => ['Content-Type', 'Authorization', 'X-Api-Key', 'X-Requested-With', 'Accept', 'X-CSRF-Token'],
        'exposed_headers' => ['X-RateLimit-Limit', 'X-RateLimit-Remaining', 'X-RateLimit-Reset', 'API-Version', 'Request-ID'],
        'max_age'         => 86400,
        'credentials'     => true,
    ],

    // ── Pagination ────────────────────────────────────────────────────────────
    'pagination' => [
        'default_per_page' => 20,
        'max_per_page'     => 100,
    ],

    // ── Upload ────────────────────────────────────────────────────────────────
    'upload' => [
        'max_size'         => (int)($_ENV['API_UPLOAD_MAX_SIZE'] ?? 10485760), // 10 Mo
        'allowed_mimes'    => ['application/pdf', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                               'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                               'image/png', 'image/jpeg', 'image/webp', 'text/csv'],
        'allowed_exts'     => ['pdf', 'docx', 'xlsx', 'png', 'jpg', 'jpeg', 'webp', 'csv'],
        'storage_path'     => ROOT_PATH . '/storage/uploads',
    ],

    // ── Webhooks ──────────────────────────────────────────────────────────────
    'webhooks' => [
        'timeout'     => 30,
        'max_attempts'=> 5,
        'retry_delays'=> [0, 60, 300, 1800, 7200], // secondes entre tentatives
    ],

    // ── Logging ───────────────────────────────────────────────────────────────
    'logging' => [
        'enabled'        => true,
        'exclude_paths'  => ['/api/v1/health', '/api/docs'],
        'retention_days' => 90,
    ],

];
