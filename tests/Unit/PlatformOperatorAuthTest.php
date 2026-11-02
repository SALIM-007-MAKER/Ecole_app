<?php
/**
 * Tests — opérateurs de la plateforme : identité INDÉPENDANTE des comptes d'établissement (étape 1 du Super-Admin SaaS).
 *
 * 1. Migration T064 : colonnes d'identité, user_id nullable, journal append-only, nature de triggered_by, aucun compte par défaut
 * 2. Politique de mot de passe opérateur
 * 3. Création d'un opérateur (service) : hachage, aucune donnée en clair, audit sans mot de passe
 * 4. Authentification : aucun lien avec `users` (même e-mail/mot de passe d'un compte d'école refusé), comptes inactifs ou anciens refusés
 * 5. Changement de mot de passe, récupération d'accès (réinitialisation), révocation
 * 6. Session opérateur : expiration par inactivité et durée maximale, changement de mot de passe obligatoire
 * 7. Script database/create-operator.php RÉELLEMENT exécuté (premier opérateur, mot de passe faible refusé, généré affiché une fois, récupération)
 * 8. Parcours HTTP complet du portail (connexion, refus, changement obligatoire, limitation des essais)
 *
 * Travaille uniquement sur une copie jetable ecole_app_copy_* : la base vivante n'est jamais touchée.
 *
 * Lance : C:\wamp64\bin\php\php8.2.29\php.exe tests/Unit/PlatformOperatorAuthTest.php
 */

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__, 2));
foreach (file(ROOT_PATH . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $l) {
    if (str_starts_with(trim($l), '#') || !str_contains($l, '=')) { continue; }
    [$k, $v] = explode('=', $l, 2);
    $_ENV[trim($k)] = trim($v, " \t\n\r\0\x0B\"'");
}
spl_autoload_register(function (string $c): void {
    foreach (['Core\\' => '/core/', 'App\\Shared\\' => '/app/Shared/'] as $prefix => $dir) {
        if (str_starts_with($c, $prefix)) {
            $f = ROOT_PATH . $dir . str_replace('\\', '/', substr($c, strlen($prefix))) . '.php';
            if (is_file($f)) { require $f; return; }
        }
    }
});
require_once ROOT_PATH . '/tests/Support/DbCopy.php';

use Core\Database;
use Core\Platform\PlatformAuditService;
use Core\Platform\PlatformAuth;
use Core\Platform\PlatformOperatorService;
use Core\Platform\PlatformPasswordPolicy;

$passed = 0; $failed = 0;
function check(bool $ok, string $label, string $detail = ''): void
{
    global $passed, $failed;
    if ($ok) { $passed++; echo "\033[32m  ✓ $label\033[0m\n"; }
    else     { $failed++; echo "\033[31m  ✗ $label" . ($detail !== '' ? " — $detail" : '') . "\033[0m\n"; }
}
function section(string $t): void { echo "\n\033[33m── $t\033[0m\n"; }
/** @return array{0:int,1:string} code de sortie, sortie standard+erreur */
function lancer(array $cmd, array $env = []): array
{
    $p = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, ROOT_PATH, array_merge(getenv(), $env));
    $out = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
    return [proc_close($p), $out];
}
/** Requête HTTP simulée du portail plateforme. @return array<string,mixed> */
function http(string $nomCopie, string $operateur, string $methode, string $uri, array $post = [], array $env = []): array
{
    [$code, $sortie] = lancer([PHP_BINARY, ROOT_PATH . '/tests/Support/platform_request.php', $operateur, $methode, $uri, json_encode($post)],
        ['REQUEST_AS_DB' => $nomCopie, 'PLATFORM_REQ_AUDIT_DIR' => sys_get_temp_dir()] + $env);
    return preg_match('/^RESULT:(.*)$/m', $sortie, $m) ? (json_decode($m[1], true) ?: ['status' => 0]) : ['status' => 0, 'brut' => $sortie, 'code' => $code];
}

$_SESSION = [];
$copie = \Tests\Support\DbCopy::redirigerVersCopie(ROOT_PATH) ?? exit("ECHEC : copie jetable impossible (mysql/mysqldump ou droit CREATE DATABASE)\n");
$nomCopie = $copie->connection()['db'];
$pdo = Database::getInstance()->getConnection();
$dossierSecours = sys_get_temp_dir() . '/pal_test_' . bin2hex(random_bytes(4));
PlatformAuditService::definirDossierSecours($dossierSecours);
$svc = new PlatformOperatorService($pdo);
$colonne = static fn(string $t, string $c) => $pdo->query("SELECT COLUMN_TYPE, IS_NULLABLE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$t' AND COLUMN_NAME = '$c'")->fetch(PDO::FETCH_ASSOC);

// ══════════════════════════════════════════════════════════════════════
section('1. Migration T064');
// ══════════════════════════════════════════════════════════════════════
foreach (['email', 'nom', 'password_hash', 'must_change_password', 'password_changed_at', 'last_login_at'] as $c) {
    check($colonne('platform_operators', $c) !== false, "platform_operators.$c existe");
}
check(($colonne('platform_operators', 'user_id')['IS_NULLABLE'] ?? '') === 'YES', 'user_id est NULLABLE : un opérateur n\'a aucun lien obligatoire avec users');
check($colonne('platform_backups', 'triggered_by_kind') !== false && $colonne('platform_restores', 'triggered_by_kind') !== false, 'triggered_by_kind existe sur platform_backups et platform_restores');
check((int)$pdo->query("SELECT COUNT(*) FROM platform_backups WHERE triggered_by_kind <> 'user'")->fetchColumn() === 0, 'anciennes sauvegardes : triggered_by_kind = user (données conservées)');
check((int)$pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TRIGGERS WHERE TRIGGER_SCHEMA = DATABASE() AND EVENT_OBJECT_TABLE = 'platform_audit_logs'")->fetchColumn() === 2, 'deux déclencheurs protègent platform_audit_logs');
check((int)$pdo->query('SELECT COUNT(*) FROM platform_operators WHERE password_hash IS NOT NULL')->fetchColumn() === 0, 'AUCUN opérateur avec mot de passe n\'existe dans une base neuve (aucun compte par défaut)');
check($svc->compterUtilisables() === 0, 'aucun opérateur utilisable tant que le premier n\'est pas créé');
$migr = require ROOT_PATH . '/database/migrations/T064_operateurs_independants_audit_global.php';
$avant = $pdo->query('SELECT COUNT(*) FROM platform_operators')->fetchColumn();
try { ($migr['run'])($pdo); $idem = true; } catch (Throwable $e) { $idem = false; }
check($idem && $avant == $pdo->query('SELECT COUNT(*) FROM platform_operators')->fetchColumn(), 'T064 est idempotente (rejouée sans erreur ni perte)');

// ══════════════════════════════════════════════════════════════════════
section('2. Politique de mot de passe opérateur');
// ══════════════════════════════════════════════════════════════════════
foreach (['password' => 'mot courant', 'Court1!a' => 'trop court', 'toutenminuscule' => '1 seul type', 'Edunova-Plateforme-9' => 'mot prévisible', 'aaaa-BBBB-1111-xx' => 'répétition', 'MonSuperAdmin-12345' => 'admin'] as $mdp => $raison) {
    check(PlatformPasswordPolicy::valider($mdp, 'moi@exemple.test', 'Awa Diallo') !== [], "refusé ($raison) : $mdp");
}
check(PlatformPasswordPolicy::valider('moi@exemple.test-Zq7!pLm2', 'moi@exemple.test') !== [], 'refusé : contient l\'adresse e-mail');
check(PlatformPasswordPolicy::valider('Diallo-Zq7!pLm2#vX', 'a@b.test', 'Awa Diallo') !== [], 'refusé : contient le nom');
check(PlatformPasswordPolicy::valider('Zq7!pLm2#vXk9@Rt', 'moi@exemple.test', 'Awa Diallo') === [], 'accepté : mot de passe fort');
$tousValides = true; $distincts = [];
for ($i = 0; $i < 30; $i++) { $g = PlatformPasswordPolicy::generer(); $distincts[$g] = 1; if (PlatformPasswordPolicy::valider($g) !== [] || strlen($g) < 20) { $tousValides = false; } }
check($tousValides && count($distincts) === 30, '30 mots de passe générés : tous conformes, longs et tous différents');

// ══════════════════════════════════════════════════════════════════════
section('3. Création d\'un opérateur');
// ══════════════════════════════════════════════════════════════════════
$fort = 'Zq7!pLm2#vXk9@Rt';
$r = $svc->creer('Premier.Operateur@Exemple.test', 'Awa Diallo', 'super_admin', $fort, PlatformOperatorService::ACTEUR_CLI);
$row = $svc->trouverParId($r['id']);
check($r['password'] === null && $row['email'] === 'premier.operateur@exemple.test', 'e-mail normalisé en minuscules ; pas de mot de passe renvoyé quand il est fourni');
check($row['user_id'] === null, 'user_id NULL : aucune dépendance envers un compte d\'établissement');
check($row['password_hash'] !== $fort && password_verify($fort, $row['password_hash']) && str_starts_with($row['password_hash'], '$2y$'), 'mot de passe haché (bcrypt), jamais stocké en clair');
check((int)$row['must_change_password'] === 0, 'mot de passe choisi par l\'opérateur : pas de changement obligatoire');
$g = $svc->creer('genere@exemple.test', 'Opérateur Généré', 'support', null, PlatformOperatorService::ACTEUR_CLI);
check($g['password'] !== null && PlatformPasswordPolicy::valider($g['password']) === [] && (int)$svc->trouverParId($g['id'])['must_change_password'] === 1, 'mot de passe généré conforme, renvoyé une fois, changement obligatoire');
foreach ([['premier.operateur@exemple.test', 'Doublon', 'support', $fort, 'e-mail déjà utilisé'], ['pas-un-email', 'Awa', 'support', $fort, 'e-mail invalide'],
          ['x@exemple.test', 'Awa', 'root', $fort, 'niveau invalide'], ['y@exemple.test', 'Awa', 'support', 'password', 'mot de passe faible'], ['z@exemple.test', 'A', 'support', $fort, 'nom trop court']] as [$e, $n, $nv, $m, $raison]) {
    try { $svc->creer($e, $n, $nv, $m, PlatformOperatorService::ACTEUR_CLI); check(false, "création refusée : $raison"); }
    catch (InvalidArgumentException) { check(true, "création refusée : $raison"); }
}
$journal = $pdo->query("SELECT * FROM platform_audit_logs WHERE action = 'operator.created' ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
check(count($journal) === 2 && $journal[0]['actor_type'] === 'system' && $journal[0]['actor_email'] === 'cli', 'création inscrite au journal d\'audit global (acteur système/cli)');
check(!str_contains(json_encode($journal), $fort) && !str_contains(json_encode($journal), $g['password']), 'aucun mot de passe (ni fourni ni généré) dans le journal');

// ══════════════════════════════════════════════════════════════════════
section('4. Authentification — indépendante de `users`');
// ══════════════════════════════════════════════════════════════════════
$op = $svc->authentifier('PREMIER.operateur@exemple.test ', $fort);
check($op !== null && $op['niveau'] === 'super_admin' && $op['must_change'] === false, 'identifiants valides acceptés (e-mail insensible à la casse)');
check($svc->trouverParId($r['id'])['last_login_at'] !== null, 'dernière connexion enregistrée');
check($svc->authentifier('premier.operateur@exemple.test', $fort . 'x') === null, 'mauvais mot de passe refusé');
check($svc->authentifier('inconnu@exemple.test', $fort) === null, 'compte inconnu refusé');
check($svc->authentifier('premier.operateur@exemple.test', '') === null, 'mot de passe vide refusé');
// Un compte d'établissement (users) ne donne AUCUN accès opérateur, même avec une ligne platform_operators ancienne liée à lui.
$mdpEcole = 'Mot-De-Passe-Ecole-77!';
$pdo->prepare("INSERT INTO users (nom, email, password, role, actif, etablissement_id) VALUES ('Admin École', 'admin-ecole@exemple.test', ?, 'admin', 1, 1)")->execute([password_hash($mdpEcole, PASSWORD_BCRYPT)]);
$uid = (int)$pdo->lastInsertId();
$pdo->exec("INSERT INTO platform_operators (user_id, niveau, actif) VALUES ($uid, 'super_admin', 1)");   // ancien modèle : opérateur lié à un compte d'établissement
check($svc->authentifier('admin-ecole@exemple.test', $mdpEcole) === null, 'l\'e-mail et le mot de passe d\'un administrateur d\'école ne donnent AUCUN accès opérateur (même lié à platform_operators)');
$pdo->exec("UPDATE platform_operators SET email = 'admin-ecole@exemple.test' WHERE user_id = $uid");
check($svc->authentifier('admin-ecole@exemple.test', $mdpEcole) === null, 'ancienne ligne sans mot de passe propre : connexion impossible même avec l\'e-mail du compte d\'école');
$pdo->prepare('UPDATE platform_operators SET actif = 0 WHERE id = ?')->execute([$r['id']]);
check($svc->authentifier('premier.operateur@exemple.test', $fort) === null, 'opérateur désactivé : connexion refusée avec le bon mot de passe');
$pdo->prepare('UPDATE platform_operators SET actif = 1 WHERE id = ?')->execute([$r['id']]);
$t0 = microtime(true); for ($i = 0; $i < 3; $i++) { $svc->authentifier('absent@exemple.test', 'Zq7!pLm2#vXk9@Rt'); } $tInconnu = (microtime(true) - $t0) / 3;
$t0 = microtime(true); for ($i = 0; $i < 3; $i++) { $svc->authentifier('premier.operateur@exemple.test', 'mauvais-mot-de-passe'); } $tConnu = (microtime(true) - $t0) / 3;
check($tInconnu > 0.3 * $tConnu, 'un compte inconnu coûte un calcul de hachage (pas d\'énumération des comptes par le temps de réponse)', sprintf('inconnu=%.0f ms, connu=%.0f ms', $tInconnu * 1000, $tConnu * 1000));

// ══════════════════════════════════════════════════════════════════════
section('5. Mot de passe, récupération d\'accès, révocation');
// ══════════════════════════════════════════════════════════════════════
check($svc->changerMotDePasse($r['id'], 'faux-actuel', 'Nouveau-Mdp-Tres-Solide-8!', 'Nouveau-Mdp-Tres-Solide-8!') !== [], 'changement refusé : mot de passe actuel incorrect');
check((int)$pdo->query("SELECT COUNT(*) FROM platform_audit_logs WHERE action = 'operator.password_change_failed' AND resultat = 'failure'")->fetchColumn() === 1, 'échec de changement inscrit au journal');
check($svc->changerMotDePasse($r['id'], $fort, 'faible', 'faible') !== [], 'changement refusé : nouveau mot de passe faible');
check($svc->changerMotDePasse($r['id'], $fort, 'Nouveau-Mdp-Tres-Solide-8!', 'autre-confirmation') !== [], 'changement refusé : confirmation différente');
check($svc->changerMotDePasse($r['id'], $fort, $fort, $fort) !== [], 'changement refusé : identique à l\'actuel');
$nouveau = 'Nouveau-Mdp-Tres-Solide-8!';
check($svc->changerMotDePasse($r['id'], $fort, $nouveau, $nouveau) === [], 'changement accepté');
check($svc->authentifier('premier.operateur@exemple.test', $fort) === null && $svc->authentifier('premier.operateur@exemple.test', $nouveau) !== null, 'l\'ancien mot de passe ne fonctionne plus, le nouveau oui');
check((int)$pdo->query("SELECT COUNT(*) FROM platform_audit_logs WHERE action = 'operator.password_changed' AND resultat = 'success'")->fetchColumn() === 1, 'changement inscrit au journal');

$svc->definirActif('premier.operateur@exemple.test', false, PlatformOperatorService::ACTEUR_CLI);
check($svc->authentifier('premier.operateur@exemple.test', $nouveau) === null, 'révocation : un opérateur désactivé ne peut plus se connecter (compte et historique conservés)');
$rec = $svc->reinitialiserMotDePasse('premier.operateur@exemple.test', null, PlatformOperatorService::ACTEUR_CLI);
$ligne = $svc->trouverParId($r['id']);
check($rec['password'] !== null && (int)$ligne['actif'] === 1 && (int)$ligne['must_change_password'] === 1, 'récupération : nouveau mot de passe généré, compte réactivé, changement obligatoire');
check($svc->authentifier('premier.operateur@exemple.test', $nouveau) === null && ($svc->authentifier('premier.operateur@exemple.test', $rec['password'])['must_change'] ?? false) === true, 'récupération : l\'ancien mot de passe est invalide, le provisoire impose un changement');
try { $svc->reinitialiserMotDePasse('inconnu@exemple.test', null, PlatformOperatorService::ACTEUR_CLI); check(false, 'récupération d\'un opérateur inconnu refusée'); }
catch (InvalidArgumentException) { check(true, 'récupération d\'un opérateur inconnu refusée'); }
check((int)$pdo->query("SELECT COUNT(*) FROM platform_audit_logs WHERE action IN ('operator.password_reset','operator.deactivated')")->fetchColumn() === 2, 'récupération et révocation inscrites au journal');

// ══════════════════════════════════════════════════════════════════════
section('6. Session opérateur');
// ══════════════════════════════════════════════════════════════════════
$_SESSION = [];
PlatformAuth::login(['id' => 5, 'email' => 'a@b.test', 'nom' => 'A', 'niveau' => 'admin', 'must_change' => true]);
check(PlatformAuth::isLogged() && PlatformAuth::mustChangePassword(), 'session ouverte, changement de mot de passe obligatoire');
PlatformAuth::passwordChanged();
check(!PlatformAuth::mustChangePassword() && PlatformAuth::isLogged(), 'après changement, plus d\'obligation');
$_SESSION['_platform_operator']['_seen'] = time() - PlatformAuth::IDLE_SECONDS - 5;
check(!PlatformAuth::isLogged() && !isset($_SESSION['_platform_operator']), 'inactivité > ' . PlatformAuth::IDLE_SECONDS . ' s : session fermée');
PlatformAuth::login(['id' => 5, 'email' => 'a@b.test', 'nom' => 'A', 'niveau' => 'admin']);
$_SESSION['_platform_operator']['_at'] = time() - PlatformAuth::ABSOLUTE_SECONDS - 5;
check(!PlatformAuth::isLogged(), 'durée maximale > ' . PlatformAuth::ABSOLUTE_SECONDS . ' s : session fermée même si active');
PlatformAuth::login(['id' => 5, 'email' => 'a@b.test', 'nom' => 'A', 'niveau' => 'admin']);
$_SESSION['_platform_operator']['_seen'] = time() - PlatformAuth::IDLE_SECONDS + 60;
check(PlatformAuth::isLogged(), 'activité récente : session maintenue');
$_SESSION = [];

// ══════════════════════════════════════════════════════════════════════
section('7. Script database/create-operator.php exécuté pour de vrai');
// ══════════════════════════════════════════════════════════════════════
$pdo->exec('DELETE FROM platform_operators');   // base de test : on repart sans opérateur (situation d'une installation neuve)
$script = ROOT_PATH . '/database/create-operator.php';
$baseCli = (int)$pdo->query("SELECT COUNT(*) FROM platform_audit_logs WHERE actor_email = 'cli' AND action IN ('operator.created','operator.password_reset','operator.deactivated')")->fetchColumn();
$envCli = ['OPERATOR_DB' => $nomCopie];
[$c, $o] = lancer([PHP_BINARY, $script, '--email=autre@exemple.test', '--nom=Awa Diallo'], $envCli + ['OPERATOR_PASSWORD' => 'password']);
check($c === 2 && str_contains($o, 'ECHEC') && (int)$pdo->query('SELECT COUNT(*) FROM platform_operators')->fetchColumn() === 0, 'mot de passe faible refusé par le script, aucun compte créé');
[$c, $o] = lancer([PHP_BINARY, $script, '--email=autre@exemple.test', '--nom=Awa Diallo', '--password=Zq7!pLm2#vXk9@Rt'], $envCli);
check($c === 2 && str_contains($o, 'OPERATOR_PASSWORD'), 'le mot de passe en argument de ligne de commande est refusé');
[$c, $o] = lancer([PHP_BINARY, $script, '--email=pas-un-email', '--nom=Awa Diallo'], $envCli);
check($c === 2, 'e-mail invalide refusé');
[$c, $o] = lancer([PHP_BINARY, $script, '--email=x@exemple.test', '--nom=X Y'], ['OPERATOR_DB' => 'ecole_app']);
check($c === 2 && str_contains($o, 'REFUS'), 'le script refuse de cibler une autre base que les bases de test via OPERATOR_DB');
[$c, $o] = lancer([PHP_BINARY, $script, '--email=premier@exemple.test', '--nom=Awa Diallo'], $envCli + ['OPERATOR_PASSWORD' => 'Zq7!pLm2#vXk9@Rt']);
check($c === 0 && str_contains($o, 'créé') && !str_contains($o, 'Zq7!pLm2'), 'premier opérateur créé avec un mot de passe fort fourni (non réaffiché)', trim($o));
check($svc->authentifier('premier@exemple.test', 'Zq7!pLm2#vXk9@Rt') !== null, 'le premier opérateur créé par le script s\'authentifie réellement');
[$c, $o] = lancer([PHP_BINARY, $script, '--email=premier@exemple.test', '--nom=Doublon'], $envCli);
check($c === 2 && str_contains($o, 'existe déjà'), 'création d\'un doublon refusée');
[$c, $o] = lancer([PHP_BINARY, $script, '--email=genere@exemple.test', '--nom=Compte Généré', '--niveau=admin'], $envCli);
preg_match('/affiché une seule fois[^:]*: (\S+)/u', $o, $m);
$mdpGenere = $m[1] ?? '';
check($c === 0 && $mdpGenere !== '' && PlatformPasswordPolicy::valider($mdpGenere) === [], 'sans OPERATOR_PASSWORD : mot de passe aléatoire conforme, affiché une seule fois');
$auth = $svc->authentifier('genere@exemple.test', $mdpGenere);
check($auth !== null && $auth['must_change'] === true && $auth['niveau'] === 'admin', 'le mot de passe généré fonctionne et impose un changement à la première connexion');
$pdo->exec("UPDATE platform_operators SET actif = 0 WHERE email = 'genere@exemple.test'");
[$c, $o] = lancer([PHP_BINARY, $script, '--reinitialiser', '--email=genere@exemple.test'], $envCli);
preg_match('/affiché une seule fois\) : (\S+)/u', $o, $m2);
check($c === 0 && ($m2[1] ?? '') !== '' && $svc->authentifier('genere@exemple.test', $m2[1])['must_change'] === true, 'récupération d\'accès par le script : compte réactivé, nouveau mot de passe provisoire');
[$c, $o] = lancer([PHP_BINARY, $script, '--desactiver', '--email=genere@exemple.test'], $envCli);
check($c === 0 && $svc->authentifier('genere@exemple.test', $m2[1]) === null, 'désactivation par le script : connexion refusée');
[$c, $o] = lancer([PHP_BINARY, $script, '--lister'], $envCli);
check($c === 0 && str_contains($o, 'premier@exemple.test') && str_contains($o, 'Utilisables : 1'), 'inventaire des opérateurs', trim($o));
check((int)$pdo->query("SELECT COUNT(*) FROM platform_audit_logs WHERE actor_email = 'cli' AND action IN ('operator.created','operator.password_reset','operator.deactivated')")->fetchColumn() - $baseCli === 4,
      'toutes les opérations du script sont inscrites au journal (acteur cli)');

// ══════════════════════════════════════════════════════════════════════
section('8. Parcours HTTP du portail');
// ══════════════════════════════════════════════════════════════════════
$pdo->exec("UPDATE platform_operators SET actif = 1 WHERE email = 'genere@exemple.test'");
$emailHttp = 'premier-' . bin2hex(random_bytes(4)) . '@exemple.test';   // unique : la limitation des essais est conservée entre deux exécutions
$pdo->prepare("UPDATE platform_operators SET email = ? WHERE email = 'premier@exemple.test'")->execute([$emailHttp]);
$ip = '10.' . random_int(1, 250) . '.' . random_int(1, 250) . '.' . random_int(1, 250);
$mdp = 'Zq7!pLm2#vXk9@Rt';
$r = http($nomCopie, 'guest', 'GET', '/ecole_app/platform/login');
check($r['status'] === 200 && str_contains($r['text'], 'Connexion opérateur'), 'page de connexion accessible');
$r = http($nomCopie, 'guest', 'GET', '/ecole_app/platform/dashboard');
check($r['status'] === 302 && str_contains((string)$r['location'], '/platform/login'), 'tableau de bord sans session : redirigé vers la connexion');
$r = http($nomCopie, 'guest', 'GET', '/ecole_app/platform/audit');
check($r['status'] === 302 && str_contains((string)$r['location'], '/platform/login'), 'journal d\'audit sans session : redirigé vers la connexion');
$r = http($nomCopie, 'guest', 'POST', '/ecole_app/platform/login', ['email' => $emailHttp, 'password' => 'mauvais-Mdp-123!'], ['PLATFORM_REQ_IP' => $ip]);
check($r['status'] === 302 && str_contains((string)$r['location'], '/platform/login') && $r['operator'] === null, 'mauvais mot de passe : refusé, aucune session opérateur');
$r = http($nomCopie, 'guest', 'POST', '/ecole_app/platform/login', ['email' => 'admin-ecole@exemple.test', 'password' => $mdpEcole], ['PLATFORM_REQ_IP' => $ip]);
check($r['operator'] === null, 'identifiants d\'un administrateur d\'école : refusés sur le portail plateforme');
$r = http($nomCopie, 'guest', 'POST', '/ecole_app/platform/login', ['email' => $emailHttp, 'password' => $mdp], ['PLATFORM_REQ_IP' => $ip, 'REQUEST_AS_NO_CSRF' => '1']);
check($r['operator'] === null, 'connexion sans jeton CSRF : refusée');
$r = http($nomCopie, 'guest', 'POST', '/ecole_app/platform/login', ['email' => $emailHttp, 'password' => $mdp], ['PLATFORM_REQ_IP' => $ip]);
check($r['status'] === 302 && str_contains((string)$r['location'], '/platform/dashboard') && ($r['operator']['niveau'] ?? '') === 'super_admin', 'connexion valide : session opérateur super_admin, redirection vers le tableau de bord', json_encode($r));
$compte = $pdo->query("SELECT action, resultat, actor_type, actor_email FROM platform_audit_logs WHERE action LIKE 'operator.login.%' ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
$actions = array_column($compte, 'action');
check(in_array('operator.login.failed', $actions, true) && in_array('operator.login.success', $actions, true), 'échecs et succès de connexion inscrits au journal global');
check(!str_contains(json_encode($compte), $mdp) && !str_contains(json_encode($compte), 'mauvais-Mdp'), 'aucun mot de passe saisi dans le journal');
$op = json_encode(['id' => 99, 'niveau' => 'admin', 'must_change' => true]);
$r = http($nomCopie, $op, 'GET', '/ecole_app/platform/dashboard');
check($r['status'] === 302 && str_contains((string)$r['location'], '/platform/compte/mot-de-passe'), 'mot de passe provisoire : toute page est redirigée vers le changement de mot de passe');
$r = http($nomCopie, $op, 'GET', '/ecole_app/platform/compte/mot-de-passe');
check($r['status'] === 200 && str_contains($r['text'], 'Mon mot de passe'), 'la page de changement de mot de passe reste accessible');
// Limitation des essais : 5 échecs par couple e-mail+IP puis blocage, même avec le bon mot de passe.
$ip2 = '10.' . random_int(1, 250) . '.' . random_int(1, 250) . '.' . random_int(1, 250);
for ($i = 0; $i < 5; $i++) { http($nomCopie, 'guest', 'POST', '/ecole_app/platform/login', ['email' => $emailHttp, 'password' => "faux-$i-Mdp!"], ['PLATFORM_REQ_IP' => $ip2]); }
$r = http($nomCopie, 'guest', 'POST', '/ecole_app/platform/login', ['email' => $emailHttp, 'password' => $mdp], ['PLATFORM_REQ_IP' => $ip2]);
check($r['operator'] === null && (int)$pdo->query("SELECT COUNT(*) FROM platform_audit_logs WHERE action = 'operator.login.throttled'")->fetchColumn() >= 1, 'après 5 échecs : connexion bloquée même avec le bon mot de passe, blocage inscrit au journal');

echo "\n$passed OK, $failed KO\n";
exit($failed ? 1 : 0);
