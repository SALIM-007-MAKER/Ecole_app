<?php
/**
 * Tests — consultation, par le Super-Admin, du journal d'audit d'un établissement (dernier point du plan Super-Admin SaaS).
 *
 * 1. Accès : super_admin seul (admin, support, anonyme, compte d'établissement refusés), établissement inexistant = 404
 * 2. Isolation : seuls les événements de l'établissement demandé ; un événement d'un autre établissement = 404
 * 3. Filtres et pagination
 * 4. Traçabilité de la lecture : liste, détail et export sont inscrits au journal global (filtres conservés, contenu consulté non conservé)
 * 5. Export CSV : en-tête, BOM, un seul établissement, neutralisation des formules
 * 6. Lecture seule : aucune route de modification ; panne du journal global = aucune donnée servie
 *
 * Travaille uniquement sur une copie jetable ecole_app_copy_* : la base vivante n'est jamais touchée.
 *
 * Lance : C:\wamp64\bin\php\php8.2.29\php.exe tests/Unit/PlatformEtablissementAuditTest.php
 */

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__, 2));
foreach (file(ROOT_PATH . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $l) {
    if (str_starts_with(trim($l), '#') || !str_contains($l, '=')) { continue; }
    [$k, $v] = explode('=', $l, 2);
    $_ENV[trim($k)] = trim($v, " \t\n\r\0\x0B\"'");
}
spl_autoload_register(function (string $c): void {
    foreach (['Core\\' => '/core/', 'App\\Shared\\' => '/app/Shared/', 'App\\Controllers\\' => '/app/Controllers/'] as $prefix => $dir) {
        if (str_starts_with($c, $prefix)) {
            $f = ROOT_PATH . $dir . str_replace('\\', '/', substr($c, strlen($prefix))) . '.php';
            if (is_file($f)) { require $f; return; }
        }
    }
});
require_once ROOT_PATH . '/tests/Support/DbCopy.php';

use Core\Database;
use Core\Platform\PlatformAuditService;
use Core\Platform\PlatformOperatorService;

$passed = 0; $failed = 0;
function check(bool $ok, string $label, string $detail = ''): void
{
    global $passed, $failed;
    if ($ok) { $passed++; echo "\033[32m  ✓ $label\033[0m\n"; }
    else     { $failed++; echo "\033[31m  ✗ $label" . ($detail !== '' ? " — $detail" : '') . "\033[0m\n"; }
}
function section(string $t): void { echo "\n\033[33m── $t\033[0m\n"; }
/** @return array{0:int,1:string} */
function lancer(array $cmd, array $env = []): array
{
    $p = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, ROOT_PATH, array_merge(getenv(), $env));
    $out = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
    return [proc_close($p), $out];
}
/** Requête HTTP simulée du portail plateforme. @return array<string,mixed> */
function http(string $nomCopie, string $operateur, string $methode, string $uri, array $env = []): array
{
    global $dossierSecours;
    [$code, $sortie] = lancer([PHP_BINARY, ROOT_PATH . '/tests/Support/platform_request.php', $operateur, $methode, $uri, '{}'],
        $env + ['REQUEST_AS_DB' => $nomCopie, 'PLATFORM_REQ_AUDIT_DIR' => $dossierSecours]);
    return preg_match('/^RESULT:(.*)$/m', $sortie, $m) ? (json_decode($m[1], true) ?: ['status' => 0]) : ['status' => 0, 'brut' => $sortie, 'code' => $code];
}
function operateur(int $id, string $niveau): string { return json_encode(['id' => $id, 'niveau' => $niveau, 'email' => "op$id@exemple.test", 'nom' => "Op $id"]); }

$_SESSION = [];
$copie = \Tests\Support\DbCopy::redirigerVersCopie(ROOT_PATH) ?? exit("ECHEC : copie jetable impossible (mysql/mysqldump ou droit CREATE DATABASE)\n");
$nomCopie = $copie->connection()['db'];
$pdo = Database::getInstance()->getConnection();
$dossierSecours = sys_get_temp_dir() . '/pea_audit_' . bin2hex(random_bytes(4));
PlatformAuditService::definirDossierSecours($dossierSecours);
$ops = new PlatformOperatorService($pdo);
$mdp = 'Zq7!pLm2#vXk9@Rt';
$superId = $ops->creer('super@exemple.test', 'Super Opérateur', 'super_admin', $mdp, PlatformOperatorService::ACTEUR_CLI)['id'];
$adminId = $ops->creer('admin@exemple.test', 'Admin Opérateur', 'admin', $mdp, PlatformOperatorService::ACTEUR_CLI)['id'];
$supportId = $ops->creer('support@exemple.test', 'Support Opérateur', 'support', $mdp, PlatformOperatorService::ACTEUR_CLI)['id'];
$compterGlobal = static fn(string $where = '1=1') => (int)$pdo->query("SELECT COUNT(*) FROM platform_audit_logs WHERE $where")->fetchColumn();

// Deux établissements, chacun avec ses événements.
$pdo->exec("INSERT INTO etablissements (slug, nom, type, pays, statut) VALUES ('ecole-b', 'École B', 'lycee', 'DZ', 'active')");
$etabA = 1;
$etabB = (int)$pdo->lastInsertId();
$ins = $pdo->prepare('INSERT INTO audit_logs (etablissement_id, action, module, entite, entite_id, actor_nom, actor_role, avant, apres, ip, resultat) VALUES (?,?,?,?,?,?,?,NULL,?,?,?)');
for ($i = 1; $i <= 60; $i++) { $ins->execute([$etabA, 'create', 'finance', 'paiement', $i, 'Directeur A', 'directeur', '{"montant": ' . $i . '}', '10.0.0.1', 'success']); }
$ins->execute([$etabA, 'update', 'parametres', 'parametre', 5, 'Directeur A', 'directeur', '{"k": "v"}', '10.0.0.1', 'failure']);
$ins->execute([$etabA, 'login', 'zz_auth', null, null, '=HYPERLINK("http://x")', 'admin', null, '10.0.0.1', 'success']);
$ins->execute([$etabB, 'delete', 'notes', 'note', 9, 'Directeur B', 'directeur', '{"secret_b": 1}', '10.0.0.2', 'success']);
$idA = (int)$pdo->query("SELECT MIN(id) FROM audit_logs WHERE etablissement_id = $etabA AND actor_nom = 'Directeur A'")->fetchColumn();
$idB = (int)$pdo->query("SELECT MIN(id) FROM audit_logs WHERE etablissement_id = $etabB")->fetchColumn();
$nbA = (int)$pdo->query("SELECT COUNT(*) FROM audit_logs WHERE etablissement_id = $etabA")->fetchColumn();
$nbFailA = (int)$pdo->query("SELECT COUNT(*) FROM audit_logs WHERE etablissement_id = $etabA AND resultat = 'failure'")->fetchColumn();
$pages = (int)ceil($nbA / 50);
$u = "/ecole_app/platform/etablissements";

section('1. Accès');
$r = http($nomCopie, 'guest', 'GET', "$u/$etabA/audit");
check($r['status'] === 302 && str_contains((string)$r['location'], '/platform/login'), 'anonyme : redirigé vers la connexion');
foreach ([[$adminId, 'admin'], [$supportId, 'support']] as [$id, $niv]) {
    $avant = $compterGlobal("action = 'etablissement.audit.viewed'");
    $r = http($nomCopie, operateur($id, $niv), 'GET', "$u/$etabA/audit");
    check($r['status'] === 403, "niveau $niv : refusé (403)");
    check($compterGlobal("action = 'etablissement.audit.viewed'") === $avant, "niveau $niv : aucune consultation inscrite");
}
check($compterGlobal("action = 'access.denied'") >= 2, 'refus inscrits au journal global');
$r = http($nomCopie, operateur($adminId, 'admin'), 'GET', "$u/$etabA/audit/export");
check($r['status'] === 403, 'export : refusé au niveau admin');
$r = http($nomCopie, operateur($superId, 'super_admin'), 'GET', "$u/9999/audit");
check($r['status'] === 404, 'établissement inexistant : 404');
$r = http($nomCopie, operateur($superId, 'super_admin'), 'GET', "$u/$etabA/audit");
check($r['status'] === 200 && str_contains((string)$r['text'], 'Directeur A'), 'super_admin : journal affiché', 'status=' . $r['status']);

section('2. Isolation entre établissements');
check(!str_contains((string)$r['text'], 'Directeur B') && !str_contains((string)$r['text'], 'secret_b'), 'le journal de A ne contient rien de B');
check(str_contains((string)$r['text'], "$nbA événement"), "total = $nbA événements de A", mb_substr((string)$r['text'], 0, 200));
$rb = http($nomCopie, operateur($superId, 'super_admin'), 'GET', "$u/$etabB/audit");
check($rb['status'] === 200 && str_contains((string)$rb['text'], 'Directeur B') && !str_contains((string)$rb['text'], 'Directeur A'), 'le journal de B ne contient rien de A');
$r = http($nomCopie, operateur($superId, 'super_admin'), 'GET', "$u/$etabA/audit/$idB");
check($r['status'] === 404, 'événement de B demandé via A : 404');
$r = http($nomCopie, operateur($superId, 'super_admin'), 'GET', "$u/$etabA/audit/99999999");
check($r['status'] === 404, 'événement inexistant : 404 (indiscernable)');
$r = http($nomCopie, operateur($superId, 'super_admin'), 'GET', "$u/$etabA/audit/$idA");
check($r['status'] === 200 && str_contains((string)$r['text'], 'Directeur A'), 'détail d\'un événement de A affiché');

section('3. Filtres et pagination');
$r = http($nomCopie, operateur($superId, 'super_admin'), 'GET', "$u/$etabA/audit?module=zz_auth");
check($r['status'] === 200 && str_contains((string)$r['text'], '1 événement(s)') && !str_contains((string)$r['text'], 'Directeur A'), 'filtre module');
$r = http($nomCopie, operateur($superId, 'super_admin'), 'GET', "$u/$etabA/audit?resultat=failure");
check(str_contains((string)$r['text'], "$nbFailA événement"), 'filtre résultat');
$r = http($nomCopie, operateur($superId, 'super_admin'), 'GET', "$u/$etabA/audit?page=2");
check($r['status'] === 200 && str_contains((string)$r['text'], "Page 2 sur $pages"), "pagination : page 2 sur $pages ($nbA événements, 50 par page)");

section('4. Traçabilité de la lecture');
$derniere = static fn(string $a) => $pdo->query("SELECT * FROM platform_audit_logs WHERE action = '$a' ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$l = $derniere('etablissement.audit.viewed');
check($l !== false && (int)$l['etablissement_id'] === $etabA, 'la consultation de la liste est inscrite');
$avant = $compterGlobal("action = 'etablissement.audit.viewed'");
http($nomCopie, operateur($superId, 'super_admin'), 'GET', "$u/$etabB/audit?q=notes");
$l = $derniere('etablissement.audit.viewed');
check($compterGlobal("action = 'etablissement.audit.viewed'") === $avant + 1, 'une consultation = une ligne au journal global');
check((int)$l['etablissement_id'] === $etabB && (int)$l['operator_id'] === $superId && $l['actor_niveau'] === 'super_admin', 'établissement consulté et opérateur enregistrés');
check(str_contains((string)$l['nouvelle_valeur'], '"q": "notes"'), 'filtres utilisés conservés', (string)$l['nouvelle_valeur']);
check(!str_contains((string)$l['nouvelle_valeur'], '"module"'), 'filtres vides non conservés');
check(!str_contains((string)$l['nouvelle_valeur'], 'Directeur B') && !str_contains((string)$l['nouvelle_valeur'], 'secret_b'), 'le contenu consulté n\'est PAS recopié dans le journal global');
http($nomCopie, operateur($superId, 'super_admin'), 'GET', "$u/$etabA/audit/$idA");
$l = $derniere('etablissement.audit.event_viewed');
check($l !== false && (int)$l['etablissement_id'] === $etabA && str_contains((string)$l['nouvelle_valeur'], (string)$idA), 'la consultation d\'un détail est inscrite');

section('5. Export CSV');
$avant = $compterGlobal("action = 'etablissement.audit.exported'");
$corps = sys_get_temp_dir() . '/pea_export_' . bin2hex(random_bytes(3)) . '.csv';
$r = http($nomCopie, operateur($superId, 'super_admin'), 'GET', "$u/$etabA/audit/export", ['REQUEST_AS_BODY_OUT' => $corps]);
$csv = is_file($corps) ? (string)file_get_contents($corps) : ''; @unlink($corps);
check($r['status'] === 200 && str_starts_with($csv, "\xEF\xBB\xBFid;date;utilisateur_id"), 'export : CSV UTF-8 avec BOM et en-têtes');
check(substr_count($csv, "\n") - 1 === $nbA, 'export : une ligne par événement de A', 'lignes=' . (substr_count($csv, "\n") - 1));
check(!str_contains($csv, 'Directeur B'), 'export : aucun événement de B');
check(str_contains($csv, "'=HYPERLINK"), 'export : formule neutralisée');
check($compterGlobal("action = 'etablissement.audit.exported'") === $avant + 1, 'l\'export est inscrit au journal global');
$corps = sys_get_temp_dir() . '/pea_export_' . bin2hex(random_bytes(3)) . '.csv';
http($nomCopie, operateur($superId, 'super_admin'), 'GET', "$u/$etabA/audit/export?module=zz_auth", ['REQUEST_AS_BODY_OUT' => $corps]);
$csv = is_file($corps) ? (string)file_get_contents($corps) : ''; @unlink($corps);
check(substr_count($csv, "\n") - 1 === 1, 'export : filtres respectés');

section('6. Lecture seule et panne du journal global');
foreach (['POST', 'PUT', 'DELETE'] as $m) {
    $r = http($nomCopie, operateur($superId, 'super_admin'), $m, "$u/$etabA/audit");
    check($r['status'] !== 200, "méthode $m : aucune route de modification (status {$r['status']})");
}
$nAvant = (int)$pdo->query("SELECT COUNT(*) FROM audit_logs WHERE etablissement_id = $etabA")->fetchColumn();
check($nAvant === $nbA, 'le journal de l\'établissement n\'a pas été modifié par les consultations');
$r = http($nomCopie, operateur($superId, 'super_admin'), 'GET', "$u/$etabA/audit", ['PLATFORM_REQ_BREAK_AUDIT' => '1']);
check($r['status'] === 503 && !str_contains((string)$r['text'], 'Directeur A'), 'journal global en panne : aucune donnée servie (503)', 'status=' . $r['status']);
$r = http($nomCopie, operateur($superId, 'super_admin'), 'GET', "$u/$etabA/audit/export", ['PLATFORM_REQ_BREAK_AUDIT' => '1']);
check($r['status'] === 503, 'journal global en panne : export refusé (503)', 'status=' . $r['status']);

echo "\n";
echo $failed === 0 ? "\033[32m$passed OK, 0 KO\033[0m\n" : "\033[31m$passed OK, $failed KO\033[0m\n";
exit($failed === 0 ? 0 : 1);
