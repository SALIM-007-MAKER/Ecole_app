<?php

/**
 * T006 — Phase 14.3 (Migration des tables métier V1) — Étapes B1 à B9
 *
 * Ajoute etablissement_id aux 9 tables V1 identifiées non-conformes par le
 * blueprint (§3.1) : users, classes, eleves, professeurs, matieres,
 * enseignements, notes, absences, periodes.
 *
 * Pour chaque table (fenêtre de maintenance courte, séquence zero-downtime
 * du blueprint §24.1 Phase B) :
 *   1. ADD COLUMN etablissement_id INT UNSIGNED NULL
 *   2. UPDATE ... SET etablissement_id = 1 (établissement démo seedé en T005,
 *      id=1 — c'est l'établissement existant auquel rattacher les données V1)
 *   3. MODIFY COLUMN etablissement_id INT UNSIGNED NOT NULL
 *   4. ADD INDEX idx_etab (etablissement_id) — composite avec la colonne la
 *      plus filtrée de chaque table quand pertinent
 *   5. ADD FOREIGN KEY → etablissements(id)
 *
 * Idempotent : vérifie l'existence de la colonne avant chaque étape,
 * rejouable sans effet de bord.
 *
 * Voir MULTI_TENANT_V2_BLUEPRINT.md §3.1, §24.1 Phase B.
 */

return [
    'id'         => 'T006',
    'name'       => 'etablissement_id sur tables V1 (users, classes, eleves, professeurs, matieres, enseignements, notes, absences, periodes)',
    'reversible' => true,

    'run' => function (PDO $pdo): void {

        // Établissement de rattachement des données V1 existantes (seedé en T005).
        $defaultEtabId = (int)$pdo->query(
            "SELECT id FROM etablissements WHERE slug = 'edunova-demo' LIMIT 1"
        )->fetchColumn();

        if (!$defaultEtabId) {
            throw new \RuntimeException(
                "T006 : établissement 'edunova-demo' introuvable — exécuter T005 d'abord."
            );
        }

        // table => index composite additionnel (colonne la plus filtrée), ou null
        $tables = [
            'users'         => 'role',
            'classes'       => 'annee_scolaire',
            'eleves'        => 'classe_id',
            'professeurs'   => 'actif',
            'matieres'      => null,
            'enseignements' => 'classe_id',
            'notes'         => 'eleve_id',
            'absences'      => 'classe_id',
            'periodes'      => 'annee_scolaire',
        ];

        foreach ($tables as $table => $compositeCol) {
            $columns = $pdo->query(
                "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = " . $pdo->quote($table)
            )->fetchAll(PDO::FETCH_COLUMN);

            // 1. ADD COLUMN (nullable d'abord)
            if (!in_array('etablissement_id', $columns, true)) {
                $pdo->exec(
                    "ALTER TABLE `{$table}` ADD COLUMN `etablissement_id` INT UNSIGNED NULL AFTER `id`"
                );
            }

            // 2. Backfill
            $pdo->exec(
                "UPDATE `{$table}` SET `etablissement_id` = {$defaultEtabId} WHERE `etablissement_id` IS NULL"
            );

            // 3. NOT NULL
            $colInfo = $pdo->query(
                "SELECT IS_NULLABLE FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = " . $pdo->quote($table) . "
                   AND COLUMN_NAME = 'etablissement_id'"
            )->fetchColumn();

            if ($colInfo === 'YES') {
                $pdo->exec(
                    "ALTER TABLE `{$table}` MODIFY COLUMN `etablissement_id` INT UNSIGNED NOT NULL"
                );
            }

            // 4. Index
            $indexExists = $pdo->query(
                "SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = " . $pdo->quote($table) . "
                   AND INDEX_NAME = 'idx_etab'"
            )->fetchColumn();

            if (!$indexExists) {
                if ($compositeCol !== null) {
                    $pdo->exec(
                        "ALTER TABLE `{$table}` ADD INDEX `idx_etab` (`etablissement_id`, `{$compositeCol}`)"
                    );
                } else {
                    $pdo->exec(
                        "ALTER TABLE `{$table}` ADD INDEX `idx_etab` (`etablissement_id`)"
                    );
                }
            }

            // 5. Foreign key
            $fkExists = $pdo->query(
                "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = " . $pdo->quote($table) . "
                   AND CONSTRAINT_NAME = 'fk_" . $table . "_etab'"
            )->fetchColumn();

            if (!$fkExists) {
                $pdo->exec(
                    "ALTER TABLE `{$table}`
                     ADD CONSTRAINT `fk_{$table}_etab` FOREIGN KEY (`etablissement_id`)
                         REFERENCES `etablissements`(`id`) ON DELETE RESTRICT"
                );
            }
        }
    },

    'rollback' => function (PDO $pdo): void {
        $tables = ['users', 'classes', 'eleves', 'professeurs', 'matieres', 'enseignements', 'notes', 'absences', 'periodes'];
        foreach (array_reverse($tables) as $table) {
            try {
                $pdo->exec("ALTER TABLE `{$table}` DROP FOREIGN KEY `fk_{$table}_etab`");
            } catch (\PDOException) {
                // absente — ignorer
            }
            try {
                $pdo->exec("ALTER TABLE `{$table}` DROP INDEX `idx_etab`");
            } catch (\PDOException) {
                // absente — ignorer
            }
            try {
                $pdo->exec("ALTER TABLE `{$table}` DROP COLUMN `etablissement_id`");
            } catch (\PDOException) {
                // absente — ignorer
            }
        }
    },
];
