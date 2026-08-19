<?php

/**
 * T021 — Système de périodes scolaires officiel du Niger (trimestriel)
 *
 * Le Niger organise l'année scolaire en 3 trimestres. La colonne
 * `periodes.type` acceptait jusqu'ici 'trimestre' ou 'semestre' (héritage
 * d'une conception multi-pays). Aucune ligne en base n'utilise 'semestre'
 * (vérifié : les 3 périodes actuelles sont toutes 'trimestre'), donc ce
 * resserrement de l'ENUM est sans perte de données.
 *
 * Le support "semestre" reste documenté (voir NIGER_SCHOOL_PERIOD_
 * STANDARDIZATION_REPORT.md) comme point d'extension technique futur au
 * niveau du module V2 Académique (`periodes_scolaires`), mais n'est plus
 * une valeur active du système V1 utilisé en production.
 *
 * Idempotente : ne modifie l'ENUM que s'il contient encore 'semestre'.
 */

return [
    'id'         => 'T021',
    'name'       => 'Périodes scolaires — ENUM trimestre uniquement (Niger)',
    'reversible' => true,

    'run' => function (PDO $pdo): void {
        $col = $pdo->query("SHOW COLUMNS FROM `periodes` LIKE 'type'")->fetch(PDO::FETCH_ASSOC);
        if ($col && str_contains($col['Type'], "'semestre'")) {
            $pdo->exec("ALTER TABLE `periodes` MODIFY COLUMN `type` ENUM('trimestre') NOT NULL DEFAULT 'trimestre'");
        }
    },

    'rollback' => function (PDO $pdo): void {
        $col = $pdo->query("SHOW COLUMNS FROM `periodes` LIKE 'type'")->fetch(PDO::FETCH_ASSOC);
        if ($col && !str_contains($col['Type'], "'semestre'")) {
            $pdo->exec("ALTER TABLE `periodes` MODIFY COLUMN `type` ENUM('trimestre','semestre') NOT NULL DEFAULT 'trimestre'");
        }
    },
];
