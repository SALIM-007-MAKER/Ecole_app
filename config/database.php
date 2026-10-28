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
                // Mode SQL de la connexion : par défaut celui du serveur (comportement historique). DB_SQL_MODE=strict active
                // STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION (refuse troncatures, valeurs hors limites, colonnes obligatoires sans valeur) ;
                // toute autre valeur doit être une liste de mots-clés SQL. À n'activer qu'après avoir testé TOUS les formulaires d'écriture :
                // le premier défaut trouvé ainsi (platform_plans.modules_inclus) est corrigé, d'autres peuvent subsister.
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
                    . (($m = ($_ENV['DB_SQL_MODE'] ?? '')) === 'strict' ? ", sql_mode='STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION'" : (preg_match('/^[A-Z_,]+$/', $m) ? ", sql_mode='" . $m . "'" : '')),
            ],
        ],
    ],
];
