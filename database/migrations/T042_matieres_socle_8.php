<?php

/**
 * T042 — Garantir le socle de 8 matières attendu par établissement
 *
 * Le programme demandé pour chaque élève repose sur 8 matières fixes :
 * Français, Histoire-Géographie, Anglais, Mathématiques, S.V.T,
 * Physique-Chimie, EPS, Conduite.
 *
 * D'après le dernier snapshot de base (2026-08-21), 7 de ces 8 matières
 * existent déjà par établissement ; seule « Éducation Physique » y est
 * enregistrée sans son accent (« Education Physique »). Cette migration :
 *   1. corrige ce nom pour chaque établissement où il existe tel quel ;
 *   2. crée toute matière du socle qui serait totalement absente.
 *
 * Note (2026-09-01) : la matière est désormais nommée « EPS » (voir T043
 * pour le renommage des bases où T042 a déjà tourné avec l'ancien nom).
 *
 * Elle n'affecte AUCUNE matière à AUCUNE classe (cela nécessite un
 * enseignant réel par matière×classe via `enseignements`, décision hors
 * périmètre de cette migration — voir /scolarite/matieres pour l'affectation
 * manuelle une fois le socle garanti).
 *
 * Idempotente : rejouable sans effet de bord (vérifie l'existence avant
 * chaque UPDATE/INSERT, par établissement).
 */

return [
    'id'         => 'T042',
    'name'       => 'Matières — socle des 8 matières (Français, Histoire-Géo, Anglais, Maths, S.V.T, Physique-Chimie, EPS, Conduite)',
    'reversible' => true,

    'run' => function (PDO $pdo): void {
        // Socle attendu : nom => [coefficient, categorie, volume_horaire]
        // Valeurs par défaut reprises de celles déjà en place quand la
        // matière existe ailleurs dans la base ; sinon valeur raisonnable.
        $socle = [
            'Français'             => [3.0, 'litteraire',   4],
            'Histoire-Géographie'  => [2.0, 'litteraire',   2],
            'Anglais'              => [2.0, 'litteraire',   3],
            'Mathématiques'        => [5.0, 'scientifique', 5],
            'S.V.T'                => [3.0, 'scientifique', 2],
            'Physique-Chimie'      => [4.0, 'scientifique', 2],
            'EPS'                  => [1.0, 'autre',        2],
            'Conduite'             => [1.0, 'autre',        2],
        ];

        $etablissementIds = $pdo->query('SELECT id FROM `etablissements`')->fetchAll(PDO::FETCH_COLUMN);

        $renameOldAccent = $pdo->prepare(
            "UPDATE `matieres` SET `nom` = 'EPS'
             WHERE `etablissement_id` = ? AND `nom` = 'Education Physique'"
        );
        $exists = $pdo->prepare(
            'SELECT id FROM `matieres` WHERE `etablissement_id` = ? AND `nom` = ? LIMIT 1'
        );
        $insert = $pdo->prepare(
            'INSERT INTO `matieres` (`etablissement_id`, `nom`, `coefficient`, `categorie`, `volume_horaire`)
             VALUES (?, ?, ?, ?, ?)'
        );

        foreach ($etablissementIds as $etablissementId) {
            // 1. Corriger l'accent manquant s'il existe sous l'ancienne forme.
            $renameOldAccent->execute([$etablissementId]);

            // 2. Créer toute matière du socle totalement absente.
            foreach ($socle as $nom => [$coefficient, $categorie, $volumeHoraire]) {
                $exists->execute([$etablissementId, $nom]);
                if (!$exists->fetchColumn()) {
                    $insert->execute([$etablissementId, $nom, $coefficient, $categorie, $volumeHoraire]);
                }
            }
        }
    },

    'rollback' => function (PDO $pdo): void {
        // Ne supprime aucune matière (risque de casser des notes/enseignements
        // déjà rattachés) : revient uniquement sur la correction de nom.
        $pdo->exec(
            "UPDATE `matieres` SET `nom` = 'Education Physique'
             WHERE `nom` = 'EPS'"
        );
    },
];
