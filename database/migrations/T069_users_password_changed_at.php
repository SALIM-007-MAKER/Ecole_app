<?php

/**
 * T069 — users.password_changed_at : invalidation des sessions ouvertes après un changement ou une réinitialisation de mot de passe
 *
 * L'application n'a pas de registre de sessions (sessions fichier). Chaque session mémorise la date du mot de passe au moment de la connexion
 * (`pwd_at`) ; le rafraîchissement périodique des droits (60 s) la compare à cette colonne : une session dont la date est antérieure est
 * invalidée. NULL = mot de passe jamais changé depuis T069 : aucune session n'est invalidée.
 *
 *  - Colonne ADDITIVE, nullable, sans défaut : aucune ligne n'est modifiée ; aucun index.
 *  - Avant T069, le code fonctionne sans cette vérification (colonne détectée).
 *
 * Idempotente. Retour arrière : supprime la colonne (elle ne contient que des horodatages d'invalidation de session : aucune donnée métier ;
 * les sessions ne sont alors plus invalidées par changement de mot de passe).
 */

return [
    'id'         => 'T069',
    'name'       => 'users.password_changed_at — invalidation des sessions après changement ou réinitialisation du mot de passe',
    'reversible' => true,

    'run' => function (PDO $pdo): void {
        $existe = (int)$pdo->query(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'password_changed_at'"
        )->fetchColumn();
        if ($existe === 0) {
            $pdo->exec("ALTER TABLE `users` ADD COLUMN `password_changed_at` DATETIME NULL DEFAULT NULL AFTER `updated_at`");
        }
    },

    'rollback' => function (PDO $pdo): void {
        $existe = (int)$pdo->query(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'password_changed_at'"
        )->fetchColumn();
        if ($existe > 0) {
            $pdo->exec('ALTER TABLE `users` DROP COLUMN `password_changed_at`');
        }
    },
];
