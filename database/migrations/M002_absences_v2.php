<?php

/**
 * M002 — Migration sécurisée de la table absences vers le schéma V2
 *
 * Problème : ecole_app.sql crée absences(justifiee, nb_heures) = schéma V1 simplifié.
 *            absences_migration.sql tente CREATE TABLE IF NOT EXISTS absences
 *            (classe_id, session, type, duree_retard, statut_justif, ...) = schéma V2
 *            → silencieusement ignoré si V1 existe.
 *
 * Stratégie :
 *   1. Détecter le schéma actuel (V1 = absence de 'session', V2 = présence de 'session').
 *   2. Si V2 → vérifier que toutes les colonnes requises sont présentes, les ajouter si manquantes.
 *   3. Si V1 :
 *      a. Renommer absences → absences_v1_backup.
 *      b. Créer V2.
 *      c. Tenter une migration partielle des données V1 vers V2 (colonnes communes).
 *   4. Si absente → créer V2.
 *
 * Idempotent.
 */

return [
    'id'         => 'M002',
    'name'       => 'Migration sécurisée absences → schéma V2 (session, type, statut_justif)',
    'reversible' => false,

    'run' => function (PDO $pdo): void {

        $db = $pdo->query("SELECT DATABASE()")->fetchColumn();

        // ── Vérifier si la table absences existe ─────────────────────────────
        $exists = (bool)$pdo->query(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
             WHERE TABLE_SCHEMA = '{$db}' AND TABLE_NAME = 'absences'"
        )->fetchColumn();

        $isV1 = false;

        if ($exists) {
            $cols = $pdo->query(
                "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = '{$db}' AND TABLE_NAME = 'absences'"
            )->fetchAll(PDO::FETCH_COLUMN);

            if (in_array('session', $cols, true)) {
                // Schéma V2 présent — compléter si colonnes manquantes
                $requiredV2 = ['classe_id', 'session', 'type', 'duree_retard', 'motif', 'signale_par', 'statut_justif', 'updated_at'];
                $missing    = array_diff($requiredV2, $cols);

                foreach ($missing as $col) {
                    $def = match ($col) {
                        'classe_id'    => 'INT UNSIGNED NULL COMMENT "FK classes.id"',
                        'session'      => "ENUM('matin','apres_midi','journee') NOT NULL DEFAULT 'journee'",
                        'type'         => "ENUM('absence','retard') NOT NULL DEFAULT 'absence'",
                        'duree_retard' => 'TINYINT UNSIGNED NULL',
                        'motif'        => 'VARCHAR(255) NULL',
                        'signale_par'  => 'INT UNSIGNED NULL',
                        'statut_justif'=> "ENUM('non_justifiee','en_attente','justifiee','refusee') NOT NULL DEFAULT 'non_justifiee'",
                        'updated_at'   => 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
                        default        => 'TEXT NULL',
                    };
                    $pdo->exec("ALTER TABLE `absences` ADD COLUMN `{$col}` {$def}");
                }
                return;
            }

            $isV1 = true;
        }

        if ($isV1) {
            // ── Sauvegarder V1 ────────────────────────────────────────────────
            $pdo->exec("DROP TABLE IF EXISTS `absences_v1_backup`");
            $pdo->exec("RENAME TABLE `absences` TO `absences_v1_backup`");
        }

        // ── Créer la table V2 ────────────────────────────────────────────────
        $pdo->exec("
            CREATE TABLE `absences` (
                `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `eleve_id`      INT UNSIGNED NOT NULL,
                `classe_id`     INT UNSIGNED NOT NULL,
                `date_absence`  DATE         NOT NULL,
                `session`       ENUM('matin','apres_midi','journee') NOT NULL DEFAULT 'journee',
                `type`          ENUM('absence','retard')              NOT NULL DEFAULT 'absence',
                `duree_retard`  TINYINT UNSIGNED NULL COMMENT 'Durée en minutes si type=retard',
                `motif`         VARCHAR(255)  NULL,
                `signale_par`   INT UNSIGNED  NULL COMMENT 'users.id auteur du pointage',
                `statut_justif` ENUM('non_justifiee','en_attente','justifiee','refusee')
                                NOT NULL DEFAULT 'non_justifiee',
                `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_absence` (`eleve_id`, `date_absence`, `session`),
                INDEX `idx_abs_date`    (`date_absence`),
                INDEX `idx_abs_classe`  (`classe_id`, `date_absence`),
                INDEX `idx_abs_statut`  (`statut_justif`),
                INDEX `idx_abs_eleve`   (`eleve_id`),
                CONSTRAINT `fk_abs_eleve`  FOREIGN KEY (`eleve_id`)   REFERENCES `eleves`(`id`)  ON DELETE CASCADE,
                CONSTRAINT `fk_abs_classe` FOREIGN KEY (`classe_id`)  REFERENCES `classes`(`id`) ON DELETE CASCADE,
                CONSTRAINT `fk_abs_user`   FOREIGN KEY (`signale_par`) REFERENCES `users`(`id`)  ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
              COMMENT='Absences et retards des élèves (schéma V2)'
        ");

        // ── Tenter une migration partielle des données V1 ────────────────────
        if ($isV1) {
            try {
                // Récupérer la classe_id via eleve_id → eleves.classe_id (approximation)
                $pdo->exec("
                    INSERT INTO `absences`
                        (eleve_id, classe_id, date_absence, session, type, motif, statut_justif, created_at)
                    SELECT
                        b.eleve_id,
                        COALESCE(e.classe_id, 0),
                        b.date_absence,
                        'journee',
                        'absence',
                        LEFT(b.motif, 255),
                        CASE WHEN b.justifiee = 1 THEN 'justifiee' ELSE 'non_justifiee' END,
                        b.created_at
                    FROM `absences_v1_backup` b
                    LEFT JOIN `eleves` e ON e.id = b.eleve_id
                    WHERE b.eleve_id IS NOT NULL
                    ON DUPLICATE KEY UPDATE motif = VALUES(motif)
                ");
            } catch (\Throwable $e) {
                // Migration partielle échouée — les données V1 restent dans absences_v1_backup
                error_log('[M002] Migration données V1 échouée (non bloquant) : ' . $e->getMessage());
            }
        }
    },
];
