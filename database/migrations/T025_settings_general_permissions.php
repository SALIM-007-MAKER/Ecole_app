<?php

/**
 * T025 — Permissions RBAC pour la nouvelle page "Configuration générale"
 * (/parametres/general — année scolaire active, devise par défaut).
 *
 * Ajoute `settings.general.view` / `settings.general.update` dans
 * `etab_permissions` (couche RBAC tenant réellement active — voir
 * TenantAuthContext::permissions(), qui court-circuite les autres couches
 * dès que user_roles_etab a des lignes, ce qui est le cas en base live) et
 * les accorde aux mêmes rôles que `branding.view`/`branding.update`
 * (admin, directeur) — même périmètre que Paramètres > Branding.
 *
 * `config/permissions.php` (fallback V1, modifié en même temps que cette
 * migration) reste synchronisé pour les environnements où les tables RBAC
 * seraient absentes.
 *
 * Idempotente.
 */

return [
    'id'         => 'T025',
    'name'       => 'Permissions settings.general.* (Configuration générale établissement)',
    'reversible' => true,

    'run' => function (PDO $pdo): void {
        $perms = [
            ['settings.general.view',   'Voir la configuration générale',      'settings', 'view'],
            ['settings.general.update', 'Modifier la configuration générale',  'settings', 'update'],
        ];

        $insertPerm = $pdo->prepare(
            "INSERT INTO etab_permissions (code, libelle, module, action)
             VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE libelle = VALUES(libelle)"
        );
        foreach ($perms as [$code, $libelle, $module, $action]) {
            $insertPerm->execute([$code, $libelle, $module, $action]);
        }

        $roleIds = $pdo->query(
            "SELECT id FROM etab_roles WHERE code IN ('admin','directeur')"
        )->fetchAll(PDO::FETCH_COLUMN);

        $permIds = $pdo->query(
            "SELECT id FROM etab_permissions WHERE code IN ('settings.general.view','settings.general.update')"
        )->fetchAll(PDO::FETCH_COLUMN);

        $grant = $pdo->prepare(
            "INSERT IGNORE INTO etab_role_permissions (role_id, permission_id) VALUES (?, ?)"
        );
        foreach ($roleIds as $roleId) {
            foreach ($permIds as $permId) {
                $grant->execute([$roleId, $permId]);
            }
        }
    },

    'rollback' => function (PDO $pdo): void {
        $pdo->exec(
            "DELETE rp FROM etab_role_permissions rp
             JOIN etab_permissions p ON p.id = rp.permission_id
             WHERE p.code IN ('settings.general.view','settings.general.update')"
        );
        $pdo->exec(
            "DELETE FROM etab_permissions WHERE code IN ('settings.general.view','settings.general.update')"
        );
    },
];
