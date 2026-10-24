<?php

/**
 * T045 — Retrait ciblé de `bulletins.view` aux rôles système « parent » et « eleve »
 *
 * Contexte (audit RBAC) : `bulletins.view` ouvrait les routes V1 /bulletins, /bulletins/classe,
 * /bulletins/classement et /bulletins/{id} à tout détenteur de la permission, sans contrôle de
 * propriété : un élève ou un parent pouvait lire le bulletin de n'importe quel élève et les
 * résultats/classement de n'importe quelle classe. Parents et élèves n'en ont pas besoin : leurs
 * écrans sont /parent/bulletin, /eleve/bulletin et l'impression V2 (contrôle de rattachement).
 * Le contrôle de propriété côté serveur a été ajouté en parallèle (BulletinPolicy, BulletinController) ;
 * cette migration aligne la table tenant sur config/permissions.php.
 *
 * Périmètre STRICT : 2 lignes de `etab_role_permissions` au plus (parent+bulletins.view,
 * eleve+bulletins.view), uniquement pour les rôles système (etablissement_id NULL, is_system = 1).
 * Aucune autre permission, aucun rôle personnalisé, aucune permission du catalogue supprimée.
 * Idempotente. Les droits ne changent qu'à la prochaine connexion des intéressés (session).
 *
 * Sauvegarde préalable : database/backups/rbac_avant_T045_20261024.sql.
 */

return [
    'id'         => 'T045',
    'name'       => 'Retrait bulletins.view aux rôles système parent et eleve',
    'reversible' => true,

    'run' => function (PDO $pdo): void {
        $stmt = $pdo->prepare(
            "DELETE rp FROM etab_role_permissions rp
               JOIN etab_roles r       ON r.id = rp.role_id
               JOIN etab_permissions p ON p.id = rp.permission_id
              WHERE r.etablissement_id IS NULL AND r.is_system = 1
                AND r.code IN ('parent', 'eleve')
                AND p.code = 'bulletins.view'"
        );
        $stmt->execute();
    },

    'rollback' => function (PDO $pdo): void {
        // Restaure l'état antérieur (attention : réexpose /bulletins/* si les gardes serveur sont retirées).
        $pdo->exec(
            "INSERT IGNORE INTO etab_role_permissions (role_id, permission_id)
             SELECT r.id, p.id
               FROM etab_roles r
               JOIN etab_permissions p ON p.code = 'bulletins.view'
              WHERE r.etablissement_id IS NULL AND r.is_system = 1 AND r.code IN ('parent', 'eleve')"
        );
    },
];
