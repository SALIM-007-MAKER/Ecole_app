<?php

/**
 * T032 — Types de notification pour Retards / Discipline / Récompenses
 *
 * Étape 1 du plan d'activation Vie Scolaire V2 (Retards/Discipline/
 * Récompenses/Présences) validé après l'audit d'architecture V1/V2 :
 * les listeners de notification de ces domaines sont actuellement des
 * stubs (`Logger::info` uniquement, marqués "MS2-M-004 différé") — avant
 * de les brancher sur `NotificationService`, la colonne `notifications.type`
 * doit accepter ces nouvelles catégories (ENUM existant limité à
 * note/absence/paiement/annonce/info).
 *
 * Idempotente.
 */

return [
    'id'         => 'T032',
    'name'       => 'Notifications — types retard/discipline/recompense',
    'reversible' => true,

    'run' => function (PDO $pdo): void {
        $col = $pdo->query("SHOW COLUMNS FROM `notifications` LIKE 'type'")->fetch(PDO::FETCH_ASSOC);
        if ($col && !str_contains($col['Type'], "'retard'")) {
            $pdo->exec(
                "ALTER TABLE `notifications`
                 MODIFY COLUMN `type` ENUM('note','absence','paiement','annonce','info','retard','discipline','recompense')
                 NULL DEFAULT 'info'"
            );
        }
    },

    'rollback' => function (PDO $pdo): void {
        // Ne réduit pas l'ENUM (nécessiterait de vérifier qu'aucune ligne
        // n'utilise encore ces valeurs) — laissé volontairement large.
    },
];
