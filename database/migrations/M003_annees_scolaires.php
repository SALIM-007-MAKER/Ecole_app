<?php

/**
 * M003 — Table annees_scolaires (nouveau référentiel)
 *
 * Remplace les champs `annee_scolaire VARCHAR(9)` éparpillés dans 6 tables
 * par une FK vers ce référentiel centralisé.
 *
 * Phase 1 (cette migration) : créer la table et la peupler depuis les valeurs existantes.
 * Phase 2 (migrations futures) : ajouter les FK dans les 6 tables concernées.
 */

return [
    'id'         => 'M003',
    'name'       => 'Créer le référentiel annees_scolaires',
    'reversible' => true,

    'run' => function (PDO $pdo): void {
        // ── Créer la table ───────────────────────────────────────────────────
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `annees_scolaires` (
                `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `libelle`     VARCHAR(9)   NOT NULL UNIQUE COMMENT 'Format : 2025-2026',
                `date_debut`  DATE         NULL,
                `date_fin`    DATE         NULL,
                `active`      TINYINT(1)   NOT NULL DEFAULT 0,
                `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                INDEX `idx_as_active` (`active`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
              COMMENT='Référentiel des années scolaires'
        ");

        // ── Peupler depuis les valeurs existantes dans classes ───────────────
        // Collecte toutes les valeurs annee_scolaire distinctes déjà utilisées
        $tables = ['classes', 'periodes', 'frais_eleves', 'paiements', 'emplois_du_temps', 'enseignements'];

        $existingAnnees = [];
        foreach ($tables as $table) {
            try {
                // Vérifier que la table et la colonne existent
                $check = $pdo->query(
                    "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
                     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$table}'
                     AND COLUMN_NAME = 'annee_scolaire'"
                )->fetchColumn();

                if ($check) {
                    $rows = $pdo->query("SELECT DISTINCT `annee_scolaire` FROM `{$table}` WHERE `annee_scolaire` IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN);
                    foreach ($rows as $annee) {
                        if (preg_match('/^\d{4}-\d{4}$/', $annee)) {
                            $existingAnnees[$annee] = true;
                        }
                    }
                }
            } catch (\Throwable) {
                // Table absente — ignorer
            }
        }

        // ── Insérer les années trouvées ──────────────────────────────────────
        $stmt = $pdo->prepare(
            "INSERT IGNORE INTO `annees_scolaires` (`libelle`) VALUES (?)"
        );
        foreach (array_keys($existingAnnees) as $annee) {
            $stmt->execute([$annee]);
        }

        // ── Ajouter l'année en cours si absente ──────────────────────────────
        $year    = (int)date('Y');
        $month   = (int)date('m');
        $current = $month >= 9 ? "{$year}-" . ($year + 1) : ($year - 1) . "-{$year}";
        $pdo->prepare("INSERT IGNORE INTO `annees_scolaires` (`libelle`, `active`) VALUES (?, 1)")
            ->execute([$current]);

        // ── Marquer l'année active (si une seule ligne ou correspondance exacte) ─
        $count = (int)$pdo->query("SELECT COUNT(*) FROM `annees_scolaires` WHERE `active` = 1")->fetchColumn();
        if ($count === 0) {
            $pdo->prepare("UPDATE `annees_scolaires` SET `active` = 1 WHERE `libelle` = ? LIMIT 1")
                ->execute([$current]);
        }
    },

    'rollback' => function (PDO $pdo): void {
        $pdo->exec("DROP TABLE IF EXISTS `annees_scolaires`");
    },
];
