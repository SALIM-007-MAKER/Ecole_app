<?php

/**
 * T027 — Ciblage des annonces par classe et par utilisateur
 *
 * Étend `annonces.audience` avec deux nouvelles valeurs :
 *   - 'classe'       : élèves d'une classe précise + leurs parents
 *                      (colonne `classe_id`, FK vers `classes`)
 *   - 'utilisateurs' : liste explicite d'utilisateurs (colonne
 *                      `destinataires_ids`, JSON — tableau d'IDs `users`)
 *
 * Les deux colonnes sont NULL par défaut et ne sont renseignées que pour
 * l'audience correspondante — aucun impact sur les annonces existantes.
 *
 * Idempotente.
 */

return [
    'id'         => 'T027',
    'name'       => 'Annonces — ciblage par classe et par utilisateur',
    'reversible' => true,

    'run' => function (PDO $pdo): void {
        $col = $pdo->query("SHOW COLUMNS FROM `annonces` LIKE 'audience'")->fetch(PDO::FETCH_ASSOC);
        if ($col && !str_contains($col['Type'], "'classe'")) {
            $pdo->exec(
                "ALTER TABLE `annonces`
                 MODIFY COLUMN `audience` ENUM('tous','parents','eleves','enseignants','classe','utilisateurs')
                 NOT NULL DEFAULT 'tous'"
            );
        }

        $hasClasseId = $pdo->query("SHOW COLUMNS FROM `annonces` LIKE 'classe_id'")->fetch(PDO::FETCH_ASSOC);
        if (!$hasClasseId) {
            $pdo->exec("ALTER TABLE `annonces` ADD COLUMN `classe_id` INT UNSIGNED NULL AFTER `audience`");
            $pdo->exec(
                "ALTER TABLE `annonces` ADD CONSTRAINT `fk_annonce_classe`
                 FOREIGN KEY (`classe_id`) REFERENCES `classes`(`id`) ON DELETE SET NULL"
            );
            $pdo->exec("ALTER TABLE `annonces` ADD INDEX `idx_annonce_classe` (`classe_id`)");
        }

        $hasDestinataires = $pdo->query("SHOW COLUMNS FROM `annonces` LIKE 'destinataires_ids'")->fetch(PDO::FETCH_ASSOC);
        if (!$hasDestinataires) {
            $pdo->exec("ALTER TABLE `annonces` ADD COLUMN `destinataires_ids` JSON NULL AFTER `classe_id`");
        }
    },

    'rollback' => function (PDO $pdo): void {
        $hasDestinataires = $pdo->query("SHOW COLUMNS FROM `annonces` LIKE 'destinataires_ids'")->fetch(PDO::FETCH_ASSOC);
        if ($hasDestinataires) {
            $pdo->exec("ALTER TABLE `annonces` DROP COLUMN `destinataires_ids`");
        }

        $hasClasseId = $pdo->query("SHOW COLUMNS FROM `annonces` LIKE 'classe_id'")->fetch(PDO::FETCH_ASSOC);
        if ($hasClasseId) {
            $pdo->exec("ALTER TABLE `annonces` DROP FOREIGN KEY `fk_annonce_classe`");
            $pdo->exec("ALTER TABLE `annonces` DROP INDEX `idx_annonce_classe`");
            $pdo->exec("ALTER TABLE `annonces` DROP COLUMN `classe_id`");
        }

        // Ne réduit pas l'ENUM (nécessiterait de vérifier qu'aucune ligne n'utilise
        // encore 'classe'/'utilisateurs' — laissé volontairement large, sans risque).
    },
];
