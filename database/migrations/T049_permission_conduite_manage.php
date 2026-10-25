<?php

/**
 * T049 — Permission `academique.conduite.manage`
 *
 * Droit dédié à la gestion de la Conduite (évaluations, notes, appréciations de la matière marquée
 * `est_conduite`). Attribué aux seuls rôles système « admin » et « directeur » (etablissement_id NULL, is_system = 1).
 * Aucun autre rôle (enseignant, secrétaire…) ne la reçoit. Aucune permission existante n'est modifiée ni supprimée.
 *
 * Idempotente. Les droits ne changent qu'à la prochaine connexion des intéressés (session).
 * Retour arrière : retire les deux attributions puis la permission.
 */

return [
    'id'         => 'T049',
    'name'       => 'Permission academique.conduite.manage (admin, directeur)',
    'reversible' => true,

    'run' => function (PDO $pdo): void {
        $pdo->exec(
            "INSERT IGNORE INTO etab_permissions (code, libelle, module, action)
             VALUES ('academique.conduite.manage', 'Gérer la Conduite (évaluations, notes, appréciations)', 'academique', 'manage')"
        );
        $pdo->exec(
            "INSERT IGNORE INTO etab_role_permissions (role_id, permission_id)
             SELECT r.id, p.id
               FROM etab_roles r
               JOIN etab_permissions p ON p.code = 'academique.conduite.manage'
              WHERE r.etablissement_id IS NULL AND r.is_system = 1 AND r.code IN ('admin', 'directeur')"
        );
    },

    'rollback' => function (PDO $pdo): void {
        $pdo->exec(
            "DELETE rp FROM etab_role_permissions rp
               JOIN etab_permissions p ON p.id = rp.permission_id
              WHERE p.code = 'academique.conduite.manage'"
        );
        $pdo->exec("DELETE FROM etab_permissions WHERE code = 'academique.conduite.manage'");
    },
];
