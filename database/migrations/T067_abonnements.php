<?php

/**
 * T067 — Abonnements SaaS (étape 4 du Super-Admin SaaS)
 *
 * 1. `etablissements.suspension_origine` (VARCHAR(20) NULL) : distingue une suspension AUTOMATIQUE pour expiration d'abonnement ('subscription')
 *    d'une suspension décidée par un opérateur ('manual'). NULL = suspension antérieure à T067 ou jamais suspendu : traitée comme manuelle
 *    (jamais levée automatiquement). Colonne additive, AUCUNE ligne n'est modifiée.
 * 2. `platform_subscriptions` : une ligne par période d'abonnement (historique). Les conditions commerciales (prix, devise, essai, grâce) y sont
 *    FIGÉES à la souscription. Un seul abonnement « courant » (trial / active / grace / cancelled) par établissement, garanti par l'index unique
 *    sur la colonne générée `est_courant`.
 * 3. Reprise de l'existant : une ligne par établissement non supprimé ayant un `plan_id` et aucun abonnement. Dates et statuts de
 *    `etablissements` sont recopiés TELS QUELS (aucune écriture dans `etablissements`) :
 *      - établissement 'trial'     -> abonnement 'trial'  (échéance = trial_ends_at, éventuellement NULL = sans échéance) ;
 *      - établissement 'cancelled' -> abonnement 'cancelled' ;
 *      - autres (active, suspended, archived) -> abonnement 'active' (échéance = plan_expires_at, NULL = sans échéance).
 *    Cycle 'legacy' et prix NULL : le cycle et le prix payés avant T067 sont inconnus. Délai de grâce 0 : le comportement actuel est conservé.
 *
 * Idempotente. Retour arrière : supprime `platform_subscriptions` (l'historique d'abonnement est perdu : sauvegarde préalable) ; la colonne
 * `suspension_origine` est conservée.
 */

return [
    'id'         => 'T067',
    'name'       => 'Abonnements SaaS — platform_subscriptions (historique, conditions figées), etablissements.suspension_origine, reprise de l\'existant',
    'reversible' => true,

    'run' => function (PDO $pdo): void {
        $existe = static function (string $table, ?string $colonne = null) use ($pdo): bool {
            $sql = $colonne === null
                ? 'SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
                : 'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?';
            $s = $pdo->prepare($sql);
            $s->execute($colonne === null ? [$table] : [$table, $colonne]);
            return (int)$s->fetchColumn() > 0;
        };

        // 1. Origine de la suspension (additif, valeur NULL pour toutes les lignes existantes).
        if (!$existe('etablissements', 'suspension_origine')) {
            $pdo->exec("ALTER TABLE `etablissements` ADD COLUMN `suspension_origine` VARCHAR(20) NULL DEFAULT NULL AFTER `suspension_reason`");
        }

        // 2. Table des abonnements.
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `platform_subscriptions` (
                `id`                     INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `etablissement_id`       INT UNSIGNED NOT NULL,
                `plan_id`                INT UNSIGNED NOT NULL,
                `statut`                 ENUM('trial','active','grace','expired','cancelled','superseded') NOT NULL,
                `cycle`                  ENUM('trial','monthly','yearly','legacy') NOT NULL,
                `date_debut`             DATE NOT NULL,
                `date_fin`               DATE NULL,
                `trial_ends_at`          DATE NULL,
                `grace_ends_at`          DATE NULL,
                `prix_applique`          DECIMAL(10,2) NULL,
                `devise`                 CHAR(3) NOT NULL,
                `duree_essai_appliquee`  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                `jours_grace_appliques`  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                `auto_renew`             TINYINT(1) NOT NULL DEFAULT 0,
                `precedent_id`           INT UNSIGNED NULL,
                `cancelled_at`           DATETIME NULL,
                `cancel_reason`          VARCHAR(255) NULL,
                `created_by`             INT UNSIGNED NULL,
                `created_at`             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at`             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                `est_courant`            TINYINT GENERATED ALWAYS AS (CASE WHEN `statut` IN ('trial','active','grace','cancelled') THEN 1 ELSE NULL END) STORED,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_sub_courant` (`etablissement_id`, `est_courant`),
                KEY `idx_sub_etab` (`etablissement_id`, `id`),
                KEY `idx_sub_echeance` (`statut`, `date_fin`),
                CONSTRAINT `fk_sub_etab` FOREIGN KEY (`etablissement_id`) REFERENCES `etablissements`(`id`) ON DELETE RESTRICT,
                CONSTRAINT `fk_sub_plan` FOREIGN KEY (`plan_id`) REFERENCES `platform_plans`(`id`) ON DELETE RESTRICT,
                CONSTRAINT `fk_sub_precedent` FOREIGN KEY (`precedent_id`) REFERENCES `platform_subscriptions`(`id`) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // 3. Reprise de l'existant (lecture seule de etablissements : aucune modification de statut ni de date).
        $pdo->exec("
            INSERT INTO `platform_subscriptions`
                (etablissement_id, plan_id, statut, cycle, date_debut, date_fin, trial_ends_at, grace_ends_at,
                 prix_applique, devise, duree_essai_appliquee, jours_grace_appliques, auto_renew, created_at)
            SELECT e.id, e.plan_id,
                   CASE e.statut WHEN 'trial' THEN 'trial' WHEN 'cancelled' THEN 'cancelled' ELSE 'active' END,
                   CASE e.statut WHEN 'trial' THEN 'trial' ELSE 'legacy' END,
                   DATE(COALESCE(e.activated_at, e.created_at)),
                   CASE e.statut WHEN 'trial' THEN e.trial_ends_at ELSE e.plan_expires_at END,
                   CASE e.statut WHEN 'trial' THEN e.trial_ends_at ELSE NULL END,
                   CASE e.statut WHEN 'trial' THEN e.trial_ends_at ELSE e.plan_expires_at END,
                   NULL, p.devise, 0, 0, 0, NOW()
              FROM `etablissements` e
              JOIN `platform_plans` p ON p.id = e.plan_id
             WHERE e.deleted_at IS NULL
               AND e.plan_id IS NOT NULL
               AND NOT EXISTS (SELECT 1 FROM `platform_subscriptions` s WHERE s.etablissement_id = e.id)
        ");
    },

    'rollback' => function (PDO $pdo): void {
        $pdo->exec('DROP TABLE IF EXISTS `platform_subscriptions`');
    },
];
