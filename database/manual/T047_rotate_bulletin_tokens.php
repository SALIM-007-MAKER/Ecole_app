<?php

/**
 * T047 — Rotation des jetons de bulletins (version 1 -> jeton HMAC version 2)
 *
 * ÉTAT : INACTIF. Ce fichier est volontairement placé HORS de database/migrations/ : migrate.php ne le découvre
 * jamais, il ne peut donc pas s'appliquer par accident (déploiement, « migrate »). Il n'est PAS enregistré dans
 * migrations_log tant que la rotation n'a pas été exécutée sur autorisation distincte.
 *
 * Exécution (uniquement après autorisation écrite, base par base) :
 *   php database/tools/bulletin_tokens.php --check
 *   php database/tools/bulletin_tokens.php --simulate
 *   php database/tools/bulletin_tokens.php --apply --confirm=<somme de la simulation> --authorized-by="<nom>"
 *   php database/tools/bulletin_tokens.php --restore=<sauvegarde> --authorized-by="<nom>"
 *
 * Ce descripteur ne contient aucune logique : tout est dans BulletinTokenRotator.
 */

return [
    'id'         => 'T047',
    'name'       => 'Rotation des jetons de bulletins (manuelle)',
    'active'     => false,
    'reversible' => true,
    'tool'       => 'database/tools/bulletin_tokens.php',
    'run'        => function (PDO $pdo): void {
        throw new RuntimeException('T047 est inactive : utiliser database/tools/bulletin_tokens.php après autorisation explicite.');
    },
];
