<?php

/**
 * T036 — Classification initiale des matières (littéraire/scientifique/autre)
 *
 * La colonne `matieres.categorie` (T034) vaut `'autre'` par défaut pour toute
 * matière — aucune matière existante n'a jamais été reclassifiée, ce qui
 * laisse les cases "Moyenne littéraire"/"Moyenne scientifique" du bulletin
 * vides (cf. audit métier du bulletin). Cette migration fixe un classement de
 * départ raisonnable pour les matières usuelles ; il reste ajustable à tout
 * moment via l'écran /matieres/{id}/edit (déjà fonctionnel).
 *
 * Idempotente : simples UPDATE par nom, rejouables sans effet de bord.
 */

return [
    'id'         => 'T036',
    'name'       => 'Matières — classification initiale littéraire/scientifique',
    'reversible' => true,

    'run' => function (PDO $pdo): void {
        $scientifiques = ['Mathématiques', 'Physique', 'Physique-Chimie', 'Sciences Naturelles', 'S.V.T', 'SVT'];
        $litteraires   = ['Français', 'Anglais', 'Arabe', 'Histoire-Géographie', 'Philosophie'];

        $stmt = $pdo->prepare("UPDATE `matieres` SET `categorie` = 'scientifique' WHERE `nom` = ?");
        foreach ($scientifiques as $nom) {
            $stmt->execute([$nom]);
        }

        $stmt = $pdo->prepare("UPDATE `matieres` SET `categorie` = 'litteraire' WHERE `nom` = ?");
        foreach ($litteraires as $nom) {
            $stmt->execute([$nom]);
        }
    },

    'rollback' => function (PDO $pdo): void {
        $noms = [
            'Mathématiques', 'Physique', 'Physique-Chimie', 'Sciences Naturelles', 'S.V.T', 'SVT',
            'Français', 'Anglais', 'Arabe', 'Histoire-Géographie', 'Philosophie',
        ];
        $stmt = $pdo->prepare("UPDATE `matieres` SET `categorie` = 'autre' WHERE `nom` = ?");
        foreach ($noms as $nom) {
            $stmt->execute([$nom]);
        }
    },
];
