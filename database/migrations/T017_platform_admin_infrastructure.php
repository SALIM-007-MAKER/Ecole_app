<?php

/**
 * T017 — Phase 14.10 (SaaS Super-Admin)
 *
 * 1. Ajoute 'archived' à etablissements.statut (ENUM actuel :
 *    trial/active/suspended/cancelled — Phase 14.2, T001). Distinct de
 *    'suspended' (temporaire, réversible par l'opérateur) : 'archived' est
 *    l'état "établissement retiré, données conservées mais inactif",
 *    demandé explicitement par le blueprint 14.10 ("archivage" distinct de
 *    "suspension"). Purement additif sur l'ENUM — aucune ligne existante
 *    affectée (toutes sont 'active' aujourd'hui).
 *
 * 2. Crée `platform_analytics_snapshots` — MULTI_TENANT_V2_BLUEPRINT.md §14.2.
 *
 * 3. Bootstrap : relie l'utilisateur admin@ecole.dz (s'il existe) à
 *    `platform_operators` en tant que 'super_admin' — sans ce bootstrap,
 *    AUCUN compte ne pourrait jamais se connecter au portail Super-Admin
 *    (problème de l'œuf et de la poule : platform_operators existe depuis
 *    la Phase 14.2 mais n'a jamais été peuplée). Idempotent
 *    (INSERT IGNORE sur la contrainte UNIQUE user_id).
 *
 * Idempotent, purement additif.
 */

return [
    'id'         => 'T017',
    'name'       => 'etablissements.statut+archived, platform_analytics_snapshots, bootstrap super_admin',
    'reversible' => true,

    'run' => function (PDO $pdo): void {
        $enumRow = $pdo->query(
            "SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'etablissements' AND COLUMN_NAME = 'statut'"
        )->fetch(PDO::FETCH_ASSOC);

        if ($enumRow !== false && !str_contains($enumRow['COLUMN_TYPE'], "'archived'")) {
            $pdo->exec(
                "ALTER TABLE `etablissements`
                 MODIFY COLUMN `statut` ENUM('trial','active','suspended','cancelled','archived') NOT NULL DEFAULT 'trial'"
            );
        }

        $tables = $pdo->query("SHOW TABLES LIKE 'platform_analytics_snapshots'")->fetchAll(PDO::FETCH_COLUMN);
        if (empty($tables)) {
            $pdo->exec(
                "CREATE TABLE `platform_analytics_snapshots` (
                    `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `snapshot_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `period`           VARCHAR(20)     NOT NULL DEFAULT 'manual',
                    `total_tenants`    INT UNSIGNED    NOT NULL DEFAULT 0,
                    `active_tenants`   INT UNSIGNED    NOT NULL DEFAULT 0,
                    `total_users`      INT UNSIGNED    NOT NULL DEFAULT 0,
                    `total_eleves`     INT UNSIGNED    NOT NULL DEFAULT 0,
                    `total_api_calls`  BIGINT UNSIGNED NULL,
                    `storage_used_gb`  DECIMAL(12,4)   NOT NULL DEFAULT 0,
                    `top_tenants`      JSON            NULL,
                    `errors_total`     INT UNSIGNED    NOT NULL DEFAULT 0,
                    PRIMARY KEY (`id`),
                    KEY `idx_snapshot_at` (`snapshot_at`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }

        $admin = $pdo->query("SELECT id FROM users WHERE email = 'admin@ecole.dz' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        if ($admin !== false) {
            $stmt = $pdo->prepare(
                "INSERT IGNORE INTO platform_operators (user_id, niveau, actif) VALUES (?, 'super_admin', 1)"
            );
            $stmt->execute([$admin['id']]);
        }
    },

    'rollback' => function (PDO $pdo): void {
        $pdo->exec("DROP TABLE IF EXISTS `platform_analytics_snapshots`");
        // L'ENUM 'archived' et le bootstrap platform_operators ne sont pas retirés
        // (retirer une valeur d'ENUM potentiellement utilisée par des lignes
        // existantes est risqué ; la ligne platform_operators bootstrap est
        // inoffensive à laisser en place).
    },
];
