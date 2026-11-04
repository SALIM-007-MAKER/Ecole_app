<?php

/**
 * T070 — Paiements manuels des abonnements SaaS (étape 5 du Super-Admin SaaS)
 *
 * Table `platform_payments` : un encaissement saisi par un super_admin (espèces, virement, Orange Money, chèque…), rattaché à UNE période
 * d'abonnement (`platform_subscriptions`) et à l'établissement. Montant et devise sont ceux de l'encaissement réel : aucune conversion.
 *
 * Intégrité :
 *  - une ligne n'est jamais supprimée (déclencheur) ni modifiée sur ses champs financiers (déclencheur) ; la seule évolution permise est
 *    l'ANNULATION (recorded -> cancelled, avec motif), qui conserve la ligne et son historique ;
 *  - `reference` (PAY-AAAA-NNNNNN) est attribuée dans la transaction d'insertion à partir de l'identifiant ;
 *  - une même référence externe (n° de virement, de transaction mobile…) ne peut pas être enregistrée deux fois pour un même mode tant que
 *    le premier paiement n'est pas annulé (colonne générée `ref_unique` + index unique) : protection contre la double saisie.
 *
 * Mode de paiement en VARCHAR (liste validée par le code) : ajouter un fournisseur ne demande aucune migration.
 *
 * Idempotente. Retour arrière : supprime `platform_payments` (les encaissements saisis sont perdus : sauvegarde préalable).
 */

return [
    'id'         => 'T070',
    'name'       => 'Paiements manuels SaaS — platform_payments (rattachés aux périodes d\'abonnement, annulation sans suppression)',
    'reversible' => true,

    'run' => function (PDO $pdo): void {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `platform_payments` (
                `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `reference`         VARCHAR(20) NULL,
                `etablissement_id`  INT UNSIGNED NOT NULL,
                `subscription_id`   INT UNSIGNED NOT NULL,
                `montant`           DECIMAL(12,2) NOT NULL,
                `devise`            CHAR(3) NOT NULL,
                `mode`              VARCHAR(30) NOT NULL,
                `reference_externe` VARCHAR(100) NULL,
                `date_paiement`     DATE NOT NULL,
                `note`              VARCHAR(255) NULL,
                `statut`            ENUM('recorded','cancelled') NOT NULL DEFAULT 'recorded',
                `recorded_by`       INT UNSIGNED NULL,
                `cancelled_at`      DATETIME NULL,
                `cancelled_by`      INT UNSIGNED NULL,
                `cancel_reason`     VARCHAR(255) NULL,
                `created_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `ref_unique`        VARCHAR(140) GENERATED ALWAYS AS (CASE WHEN `statut` = 'recorded' AND `reference_externe` IS NOT NULL THEN CONCAT(`mode`, ':', `reference_externe`) ELSE NULL END) STORED,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_pay_reference` (`reference`),
                UNIQUE KEY `uq_pay_ref_externe` (`ref_unique`),
                KEY `idx_pay_etab` (`etablissement_id`, `id`),
                KEY `idx_pay_sub` (`subscription_id`),
                KEY `idx_pay_date` (`date_paiement`),
                CONSTRAINT `fk_pay_etab` FOREIGN KEY (`etablissement_id`) REFERENCES `etablissements`(`id`) ON DELETE RESTRICT,
                CONSTRAINT `fk_pay_sub`  FOREIGN KEY (`subscription_id`)  REFERENCES `platform_subscriptions`(`id`) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
              COMMENT='Encaissements manuels des abonnements SaaS — jamais supprimés, annulation tracée'
        ");

        $pdo->exec('DROP TRIGGER IF EXISTS `trg_platform_payments_no_delete`');
        $pdo->exec(
            "CREATE TRIGGER `trg_platform_payments_no_delete` BEFORE DELETE ON `platform_payments` FOR EACH ROW
             SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'platform_payments : suppression interdite (annuler le paiement à la place)'"
        );
        $pdo->exec('DROP TRIGGER IF EXISTS `trg_platform_payments_guard_update`');
        $pdo->exec(
            "CREATE TRIGGER `trg_platform_payments_guard_update` BEFORE UPDATE ON `platform_payments` FOR EACH ROW
             IF NOT (OLD.etablissement_id <=> NEW.etablissement_id AND OLD.subscription_id <=> NEW.subscription_id AND OLD.montant <=> NEW.montant
                     AND OLD.devise <=> NEW.devise AND OLD.mode <=> NEW.mode AND OLD.reference_externe <=> NEW.reference_externe
                     AND OLD.date_paiement <=> NEW.date_paiement AND OLD.note <=> NEW.note AND OLD.recorded_by <=> NEW.recorded_by
                     AND OLD.created_at <=> NEW.created_at AND (OLD.reference IS NULL OR OLD.reference <=> NEW.reference))
                OR (OLD.statut = 'cancelled' AND NOT (OLD.statut <=> NEW.statut AND OLD.cancelled_at <=> NEW.cancelled_at
                     AND OLD.cancelled_by <=> NEW.cancelled_by AND OLD.cancel_reason <=> NEW.cancel_reason)) THEN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'platform_payments : champs financiers non modifiables (seule l\\'annulation est permise)';
             END IF"
        );
    },

    'rollback' => function (PDO $pdo): void {
        $pdo->exec('DROP TRIGGER IF EXISTS `trg_platform_payments_no_delete`');
        $pdo->exec('DROP TRIGGER IF EXISTS `trg_platform_payments_guard_update`');
        $pdo->exec('DROP TABLE IF EXISTS `platform_payments`');
    },
];
