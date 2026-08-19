<?php

/**
 * T035 — Modèle de génération par défaut des périodes scolaires : semestre
 * (La Persévérance)
 *
 * Le Complexe Scolaire Privé La Persévérance fonctionne en 2 semestres par
 * année scolaire, pas en 3 trimestres. La table `periodes_scolaires_config`
 * (T022) avait été préconfigurée avec 3 lignes 'trimestre' (calendrier
 * Niger générique). Cette migration remplace ce modèle par 2 lignes
 * 'semestre', conformément au fonctionnement réel de l'établissement.
 *
 * N'affecte que la table de configuration (modèle de génération), pas les
 * périodes déjà créées dans `periodes_scolaires` — celles-ci restent
 * inchangées, qu'elles soient 'trimestre' ou 'semestre'.
 *
 * Idempotente : DELETE + INSERT ... ON DUPLICATE KEY UPDATE.
 */

return [
    'id'         => 'T035',
    'name'       => 'Modèle par défaut des périodes — semestre (La Persévérance)',
    'reversible' => true,

    'run' => function (PDO $pdo): void {
        $pdo->exec("DELETE FROM `periodes_scolaires_config` WHERE `numero` = 3");

        $pdo->exec("
            INSERT INTO `periodes_scolaires_config`
                (`numero`, `nom_defaut`, `mois_debut`, `jour_debut`, `mois_fin`, `jour_fin`, `ordre`)
            VALUES
                (1, 'Premier semestre',  10, 1, 1, 31, 1),
                (2, 'Deuxième semestre',  2, 1, 6, 30, 2)
            ON DUPLICATE KEY UPDATE
                `nom_defaut` = VALUES(`nom_defaut`),
                `mois_debut` = VALUES(`mois_debut`),
                `jour_debut` = VALUES(`jour_debut`),
                `mois_fin`   = VALUES(`mois_fin`),
                `jour_fin`   = VALUES(`jour_fin`),
                `ordre`      = VALUES(`ordre`)
        ");
    },

    'rollback' => function (PDO $pdo): void {
        $pdo->exec("DELETE FROM `periodes_scolaires_config` WHERE `numero` IN (1, 2)");

        $pdo->exec("
            INSERT INTO `periodes_scolaires_config`
                (`numero`, `nom_defaut`, `mois_debut`, `jour_debut`, `mois_fin`, `jour_fin`, `ordre`)
            VALUES
                (1, 'Premier trimestre',   10, 1, 12, 31, 1),
                (2, 'Deuxième trimestre',   1, 1,  3, 31, 2),
                (3, 'Troisième trimestre',  4, 1,  6, 30, 3)
            ON DUPLICATE KEY UPDATE
                `nom_defaut` = VALUES(`nom_defaut`),
                `mois_debut` = VALUES(`mois_debut`),
                `jour_debut` = VALUES(`jour_debut`),
                `mois_fin`   = VALUES(`mois_fin`),
                `jour_fin`   = VALUES(`jour_fin`),
                `ordre`      = VALUES(`ordre`)
        ");
    },
];
