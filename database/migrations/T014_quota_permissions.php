<?php

/**
 * T014 — Phase 14.8 (Stockage & Quotas) — Permission admin
 *
 * Ajoute `quota.view` (config/permissions.php, rôles admin + directeur) à
 * etab_permissions et la relie aux rôles système correspondants dans
 * etab_role_permissions — même mécanique que T009/T012.
 *
 * Idempotent, purement additif.
 */

return [
    'id'         => 'T014',
    'name'       => 'Permission quota.view (etab_permissions, etab_role_permissions)',
    'reversible' => true,

    'run' => function (PDO $pdo): void {

        $stmtPerm = $pdo->prepare(
            "INSERT INTO etab_permissions (code, libelle, module, action)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE libelle = VALUES(libelle)"
        );
        $stmtPerm->execute(['quota.view', 'Voir les quotas et la consommation de stockage', 'quota', 'view']);

        $permId = null;
        foreach ($pdo->query("SELECT id FROM etab_permissions WHERE code = 'quota.view'") as $row) {
            $permId = (int)$row->id;
        }

        $roleIds = [];
        foreach ($pdo->query("SELECT id, code FROM etab_roles WHERE etablissement_id IS NULL AND code IN ('admin','directeur')") as $row) {
            $roleIds[$row->code] = (int)$row->id;
        }

        $stmtRP = $pdo->prepare("INSERT IGNORE INTO etab_role_permissions (role_id, permission_id) VALUES (?, ?)");
        foreach (['admin', 'directeur'] as $roleCode) {
            if (!isset($roleIds[$roleCode]) || $permId === null) continue;
            $stmtRP->execute([$roleIds[$roleCode], $permId]);
        }
    },

    'rollback' => function (PDO $pdo): void {
        $pdo->exec("DELETE rp FROM etab_role_permissions rp
                     JOIN etab_permissions p ON p.id = rp.permission_id
                     WHERE p.code = 'quota.view'");
        $pdo->exec("DELETE FROM etab_permissions WHERE code = 'quota.view'");
    },
];
