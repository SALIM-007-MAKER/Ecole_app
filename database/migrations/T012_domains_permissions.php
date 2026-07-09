<?php

/**
 * T012 — Phase 14.7 (Domaines Personnalisés) — Permissions admin
 *
 * Ajoute les 2 nouvelles permissions `domains.view` / `domains.manage`
 * (config/permissions.php, rôles admin + directeur) à etab_permissions et
 * les relie aux rôles système correspondants dans etab_role_permissions —
 * même mécanique que T009 (branding.*).
 *
 * Idempotent, purement additif.
 */

return [
    'id'         => 'T012',
    'name'       => 'Permissions domains.view / domains.manage (etab_permissions, etab_role_permissions)',
    'reversible' => true,

    'run' => function (PDO $pdo): void {

        $newPerms = [
            ['domains.view',   'Voir les domaines personnalisés', 'domains', 'view'],
            ['domains.manage', 'Gérer les domaines personnalisés', 'domains', 'manage'],
        ];

        $stmtPerm = $pdo->prepare(
            "INSERT INTO etab_permissions (code, libelle, module, action)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE libelle = VALUES(libelle)"
        );
        foreach ($newPerms as $p) {
            $stmtPerm->execute($p);
        }

        $permIds = [];
        foreach ($pdo->query("SELECT id, code FROM etab_permissions WHERE code IN ('domains.view','domains.manage')") as $row) {
            $permIds[$row->code] = (int)$row->id;
        }

        $roleIds = [];
        foreach ($pdo->query("SELECT id, code FROM etab_roles WHERE etablissement_id IS NULL AND code IN ('admin','directeur')") as $row) {
            $roleIds[$row->code] = (int)$row->id;
        }

        $stmtRP = $pdo->prepare("INSERT IGNORE INTO etab_role_permissions (role_id, permission_id) VALUES (?, ?)");
        foreach (['admin', 'directeur'] as $roleCode) {
            if (!isset($roleIds[$roleCode])) continue;
            foreach ($permIds as $permId) {
                $stmtRP->execute([$roleIds[$roleCode], $permId]);
            }
        }
    },

    'rollback' => function (PDO $pdo): void {
        $pdo->exec("DELETE rp FROM etab_role_permissions rp
                     JOIN etab_permissions p ON p.id = rp.permission_id
                     WHERE p.code IN ('domains.view','domains.manage')");
        $pdo->exec("DELETE FROM etab_permissions WHERE code IN ('domains.view','domains.manage')");
    },
];
