<?php

/**
 * T061 — Collations uniformes et index redondants (audit BD)
 *
 * Constat : 6 tables sont en utf8mb4_0900_ai_ci alors que la base, la connexion (SET NAMES ... utf8mb4_unicode_ci) et le reste du schéma
 * sont en utf8mb4_unicode_ci : jointures/comparaisons avec les autres tables = « Illegal mix of collations ». Elles sont converties.
 * `migrations_log` (même défaut) l'est aussi. Trois index doublonnent un index UNIQUE de même colonne : ils sont supprimés.
 * Aucune donnée n'est modifiée (conversion de collation uniquement). Idempotente.
 * NON APPLIQUÉE À LA BASE RÉELLE tant qu'une autorisation distincte n'a pas été donnée (sauvegarde préalable obligatoire).
 * Retour arrière : remet utf8mb4_0900_ai_ci sur ces tables et recrée les trois index.
 */

return [
    'id'         => 'T061',
    'name'       => 'Collations utf8mb4_unicode_ci (7 tables) + 3 index redondants',
    'reversible' => true,

    'run' => function (PDO $pdo): void {
        $tables = ['controles', 'finance_sequences', 'moyennes_generales', 'moyennes_matieres', 'notes', 'periodes', 'migrations_log'];
        foreach ($tables as $t) {
            $c = $pdo->query("SELECT table_collation FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = '$t'")->fetchColumn();
            if ($c && $c !== 'utf8mb4_unicode_ci') {
                $pdo->exec("ALTER TABLE `$t` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            }
        }
        foreach ([['password_resets', 'idx_pr_token'], ['platform_operators', 'idx_operator_user'], ['rbac_roles', 'idx_role_slug']] as [$t, $i]) {
            $has = $pdo->query("SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = '$t' AND index_name = '$i'")->fetchColumn();
            if ($has) {
                $pdo->exec("ALTER TABLE `$t` DROP INDEX `$i`");
            }
        }
    },

    'rollback' => function (PDO $pdo): void {
        foreach ([['password_resets', 'idx_pr_token', 'token'], ['platform_operators', 'idx_operator_user', 'user_id'], ['rbac_roles', 'idx_role_slug', 'slug']] as [$t, $i, $col]) {
            $has = $pdo->query("SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = '$t' AND index_name = '$i'")->fetchColumn();
            if (!$has) {
                $pdo->exec("ALTER TABLE `$t` ADD INDEX `$i` (`$col`)");
            }
        }
        foreach (['controles', 'finance_sequences', 'moyennes_generales', 'moyennes_matieres', 'notes', 'periodes', 'migrations_log'] as $t) {
            $pdo->exec("ALTER TABLE `$t` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci");
        }
    },
];
