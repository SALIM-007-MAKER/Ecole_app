<?php

/**
 * T026 — Refonte "Paramètres" en centre de configuration métier ERP
 *
 * Remplace l'orientation technique du module Paramètres (Domaines, Quotas,
 * Monitoring — désormais réservés à l'Administration de la plateforme,
 * Super Administrateur uniquement) par 12 catégories métier accessibles
 * aux administrateurs d'établissement (admin, directeur) :
 * Établissement, Année scolaire, Organisation académique, Matières et
 * coefficients, Système de notation, Utilisateurs & rôles, Finances,
 * Documents, Notifications, Apparence, Sécurité, Sauvegarde & restauration,
 * Paramètres avancés. Voir SAAS_CONFIGURATION_METIER_REPORT.md.
 *
 * - Ajoute 16 nouvelles permissions (8 catégories × view/update) dans
 *   `etab_permissions`, accordées à admin/directeur dans
 *   `etab_role_permissions` (Établissement/Apparence réutilisent
 *   branding.*, Année scolaire réutilise settings.general.*, Matières et
 *   Utilisateurs réutilisent les permissions existantes matieres.view/
 *   users.view — pas de doublon créé pour celles-ci).
 * - RÉVOQUE domains.view/manage, quota.view, monitoring.view des rôles
 *   admin/directeur (etab_role_permissions) : ces fonctionnalités
 *   deviennent exclusivement accessibles aux opérateurs de la plateforme
 *   (Core\Platform\PlatformAuth, niveaux admin/super_admin), un modèle de
 *   permission entièrement distinct et déjà isolé par construction. Les
 *   permissions elles-mêmes restent définies dans `etab_permissions` (au
 *   cas où un usage futur les réintroduirait), seule l'attribution aux
 *   rôles d'établissement est retirée.
 *
 * Idempotente.
 */

return [
    'id'         => 'T026',
    'name'       => 'Paramètres métier ERP — nouvelles permissions + révocation domaines/quotas/monitoring',
    'reversible' => true,

    'run' => function (PDO $pdo): void {
        $perms = [
            ['settings.academique.view',     'Voir l\'organisation académique',        'settings', 'view'],
            ['settings.academique.update',   'Modifier l\'organisation académique',    'settings', 'update'],
            ['settings.notation.view',       'Voir le système de notation',            'settings', 'view'],
            ['settings.notation.update',     'Modifier le système de notation',        'settings', 'update'],
            ['settings.finances.view',       'Voir la configuration financière',       'settings', 'view'],
            ['settings.finances.update',     'Modifier la configuration financière',   'settings', 'update'],
            ['settings.documents.view',      'Voir la configuration des documents',    'settings', 'view'],
            ['settings.documents.update',    'Modifier la configuration des documents','settings', 'update'],
            ['settings.notifications.view',  'Voir la configuration des notifications','settings', 'view'],
            ['settings.notifications.update','Modifier la configuration des notifications','settings', 'update'],
            ['settings.securite.view',       'Voir les paramètres de sécurité',        'settings', 'view'],
            ['settings.securite.update',     'Modifier les paramètres de sécurité',    'settings', 'update'],
            ['settings.sauvegarde.view',     'Voir les préférences de sauvegarde',     'settings', 'view'],
            ['settings.sauvegarde.update',   'Modifier les préférences de sauvegarde', 'settings', 'update'],
            ['settings.avance.view',         'Voir les paramètres avancés',            'settings', 'view'],
            ['settings.avance.update',       'Modifier les paramètres avancés',        'settings', 'update'],
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

        $codes = array_column($perms, 0);
        $in    = implode(',', array_fill(0, count($codes), '?'));
        $permIds = $pdo->prepare("SELECT id FROM etab_permissions WHERE code IN ({$in})");
        $permIds->execute($codes);
        $permIds = $permIds->fetchAll(PDO::FETCH_COLUMN);

        $grant = $pdo->prepare(
            "INSERT IGNORE INTO etab_role_permissions (role_id, permission_id) VALUES (?, ?)"
        );
        foreach ($roleIds as $roleId) {
            foreach ($permIds as $permId) {
                $grant->execute([$roleId, $permId]);
            }
        }

        // ── Révocation : domaines/quotas/monitoring quittent le périmètre établissement ──
        $pdo->exec(
            "DELETE rp FROM etab_role_permissions rp
             JOIN etab_roles r ON r.id = rp.role_id
             JOIN etab_permissions p ON p.id = rp.permission_id
             WHERE r.code IN ('admin','directeur')
               AND p.code IN ('domains.view','domains.manage','quota.view','monitoring.view')"
        );
    },

    'rollback' => function (PDO $pdo): void {
        $codes = [
            'settings.academique.view','settings.academique.update',
            'settings.notation.view','settings.notation.update',
            'settings.finances.view','settings.finances.update',
            'settings.documents.view','settings.documents.update',
            'settings.notifications.view','settings.notifications.update',
            'settings.securite.view','settings.securite.update',
            'settings.sauvegarde.view','settings.sauvegarde.update',
            'settings.avance.view','settings.avance.update',
        ];
        $in = implode(',', array_fill(0, count($codes), '?'));

        $pdo->prepare(
            "DELETE rp FROM etab_role_permissions rp
             JOIN etab_permissions p ON p.id = rp.permission_id
             WHERE p.code IN ({$in})"
        )->execute($codes);

        $pdo->prepare("DELETE FROM etab_permissions WHERE code IN ({$in})")->execute($codes);

        // Ré-octroi domaines/quotas/monitoring à admin/directeur (état avant T026)
        $roleIds = $pdo->query("SELECT id FROM etab_roles WHERE code IN ('admin','directeur')")->fetchAll(PDO::FETCH_COLUMN);
        $permIds = $pdo->query(
            "SELECT id FROM etab_permissions WHERE code IN ('domains.view','domains.manage','quota.view','monitoring.view')"
        )->fetchAll(PDO::FETCH_COLUMN);
        $grant = $pdo->prepare("INSERT IGNORE INTO etab_role_permissions (role_id, permission_id) VALUES (?, ?)");
        foreach ($roleIds as $roleId) {
            foreach ($permIds as $permId) {
                $grant->execute([$roleId, $permId]);
            }
        }
    },
];
