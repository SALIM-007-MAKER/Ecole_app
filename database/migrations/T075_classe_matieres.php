<?php

/**
 * T075 — Programme pédagogique d'une classe : `classe_matieres`
 *
 * Une ligne = une matière au programme d'une classe, avec SON coefficient et SON volume horaire (qui peuvent différer de ceux de la
 * matière : Mathématiques coefficient 5 en Terminale C et 2 en Terminale A). La classe porte déjà son établissement, son niveau, sa série
 * et son année scolaire : le programme est donc rattaché à tout cela sans colonne supplémentaire.
 *
 * - INDÉPENDANT des enseignements : une matière peut figurer au programme sans aucun enseignant affecté, et inversement ;
 * - `matieres.coefficient` / `matieres.volume_horaire` restent les valeurs PAR DÉFAUT : sans ligne de programme, rien ne change ;
 * - `matiere_id` en ON DELETE RESTRICT : une matière d'un programme ne peut pas être supprimée en silence ;
 *   `classe_id` en ON DELETE CASCADE : supprimer une classe supprime son programme (sans effet sur les matières) ;
 * - `obligatoire` (1 = comptée dans la moyenne générale et le classement ; 0 = facultative : suivie, notée et affichée, mais SANS poids dans
 *   la moyenne générale ni le classement) se règle matière par matière et classe par classe ;
 * - bornes contrôlées aussi en base (coefficient 0 à 20, 0 = suivie mais non comptée ; volume 0 à 40 h/semaine).
 *
 * Additive et idempotente : aucune table existante n'est modifiée, aucune donnée n'est insérée (les classes existantes n'ont pas de programme
 * tant qu'on n'en crée pas un, explicitement). Retour arrière : supprime la table (les programmes saisis sont perdus ; classes, matières,
 * enseignements, notes et bulletins ne sont pas touchés).
 */

return [
    'id'         => 'T075',
    'name'       => 'Programme pédagogique d\'une classe — table classe_matieres (coefficient et volume horaire par classe)',
    'reversible' => true,

    'run' => function (PDO $pdo): void {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `classe_matieres` (
                `id`               INT UNSIGNED      NOT NULL AUTO_INCREMENT,
                `etablissement_id` INT UNSIGNED      NOT NULL,
                `classe_id`        INT UNSIGNED      NOT NULL,
                `matiere_id`       INT UNSIGNED      NOT NULL,
                `coefficient`      DECIMAL(3,1)      NOT NULL,
                `volume_horaire`   TINYINT UNSIGNED  NOT NULL,
                `obligatoire`      TINYINT UNSIGNED  NOT NULL DEFAULT 1,
                `ordre`            SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                `created_at`       TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at`       TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_cm_classe_matiere` (`classe_id`, `matiere_id`),
                KEY `idx_cm_etab_matiere` (`etablissement_id`, `matiere_id`),
                CONSTRAINT `fk_cm_etab`    FOREIGN KEY (`etablissement_id`) REFERENCES `etablissements` (`id`) ON DELETE RESTRICT,
                CONSTRAINT `fk_cm_classe`  FOREIGN KEY (`classe_id`)        REFERENCES `classes` (`id`)        ON DELETE CASCADE,
                CONSTRAINT `fk_cm_matiere` FOREIGN KEY (`matiere_id`)       REFERENCES `matieres` (`id`)       ON DELETE RESTRICT,
                CONSTRAINT `chk_cm_coefficient` CHECK (`coefficient` >= 0 AND `coefficient` <= 20),
                CONSTRAINT `chk_cm_volume`      CHECK (`volume_horaire` <= 40),
                CONSTRAINT `chk_cm_obligatoire` CHECK (`obligatoire` IN (0, 1))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
              COMMENT='Programme d''une classe : coefficient et volume horaire propres à la classe'
        ");
    },

    'rollback' => function (PDO $pdo): void {
        $pdo->exec('DROP TABLE IF EXISTS `classe_matieres`');
    },
];
