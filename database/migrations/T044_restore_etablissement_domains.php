<?php

/**
 * T044 — Restauration de la table `etablissement_domains`
 *
 * T031 a supprimé cette table en la jugeant « morte » (« 0 référence dans
 * tout app/ »). Le contrôle n'avait pas couvert `core/` : la table est
 * pourtant lue/écrite par
 *   - Core\Tenant\TenantRepository::findByDomain()   (résolution par domaine
 *     personnalisé, via TenantResolver / BrandingService / TenantMiddleware),
 *   - Core\Tenant\DomainVerificationService          (utilisé par
 *     PlatformDomainController, routes /platform/etablissements/{id}/domaines).
 * Depuis T031, ces écrans lèvent « Base table or view not found: 1146 » et la
 * résolution par domaine échouait silencieusement (repli sur l'établissement
 * par défaut dans BrandingService).
 *
 * T031 ne supprimait la table que si elle était vide : aucune donnée n'a été
 * perdue, il n'y a donc rien à restaurer hormis la structure. Cette migration
 * recrée le schéma exact de T004 + T011 (clé unique sur `domain`, clé étrangère
 * ON DELETE CASCADE vers `etablissements`, colonne `verification_token`).
 *
 * Idempotente : sans effet si la table existe déjà (la colonne
 * `verification_token` est ajoutée si elle manque). T031 n'est pas modifiée
 * (migration déjà appliquée) ; l'ordre T031 → T044 reste cohérent sur une
 * installation neuve.
 */

return [
    'id'         => 'T044',
    'name'       => 'Restauration table etablissement_domains (régression T031)',
    'reversible' => true,

    'run' => function (PDO $pdo): void {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `etablissement_domains` (
                `id`                 INT UNSIGNED    NOT NULL AUTO_INCREMENT,
                `etablissement_id`   INT UNSIGNED    NOT NULL,
                `domain`             VARCHAR(255)    NOT NULL,
                `type`               ENUM('custom','subdomain') NOT NULL DEFAULT 'subdomain',
                `verification_token` VARCHAR(64)     NULL,
                `verified`           TINYINT(1)      NOT NULL DEFAULT 0,
                `ssl_status`         ENUM('pending','issued','error') NOT NULL DEFAULT 'pending',
                `ssl_expires_at`     DATE            NULL,
                `verified_at`        DATETIME        NULL,
                `created_at`         DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,

                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_etabdomain_domain` (`domain`),
                KEY `idx_etabdomain_etab` (`etablissement_id`),
                CONSTRAINT `fk_etabdomain_etab` FOREIGN KEY (`etablissement_id`)
                    REFERENCES `etablissements`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $columns = $pdo->query(
            "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'etablissement_domains'"
        )->fetchAll(PDO::FETCH_COLUMN);

        if (!in_array('verification_token', $columns, true)) {
            $pdo->exec(
                "ALTER TABLE `etablissement_domains`
                 ADD COLUMN `verification_token` VARCHAR(64) NULL AFTER `type`"
            );
        }
    },

    'rollback' => function (PDO $pdo): void {
        // Ne supprime la table que si elle est vide : jamais de perte de domaines enregistrés.
        $exists = $pdo->query("SHOW TABLES LIKE 'etablissement_domains'")->fetch(PDO::FETCH_ASSOC);
        if ($exists && (int)$pdo->query("SELECT COUNT(*) FROM `etablissement_domains`")->fetchColumn() === 0) {
            $pdo->exec("DROP TABLE `etablissement_domains`");
        }
    },
];
