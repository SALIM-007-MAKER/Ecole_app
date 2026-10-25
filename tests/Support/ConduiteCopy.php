<?php

declare(strict_types=1);

namespace Tests\Support;

use PDO;

/**
 * Prépare une COPIE jetable de la base (DbCopy) pour les tests de la Conduite, que la base source ait déjà reçu les
 * migrations T048 à T050 ou non, et exécute des requêtes HTTP complètes de l'application sur cette copie
 * (tests/Support/request_as.php, REQUEST_AS_DB). Rien n'est jamais écrit dans la base source.
 */
final class ConduiteCopy
{
    /** Applique T048, T049 et T050 sur la copie si elles n'y sont pas déjà (idempotentes). */
    public static function migrer(PDO $db, string $root): void
    {
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        foreach (['T048_matieres_est_conduite', 'T049_permission_conduite_manage', 'T050_matieres_conduite_declencheurs'] as $f) {
            $m = require $root . "/database/migrations/{$f}.php";
            ($m['run'])($db);
        }
    }

    /**
     * Droits de bulletin que le palier RBAC actif n'accorde pas encore à admin/directeur (chantier RBAC distinct) :
     * accordés UNIQUEMENT dans la copie, pour éprouver de bout en bout la régénération et la publication.
     */
    public static function accorderDroitsBulletin(PDO $db): void
    {
        foreach (['academique.bulletin.generate', 'academique.bulletin.publish', 'academique.bulletin.admin'] as $code) {
            $db->prepare("INSERT IGNORE INTO etab_permissions (code, libelle, module, action) VALUES (?, ?, 'academique', 'test')")->execute([$code, $code]);
            $db->prepare(
                "INSERT IGNORE INTO etab_role_permissions (role_id, permission_id)
                 SELECT r.id, p.id FROM etab_roles r JOIN etab_permissions p ON p.code = ?
                  WHERE r.etablissement_id IS NULL AND r.is_system = 1 AND r.code IN ('admin', 'directeur')"
            )->execute([$code]);
        }
    }

    /**
     * Exécute UNE requête de l'application complète sur la copie (session simulée). Les écritures sont VALIDÉES dans
     * la copie (REQUEST_AS_COMMIT=1) pour pouvoir être relues ensuite.
     *
     * @return array{0:array,1:string,2:string} [résultat JSON, corps de la réponse, sortie brute]
     */
    public static function http(string $root, string $db, string $role, int $uid, string $method, string $uri, array $post = [], array $env = []): array
    {
        $bodyFile = tempnam(sys_get_temp_dir(), 'ra_body_');
        // Secret de jetons JETABLE propre aux tests (jamais celui de l'environnement réel)
        $vars = array_merge(getenv(), $_ENV, [
            'REQUEST_AS_DB' => $db, 'REQUEST_AS_COMMIT' => '1', 'REQUEST_AS_BODY_OUT' => $bodyFile,
            'BULLETIN_TOKEN_SECRET' => hash('sha256', 'conduite-lot2-test-secret'),
        ], $env);
        $cmd = [PHP_BINARY, $root . '/tests/Support/request_as.php', $role, (string)$uid, $method, $uri];
        if ($post !== []) { $cmd[] = json_encode($post); }
        $p = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $root, $vars);
        $out = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]); proc_close($p);
        $body = (string)file_get_contents($bodyFile);
        @unlink($bodyFile);
        $res = [];
        if (preg_match('/RESULT:(\{.*\})/', $out, $m)) { $res = json_decode($m[1], true) ?: []; }
        return [$res, $body, $out];
    }
}
