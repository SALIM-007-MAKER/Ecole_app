<?php

/**
 * T010 — Phase 14.6 (Utilisateurs Multi-Établissements)
 *
 * Ajoute `dernier_etablissement_id` sur `users` : mémorise le dernier
 * établissement activé par l'utilisateur (blueprint §7.2, "mémorisation du
 * dernier établissement utilisé") — utilisé pour pré-sélectionner un choix
 * par défaut sur l'écran de sélection d'établissement, jamais pour
 * contourner le choix explicite quand l'utilisateur appartient à
 * plusieurs établissements.
 *
 * Colonne nullable, FK ON DELETE SET NULL (un établissement supprimé ne
 * doit jamais bloquer la suppression ni casser un compte utilisateur).
 * Purement additive.
 */

return [
    'id'         => 'T010',
    'name'       => 'users.dernier_etablissement_id (mémorisation du dernier établissement)',
    'reversible' => true,

    'run' => function (PDO $pdo): void {
        $columns = $pdo->query(
            "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users'"
        )->fetchAll(PDO::FETCH_COLUMN);

        if (!in_array('dernier_etablissement_id', $columns, true)) {
            $pdo->exec(
                "ALTER TABLE `users`
                 ADD COLUMN `dernier_etablissement_id` INT UNSIGNED NULL AFTER `etablissement_id`"
            );
        }

        $fkExists = $pdo->query(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users'
               AND CONSTRAINT_NAME = 'fk_users_dernier_etab'"
        )->fetchColumn();

        if (!$fkExists) {
            $pdo->exec(
                "ALTER TABLE `users`
                 ADD CONSTRAINT `fk_users_dernier_etab` FOREIGN KEY (`dernier_etablissement_id`)
                     REFERENCES `etablissements`(`id`) ON DELETE SET NULL"
            );
        }

        // Initialise à l'établissement actuel pour les utilisateurs existants
        // (cohérence : leur "dernier établissement utilisé" est celui où ils
        // sont déjà, comportement identique à avant cette phase).
        $pdo->exec(
            "UPDATE `users` SET `dernier_etablissement_id` = `etablissement_id`
             WHERE `dernier_etablissement_id` IS NULL"
        );
    },

    'rollback' => function (PDO $pdo): void {
        try {
            $pdo->exec("ALTER TABLE `users` DROP FOREIGN KEY `fk_users_dernier_etab`");
        } catch (\PDOException) {
        }
        try {
            $pdo->exec("ALTER TABLE `users` DROP COLUMN `dernier_etablissement_id`");
        } catch (\PDOException) {
        }
    },
];
