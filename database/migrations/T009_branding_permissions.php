<?php

/**
 * T009 — Phase 14.5 (Branding Multi-Tenant) — Permissions admin
 *
 * Ajoute les 2 nouvelles permissions `branding.view` / `branding.update`
 * (config/permissions.php, rôles admin + directeur) à etab_permissions et
 * les relie aux rôles système correspondants dans etab_role_permissions —
 * même mécanique que T005/T007, appliquée aux 2 codes ajoutés pour cette
 * phase uniquement.
 *
 * Idempotent, purement additif.
 */

return [
    'id'         => 'T009',
    'name'       => 'Permissions branding.view / branding.update (etab_permissions, etab_role_permissions)',
    'reversible' => true,

    'run' => function (PDO $pdo): void {

        $newPerms = [
            ['branding.view',   'Voir les paramètres de branding', 'branding', 'view'],
            ['branding.update', 'Modifier les paramètres de branding', 'branding', 'update'],
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
        foreach ($pdo->query("SELECT id, code FROM etab_permissions WHERE code IN ('branding.view','branding.update')") as $row) {
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
                     WHERE p.code IN ('branding.view','branding.update')");
        $pdo->exec("DELETE FROM etab_permissions WHERE code IN ('branding.view','branding.update')");
    },
];
