<?php

/**
 * Registre des modules SCOLARIS V2.
 *
 * Chaque module déclare :
 *   - enabled    : false = présent mais inactif (routes non chargées)
 *   - namespace  : préfixe PSR-4 des classes du module
 *   - routes     : chemin absolu vers le fichier de routes du module
 *   - manifest   : chemin vers module.json
 *
 * Pour activer un module : passer enabled à true.
 * L'Application charge automatiquement les routes des modules actifs.
 */

return [

    'scolarite' => [
        'enabled'   => false,
        'namespace' => 'App\\Modules\\Scolarite',
        'routes'    => ROOT_PATH . '/app/Modules/Scolarite/routes.php',
        'manifest'  => ROOT_PATH . '/app/Modules/Scolarite/module.json',
    ],

    'academique' => [
        'enabled'   => false,
        'namespace' => 'App\\Modules\\Academique',
        'routes'    => ROOT_PATH . '/app/Modules/Academique/routes.php',
        'manifest'  => ROOT_PATH . '/app/Modules/Academique/module.json',
    ],

    'finance' => [
        'enabled'   => true,
        'namespace' => 'App\\Modules\\Finance',
        'routes'    => ROOT_PATH . '/app/Modules/Finance/routes.php',
        'manifest'  => ROOT_PATH . '/app/Modules/Finance/module.json',
    ],

    'vie_scolaire' => [
        'enabled'   => true,
        'namespace' => 'App\\Modules\\VieScolaire',
        'routes'    => ROOT_PATH . '/app/Modules/VieScolaire/routes.php',
        'manifest'  => ROOT_PATH . '/app/Modules/VieScolaire/module.json',
    ],

    'rh' => [
        'enabled'   => true,
        'namespace' => 'App\\Modules\\RH',
        'routes'    => ROOT_PATH . '/app/Modules/RH/routes.php',
        'manifest'  => ROOT_PATH . '/app/Modules/RH/module.json',
    ],

    'documents' => [
        'enabled'   => false,
        'namespace' => 'App\\Modules\\Documents',
        'routes'    => ROOT_PATH . '/app/Modules/Documents/routes.php',
        'manifest'  => ROOT_PATH . '/app/Modules/Documents/module.json',
    ],

    'communication' => [
        'enabled'   => false,
        'namespace' => 'App\\Modules\\Communication',
        'routes'    => ROOT_PATH . '/app/Modules/Communication/routes.php',
        'manifest'  => ROOT_PATH . '/app/Modules/Communication/module.json',
    ],

    'bibliotheque' => [
        'enabled'   => false,
        'namespace' => 'App\\Modules\\Bibliotheque',
        'routes'    => ROOT_PATH . '/app/Modules/Bibliotheque/routes.php',
        'manifest'  => ROOT_PATH . '/app/Modules/Bibliotheque/module.json',
    ],

    'inventaire' => [
        'enabled'   => false,
        'namespace' => 'App\\Modules\\Inventaire',
        'routes'    => ROOT_PATH . '/app/Modules/Inventaire/routes.php',
        'manifest'  => ROOT_PATH . '/app/Modules/Inventaire/module.json',
    ],

    'rapports' => [
        'enabled'   => false,
        'namespace' => 'App\\Modules\\Rapports',
        'routes'    => ROOT_PATH . '/app/Modules/Rapports/routes.php',
        'manifest'  => ROOT_PATH . '/app/Modules/Rapports/module.json',
    ],

    'portals' => [
        'enabled'   => false,
        'namespace' => 'App\\Modules\\Portals',
        'routes'    => ROOT_PATH . '/app/Modules/Portals/routes.php',
        'manifest'  => ROOT_PATH . '/app/Modules/Portals/module.json',
    ],

    'api' => [
        'enabled'   => true,
        'namespace' => 'App\\Modules\\Api',
        'routes'    => ROOT_PATH . '/app/Modules/Api/routes.php',
        'manifest'  => ROOT_PATH . '/app/Modules/Api/module.json',
    ],

];
