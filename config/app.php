<?php

return [
    'name'     => $_ENV['APP_NAME']     ?? 'EduNova',
    'version'  => '2.0.0',              // SCOLARIS V2.0.0 — figé Phase 16.0, voir SCOLARIS_V2_FINAL_RELEASE_REPORT.md
    'env'      => $_ENV['APP_ENV']      ?? 'development',
    // Repli à false (pas true) si APP_DEBUG est absent de .env : un défaut
    // "sûr par défaut" évite qu'un environnement mal configuré (variable
    // manquante) n'expose accidentellement des traces d'erreur détaillées
    // en production — trouvé Phase 15.1 (RC1). .env local garde sa valeur
    // explicite (true), aucun changement de comportement en développement.
    'debug'    => filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN),
    'url'      => $_ENV['APP_URL']      ?? 'http://localhost/ecole_app',
    'key'      => $_ENV['APP_KEY']      ?? null,
    'timezone' => $_ENV['APP_TIMEZONE'] ?? 'Africa/Algiers',

    'session' => [
        'lifetime' => (int)($_ENV['SESSION_LIFETIME'] ?? 7200),
        'name'     => $_ENV['SESSION_NAME'] ?? 'ecole_session',
    ],

    'upload' => [
        'max_size' => (int)($_ENV['UPLOAD_MAX_SIZE'] ?? 5242880),
        'path'     => $_ENV['UPLOAD_PATH'] ?? 'storage/uploads',
    ],
];
