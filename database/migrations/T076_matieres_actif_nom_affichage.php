<?php

/**
 * T076 — Matières : état actif/inactif et nom d'affichage propre à l'établissement
 *
 * - `actif` (1 par défaut) : une matière désactivée n'est plus proposée pour de NOUVELLES affectations (enseignements, programmes de classe,
 *   évaluations). Ses affectations, notes, évaluations et bulletins existants sont conservés tels quels. Réactivable à tout moment.
 * - `nom_affichage` (NULL par défaut) : libellé montré aux utilisateurs et sur les documents à la place du nom de référence `nom`, qui reste
 *   la clé fonctionnelle (unicité, imports, recherches). NULL ou vide = on affiche `nom`. Chaque matière appartenant à un établissement,
 *   le nom d'affichage est propre à l'établissement : aucune autre école n'est concernée.
 *
 * Additive et idempotente : deux colonnes ajoutées, aucune donnée modifiée (toutes les matières existantes restent actives, sans nom
 * d'affichage). Retour arrière : supprime les deux colonnes (les désactivations et noms d'affichage saisis sont perdus ; rien d'autre).
 */

return [
    'id'         => 'T076',
    'name'       => 'Matières — état actif/inactif et nom d\'affichage par établissement',
    'reversible' => true,

    'run' => function (PDO $pdo): void {
        $existe = static function (string $colonne) use ($pdo): bool {
            $s = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'matieres' AND COLUMN_NAME = ?");
            $s->execute([$colonne]);
            return (int)$s->fetchColumn() > 0;
        };
        if (!$existe('actif')) {
            $pdo->exec("ALTER TABLE `matieres` ADD COLUMN `actif` TINYINT UNSIGNED NOT NULL DEFAULT 1 AFTER `est_conduite`");
        }
        if (!$existe('nom_affichage')) {
            $pdo->exec("ALTER TABLE `matieres` ADD COLUMN `nom_affichage` VARCHAR(100) NULL DEFAULT NULL AFTER `nom`");
        }
    },

    'rollback' => function (PDO $pdo): void {
        foreach (['actif', 'nom_affichage'] as $colonne) {
            $s = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'matieres' AND COLUMN_NAME = ?");
            $s->execute([$colonne]);
            if ((int)$s->fetchColumn() > 0) {
                $pdo->exec("ALTER TABLE `matieres` DROP COLUMN `$colonne`");
            }
        }
    },
];
