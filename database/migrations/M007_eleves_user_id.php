<?php

/**
 * M007 — Ajout `eleves.user_id` (lien compte utilisateur élève)
 *
 * DB-005 (DATABASE_V2.md) : aucune FK ne relie un élève à son compte
 * utilisateur (`users.role='eleve'`) — jusqu'ici résolu par correspondance
 * d'email (`EleveModel::findByEmail()`), fragile car `eleves.email` n'est
 * ni UNIQUE ni synchronisé avec `users.email` (les deux sont modifiables
 * indépendamment via EleveController::update() et UtilisateurController::
 * update() sans cascade).
 *
 * Ajoute une FK 1:1 indexée et UNIQUE (même convention que
 * `professeurs.user_id`), puis rattache automatiquement les comptes déjà
 * en place — uniquement quand la correspondance par email est sans
 * ambiguïté (un seul élève et un seul compte partagent cet email), pour
 * ne jamais lier silencieusement la mauvaise paire.
 */

return [
    'id'         => 'M007',
    'name'       => "Ajout eleves.user_id (lien compte utilisateur élève)",
    'reversible' => true,

    'run' => function (PDO $pdo): void {
        $exists = (int)$pdo->query(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'eleves' AND COLUMN_NAME = 'user_id'"
        )->fetchColumn();

        if ($exists === 0) {
            $pdo->exec("
                ALTER TABLE `eleves`
                    ADD COLUMN `user_id` INT UNSIGNED NULL
                        COMMENT 'Compte utilisateur de l\'élève (users.role=eleve)'
                        AFTER `parent_id`,
                    ADD UNIQUE INDEX `idx_eleve_user` (`user_id`),
                    ADD CONSTRAINT `fk_eleve_user`
                        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
            ");
        }

        // Rattachement best-effort, non ambigu uniquement. Les comptages
        // sont matérialisés via des sous-requêtes dérivées (MySQL interdit
        // de lire directement la table cible d'un UPDATE dans son propre
        // FROM — erreur 1093 sinon).
        $pdo->exec("
            UPDATE `eleves` el
            JOIN `users` u ON u.email = el.email AND u.role = 'eleve' AND u.actif = 1
            JOIN (
                SELECT `email` FROM `eleves`
                WHERE `email` IS NOT NULL AND `email` <> ''
                GROUP BY `email` HAVING COUNT(*) = 1
            ) el_uniq ON el_uniq.email = el.email
            JOIN (
                SELECT `email` FROM `users`
                WHERE `role` = 'eleve'
                GROUP BY `email` HAVING COUNT(*) = 1
            ) u_uniq ON u_uniq.email = el.email
            SET el.user_id = u.id
            WHERE el.user_id IS NULL
        ");
    },

    'rollback' => function (PDO $pdo): void {
        $pdo->exec("ALTER TABLE `eleves` DROP FOREIGN KEY IF EXISTS `fk_eleve_user`");
        $pdo->exec("ALTER TABLE `eleves` DROP INDEX IF EXISTS `idx_eleve_user`");
        $pdo->exec("ALTER TABLE `eleves` DROP COLUMN IF EXISTS `user_id`");
    },
];
