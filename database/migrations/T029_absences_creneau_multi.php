<?php

/**
 * T029 — Absences par créneau : correctif T028 + unicité multi-créneaux
 *
 * 1) Correctif T028 : une première tentative de T028 avait échoué après avoir
 *    déjà exécuté son ADD COLUMN (creneau_id créée en INT UNSIGNED, incompatible
 *    avec `creneaux.id` qui est INT signé) ; la ré-exécution corrigée de T028
 *    a ensuite été court-circuitée par son garde-fou "colonne déjà existante"
 *    et n'a donc jamais posé la FK ni l'index. On corrige ici le type de la
 *    colonne et on (ré)ajoute la FK/l'index manquants, chaque étape étant
 *    vérifiée indépendamment (pas de garde-fou global).
 *
 * 2) Un élève peut chômer certains créneaux d'une session (ex: Cours 1 et 2)
 *    et venir aux autres (ex: Cours 3) : chaque créneau manqué doit pouvoir
 *    être sa propre ligne d'absence indépendante. L'ancienne contrainte
 *    `uq_absence` (eleve_id, date_absence, session) ne permettait qu'UNE
 *    ligne par élève/jour/session, quel que soit le créneau — impossible de
 *    distinguer "absent aux 2 premiers cours" de "absent toute la matinée".
 *
 *    On introduit une colonne générée `creneau_key` = IFNULL(creneau_id, 0)
 *    et on bascule l'unicité dessus : (eleve_id, date_absence, session,
 *    creneau_key). Les lignes "session entière" (creneau_id NULL, ex: saisie
 *    via le pointage journalier) retombent toutes sur creneau_key=0 et
 *    restent donc uniques entre elles (comportement inchangé pour le
 *    pointage) ; les lignes à créneau précis (T028) deviennent chacune
 *    unique par créneau, permettant plusieurs lignes indépendantes pour une
 *    même session.
 *
 * Idempotente.
 */

return [
    'id'         => 'T029',
    'name'       => 'Absences — correctif créneau + unicité par créneau (multi-créneaux/jour)',
    'reversible' => true,

    'run' => function (PDO $pdo): void {
        // 1) Correctif type de colonne (UNSIGNED → signé, comme creneaux.id)
        $col = $pdo->query("SHOW COLUMNS FROM `absences` LIKE 'creneau_id'")->fetch(PDO::FETCH_ASSOC);
        if ($col && str_contains($col['Type'], 'unsigned')) {
            $pdo->exec("ALTER TABLE `absences` MODIFY COLUMN `creneau_id` INT NULL");
        }

        // 1bis) Correctif FK manquante
        $fk = $pdo->query(
            "SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'absences'
               AND CONSTRAINT_NAME = 'fk_abs_creneau'"
        )->fetchColumn();
        if (!$fk) {
            $pdo->exec(
                "ALTER TABLE `absences` ADD CONSTRAINT `fk_abs_creneau`
                 FOREIGN KEY (`creneau_id`) REFERENCES `creneaux`(`id`) ON DELETE SET NULL"
            );
        }

        // 1ter) Correctif index manquant
        $idx = $pdo->query("SHOW INDEX FROM `absences` WHERE Key_name = 'idx_abs_creneau'")->fetch(PDO::FETCH_ASSOC);
        if (!$idx) {
            $pdo->exec("ALTER TABLE `absences` ADD INDEX `idx_abs_creneau` (`creneau_id`)");
        }

        // 2) Colonne générée pour l'unicité multi-créneaux.
        // VIRTUAL (pas STORED) : MySQL interdit une colonne générée STORED
        // dépendant d'une colonne portant une FK avec ON DELETE SET NULL
        // (erreur 1215) — une colonne VIRTUAL n'a pas cette restriction et
        // reste indexable normalement.
        $hasKey = $pdo->query("SHOW COLUMNS FROM `absences` LIKE 'creneau_key'")->fetch(PDO::FETCH_ASSOC);
        if (!$hasKey) {
            $pdo->exec(
                "ALTER TABLE `absences`
                 ADD COLUMN `creneau_key` INT AS (IFNULL(`creneau_id`, 0)) VIRTUAL AFTER `creneau_id`"
            );
        }

        // Bascule de la contrainte d'unicité. `eleve_id` (colonne de tête de
        // uq_absence) porte des FK (fk_abs_eleve...) qui exigent qu'un index
        // le couvrant existe à tout instant : on ajoute donc le nouvel index
        // (sous un nom temporaire) AVANT de supprimer l'ancien, jamais l'ordre
        // inverse (sinon erreur 1553 "needed in a foreign key constraint").
        $uqCols = $pdo->query(
            "SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS cols
             FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'absences' AND INDEX_NAME = 'uq_absence'"
        )->fetchColumn();
        if ($uqCols !== 'eleve_id,date_absence,session,creneau_key') {
            $pdo->exec(
                "ALTER TABLE `absences` ADD UNIQUE KEY `uq_absence_new`
                 (`eleve_id`, `date_absence`, `session`, `creneau_key`)"
            );
            if ($uqCols !== false && $uqCols !== null) {
                $pdo->exec("ALTER TABLE `absences` DROP INDEX `uq_absence`");
            }
            $pdo->exec("ALTER TABLE `absences` RENAME INDEX `uq_absence_new` TO `uq_absence`");
        }
    },

    'rollback' => function (PDO $pdo): void {
        $uqCols = $pdo->query(
            "SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS cols
             FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'absences' AND INDEX_NAME = 'uq_absence'"
        )->fetchColumn();
        if ($uqCols === 'eleve_id,date_absence,session,creneau_key') {
            $pdo->exec("ALTER TABLE `absences` ADD UNIQUE KEY `uq_absence_old` (`eleve_id`, `date_absence`, `session`)");
            $pdo->exec("ALTER TABLE `absences` DROP INDEX `uq_absence`");
            $pdo->exec("ALTER TABLE `absences` RENAME INDEX `uq_absence_old` TO `uq_absence`");
        }

        $hasKey = $pdo->query("SHOW COLUMNS FROM `absences` LIKE 'creneau_key'")->fetch(PDO::FETCH_ASSOC);
        if ($hasKey) {
            $pdo->exec("ALTER TABLE `absences` DROP COLUMN `creneau_key`");
        }
    },
];
