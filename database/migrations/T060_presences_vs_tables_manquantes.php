<?php

/**
 * T060 — Tables de présences Vie scolaire absentes de la base (audit BD)
 *
 * Constat : `vie_scolaire_002_presences.sql` n'est pas exécuté par le runner (qui ne lit que les *.php) ; `vs_appels`, `vs_presences` et
 * `vs_presences_historique` n'existent donc pas alors que le module Vie scolaire est actif et que ses modèles les interrogent.
 * Cette migration rejoue ce fichier SQL (CREATE TABLE IF NOT EXISTS : aucune donnée existante n'est touchée).
 * Idempotente. NON APPLIQUÉE À LA BASE RÉELLE tant qu'une autorisation distincte n'a pas été donnée (sauvegarde préalable obligatoire).
 * Retour arrière : supprime les trois tables UNIQUEMENT si elles sont vides (sinon refuse, pour ne jamais perdre de pointages).
 */

return [
    'id'         => 'T060',
    'name'       => 'Tables vs_appels, vs_presences, vs_presences_historique (rattrapage du .sql hors runner)',
    'reversible' => true,

    'run' => function (PDO $pdo): void {
        $sql = file_get_contents(dirname(__DIR__) . '/migrations/vie_scolaire_002_presences.sql');
        if ($sql === false) {
            throw new RuntimeException('vie_scolaire_002_presences.sql introuvable.');
        }
        $sql = preg_replace('/^\s*--.*$/m', '', $sql);          // commentaires de ligne
        foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
            $pdo->exec($stmt);
        }
    },

    'rollback' => function (PDO $pdo): void {
        foreach (['vs_presences_historique', 'vs_presences', 'vs_appels'] as $t) {
            $exists = $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = '$t'")->fetchColumn();
            if ($exists && (int)$pdo->query("SELECT COUNT(*) FROM `$t`")->fetchColumn() > 0) {
                throw new RuntimeException("Retour arrière refusé : `$t` contient des données.");
            }
        }
        foreach (['vs_presences_historique', 'vs_presences', 'vs_appels'] as $t) {
            $pdo->exec("DROP TABLE IF EXISTS `$t`");
        }
    },
];
