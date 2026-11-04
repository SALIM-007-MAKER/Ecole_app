<?php

/**
 * T071 — Paramètres globaux de la plateforme (étape 6 du Super-Admin SaaS)
 *
 * Table `platform_settings` : une ligne par réglage modifiable depuis /platform/parametres (clé => valeur texte). L'ABSENCE de ligne signifie
 * « pas de surcharge » : la valeur retombe sur la variable d'environnement (.env), puis sur la valeur par défaut du code. La migration
 * n'insère donc AUCUNE ligne : le comportement existant est strictement conservé tant qu'un super_admin ne modifie rien.
 *
 * L'historique des changements est dans le journal d'audit global (`settings.updated`, ancienne et nouvelle valeur) ; la table ne garde que
 * l'état courant et le dernier auteur.
 *
 * Idempotente. Retour arrière : supprime `platform_settings` (les surcharges sont perdues : retour aux valeurs du .env).
 */

return [
    'id'         => 'T071',
    'name'       => 'Paramètres globaux — platform_settings (surcharges de la configuration, aucune ligne insérée)',
    'reversible' => true,

    'run' => function (PDO $pdo): void {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `platform_settings` (
                `cle`        VARCHAR(60) NOT NULL,
                `valeur`     TEXT NULL,
                `updated_by` INT UNSIGNED NULL,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`cle`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
              COMMENT='Surcharges des paramètres globaux de la plateforme — absence de ligne = valeur du .env / du code'
        ");
    },

    'rollback' => function (PDO $pdo): void {
        $pdo->exec('DROP TABLE IF EXISTS `platform_settings`');
    },
];
