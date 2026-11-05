<?php
/**
 * Tests — corrections issues de la revue de code du Super-Admin SaaS.
 *
 * 1. Second facteur : échec FERMÉ quand l'état de la base est illisible (jamais de connexion par le seul mot de passe)
 * 2. Réglage « 2FA obligatoire » : le garde-fou voit « oui », « on », « true », « 1 » avec espaces, pas seulement « 1 »
 * 3. Connexion : une adresse avec apostrophe n'est plus corrompue par l'échappement HTML
 * 4. Export du journal global : sans trace possible de l'export, rien n'est servi (503)
 * 5. Échec d'identifiants typé : la limitation des essais ne dépend plus du texte du message
 *
 * (Activation 2FA avec mot de passe + âge du secret : PlatformTwoFactorTest. Double envoi d'un paiement : PlatformPaymentsTest.)
 *
 * Travaille uniquement sur une copie jetable ecole_app_copy_* : la base vivante n'est jamais touchée.
 *
 * Lance : C:\wamp64\bin\php\php8.2.29\php.exe tests/Unit/PlatformRevueCorrectionsTest.php
 */

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__, 2));
foreach (file(ROOT_PATH . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $l) {
    if (str_starts_with(trim($l), '#') || !str_contains($l, '=')) { continue; }
    [$k, $v] = explode('=', $l, 2);
    $_ENV[trim($k)] = trim($v, " \t\n\r\0\x0B\"'");
}
foreach (['MAIL_DRIVER', 'MAIL_USERNAME', 'MAIL_PASSWORD', 'PLATFORM_TIMEZONE', 'PLATFORM_DEFAULT_CURRENCY', 'PLATFORM_REQUIRE_2FA', 'PLATFORM_NOTIFY_LOGIN'] as $cle) {
    unset($_ENV[$cle]); putenv($cle);
}
if (strlen((string)($_ENV['APP_KEY'] ?? '')) < 16) { $_ENV['APP_KEY'] = bin2hex(random_bytes(16)); }
spl_autoload_register(function (string $c): void {
    foreach (['Core\\' => '/core/', 'App\\Shared\\' => '/app/Shared/', 'App\\Controllers\\' => '/app/Controllers/', 'App\\Services\\' => '/app/Services/'] as $prefix => $dir) {
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
use Core\Platform\PlatformTwoFactorService as TF;
use Core\Platform\TwoFactorException;

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
function http(string $nomCopie, string $operateur, string $methode, string $uri, array $post = [], array $env = []): array
{
    global $dossierSecours, $dossierThrottle;
    [$code, $sortie] = lancer([PHP_BINARY, ROOT_PATH . '/tests/Support/platform_request.php', $operateur, $methode, $uri, json_encode($post)],
        array_merge(['REQUEST_AS_DB' => $nomCopie, 'PLATFORM_REQ_AUDIT_DIR' => $dossierSecours, 'PLATFORM_THROTTLE_DIR' => $dossierThrottle], $env));
    return preg_match('/^RESULT:(.*)$/m', $sortie, $m) ? (json_decode($m[1], true) ?: ['status' => 0]) : ['status' => 0, 'brut' => $sortie, 'code' => $code];
}
function operateur(int $id, string $niveau): string { return json_encode(['id' => $id, 'niveau' => $niveau, 'email' => "op$id@exemple.test", 'nom' => "Op $id", 'must_change' => false]); }
function ip(): string { return '10.' . random_int(1, 250) . '.' . random_int(1, 250) . '.' . random_int(1, 250); }

$_SESSION = [];
$copie = \Tests\Support\DbCopy::redirigerVersCopie(ROOT_PATH) ?? exit("ECHEC : copie jetable impossible (mysql/mysqldump ou droit CREATE DATABASE)\n");
$nomCopie = $copie->connection()['db'];
$pdo = Database::getInstance()->getConnection();
$tmp = sys_get_temp_dir() . '/prc_' . bin2hex(random_bytes(4));
$dossierSecours = $tmp . '_secours'; $dossierThrottle = $tmp . '_thr';
PlatformAuditService::definirDossierSecours($dossierSecours);
$ops = new PlatformOperatorService($pdo);
$mdp = 'Zq7!pLm2#vXk9@Rt';
$superId = $ops->creer('super@exemple.test', 'Super Opérateur', 'super_admin', $mdp, PlatformOperatorService::ACTEUR_CLI)['id'];
$sansTf = $ops->creer('sans@exemple.test', 'Sans Deux', 'super_admin', $mdp, PlatformOperatorService::ACTEUR_CLI)['id'];
$P = '/ecole_app/platform';

// ═════════════════════════════════════════════════════════════════════
section('1. Second facteur : échec fermé');
check(TF::disponible($pdo) === true, 'base lisible, T072 appliquée : disponible');
$pdoCasse = (new ReflectionClass(PdoQuiEchoue::class))->newInstanceWithoutConstructor();
$leve = false;
try { TF::disponible($pdoCasse); } catch (\Throwable) { $leve = true; }
check($leve, 'base illisible : l\'exception remonte (plus de « pas de 2FA » par défaut)');
$src = (string)file_get_contents(ROOT_PATH . '/core/Platform/PlatformTwoFactorService.php');
preg_match('/public static function disponible\(.*?\n    }\n/s', str_replace("\r\n", "\n", $src), $m);
check(isset($m[0]) && !str_contains($m[0], 'catch'), 'le code de disponible() ne contient plus de catch');
$ctrl = str_replace("\r\n", "\n", (string)file_get_contents(ROOT_PATH . '/app/Controllers/PlatformAuthController.php'));
check(preg_match('/try\s*\{[^}]*PlatformTwoFactorService::disponible/s', $ctrl) !== 1, 'la connexion n\'enveloppe pas la vérification de la 2FA dans un try');
// Sans la migration (colonnes absentes) : toujours « indisponible » sans erreur, comme avant.
$cols = $pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'platform_operators' AND COLUMN_NAME = 'totp_enabled_at'")->fetchColumn();
check((int)$cols === 1, 'contrôle de cohérence : la colonne T072 existe dans la copie');

// ═════════════════════════════════════════════════════════════════════
section('2. Réglage « 2FA obligatoire » : garde-fou sur la valeur normalisée');
$S = operateur($sansTf, 'super_admin');
foreach (['1', 'oui', 'on', 'true', 'yes', ' 1 ', 'OUI', 'True'] as $valeur) {
    $r = http($nomCopie, $S, 'POST', "$P/parametres", ['deux_facteurs_obligatoire' => $valeur], ['PLATFORM_REQ_IP' => ip()]);
    $enBase = $pdo->query("SELECT valeur FROM platform_settings WHERE cle = 'deux_facteurs_obligatoire'")->fetchColumn();
    check($enBase === false || $enBase === '' || $enBase === '0', "valeur « $valeur » sans sa propre 2FA : refusée, rien d'enregistré", 'en base=' . var_export($enBase, true) . ' flash=' . json_encode($r['flash'] ?? []));
}
$r = http($nomCopie, $S, 'POST', "$P/parametres", ['deux_facteurs_obligatoire' => '0'], ['PLATFORM_REQ_IP' => ip()]);
check(($r['flash']['errors'] ?? []) === [] || !str_contains(json_encode($r['flash'] ?? []), 'Activez d'), 'valeur « 0 » : le garde-fou ne se déclenche pas');
// Avec sa propre 2FA, « oui » est accepté et enregistré normalisé.
$s = TF::nouveauSecret();
(new TF($pdo))->activer($superId, $s, TF::totp($s, intdiv(time(), 30)));
$r = http($nomCopie, operateur($superId, 'super_admin'), 'POST', "$P/parametres", ['deux_facteurs_obligatoire' => 'oui'], ['PLATFORM_REQ_IP' => ip()]);
check($pdo->query("SELECT valeur FROM platform_settings WHERE cle = 'deux_facteurs_obligatoire'")->fetchColumn() === '1', 'avec sa propre 2FA : « oui » accepté et enregistré comme « 1 »');

// ═════════════════════════════════════════════════════════════════════
section('3. Connexion : adresse avec apostrophe');
$pdo->exec("DELETE FROM platform_settings WHERE cle = 'deux_facteurs_obligatoire'");
$apo = $ops->creer("o'brien@exemple.test", 'Pat O\'Brien', 'support', $mdp, PlatformOperatorService::ACTEUR_CLI);
$r = http($nomCopie, 'guest', 'POST', "$P/login", ['email' => "O'Brien@Exemple.test ", 'password' => $mdp], ['PLATFORM_REQ_IP' => ip()]);
check($r['operator'] !== null && (int)$r['operator']['id'] === $apo['id'], 'o\'brien@… : connexion réussie (casse et espaces tolérés)', json_encode($r));
$echec = http($nomCopie, 'guest', 'POST', "$P/login", ['email' => "o'brien@exemple.test", 'password' => 'faux'], ['PLATFORM_REQ_IP' => ip()]);
$acteur = $pdo->query("SELECT actor_email FROM platform_audit_logs WHERE action = 'operator.login.failed' ORDER BY id DESC LIMIT 1")->fetchColumn();
check($echec['operator'] === null && $acteur === "o'brien@exemple.test", 'échec de connexion : l\'adresse est journalisée telle quelle (pas de &#039;)', (string)$acteur);
$r = http($nomCopie, 'guest', 'POST', "$P/login", ['email' => 'super@exemple.test', 'password' => $mdp], ['PLATFORM_REQ_IP' => ip()]);
check($r['operator'] !== null || $r['mfa_pending'] === true, 'une adresse ordinaire se connecte toujours');
$r = http($nomCopie, 'guest', 'POST', "$P/login", ['email' => str_repeat('a', 300) . '@exemple.test', 'password' => 'faux'], ['PLATFORM_REQ_IP' => ip()]);
check($r['operator'] === null && $r['status'] !== 500 && $r['status'] !== 0, 'adresse démesurée : refusée proprement');

// ═════════════════════════════════════════════════════════════════════
section('4. Export du journal global : trace stricte');
$avant = (int)$pdo->query("SELECT COUNT(*) FROM platform_audit_logs WHERE action = 'audit.exported'")->fetchColumn();
$corps = $tmp . '_export.csv';
$r = http($nomCopie, operateur($superId, 'super_admin'), 'GET', "$P/audit/export", [], ['REQUEST_AS_BODY_OUT' => $corps]);
$csv = is_file($corps) ? (string)file_get_contents($corps) : '';
check($r['status'] === 200 && str_starts_with($csv, "\xEF\xBB\xBFid;date") && (int)$pdo->query("SELECT COUNT(*) FROM platform_audit_logs WHERE action = 'audit.exported'")->fetchColumn() === $avant + 1, 'journal en état : export servi et tracé');
@unlink($corps);
$r = http($nomCopie, operateur($superId, 'super_admin'), 'GET', "$P/audit/export", [], ['PLATFORM_REQ_BREAK_AUDIT' => '1', 'REQUEST_AS_BODY_OUT' => $corps]);
$csv = is_file($corps) ? (string)file_get_contents($corps) : '';
check($r['status'] === 503 && !str_contains($csv, 'id;date') && !str_contains($csv, 'operator.login'), 'journal en panne : 503, aucune ligne du journal servie', 'status=' . $r['status']);
@unlink($corps);

// ═════════════════════════════════════════════════════════════════════
section('5. Échec d\'identifiants typé');
$tf = new TF($pdo);
$typed = null;
try { $tf->verifierMotDePasse($sansTf, 'faux'); } catch (TwoFactorException $e) { $typed = $e; }
check($typed !== null && $typed->estEchecIdentifiants(), 'mauvais mot de passe : exception marquée « échec d\'identifiants »');
check(!(new TwoFactorException('Code incorrect.'))->estEchecIdentifiants(), 'une exception ordinaire ne compte jamais, quel que soit son texte (« Code incorrect. »)');
$typed = null;
try { $tf->activer($sansTf, TF::nouveauSecret(), '000000'); } catch (TwoFactorException $e) { $typed = $e; }
check($typed !== null && $typed->estEchecIdentifiants(), 'activation avec un faux code : marquée « échec d\'identifiants »');
$typed = null;
try { $tf->verifierMotDePasse($sansTf, $mdp); $ok = true; } catch (TwoFactorException) { $ok = false; }
check($ok, 'bon mot de passe : accepté');
$typed = null;
try { $tf->desactiver($sansTf, $mdp, '123456'); } catch (TwoFactorException $e) { $typed = $e; }
check($typed !== null && $typed->estEchecIdentifiants() && $typed->getMessage() === 'Code incorrect.', 'compte sans 2FA : code refusé comme « incorrect » et compté comme un essai (aucune indication sur la cause)');

section('Point 6 : changement de mot de passe, seule une erreur d\'authentification compte pour le verrouillage');
$cpt = $ops->creer('cpt@exemple.test', 'Compte Test', 'support', $mdp, PlatformOperatorService::ACTEUR_CLI)['id'];
$C = operateur($cpt, 'support');
$nouveau = 'Hn4$kWp8!zRq5#Ly';
for ($i = 0; $i < 6; $i++) {
    http($nomCopie, $C, 'POST', "$P/compte/mot-de-passe", ['current_password' => $mdp, 'new_password' => $nouveau, 'new_password_confirmation' => 'autre'], ['PLATFORM_REQ_IP' => ip()]);
}
http($nomCopie, $C, 'POST', "$P/compte/mot-de-passe", ['current_password' => $mdp, 'new_password' => $nouveau, 'new_password_confirmation' => $nouveau], ['PLATFORM_REQ_IP' => ip()]);
$hash = (string)$pdo->query("SELECT password_hash FROM platform_operators WHERE id = $cpt")->fetchColumn();
check(password_verify($nouveau, $hash), '6 confirmations mal tapées ne verrouillent pas : le changement correct réussit ensuite');
for ($i = 0; $i < 6; $i++) {
    http($nomCopie, $C, 'POST', "$P/compte/mot-de-passe", ['current_password' => 'faux', 'new_password' => $mdp, 'new_password_confirmation' => $mdp], ['PLATFORM_REQ_IP' => ip()]);
}
http($nomCopie, $C, 'POST', "$P/compte/mot-de-passe", ['current_password' => $nouveau, 'new_password' => $mdp, 'new_password_confirmation' => $mdp], ['PLATFORM_REQ_IP' => ip()]);
$hash = (string)$pdo->query("SELECT password_hash FROM platform_operators WHERE id = $cpt")->fetchColumn();
check(password_verify($nouveau, $hash) && !password_verify($mdp, $hash), 'mots de passe actuels erronés répétés : la page est bien verrouillée');
section('Point 7 : un tiers ne peut pas bloquer un opérateur connu depuis d\'autres adresses IP');
$cible = $ops->creer('cible@exemple.test', 'Cible Test', 'support', $mdp, PlatformOperatorService::ACTEUR_CLI)['id'];
for ($k = 0; $k < 4; $k++) {
    $ipAttaque = ip();
    for ($i = 0; $i < 5; $i++) {
        http($nomCopie, 'guest', 'POST', "$P/login", ['email' => 'cible@exemple.test', 'password' => 'faux'], ['PLATFORM_REQ_IP' => $ipAttaque]);
    }
}   // 20 échecs répartis sur 4 IP : au-dessus de l'ancien plafond par e-mail (15)
$r = http($nomCopie, 'guest', 'POST', "$P/login", ['email' => 'cible@exemple.test', 'password' => $mdp], ['PLATFORM_REQ_IP' => ip()]);
check($r['operator'] !== null && (int)$r['operator']['id'] === $cible, 'l\'opérateur se connecte depuis son IP malgré 20 échecs répartis sur d\'autres IP', json_encode($r));
$ipMeme = ip();
for ($i = 0; $i < 5; $i++) {
    http($nomCopie, 'guest', 'POST', "$P/login", ['email' => 'cible@exemple.test', 'password' => 'faux'], ['PLATFORM_REQ_IP' => $ipMeme]);
}
$r = http($nomCopie, 'guest', 'POST', "$P/login", ['email' => 'cible@exemple.test', 'password' => $mdp], ['PLATFORM_REQ_IP' => $ipMeme]);
check($r['operator'] === null, 'depuis une même IP, 5 échecs verrouillent bien le couple e-mail+IP');
check(\App\Controllers\PlatformAuthController::LIMITE_PAR_EMAIL > 15, 'plafond par e-mail seul relevé au-dessus de l\'ancienne valeur');
$ctrlSrc = (string)file_get_contents(ROOT_PATH . '/app/Controllers/PlatformAccountController.php') . (string)file_get_contents(ROOT_PATH . '/app/Controllers/PlatformAuthController.php');
check(strpos($ctrlSrc, 'new LoginThrottle') === false, 'point 10 : contrôleurs de connexion et de compte passent par throttle()');

echo "\n";
echo $failed === 0 ? "\033[32m$passed OK, 0 KO\033[0m\n" : "\033[31m$passed OK, $failed KO\033[0m\n";
exit($failed === 0 ? 0 : 1);

/** Connexion dont toute requête échoue : simule une base momentanément illisible. */
class PdoQuiEchoue extends PDO
{
    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
    {
        throw new PDOException('base momentanément illisible');
    }
}
