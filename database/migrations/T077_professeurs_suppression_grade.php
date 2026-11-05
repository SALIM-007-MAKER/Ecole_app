<?php

/**
 * T077 — Enseignants : suppression de la colonne `grade`
 *
 * Le grade n'est plus saisi ni affiché (formulaires, liste, fiche, tableau de bord). La colonne et les valeurs qu'elle contenait sont supprimées.
 *
 * DESTRUCTIVE : les valeurs de `professeurs.grade` sont perdues. Faire une sauvegarde complète (database/backup.php) AVANT de l'appliquer.
 * Idempotente : sans effet si la colonne n'existe plus.
 * Retour arrière : recrée la colonne VIDE (VARCHAR(100) NULL) ; les anciennes valeurs ne reviennent que par restauration de la sauvegarde.
 */

return [
    'id'         => 'T077',
    'name'       => 'Enseignants — suppression de la colonne grade',
    'reversible' => false,

    'run' => function (PDO $pdo): void {
        $s = $pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'professeurs' AND COLUMN_NAME = 'grade'");
        if ((int)$s->fetchColumn() > 0) {
            $pdo->exec('ALTER TABLE `professeurs` DROP COLUMN `grade`');
        }
    },

    'rollback' => function (PDO $pdo): void {
        $s = $pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'professeurs' AND COLUMN_NAME = 'grade'");
        if ((int)$s->fetchColumn() === 0) {
            $pdo->exec('ALTER TABLE `professeurs` ADD COLUMN `grade` VARCHAR(100) NULL DEFAULT NULL AFTER `specialite`');
        }
    },
];