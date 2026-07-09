<?php

/**
 * T011 — Phase 14.7 (Domaines Personnalisés)
 *
 * Ajoute `verification_token` sur `etablissement_domains` (créée vide en
 * Phase 14.2, T004) — nécessaire au processus de vérification DNS décrit
 * au blueprint §11.2 (challenge TXT `scolaris-verify=<token>`).
 *
 * Colonne nullable, purement additive.
 */

return [
    'id'         => 'T011',
    'name'       => 'etablissement_domains.verification_token (challenge DNS)',
    'reversible' => true,

    'run' => function (PDO $pdo): void {
        $columns = $pdo->query(
            "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'etablissement_domains'"
        )->fetchAll(PDO::FETCH_COLUMN);

        if (!in_array('verification_token', $columns, true)) {
            $pdo->exec(
                "ALTER TABLE `etablissement_domains`
                 ADD COLUMN `verification_token` VARCHAR(64) NULL AFTER `type`"
            );
        }
    },

    'rollback' => function (PDO $pdo): void {
        try {
            $pdo->exec("ALTER TABLE `etablissement_domains` DROP COLUMN `verification_token`");
        } catch (\PDOException) {
        }
    },
];
