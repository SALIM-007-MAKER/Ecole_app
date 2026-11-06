<?php
/**
 * Lance : C:\wamp64\bin\php\php8.3.28\php.exe tests/Support/balayage_pages.php   (quelques minutes ; code de sortie 1 si une page est en erreur)
 *
 * Balayage des pages GET sans paramètre (espace établissement + portail plateforme) sur une COPIE jetable de la base, avec le sql_mode réel du serveur.
 * Détecte les erreurs SQL (ONLY_FULL_GROUP_BY, colonnes inconnues…) et les erreurs fatales. Ne modifie jamais la base vivante.
 */
declare(strict_types=1);
define('ROOT_PATH', dirname(__DIR__, 2));
foreach (file(ROOT_PATH . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $l) {
    if (str_starts_with(trim($l), '#') || !str_contains($l, '=')) { continue; }
    [$k, $v] = explode('=', $l, 2);
    $_ENV[trim($k)] = trim($v, " \t\n\r\0\x0B\"'");
}
spl_autoload_register(function (string $c): void {
    foreach (['Core\\' => '/core/', 'App\\' => '/app/'] as $prefix => $dir) {
        if (str_starts_with($c, $prefix)) { $f = ROOT_PATH . $dir . str_replace('\\', '/', substr($c, strlen($prefix))) . '.php'; if (is_file($f)) { require $f; return; } }
    }
});
require_once ROOT_PATH . '/tests/Support/DbCopy.php';
$copie = \Tests\Support\DbCopy::redirigerVersCopie(ROOT_PATH) ?? exit("copie impossible\n");
$db = $copie->connection()['db'];
echo "copie : $db ; sql_mode = " . \Core\Database::getInstance()->getConnection()->query('SELECT @@SESSION.sql_mode')->fetchColumn() . "\n";

// Routes GET sans paramètre
$fichiers = array_merge([ROOT_PATH . '/config/routes.php'], glob(ROOT_PATH . '/app/Modules/*/routes.php') ?: [], glob(ROOT_PATH . '/app/Modules/*/*/routes.php') ?: []);
$routes = [];
foreach ($fichiers as $f) {
    if (preg_match_all('/\$router->get\(\s*[\'"]([^\'"{}]+)[\'"]/', (string)file_get_contents($f), $m)) {
        foreach ($m[1] as $r) { $routes[$r] = true; }
    }
}
$routes = array_keys($routes);
sort($routes);
$exclus = '#logout|deconnexion|/delete|/supprimer|/export|/telecharger|/download|/api/|/stream|/sse|/health|/cron|/queue|webhook|/verify|/callback|/oauth#i';
$op = json_encode(['id' => 1, 'email' => 'op@exemple.test', 'nom' => 'Op', 'niveau' => 'super_admin', 'must_change' => false]);
$problemes = []; $ok = 0; $n = 0; $statuts = [];
foreach ($routes as $r) {
    if (preg_match($exclus, $r)) { continue; }
    $plateforme = str_starts_with($r, '/platform');
    $corps = sys_get_temp_dir() . '/bal_' . bin2hex(random_bytes(3)) . '.html';
    $cmd = $plateforme
        ? [PHP_BINARY, ROOT_PATH . '/tests/Support/platform_request.php', $op, 'GET', '/ecole_app' . $r, '{}']
        : [PHP_BINARY, ROOT_PATH . '/tests/Support/request_as.php', 'admin', '1', 'GET', '/ecole_app' . $r, '{}'];
    $p = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, ROOT_PATH, array_merge(getenv(), ['REQUEST_AS_DB' => $db, 'REQUEST_AS_BODY_OUT' => $corps]));
    $sortie = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]); proc_close($p);
    $res = preg_match('/^RESULT:(.*)$/m', $sortie, $m) ? (json_decode($m[1], true) ?: []) : [];
    $body = (string)@file_get_contents($corps); @unlink($corps);
    $st = (int)($res['status'] ?? 0); $n++;
    $statuts[$st] = ($statuts[$st] ?? 0) + 1;
    $texte = $body . $sortie;
    if (preg_match('/SQLSTATE\[[0-9A-Z]+\][^\n<]{0,260}|Fatal error:[^\n<]{0,200}|Uncaught [A-Za-z\\\\]+[^\n<]{0,200}|Call to undefined[^\n<]{0,120}/', $texte, $mm)) {
        $problemes[$r] = trim($mm[0]);
    } elseif ($st >= 500 || $st === 0) {
        $problemes[$r] = "statut $st";
    } else { $ok++; }
}
echo "\nPages testées : $n ; sans erreur : $ok ; en erreur : " . count($problemes) . "\nStatuts : " . json_encode($statuts) . "\n\n";
foreach ($problemes as $r => $e) { echo "ERREUR $r\n    " . mb_substr($e, 0, 330) . "\n"; }
exit($problemes === [] ? 0 : 1);
