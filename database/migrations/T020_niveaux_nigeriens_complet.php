<?php

/**
 * T020 — Complète la conversion de nomenclature (Niger) amorcée par T019
 *
 * T019 ne convertissait que le cycle Lycée (variantes "AS"). Cette migration
 * couvre les deux cycles restants que son propre docblock annonçait sans les
 * traiter : Primaire (1AP-5AP) et Collège/Moyen (1AM-4AM), plus quelques
 * variantes textuelles observées dans les données de démonstration
 * (database/ecole_app.sql, database/classes_matieres_migration.sql).
 *
 * Toute valeur de `classes.niveau` non listée ici (donc non reconnue comme
 * un code algérien connu) est laissée strictement inchangée — cette
 * migration ne devine jamais une correspondance, elle ne fait que
 * compléter le mapping explicite déjà commencé par T019.
 *
 * Idempotente : ne convertit que les libellés source encore présents.
 */

return [
    'id'         => 'T020',
    'name'       => 'Nomenclature des classes — complément Primaire/Collège (Niger)',
    'reversible' => true,

    'run' => function (PDO $pdo): void {
        $map = [
            // Primaire : 1AP-5AP → CI, CP, CE1, CE2, CM1, CM2
            // (1AP correspond au CP nigérien — il n'y a pas d'équivalent
            // algérien direct au CI, qui précède le cycle primaire à 5 ans
            // algérien ; les établissements utilisant réellement un CI
            // devront le saisir explicitement, cette migration ne peut pas
            // le déduire d'un code à 5 niveaux).
            '1AP' => 'CP',
            '2AP' => 'CE1',
            '3AP' => 'CE2',
            '4AP' => 'CM1',
            '5AP' => 'CM2',

            // Collège / Moyen : 1AM-4AM → 6e, 5e, 4e, 3e
            '1AM' => '6e',
            '2AM' => '5e',
            '3AM' => '4e',
            '4AM' => '3e',

            // Variantes textuelles observées dans les données de démonstration
            '6ème' => '6e', '5ème' => '5e', '4ème' => '4e', '3ème' => '3e',
            '6EME' => '6e', '5EME' => '5e', '4EME' => '4e', '3EME' => '3e',
        ];

        $stmt = $pdo->prepare("UPDATE `classes` SET niveau = ? WHERE niveau = ?");
        foreach ($map as $ancien => $nouveau) {
            $stmt->execute([$nouveau, $ancien]);
        }
    },

    'rollback' => function (PDO $pdo): void {
        $map = [
            'CP' => '1AP', 'CE1' => '2AP', 'CE2' => '3AP', 'CM1' => '4AP', 'CM2' => '5AP',
            '6e' => '1AM', '5e' => '2AM', '4e' => '3AM', '3e' => '4AM',
        ];

        $stmt = $pdo->prepare("UPDATE `classes` SET niveau = ? WHERE niveau = ?");
        foreach ($map as $nouveau => $ancien) {
            $stmt->execute([$ancien, $nouveau]);
        }
    },
];
