<?php

/**
 * T074 — Série d'une classe de lycée (A, C, D… ou série personnalisée)
 *
 * Ajoute `classes.serie` VARCHAR(10) NULL : donnée INDÉPENDANTE du nom de la classe (Terminale « D1 » = niveau Terminale, série D,
 * nom D1). Aucune valeur par défaut, aucun CHECK : la liste des séries n'est pas figée en base (elle se configure par établissement
 * et par niveau dans `etab_settings`, clé `academique.series_par_niveau`), donc une série personnalisée ne demande jamais de migration.
 * Un index non unique `(etablissement_id, annee_scolaire, niveau, serie)` sert le filtre par série.
 *
 * Additive, non destructive et idempotente :
 *  - toutes les lignes existantes restent à NULL (aucune série déduite du nom) ;
 *  - l'unicité des classes `(établissement, nom, niveau, année)` n'est pas modifiée : plusieurs classes parallèles d'une même série
 *    (Terminale D1, D2…) sont possibles sans limite ;
 *  - relancée, la migration ne fait rien si la colonne et l'index existent déjà.
 *
 * Retour arrière : supprime l'index puis la colonne (les séries saisies sont alors perdues ; les classes restent intactes).
 */

return [
    'id'         => 'T074',
    'name'       => 'Série d\'une classe de lycée — colonne classes.serie (nullable, libre) et index de filtre',
    'reversible' => true,

    'run' => function (PDO $pdo): void {
        $existe = static function (string $sql, array $p) use ($pdo): bool {
            $s = $pdo->prepare($sql);
            $s->execute($p);
            return (int)$s->fetchColumn() > 0;
        };

        if (!$existe(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'classes' AND COLUMN_NAME = 'serie'",
            []
        )) {
            $pdo->exec("ALTER TABLE `classes` ADD COLUMN `serie` VARCHAR(10) NULL DEFAULT NULL AFTER `niveau`");
        }

        if (!$existe(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'classes' AND INDEX_NAME = 'idx_classes_serie'",
            []
        )) {
            $pdo->exec("ALTER TABLE `classes` ADD INDEX `idx_classes_serie` (`etablissement_id`, `annee_scolaire`, `niveau`, `serie`)");
        }
    },

    'rollback' => function (PDO $pdo): void {
        $a = $pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'classes' AND INDEX_NAME = 'idx_classes_serie'");
        if ((int)$a->fetchColumn() > 0) {
            $pdo->exec("ALTER TABLE `classes` DROP INDEX `idx_classes_serie`");
        }
        $b = $pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'classes' AND COLUMN_NAME = 'serie'");
        if ((int)$b->fetchColumn() > 0) {
            $pdo->exec("ALTER TABLE `classes` DROP COLUMN `serie`");
        }
    },
];
