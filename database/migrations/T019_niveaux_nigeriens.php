<?php

/**
 * T019 — Nomenclature des classes du système éducatif nigérien
 *
 * Remplace la nomenclature algérienne (1AP-5AP / 1AM-4AM / 1AS-3AS) par la
 * nomenclature officielle du Niger (App\Models\ClasseModel::NIVEAUX) :
 *   Préscolaire : PS, MS, GS
 *   Primaire    : CI, CP, CE1, CE2, CM1, CM2
 *   Collège     : 6e, 5e, 4e, 3e
 *   Lycée       : Seconde, Première, Terminale
 *
 * `classes.niveau` est un simple VARCHAR(30) sans contrainte ENUM en base —
 * cette migration convertit uniquement les LIGNES EXISTANTES dont le niveau
 * correspond à un des libellés observés dans cet environnement ("1ère AS",
 * "2ème AS", "3ème AS" — variantes textuelles du cycle lycée algérien à 3
 * ans, mappées vers leur équivalent nigérien direct). Toute autre valeur de
 * niveau déjà présente (non reconnue) est laissée inchangée plutôt que
 * supprimée ou devinée.
 *
 * Idempotente : ne convertit que les libellés source encore présents.
 */

return [
    'id'         => 'T019',
    'name'       => 'Nomenclature des classes — système éducatif nigérien',
    'reversible' => true,

    'run' => function (PDO $pdo): void {
        $map = [
            '1ère AS' => 'Seconde',
            '2ème AS' => 'Première',
            '3ème AS' => 'Terminale',
            // Variantes courtes déjà utilisées ailleurs dans le code avant cette phase
            '1AS'     => 'Seconde',
            '2AS'     => 'Première',
            '3AS'     => 'Terminale',
        ];

        $stmt = $pdo->prepare("UPDATE `classes` SET niveau = ? WHERE niveau = ?");
        foreach ($map as $ancien => $nouveau) {
            $stmt->execute([$nouveau, $ancien]);
        }
    },

    'rollback' => function (PDO $pdo): void {
        $map = [
            'Seconde'   => '1ère AS',
            'Première'  => '2ème AS',
            'Terminale' => '3ème AS',
        ];

        $stmt = $pdo->prepare("UPDATE `classes` SET niveau = ? WHERE niveau = ?");
        foreach ($map as $nouveau => $ancien) {
            $stmt->execute([$ancien, $nouveau]);
        }
    },
];
