<?php

/**
 * T078 — Préférences de notifications : tous les déclencheurs de NotificationService
 *
 * La colonne `notification_preferences.trigger_type` était un ENUM limité à
 * note/absence/paiement/annonce ; retard, discipline et récompense s'affichaient
 * mais ne pouvaient pas être enregistrés.
 *
 * Non destructive : l'ENUM est seulement élargi, les lignes existantes sont conservées.
 * Idempotente. Retour arrière : réduit l'ENUM aux 4 valeurs d'origine après
 * suppression des lignes retard/discipline/recompense.
 */

return [
    'id'         => 'T078',
    'name'       => 'Préférences de notifications — déclencheurs retard, discipline, récompense',
    'reversible' => true,

    'run' => function (PDO $pdo): void {
        $pdo->exec("ALTER TABLE `notification_preferences` MODIFY `trigger_type` ENUM('note','absence','paiement','annonce','retard','discipline','recompense') NOT NULL");
    },

    'rollback' => function (PDO $pdo): void {
        $pdo->exec("DELETE FROM `notification_preferences` WHERE `trigger_type` IN ('retard','discipline','recompense')");
        $pdo->exec("ALTER TABLE `notification_preferences` MODIFY `trigger_type` ENUM('note','absence','paiement','annonce') NOT NULL");
    },
];
