<?php

/**
 * T065 — Audit par établissement : `audit_logs` rattaché à un établissement, acteur figé, résultat, protection, permission `audit.view`
 *
 * 1. `audit_logs` : + `etablissement_id` (NULL = événement sans établissement démontré), `actor_nom` et `actor_role` (instantanés de l'acteur au
 *    moment de l'action : survivent à la suppression du compte), `resultat` (success|failure), index (établissement, date) et (établissement,
 *    module, action), clé étrangère vers `etablissements` (RESTRICT : un établissement ayant un journal ne peut pas être supprimé physiquement).
 *    Les clés étrangères et colonnes existantes (dont fk_al_user ON DELETE SET NULL) sont conservées.
 * 2. Rétro-remplissage de `etablissement_id` des lignes historiques UNIQUEMENT quand l'appartenance de l'acteur à un seul établissement est
 *    démontrée : compte rattaché à l'établissement X, AUCUNE appartenance (user_etablissements, user_roles_etab) à un autre établissement, même
 *    ancienne, et dernier établissement mémorisé absent ou égal à X. Tous les autres événements (acteur inconnu, système, multi-établissements)
 *    gardent etablissement_id = NULL : ils ne sont visibles dans aucun établissement. Aucune autre colonne historique n'est modifiée.
 * 3. Déclencheurs BEFORE UPDATE / BEFORE DELETE : refusent toute modification ou suppression ligne à ligne. LIMITES RÉELLES (non absolues) :
 *    - TRUNCATE, DROP, ALTER et la suppression de la base ne passent pas par les déclencheurs ;
 *    - une action de clé étrangère en cascade (fk_al_user ON DELETE SET NULL, si un compte est supprimé physiquement) n'active PAS les déclencheurs
 *      MySQL : l'acteur peut passer à NULL — d'où les instantanés actor_nom / actor_role ;
 *    - un compte MySQL disposant de TRIGGER ou SUPER peut supprimer les déclencheurs.
 *    En production, n'accorder à l'utilisateur MySQL de l'application que SELECT et INSERT sur cette table, TABLE PAR TABLE (un REVOKE de table ne
 *    retire pas un droit accordé au niveau de la base) : recette vérifiée dans docs/deploiement/AUDIT_ETABLISSEMENT.md §7.
 * 4. Permission `audit.view` (etab_permissions) attribuée aux seuls rôles système « admin » et « directeur » (modèle de T049).
 *
 * Idempotente. NON APPLIQUÉE À LA BASE RÉELLE sans sauvegarde vérifiée. Retour arrière : retire UNIQUEMENT les déclencheurs ; colonnes, index,
 * données et permission sont conservés (aucune donnée ajoutée n'est supprimée).
 */

return [
    'id'         => 'T065',
    'name'       => 'Audit par établissement (audit_logs.etablissement_id, acteur figé, déclencheurs) + permission audit.view',
    'reversible' => true,

    'run' => function (PDO $pdo): void {
        $colExiste = static function (string $colonne) use ($pdo): bool {
            $s = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'audit_logs' AND COLUMN_NAME = ?");
            $s->execute([$colonne]);
            return (int)$s->fetchColumn() > 0;
        };
        $indexExiste = static function (string $index) use ($pdo): bool {
            $s = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'audit_logs' AND INDEX_NAME = ?");
            $s->execute([$index]);
            return (int)$s->fetchColumn() > 0;
        };

        // Les déclencheurs d'une exécution précédente empêcheraient le rétro-remplissage : retirés ici, recréés en fin de migration.
        $pdo->exec('DROP TRIGGER IF EXISTS `trg_audit_logs_no_update`');
        $pdo->exec('DROP TRIGGER IF EXISTS `trg_audit_logs_no_delete`');

        // 1. Colonnes et index ------------------------------------------------------------------------------------------------------
        $ajouts = [
            'etablissement_id' => "ADD COLUMN `etablissement_id` INT UNSIGNED NULL COMMENT 'Établissement du contexte tenant au moment de l\\'action ; NULL = non démontré' AFTER `id`",
            'actor_nom'        => "ADD COLUMN `actor_nom` VARCHAR(150) NULL COMMENT 'Instantané du nom de l\\'acteur' AFTER `user_id`",
            'actor_role'       => "ADD COLUMN `actor_role` VARCHAR(30) NULL COMMENT 'Instantané du rôle de l\\'acteur au moment de l\\'action' AFTER `actor_nom`",
            'resultat'         => "ADD COLUMN `resultat` ENUM('success','failure') NOT NULL DEFAULT 'success' AFTER `user_agent`",
        ];
        foreach ($ajouts as $colonne => $ddl) {
            if (!$colExiste($colonne)) {
                $pdo->exec("ALTER TABLE `audit_logs` {$ddl}");
            }
        }
        if (!$indexExiste('idx_al_etab_date')) {
            $pdo->exec('ALTER TABLE `audit_logs` ADD KEY `idx_al_etab_date` (`etablissement_id`, `created_at`)');
        }
        if (!$indexExiste('idx_al_etab_module')) {
            $pdo->exec('ALTER TABLE `audit_logs` ADD KEY `idx_al_etab_module` (`etablissement_id`, `module`, `action`)');
        }
        $fk = $pdo->query(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'audit_logs'
                AND COLUMN_NAME = 'etablissement_id' AND REFERENCED_TABLE_NAME = 'etablissements'"
        )->fetchColumn();
        if ((int)$fk === 0) {
            $pdo->exec('ALTER TABLE `audit_logs` ADD CONSTRAINT `fk_al_etablissement` FOREIGN KEY (`etablissement_id`) REFERENCES `etablissements` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE');
        }

        // 2. Rétro-remplissage prudent ----------------------------------------------------------------------------------------------
        $pdo->exec(
            "UPDATE audit_logs al
               JOIN users u ON u.id = al.user_id
                SET al.etablissement_id = u.etablissement_id
              WHERE al.etablissement_id IS NULL
                AND u.etablissement_id IS NOT NULL
                AND (u.dernier_etablissement_id IS NULL OR u.dernier_etablissement_id = u.etablissement_id)
                AND NOT EXISTS (SELECT 1 FROM user_etablissements ue WHERE ue.user_id = u.id AND ue.etablissement_id <> u.etablissement_id)
                AND NOT EXISTS (SELECT 1 FROM user_roles_etab ur WHERE ur.user_id = u.id AND ur.etablissement_id <> u.etablissement_id)"
        );

        // 3. Déclencheurs ------------------------------------------------------------------------------------------------------------
        $pdo->exec(
            "CREATE TRIGGER `trg_audit_logs_no_update` BEFORE UPDATE ON `audit_logs` FOR EACH ROW
             SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'audit_logs est en ajout seul : modification interdite'"
        );
        $pdo->exec(
            "CREATE TRIGGER `trg_audit_logs_no_delete` BEFORE DELETE ON `audit_logs` FOR EACH ROW
             SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'audit_logs est en ajout seul : suppression interdite'"
        );

        // 4. Permission audit.view (admin, directeur) ---------------------------------------------------------------------------
        $pdo->exec(
            "INSERT IGNORE INTO etab_permissions (code, libelle, module, action)
             VALUES ('audit.view', 'Consulter le journal d\\'audit de l\\'établissement', 'audit', 'view')"
        );
        $pdo->exec(
            "INSERT IGNORE INTO etab_role_permissions (role_id, permission_id)
             SELECT r.id, p.id
               FROM etab_roles r
               JOIN etab_permissions p ON p.code = 'audit.view'
              WHERE r.etablissement_id IS NULL AND r.is_system = 1 AND r.code IN ('admin', 'directeur')"
        );
    },

    'rollback' => function (PDO $pdo): void {
        // Retrait des seuls déclencheurs : colonnes, index, clé étrangère, permission et toutes les données restent en place.
        $pdo->exec('DROP TRIGGER IF EXISTS `trg_audit_logs_no_update`');
        $pdo->exec('DROP TRIGGER IF EXISTS `trg_audit_logs_no_delete`');
    },
];
