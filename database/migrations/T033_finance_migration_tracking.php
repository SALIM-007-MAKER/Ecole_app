<?php

/**
 * T033 — Traçabilité de la migration Finance V1 → V2
 *
 * Étape 3 (Finance) de l'unification V1/V2. Avant de répliquer les
 * paiements/frais V1 réels vers finance_factures/finance_paiements, on a
 * besoin d'un marqueur structurel — pas une convention de texte — pour que
 * les enregistrements migrés restent en permanence distinguables des
 * factures/paiements créés en exploitation normale via l'UI V2 :
 *
 *  - `origine`         : ENUM, jamais ambigu, indexé pour filtrage rapide
 *                         dans les rapports.
 *  - `migration_source`: référence lisible vers la ligne V1 d'origine
 *                         (ex. 'frais_eleve:8', 'paiement:6').
 *  - `migration_meta`  : snapshot JSON complet de la ligne V1 d'origine —
 *                         garantit qu'aucune information du système legacy
 *                         n'est perdue même si le mapping vers les colonnes
 *                         V2 est partiel, et permet un audit/rollback
 *                         complet sans dépendre de V1 qui reste par
 *                         ailleurs intact et non modifié.
 *
 * Idempotente.
 */

return [
    'id'         => 'T033',
    'name'       => 'Finance — colonnes de traçabilité migration V1→V2',
    'reversible' => true,

    'run' => function (PDO $pdo): void {
        foreach (['finance_factures', 'finance_paiements'] as $table) {
            $cols = $pdo->query("SHOW COLUMNS FROM `{$table}`")->fetchAll(PDO::FETCH_COLUMN);

            if (!in_array('origine', $cols, true)) {
                $pdo->exec(
                    "ALTER TABLE `{$table}`
                     ADD COLUMN `origine` ENUM('operationnelle','migration_v1') NOT NULL DEFAULT 'operationnelle' AFTER `statut`,
                     ADD COLUMN `migration_source` VARCHAR(100) NULL AFTER `origine`,
                     ADD COLUMN `migration_meta` JSON NULL AFTER `migration_source`,
                     ADD INDEX `idx_{$table}_origine` (`origine`)"
                );
            }
        }
    },

    'rollback' => function (PDO $pdo): void {
        foreach (['finance_factures', 'finance_paiements'] as $table) {
            $cols = $pdo->query("SHOW COLUMNS FROM `{$table}`")->fetchAll(PDO::FETCH_COLUMN);
            if (in_array('origine', $cols, true)) {
                $pdo->exec("ALTER TABLE `{$table}` DROP INDEX `idx_{$table}_origine`, DROP COLUMN `origine`, DROP COLUMN `migration_source`, DROP COLUMN `migration_meta`");
            }
        }
    },
];
