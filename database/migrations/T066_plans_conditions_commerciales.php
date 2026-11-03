<?php

/**
 * T066 — Plans SaaS : conditions commerciales (essai, grâce, ordre, visibilité, date de modification) et devise par défaut XOF
 *
 * 1. `platform_plans` : + `duree_essai_jours` (0 = pas d'essai), `jours_grace` (délai après expiration avant suspension), `ordre`
 *    (tri d'affichage), `visible_catalogue` (1 = visible ; un plan masqué reste assignable par un opérateur), `updated_at`.
 *    Toutes les colonnes sont additives, avec des valeurs neutres pour les lignes existantes.
 * 2. `devise` : SEUL le défaut de colonne passe de 'DZD' à 'XOF' (ALTER COLUMN … SET DEFAULT). AUCUNE ligne n'est modifiée : les plans
 *    existants (starter, pro, enterprise) gardent leur devise et leurs prix, sans conversion ni réinterprétation. `prix_mensuel` et
 *    `prix_annuel` ne changent ni de type ni de valeur.
 * 3. L'archivage réutilise `actif` (0 = archivé) : aucune colonne ajoutée.
 *
 * Idempotente. Appliquée à la base WAMP réelle (`ecole_app`) le 03/11/2026 avec `php database/migrate.php --id=T066` : cinq colonnes
 * ajoutées, défaut de `devise` à 'XOF', plans starter / pro / enterprise inchangés (DZD, prix d'origine).
 * Retour arrière : remet le défaut de devise à 'DZD' ; les colonnes ajoutées et toutes les données sont conservées (aucune perte).
 */

return [
    'id'         => 'T066',
    'name'       => 'Plans SaaS — essai, grâce, ordre, visibilité, updated_at ; devise par défaut XOF (lignes existantes inchangées)',
    'reversible' => true,

    'run' => function (PDO $pdo): void {
        $colExiste = static function (string $colonne) use ($pdo): bool {
            $s = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'platform_plans' AND COLUMN_NAME = ?");
            $s->execute([$colonne]);
            return (int)$s->fetchColumn() > 0;
        };

        $ajouts = [
            'duree_essai_jours' => 'SMALLINT UNSIGNED NOT NULL DEFAULT 0',
            'jours_grace'       => 'SMALLINT UNSIGNED NOT NULL DEFAULT 0',
            'ordre'             => 'SMALLINT NOT NULL DEFAULT 0',
            'visible_catalogue' => 'TINYINT(1) NOT NULL DEFAULT 1',
            'updated_at'        => 'DATETIME NULL DEFAULT NULL',
        ];
        foreach ($ajouts as $colonne => $definition) {
            if (!$colExiste($colonne)) {
                $pdo->exec("ALTER TABLE `platform_plans` ADD COLUMN `$colonne` $definition");
            }
        }

        // Défaut de colonne uniquement : les lignes existantes ne sont pas touchées.
        $pdo->exec("ALTER TABLE `platform_plans` ALTER COLUMN `devise` SET DEFAULT 'XOF'");
    },

    'rollback' => function (PDO $pdo): void {
        // Seul le défaut de devise est restauré ; colonnes et données sont conservées.
        $pdo->exec("ALTER TABLE `platform_plans` ALTER COLUMN `devise` SET DEFAULT 'DZD'");
    },
];
