<?php

/**
 * T007 — Phase 14.4 (RBAC Multi-Tenant) — Peuplement
 *
 * Peuple les tables RBAC tenant-aware créées en Phase 14.2 (T002/T003),
 * restées vides jusqu'ici :
 *   - etab_role_permissions : association rôle système → permissions,
 *     construite depuis config/permissions.php (source de vérité V1
 *     inchangée) pour que la résolution DB-driven produise EXACTEMENT
 *     les mêmes permissions que le système actuel.
 *   - user_roles_etab : association utilisateur → rôle DANS son
 *     établissement, construite depuis users.role + users.etablissement_id
 *     (disponible depuis la Phase 14.3).
 *
 * Idempotent (INSERT IGNORE), purement additif — aucune table existante
 * modifiée, aucune donnée supprimée.
 *
 * Voir MULTI_TENANT_V2_BLUEPRINT.md §8.2, §8.3.
 */

return [
    'id'         => 'T007',
    'name'       => 'Peuplement RBAC tenant (etab_role_permissions, user_roles_etab)',
    'reversible' => true,

    'run' => function (PDO $pdo): void {

        // ── 1. etab_role_permissions depuis config/permissions.php ───────────
        $permissionsByRole = require ROOT_PATH . '/config/permissions.php';

        $roleIds = [];
        foreach ($pdo->query("SELECT id, code FROM etab_roles WHERE etablissement_id IS NULL") as $row) {
            $roleIds[$row->code] = (int)$row->id;
        }

        $permIds = [];
        foreach ($pdo->query("SELECT id, code FROM etab_permissions") as $row) {
            $permIds[$row->code] = (int)$row->id;
        }

        $stmtRP = $pdo->prepare(
            "INSERT IGNORE INTO etab_role_permissions (role_id, permission_id) VALUES (?, ?)"
        );

        $linked = 0;
        $missingPerms = [];
        foreach ($permissionsByRole as $roleSlug => $codes) {
            if (!isset($roleIds[$roleSlug])) {
                continue; // rôle inconnu de etab_roles — ne devrait pas arriver (seedé en T005)
            }
            $roleId = $roleIds[$roleSlug];
            foreach ($codes as $code) {
                if (!isset($permIds[$code])) {
                    $missingPerms[$code] = true; // ne devrait pas arriver (seedé en T005)
                    continue;
                }
                $stmtRP->execute([$roleId, $permIds[$code]]);
                $linked++;
            }
        }

        if ($missingPerms) {
            throw new \RuntimeException(
                'T007 : permissions absentes de etab_permissions : ' . implode(', ', array_keys($missingPerms))
            );
        }

        // ── 2. user_roles_etab depuis users.role + users.etablissement_id ────
        $stmtUR = $pdo->prepare(
            "INSERT IGNORE INTO user_roles_etab (user_id, etablissement_id, role_id, assigned_at)
             SELECT id, etablissement_id, ?, NOW() FROM users WHERE id = ?"
        );

        $assigned = 0;
        foreach ($pdo->query("SELECT id, role, etablissement_id FROM users") as $u) {
            $roleId = $roleIds[$u->role] ?? null;
            if ($roleId === null) {
                continue; // rôle inconnu — ne devrait pas arriver
            }
            $stmtUR->execute([$roleId, (int)$u->id]);
            $assigned++;
        }

        // ── 3. user_etablissements (membership miroir — cohérence avec Phase 14.2) ─
        $stmtUE = $pdo->prepare(
            "INSERT IGNORE INTO user_etablissements (user_id, etablissement_id, is_primary)
             VALUES (?, ?, 1)"
        );
        foreach ($pdo->query("SELECT id, etablissement_id FROM users") as $u) {
            $stmtUE->execute([(int)$u->id, (int)$u->etablissement_id]);
        }

        echo "  (T007 : {$linked} associations role->permission, {$assigned} attributions user_roles_etab)\n";
    },

    'rollback' => function (PDO $pdo): void {
        $pdo->exec("DELETE FROM user_etablissements");
        $pdo->exec("DELETE FROM user_roles_etab");
        $pdo->exec("DELETE FROM etab_role_permissions");
    },
];
