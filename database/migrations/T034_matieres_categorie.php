<?php

/**
 * T034 — Classification littéraire / scientifique / autre des matières
 *
 * Ajoute `matieres.categorie` (ENUM, défaut 'autre') pour permettre le
 * regroupement des moyennes du bulletin V1 par filière (voir
 * BulletinGenerator::moyennesParFiliere). Aucun impact sur les matières
 * existantes — elles tombent toutes dans 'autre' jusqu'à classification
 * manuelle par l'établissement.
 *
 * Idempotente.
 */

return [
    'id'         => 'T034',
    'name'       => 'Matières — colonne categorie (littéraire/scientifique/autre)',
    'reversible' => true,

    'run' => function (PDO $pdo): void {
        $col = $pdo->query("SHOW COLUMNS FROM `matieres` LIKE 'categorie'")->fetch(PDO::FETCH_ASSOC);
        if (!$col) {
            $pdo->exec(
                "ALTER TABLE `matieres`
                 ADD COLUMN `categorie` ENUM('litteraire','scientifique','autre')
                 NOT NULL DEFAULT 'autre' AFTER `coefficient`"
            );
        }
    },

    'rollback' => function (PDO $pdo): void {
        $col = $pdo->query("SHOW COLUMNS FROM `matieres` LIKE 'categorie'")->fetch(PDO::FETCH_ASSOC);
        if ($col) {
            $pdo->exec("ALTER TABLE `matieres` DROP COLUMN `categorie`");
        }
    },
];
