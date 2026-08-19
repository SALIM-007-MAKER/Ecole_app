<?php

/**
 * T031 — Suppression de la table morte `etablissement_domains`
 *
 * Constat de l'audit d'architecture (unification V1/V2) : cette table
 * était utilisée par `app/Controllers/DomainController.php`, déjà supprimé
 * du projet (remplacé par `PlatformDomainController` au niveau plateforme).
 * Vérifié avant suppression : 0 ligne en base, 0 référence dans tout `app/`.
 *
 * Idempotente.
 */

return [
    'id'         => 'T031',
    'name'       => 'Suppression table morte etablissement_domains',
    'reversible' => false,

    'run' => function (PDO $pdo): void {
        $hasTable = $pdo->query("SHOW TABLES LIKE 'etablissement_domains'")->fetch(PDO::FETCH_ASSOC);
        if ($hasTable) {
            $count = (int)$pdo->query("SELECT COUNT(*) FROM `etablissement_domains`")->fetchColumn();
            if ($count === 0) {
                $pdo->exec("DROP TABLE `etablissement_domains`");
            }
            // Si des lignes existent (état inattendu), on ne supprime rien —
            // sécurité : mieux vaut laisser la table en place que perdre des
            // données non prévues par cet audit.
        }
    },

    'rollback' => function (PDO $pdo): void {
        // Non réversible : la table étant vide et le contrôleur qui l'utilisait
        // déjà supprimé, il n'y a rien de significatif à restaurer.
    },
];
