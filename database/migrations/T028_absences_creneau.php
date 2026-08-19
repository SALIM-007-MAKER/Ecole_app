<?php

/**
 * T028 — Précision horaire des absences (créneau de l'emploi du temps)
 *
 * Ajoute `absences.creneau_id` (FK vers `creneaux`, NULL par défaut) pour
 * permettre de saisir une absence sur un créneau précis de l'emploi du
 * temps (ex: "Cours 3 — 10h20-11h20") plutôt que seulement matin/après-midi.
 *
 * `session` reste renseignée pour compatibilité avec l'existant (filtres,
 * statistiques) et est dérivée automatiquement du créneau choisi côté
 * contrôleur. `creneau_id` est purement informatif (NULL = pas de créneau
 * précis, ex: "Journée complète"), il n'entre pas dans la contrainte
 * d'unicité `uq_absence` (eleve_id, date_absence, session) qui reste
 * inchangée.
 *
 * Idempotente.
 */

return [
    'id'         => 'T028',
    'name'       => 'Absences — créneau précis de l\'emploi du temps',
    'reversible' => true,

    'run' => function (PDO $pdo): void {
        $hasCreneauId = $pdo->query("SHOW COLUMNS FROM `absences` LIKE 'creneau_id'")->fetch(PDO::FETCH_ASSOC);
        if (!$hasCreneauId) {
            $pdo->exec("ALTER TABLE `absences` ADD COLUMN `creneau_id` INT NULL AFTER `session`");
            $pdo->exec(
                "ALTER TABLE `absences` ADD CONSTRAINT `fk_abs_creneau`
                 FOREIGN KEY (`creneau_id`) REFERENCES `creneaux`(`id`) ON DELETE SET NULL"
            );
            $pdo->exec("ALTER TABLE `absences` ADD INDEX `idx_abs_creneau` (`creneau_id`)");
        }
    },

    'rollback' => function (PDO $pdo): void {
        $hasCreneauId = $pdo->query("SHOW COLUMNS FROM `absences` LIKE 'creneau_id'")->fetch(PDO::FETCH_ASSOC);
        if ($hasCreneauId) {
            $pdo->exec("ALTER TABLE `absences` DROP FOREIGN KEY `fk_abs_creneau`");
            $pdo->exec("ALTER TABLE `absences` DROP INDEX `idx_abs_creneau`");
            $pdo->exec("ALTER TABLE `absences` DROP COLUMN `creneau_id`");
        }
    },
];
