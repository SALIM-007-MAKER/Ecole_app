<?php

/**
 * R001 — Tables RBAC V2
 *
 * Crée les nouvelles tables RBAC sans toucher les tables V1 existantes
 * (permissions V1, role_permissions V1).
 *
 * Stratégie de compatibilité :
 *   - Les tables V1 `permissions` et `role_permissions` restent intactes.
 *   - On étend `permissions` V1 avec les colonnes V2 manquantes (action, scope, is_system).
 *   - On crée des tables RBAC nouvelles : rbac_roles, rbac_role_permissions, rbac_user_roles.
 *   - UserModel::getPermissions() interroge rbac_user_roles + rbac_role_permissions + permissions.
 *
 * Population :
 *   - Insère les 7 rôles standard dans rbac_roles.
 *   - Insère les ~85 permissions V2 dans la table permissions (avec les nouvelles colonnes).
 *   - Affecte les permissions aux rôles dans rbac_role_permissions.
 *   - Popule rbac_user_roles depuis users.role (liaison automatique).
 */

return [
    'id'         => 'R001',
    'name'       => 'Tables RBAC V2 (rbac_roles, rbac_role_permissions, rbac_user_roles)',
    'reversible' => true,

    'run' => function (PDO $pdo): void {

        // ── 1. Étendre la table permissions V1 avec les colonnes V2 ──────────
        $permCols = $pdo->query(
            "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'permissions'"
        )->fetchAll(PDO::FETCH_COLUMN);

        if (!in_array('action', $permCols, true)) {
            $pdo->exec("ALTER TABLE `permissions` ADD COLUMN `action` VARCHAR(50) NOT NULL DEFAULT '' AFTER `module`");
        }
        if (!in_array('scope', $permCols, true)) {
            $pdo->exec("ALTER TABLE `permissions` ADD COLUMN `scope` ENUM('global','own') NOT NULL DEFAULT 'global' AFTER `action`");
        }
        if (!in_array('is_system', $permCols, true)) {
            $pdo->exec("ALTER TABLE `permissions` ADD COLUMN `is_system` TINYINT(1) NOT NULL DEFAULT 1 AFTER `scope`");
        }

        // ── 2. Table rbac_roles ───────────────────────────────────────────────
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `rbac_roles` (
                `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `slug`        VARCHAR(50)  NOT NULL UNIQUE COMMENT 'admin, directeur, ...',
                `label`       VARCHAR(100) NOT NULL,
                `description` TEXT         NULL,
                `is_system`   TINYINT(1)   NOT NULL DEFAULT 1,
                `actif`       TINYINT(1)   NOT NULL DEFAULT 1,
                `ordre`       TINYINT UNSIGNED NOT NULL DEFAULT 0,
                `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                INDEX `idx_role_slug` (`slug`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // ── 3. Table rbac_role_permissions ────────────────────────────────────
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `rbac_role_permissions` (
                `role_id`       INT UNSIGNED NOT NULL,
                `permission_id` INT UNSIGNED NOT NULL,
                PRIMARY KEY (`role_id`, `permission_id`),
                CONSTRAINT `fk_rrp_role` FOREIGN KEY (`role_id`)
                    REFERENCES `rbac_roles`(`id`) ON DELETE CASCADE,
                CONSTRAINT `fk_rrp_perm` FOREIGN KEY (`permission_id`)
                    REFERENCES `permissions`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // ── 4. Table rbac_user_roles ──────────────────────────────────────────
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `rbac_user_roles` (
                `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `user_id`    INT UNSIGNED NOT NULL,
                `role_id`    INT UNSIGNED NOT NULL,
                `granted_by` INT UNSIGNED NULL,
                `granted_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `expires_at` DATETIME     NULL,
                `actif`      TINYINT(1)   NOT NULL DEFAULT 1,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_user_role` (`user_id`, `role_id`),
                INDEX `idx_ur_user` (`user_id`, `actif`),
                CONSTRAINT `fk_ur_user`    FOREIGN KEY (`user_id`)    REFERENCES `users`(`id`)      ON DELETE CASCADE,
                CONSTRAINT `fk_ur_role`    FOREIGN KEY (`role_id`)    REFERENCES `rbac_roles`(`id`) ON DELETE CASCADE,
                CONSTRAINT `fk_ur_granter` FOREIGN KEY (`granted_by`) REFERENCES `users`(`id`)      ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // ── 5. Insérer les 7 rôles standard ──────────────────────────────────
        $roles = [
            ['admin',      'Administrateur', 'Accès total au système',                       1, 1],
            ['directeur',  'Directeur',      'Direction de l\'établissement',                1, 2],
            ['secretaire', 'Secrétaire',     'Administration scolaire',                       1, 3],
            ['comptable',  'Comptable',      'Gestion financière',                            1, 4],
            ['enseignant', 'Enseignant',     'Corps pédagogique',                             1, 5],
            ['parent',     'Parent',         'Représentant légal de l\'élève',               1, 6],
            ['eleve',      'Élève',          'Apprenant',                                     1, 7],
        ];

        $stmtRole = $pdo->prepare(
            "INSERT IGNORE INTO `rbac_roles` (slug, label, description, is_system, ordre) VALUES (?,?,?,1,?)"
        );
        foreach ($roles as [$slug, $label, $desc, $sys, $ordre]) {
            $stmtRole->execute([$slug, $label, $desc, $ordre]);
        }

        // ── 6. Insérer les permissions V2 dans la table permissions ──────────
        $permissions = _r001_permissions_matrix();
        $stmtPerm = $pdo->prepare(
            "INSERT IGNORE INTO `permissions` (code, libelle, module, action, scope, is_system)
             VALUES (?, ?, ?, ?, ?, 1)"
        );
        foreach ($permissions as [$code, $libelle, $module, $action, $scope]) {
            $stmtPerm->execute([$code, $libelle, $module, $action, $scope]);
        }

        // ── 7. Affecter les permissions aux rôles ────────────────────────────
        $matrix = _r001_role_permission_matrix();
        foreach ($matrix as $roleSlug => $permCodes) {
            $roleId = $pdo->query(
                "SELECT id FROM rbac_roles WHERE slug = " . $pdo->quote($roleSlug)
            )->fetchColumn();
            if (!$roleId) continue;

            foreach ($permCodes as $code) {
                $permId = $pdo->query(
                    "SELECT id FROM permissions WHERE code = " . $pdo->quote($code)
                )->fetchColumn();
                if (!$permId) continue;

                $pdo->exec("INSERT IGNORE INTO rbac_role_permissions (role_id, permission_id) VALUES ({$roleId}, {$permId})");
            }
        }

        // ── 8. Peupler rbac_user_roles depuis users.role ─────────────────────
        $users = $pdo->query("SELECT id, role FROM users WHERE actif = 1")->fetchAll();
        $stmtUR = $pdo->prepare(
            "INSERT IGNORE INTO rbac_user_roles (user_id, role_id)
             SELECT ?, id FROM rbac_roles WHERE slug = ?"
        );
        foreach ($users as $user) {
            $stmtUR->execute([$user->id, $user->role]);
        }
    },

    'rollback' => function (PDO $pdo): void {
        $pdo->exec("DROP TABLE IF EXISTS `rbac_user_roles`");
        $pdo->exec("DROP TABLE IF EXISTS `rbac_role_permissions`");
        $pdo->exec("DROP TABLE IF EXISTS `rbac_roles`");
    },
];

// ─── Matrice des permissions V2 ──────────────────────────────────────────────
// Format : [code, libelle, module, action, scope]

function _r001_permissions_matrix(): array
{
    return [
        // Élèves
        ['eleves.view',         'Voir les élèves',           'eleves',    'view',   'global'],
        ['eleves.create',       'Créer un élève',            'eleves',    'create', 'global'],
        ['eleves.update',       'Modifier un élève',         'eleves',    'update', 'global'],
        ['eleves.delete',       'Supprimer un élève',        'eleves',    'delete', 'global'],
        ['eleves.export',       'Exporter les élèves',       'eleves',    'export', 'global'],
        // Compat V1
        ['eleves.edit',         'Modifier un élève (V1)',    'eleves',    'update', 'global'],

        // Enseignants
        ['enseignants.view',    'Voir les enseignants',      'enseignants','view',  'global'],
        ['enseignants.create',  'Créer un enseignant',       'enseignants','create','global'],
        ['enseignants.update',  'Modifier un enseignant',    'enseignants','update','global'],
        ['enseignants.delete',  'Supprimer un enseignant',   'enseignants','delete','global'],
        ['enseignants.export',  'Exporter les enseignants',  'enseignants','export','global'],
        ['enseignants.edit',    'Modifier enseignant (V1)',   'enseignants','update','global'],

        // Classes
        ['classes.view',        'Voir les classes',          'classes',   'view',   'global'],
        ['classes.create',      'Créer une classe',          'classes',   'create', 'global'],
        ['classes.update',      'Modifier une classe',       'classes',   'update', 'global'],
        ['classes.delete',      'Supprimer une classe',      'classes',   'delete', 'global'],
        ['classes.edit',        'Modifier une classe (V1)',  'classes',   'update', 'global'],

        // Matières
        ['matieres.view',       'Voir les matières',         'matieres',  'view',   'global'],
        ['matieres.create',     'Créer une matière',         'matieres',  'create', 'global'],
        ['matieres.update',     'Modifier une matière',      'matieres',  'update', 'global'],
        ['matieres.delete',     'Supprimer une matière',     'matieres',  'delete', 'global'],
        ['matieres.edit',       'Modifier une matière (V1)', 'matieres',  'update', 'global'],

        // Notes
        ['notes.view',          'Voir toutes les notes',     'notes',     'view',   'global'],
        ['notes.view.own',      'Voir ses propres notes',    'notes',     'view',   'own'],
        ['notes.view_own',      'Voir ses notes (V1)',       'notes',     'view',   'own'],
        ['notes.create',        'Saisir des notes',          'notes',     'create', 'global'],
        ['notes.update',        'Modifier des notes',        'notes',     'update', 'global'],
        ['notes.delete',        'Supprimer des notes',       'notes',     'delete', 'global'],
        ['notes.export',        'Exporter les notes',        'notes',     'export', 'global'],
        ['notes.edit',          'Modifier des notes (V1)',   'notes',     'update', 'global'],

        // Bulletins
        ['bulletins.view',      'Voir les bulletins',        'bulletins', 'view',   'global'],
        ['bulletins.view.own',  'Voir son bulletin',         'bulletins', 'view',   'own'],
        ['bulletins.print',     'Imprimer un bulletin',      'bulletins', 'print',  'global'],
        ['bulletins.print.own', 'Imprimer son bulletin',     'bulletins', 'print',  'own'],
        ['bulletins.export',    'Exporter les bulletins',    'bulletins', 'export', 'global'],
        ['bulletins.approve',   'Valider les bulletins',     'bulletins', 'approve','global'],

        // Absences
        ['absences.view',       'Voir toutes les absences',  'absences',  'view',   'global'],
        ['absences.view.own',   'Voir ses absences',         'absences',  'view',   'own'],
        ['absences.view_own',   'Voir ses absences (V1)',    'absences',  'view',   'own'],
        ['absences.create',     'Saisir des absences',       'absences',  'create', 'global'],
        ['absences.update',     'Modifier des absences',     'absences',  'update', 'global'],
        ['absences.delete',     'Supprimer des absences',    'absences',  'delete', 'global'],
        ['absences.export',     'Exporter les absences',     'absences',  'export', 'global'],
        ['absences.approve',    'Valider des justifications','absences',  'approve','global'],
        ['absences.approve.own','Justifier sa propre absence','absences', 'approve','own'],
        ['absences.edit',       'Modifier des absences (V1)','absences',  'update', 'global'],
        ['absences.justify',    'Justifier absence (V1)',    'absences',  'approve','own'],

        // Comptabilité
        ['comptabilite.view',      'Voir la comptabilité',     'comptabilite','view',  'global'],
        ['comptabilite.view.own',  'Voir ses paiements',       'comptabilite','view',  'own'],
        ['comptabilite.view_own',  'Voir ses paiements (V1)',  'comptabilite','view',  'own'],
        ['comptabilite.create',    'Créer un paiement',        'comptabilite','create','global'],
        ['comptabilite.update',    'Modifier un paiement',     'comptabilite','update','global'],
        ['comptabilite.delete',    'Supprimer un paiement',    'comptabilite','delete','global'],
        ['comptabilite.export',    'Exporter la comptabilité', 'comptabilite','export','global'],
        ['comptabilite.print',     'Imprimer les reçus',       'comptabilite','print', 'global'],
        ['comptabilite.print.own', 'Imprimer son reçu',        'comptabilite','print', 'own'],
        ['comptabilite.edit',      'Modifier paiement (V1)',   'comptabilite','update','global'],

        // Emploi du temps
        ['emploi_du_temps.view',      'Voir l\'emploi du temps', 'emploi_du_temps','view',  'global'],
        ['emploi_du_temps.view.own',  'Voir son EDT',             'emploi_du_temps','view',  'own'],
        ['emploi_du_temps.view_own',  'Voir son EDT (V1)',        'emploi_du_temps','view',  'own'],
        ['emploi_du_temps.create',    'Créer un créneau EDT',     'emploi_du_temps','create','global'],
        ['emploi_du_temps.update',    'Modifier l\'EDT',          'emploi_du_temps','update','global'],
        ['emploi_du_temps.delete',    'Supprimer un créneau EDT', 'emploi_du_temps','delete','global'],
        ['emploi_du_temps.print',     'Imprimer l\'EDT',          'emploi_du_temps','print', 'global'],
        ['emploi_du_temps.print.own', 'Imprimer son EDT',         'emploi_du_temps','print', 'own'],
        ['emploi_du_temps.edit',      'Modifier EDT (V1)',         'emploi_du_temps','update','global'],

        // Annonces
        ['annonces.view',    'Voir les annonces',     'annonces', 'view',  'global'],
        ['annonces.create',  'Créer une annonce',     'annonces', 'create','global'],
        ['annonces.update',  'Modifier une annonce',  'annonces', 'update','global'],
        ['annonces.delete',  'Supprimer une annonce', 'annonces', 'delete','global'],
        ['annonces.edit',    'Modifier annonce (V1)', 'annonces', 'update','global'],

        // Notifications
        ['notifications.view',    'Voir ses notifications',     'notifications','view',  'global'],
        ['notifications.view.own','Voir ses notifications',     'notifications','view',  'own'],
        ['notifications.create',  'Envoyer une notification',  'notifications','create','global'],
        ['notifications.delete',  'Supprimer des notifications','notifications','delete','global'],
        ['notifications.manage',  'Gérer notifications (V1)',  'notifications','create','global'],

        // Utilisateurs
        ['users.view',   'Voir les utilisateurs',     'users','view',  'global'],
        ['users.create', 'Créer un utilisateur',      'users','create','global'],
        ['users.update', 'Modifier un utilisateur',   'users','update','global'],
        ['users.delete', 'Supprimer un utilisateur',  'users','delete','global'],
        ['users.export', 'Exporter les utilisateurs', 'users','export','global'],
        ['users.update.own','Modifier son profil',    'users','update','own'],
        ['users.edit',   'Modifier utilisateur (V1)', 'users','update','global'],

        // Rapports
        ['rapports.view',   'Voir les rapports',      'rapports','view',  'global'],
        ['rapports.export', 'Exporter les rapports',  'rapports','export','global'],
        ['rapports.print',  'Imprimer les rapports',  'rapports','print', 'global'],

        // Profil
        ['profil.view.own',   'Voir son profil',    'profil','view',  'own'],
        ['profil.update.own', 'Modifier son profil','profil','update','own'],

        // Paramètres (admin uniquement)
        ['parametres.view',   'Voir les paramètres',    'parametres','view',  'global'],
        ['parametres.update', 'Modifier les paramètres','parametres','update','global'],
    ];
}

// ─── Matrice rôle → permissions ───────────────────────────────────────────────

function _r001_role_permission_matrix(): array
{
    return [
        'admin' => [
            'eleves.view', 'eleves.create', 'eleves.update', 'eleves.delete', 'eleves.export', 'eleves.edit',
            'enseignants.view', 'enseignants.create', 'enseignants.update', 'enseignants.delete', 'enseignants.export', 'enseignants.edit',
            'classes.view', 'classes.create', 'classes.update', 'classes.delete', 'classes.edit',
            'matieres.view', 'matieres.create', 'matieres.update', 'matieres.delete', 'matieres.edit',
            'notes.view', 'notes.view.own', 'notes.view_own', 'notes.create', 'notes.update', 'notes.delete', 'notes.export', 'notes.edit',
            'bulletins.view', 'bulletins.view.own', 'bulletins.print', 'bulletins.export', 'bulletins.approve',
            'absences.view', 'absences.view.own', 'absences.view_own', 'absences.create', 'absences.update', 'absences.delete', 'absences.export', 'absences.approve', 'absences.edit',
            'comptabilite.view', 'comptabilite.view.own', 'comptabilite.view_own', 'comptabilite.create', 'comptabilite.update', 'comptabilite.delete', 'comptabilite.export', 'comptabilite.print', 'comptabilite.edit',
            'emploi_du_temps.view', 'emploi_du_temps.view.own', 'emploi_du_temps.view_own', 'emploi_du_temps.create', 'emploi_du_temps.update', 'emploi_du_temps.delete', 'emploi_du_temps.print', 'emploi_du_temps.edit',
            'annonces.view', 'annonces.create', 'annonces.update', 'annonces.delete', 'annonces.edit',
            'notifications.view', 'notifications.view.own', 'notifications.create', 'notifications.delete', 'notifications.manage',
            'users.view', 'users.create', 'users.update', 'users.delete', 'users.export', 'users.edit', 'users.update.own',
            'rapports.view', 'rapports.export', 'rapports.print',
            'profil.view.own', 'profil.update.own',
            'parametres.view', 'parametres.update',
        ],
        'directeur' => [
            'eleves.view', 'eleves.create', 'eleves.update', 'eleves.delete', 'eleves.export', 'eleves.edit',
            'enseignants.view', 'enseignants.create', 'enseignants.update', 'enseignants.delete', 'enseignants.export', 'enseignants.edit',
            'classes.view', 'classes.create', 'classes.update', 'classes.delete', 'classes.edit',
            'matieres.view', 'matieres.create', 'matieres.update', 'matieres.delete', 'matieres.edit',
            'notes.view', 'notes.create', 'notes.update', 'notes.delete', 'notes.export', 'notes.edit',
            'bulletins.view', 'bulletins.print', 'bulletins.export', 'bulletins.approve',
            'absences.view', 'absences.create', 'absences.update', 'absences.delete', 'absences.export', 'absences.approve', 'absences.edit',
            'comptabilite.view', 'comptabilite.create', 'comptabilite.update', 'comptabilite.export', 'comptabilite.print', 'comptabilite.edit',
            'emploi_du_temps.view', 'emploi_du_temps.create', 'emploi_du_temps.update', 'emploi_du_temps.delete', 'emploi_du_temps.print', 'emploi_du_temps.edit',
            'annonces.view', 'annonces.create', 'annonces.update', 'annonces.delete', 'annonces.edit',
            'notifications.view', 'notifications.view.own', 'notifications.create', 'notifications.delete', 'notifications.manage',
            'users.view', 'users.create', 'users.update', 'users.edit', 'users.update.own',
            'rapports.view', 'rapports.export', 'rapports.print',
            'profil.view.own', 'profil.update.own',
        ],
        'secretaire' => [
            'eleves.view', 'eleves.create', 'eleves.update', 'eleves.export', 'eleves.edit',
            'classes.view', 'matieres.view',
            'notes.view', 'bulletins.view',
            'absences.view', 'absences.create', 'absences.update', 'absences.export', 'absences.approve', 'absences.edit',
            'comptabilite.view', 'comptabilite.create', 'comptabilite.print',
            'emploi_du_temps.view', 'emploi_du_temps.print',
            'annonces.view', 'annonces.create', 'annonces.update', 'annonces.delete', 'annonces.edit',
            'notifications.view', 'notifications.view.own',
            'rapports.view', 'rapports.export', 'rapports.print',
            'profil.view.own', 'profil.update.own',
        ],
        'comptable' => [
            'eleves.view', 'classes.view', 'matieres.view', 'enseignants.view',
            'comptabilite.view', 'comptabilite.view.own', 'comptabilite.view_own', 'comptabilite.create', 'comptabilite.update', 'comptabilite.delete', 'comptabilite.export', 'comptabilite.print', 'comptabilite.edit',
            'annonces.view',
            'notifications.view', 'notifications.view.own',
            'rapports.view', 'rapports.export', 'rapports.print',
            'profil.view.own', 'profil.update.own',
        ],
        'enseignant' => [
            'eleves.view', 'classes.view', 'matieres.view',
            'notes.view', 'notes.create', 'notes.update', 'notes.edit',
            'bulletins.view', 'bulletins.print', 'bulletins.export',
            'absences.view', 'absences.create', 'absences.update', 'absences.approve', 'absences.edit',
            'emploi_du_temps.view', 'emploi_du_temps.view.own', 'emploi_du_temps.view_own', 'emploi_du_temps.print', 'emploi_du_temps.print.own',
            'annonces.view',
            'notifications.view', 'notifications.view.own',
            'profil.view.own', 'profil.update.own',
        ],
        'parent' => [
            'notes.view.own', 'notes.view_own',
            'bulletins.view.own', 'bulletins.print.own',
            'absences.view.own', 'absences.view_own', 'absences.approve.own', 'absences.justify',
            'comptabilite.view.own', 'comptabilite.view_own', 'comptabilite.print.own',
            'emploi_du_temps.view.own', 'emploi_du_temps.view_own', 'emploi_du_temps.print.own',
            'annonces.view',
            'notifications.view.own',
            'profil.view.own', 'profil.update.own',
        ],
        'eleve' => [
            'notes.view.own', 'notes.view_own',
            'bulletins.view.own', 'bulletins.print.own',
            'absences.view.own', 'absences.view_own',
            'emploi_du_temps.view.own', 'emploi_du_temps.view_own', 'emploi_du_temps.print.own',
            'annonces.view',
            'notifications.view.own',
            'profil.view.own', 'profil.update.own',
        ],
    ];
}
