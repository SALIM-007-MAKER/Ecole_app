<?php
/**
 * P001 — Correction RBAC : ajouter bulletins.view aux rôles eleve et parent
 *
 * Root cause : R001 assignait bulletins.view.own + bulletins.print.own mais omettait
 * bulletins.view (V1 code), ce que BulletinController::requirePermission() vérifie.
 * Les rôles admin→enseignant avaient bien bulletins.view ; eleve et parent non.
 */
return [
    'id'   => 'P001',
    'name' => 'Correction RBAC — bulletins.view pour eleve et parent',
    'run'  => function (PDO $pdo): void {
        // Trouver l'ID de la permission bulletins.view
        $permId = $pdo->query(
            "SELECT id FROM permissions WHERE code = 'bulletins.view' LIMIT 1"
        )->fetchColumn();

        if (!$permId) {
            // Insérer si absent (ne devrait pas arriver — R001 l'a insérée)
            $pdo->exec(
                "INSERT IGNORE INTO permissions (code, module, action, scope, is_system)
                 VALUES ('bulletins.view', 'bulletins', 'view', 'all', 1)"
            );
            $permId = $pdo->lastInsertId();
        }

        // Trouver les IDs des rôles eleve et parent
        $roles = $pdo->query(
            "SELECT id, slug FROM rbac_roles WHERE slug IN ('eleve', 'parent')"
        )->fetchAll(PDO::FETCH_KEY_PAIR);

        foreach ($roles as $roleId => $slug) {
            $pdo->prepare(
                "INSERT IGNORE INTO rbac_role_permissions (role_id, permission_id)
                 VALUES (?, ?)"
            )->execute([$roleId, $permId]);
        }
    },
];
