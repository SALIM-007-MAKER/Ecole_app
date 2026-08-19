<?php

/**
 * T030 — Réinscription / passage de classe
 *
 * Constat de l'audit fonctionnel (Phase 2) : l'application n'avait aucun
 * moyen de faire passer en masse les élèves d'une année scolaire à l'autre —
 * seule l'affectation manuelle, un élève à la fois, existait. C'est la
 * lacune la plus lourde identifiée pour un usage réel en établissement.
 *
 * `reinscriptions` journalise, pour chaque élève traité par une campagne de
 * réinscription, la classe de départ, la classe d'arrivée (NULL si sortant
 * / fin de cycle) et qui a exécuté l'opération — traçabilité indispensable
 * pour une opération en masse difficilement réversible.
 *
 * Bonus (même audit, finding connexe) : contrainte d'unicité sur
 * (etablissement_id, nom, niveau, annee_scolaire) — rien n'empêchait
 * aujourd'hui de créer deux classes identiques la même année.
 *
 * Idempotente.
 */

return [
    'id'         => 'T030',
    'name'       => 'Réinscription — journal des passages de classe',
    'reversible' => true,

    'run' => function (PDO $pdo): void {
        $hasTable = $pdo->query("SHOW TABLES LIKE 'reinscriptions'")->fetch(PDO::FETCH_ASSOC);
        if (!$hasTable) {
            $pdo->exec(
                "CREATE TABLE `reinscriptions` (
                    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `etablissement_id` INT UNSIGNED NOT NULL,
                    `eleve_id` INT UNSIGNED NOT NULL,
                    `classe_source_id` INT UNSIGNED NOT NULL,
                    `classe_destination_id` INT UNSIGNED NULL COMMENT 'NULL = sortant / fin de cycle',
                    `annee_source` VARCHAR(9) NOT NULL,
                    `annee_destination` VARCHAR(9) NOT NULL,
                    `sortant` TINYINT(1) NOT NULL DEFAULT 0,
                    `executee_par` INT UNSIGNED NULL,
                    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    KEY `idx_reinsc_eleve` (`eleve_id`),
                    KEY `idx_reinsc_etab_annees` (`etablissement_id`, `annee_source`, `annee_destination`),
                    CONSTRAINT `fk_reinsc_eleve` FOREIGN KEY (`eleve_id`) REFERENCES `eleves`(`id`) ON DELETE CASCADE,
                    CONSTRAINT `fk_reinsc_classe_src` FOREIGN KEY (`classe_source_id`) REFERENCES `classes`(`id`) ON DELETE RESTRICT,
                    CONSTRAINT `fk_reinsc_classe_dst` FOREIGN KEY (`classe_destination_id`) REFERENCES `classes`(`id`) ON DELETE RESTRICT,
                    CONSTRAINT `fk_reinsc_user` FOREIGN KEY (`executee_par`) REFERENCES `users`(`id`) ON DELETE SET NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }

        // Bonus : empêcher les classes en double (nom+niveau+année) — aucun
        // doublon existant aujourd'hui (vérifié), donc sûr à ajouter.
        $idx = $pdo->query(
            "SELECT 1 FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'classes' AND INDEX_NAME = 'uq_classe_nom_niveau_annee'"
        )->fetchColumn();
        if (!$idx) {
            $dupes = $pdo->query(
                "SELECT COUNT(*) FROM (
                    SELECT etablissement_id, nom, niveau, annee_scolaire, COUNT(*) c
                    FROM classes GROUP BY etablissement_id, nom, niveau, annee_scolaire HAVING c > 1
                 ) t"
            )->fetchColumn();
            if ((int)$dupes === 0) {
                $pdo->exec(
                    "ALTER TABLE `classes` ADD UNIQUE KEY `uq_classe_nom_niveau_annee`
                     (`etablissement_id`, `nom`, `niveau`, `annee_scolaire`)"
                );
            }
            // Si des doublons existent déjà, on ne bloque pas la migration —
            // la contrainte sera ajoutable manuellement une fois nettoyés.
        }
    },

    'rollback' => function (PDO $pdo): void {
        $idx = $pdo->query(
            "SELECT 1 FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'classes' AND INDEX_NAME = 'uq_classe_nom_niveau_annee'"
        )->fetchColumn();
        if ($idx) {
            $pdo->exec("ALTER TABLE `classes` DROP INDEX `uq_classe_nom_niveau_annee`");
        }
        $hasTable = $pdo->query("SHOW TABLES LIKE 'reinscriptions'")->fetch(PDO::FETCH_ASSOC);
        if ($hasTable) {
            $pdo->exec("DROP TABLE `reinscriptions`");
        }
    },
];
