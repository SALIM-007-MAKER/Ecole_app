<?php

/**
 * T043 — Renommer la matière « Éducation Physique » en « EPS »
 *
 * T042 a garanti le socle des 8 matières sous le nom « Éducation Physique ».
 * Cette migration renomme cette matière en « EPS » (nom court utilisé
 * partout : bulletins, saisie de notes, listes) — pour chaque établissement
 * où elle existe, quelle que soit la forme déjà en base (avec ou sans accent,
 * selon que T042 a ou non déjà corrigé l'accent sur cet environnement).
 *
 * N'affecte que le `nom` de la matière : coefficient, catégorie, notes déjà
 * saisies, affectations aux classes (`enseignements`) restent inchangés
 * (tout est référencé par `matiere_id`, jamais par nom).
 *
 * Idempotente et réversible : rejouable sans effet de bord.
 */

return [
    'id'         => 'T043',
    'name'       => 'Matières — renommage « Éducation Physique » → « EPS »',
    'reversible' => true,

    'run' => function (PDO $pdo): void {
        $pdo->exec(
            "UPDATE `matieres` SET `nom` = 'EPS'
             WHERE `nom` IN ('Éducation Physique', 'Education Physique')"
        );
    },

    'rollback' => function (PDO $pdo): void {
        $pdo->exec(
            "UPDATE `matieres` SET `nom` = 'Éducation Physique'
             WHERE `nom` = 'EPS'"
        );
    },
];
