<?php

/**
 * T016 — Phase 14.9 (Cache & Queue) — Permission admin
 *
 * Ajoute `monitoring.view` (config/permissions.php, rôles admin + directeur)
 * à etab_permissions et la relie aux rôles système correspondants dans
 * etab_role_permissions — même mécanique que T009/T012/T014.
 *
 * Idempotent, purement additif.
 */

return [
    'id'         => 'T016',
    'name'       => 'Permission monitoring.view (etab_permissions, etab_role_permissions)',
    'reversible' => true,

    'run' => function (PDO $pdo): void {

        $stmtPerm = $pdo->prepare(
            "INSERT INTO etab_permissions (code, libelle, module, action)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE libelle = VALUES(libelle)"
        );
        $stmtPerm->execute(['monitoring.view', 'Voir le monitoring cache/files d\'attente', 'monitoring', 'view']);

        $permId = null;
        foreach ($pdo->query("SELECT id FROM etab_permissions WHERE code = 'monitoring.view'") as $row) {
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
                     WHERE p.code = 'monitoring.view'");
        $pdo->exec("DELETE FROM etab_permissions WHERE code = 'monitoring.view'");
    },
];
