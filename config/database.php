<?php

return [
    'default' => 'mysql',

    'connections' => [
        'mysql' => [
            'driver'   => 'mysql',
            'host'     => $_ENV['DB_HOST']     ?? 'localhost',
            'port'     => $_ENV['DB_PORT']     ?? '3306',
            'database' => $_ENV['DB_DATABASE'] ?? 'ecole_app',
            'username' => $_ENV['DB_USERNAME'] ?? 'root',
            'password' => $_ENV['DB_PASSWORD'] ?? '',
            'charset'  => 'utf8mb4',
            'options'  => [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
                PDO::ATTR_EMULATE_PREPARES   => false,
                // DB_SQL_MODE (optionnel, ex. STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION) : mode SQL strict pour cette application, sans toucher au serveur.
                // Absent = comportement historique (sql_mode du serveur). Valeur limitée à des mots-clés SQL (jamais interpolée librement).
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
                    . (preg_match('/^[A-Z_,]+$/', $_ENV['DB_SQL_MODE'] ?? '') ? ", sql_mode='" . $_ENV['DB_SQL_MODE'] . "'" : ''),
            ],
        ],
    ],
];
