<?php
/**
 * Tests — alertes de sécurité par e-mail aux opérateurs de la plateforme.
 *
 * 1. Disponibilité : inactif sans SMTP configuré, actif avec un envoyeur ; rien n'est tenté ni journalisé en échec quand c'est inactif
 * 2. Mot de passe : modifié par l'opérateur, réinitialisé par un administrateur (le mot de passe n'est jamais dans le message)
 * 3. Double authentification : activée, codes régénérés, désactivée, réinitialisée (ni secret ni code de secours dans le message)
 * 4. Robustesse : envoi refusé ou en exception = opération réussie + échec au journal ; opérateur sans e-mail ou inactif ; transaction annulée
 * 5. Contenu : date, adresse IP, navigateur, aucun lien, caractères de contrôle neutralisés
 * 6. Parcours HTTP : connexion (réglage « alerte à chaque connexion »), code de secours, aucune alerte sur un échec de connexion
 *
 * Travaille uniquement sur une copie jetable ecole_app_copy_* : la base vivante n'est jamais touchée. Aucun e-mail réel n'est envoyé.
 *
 * Lance : C:\wamp64\bin\php\php8.2.29\php.exe tests/Unit/PlatformSecurityNotifierTest.php
 */

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__, 2));
foreach (file(ROOT_PATH . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $l) {
    if (str_starts_with(trim($l), '#') || !str_contains($l, '=')) { continue; }
    [$k, $v] = explode('=', $l, 2);
    $_ENV[trim($k)] = trim($v, " \t\n\r\0\x0B\"'");
}
// Aucun SMTP réel, quel que soit le .env de la machine ; réglages de la plateforme neutres.
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
use Core\Platform\PlatformSecurityNotifier as N;
use Core\Platform\PlatformSettings;
use Core\Platform\PlatformTwoFactorService as TF;

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
    global $dossierSecours, $dossierThrottle, $sink;
    [$code, $sortie] = lancer([PHP_BINARY, ROOT_PATH . '/tests/Support/platform_request.php', $operateur, $methode, $uri, json_encode($post)],
        array_merge(['REQUEST_AS_DB' => $nomCopie, 'PLATFORM_REQ_AUDIT_DIR' => $dossierSecours, 'PLATFORM_THROTTLE_DIR' => $dossierThrottle, 'PLATFORM_REQ_MAIL_SINK' => $sink], $env));
    return preg_match('/^RESULT:(.*)$/m', $sortie, $m) ? (json_decode($m[1], true) ?: ['status' => 0]) : ['status' => 0, 'brut' => $sortie, 'code' => $code];
}
function ip(): string { return '10.' . random_int(1, 250) . '.' . random_int(1, 250) . '.' . random_int(1, 250); }

$_SESSION = [];
$copie = \Tests\Support\DbCopy::redirigerVersCopie(ROOT_PATH) ?? exit("ECHEC : copie jetable impossible (mysql/mysqldump ou droit CREATE DATABASE)\n");
$nomCopie = $copie->connection()['db'];
$pdo = Database::getInstance()->getConnection();
$tmp = sys_get_temp_dir() . '/psn_' . bin2hex(random_bytes(4));
$dossierSecours = $tmp . '_secours'; $dossierThrottle = $tmp . '_thr'; $sink = $tmp . '_sink.jsonl';
PlatformAuditService::definirDossierSecours($dossierSecours);
$ops = new PlatformOperatorService($pdo);
$mdp = 'Zq7!pLm2#vXk9@Rt';
$superId = $ops->creer('super@exemple.test', 'Super Opérateur', 'super_admin', $mdp, PlatformOperatorService::ACTEUR_CLI)['id'];
$autreId = $ops->creer('autre@exemple.test', 'Autre Opérateur', 'super_admin', $mdp, PlatformOperatorService::ACTEUR_CLI)['id'];
$adminId = $ops->creer('admin@exemple.test', 'Admin Opérateur', 'admin', $mdp, PlatformOperatorService::ACTEUR_CLI)['id'];

/** @var list<array{to:string,objet:string,html:string}> $recus */
$recus = [];
$mode = 'ok';   // ok | refus | exception
$envoyeur = static function (string $to, string $objet, string $html) use (&$recus, &$mode): bool {
    if ($mode === 'exception') { throw new \RuntimeException('serveur SMTP injoignable'); }
    if ($mode === 'refus') { return false; }
    $recus[] = ['to' => $to, 'objet' => $objet, 'html' => $html];
    return true;
};
$audits = static fn(string $like) => $pdo->query('SELECT * FROM platform_audit_logs WHERE action LIKE ' . $pdo->quote($like) . ' ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
$texte = static fn(array $m): string => html_entity_decode(strip_tags(str_replace(['<br />', '<br>'], "\n", $m['html'])), ENT_QUOTES, 'UTF-8');
$maintenant = time();   // horloge de test pilotée : chaque code TOTP utilisé doit être plus récent que le précédent (anti-rejeu)
$horloge = static function () use (&$maintenant): int { return $maintenant; };
$code = static function (string $secret) use (&$maintenant): string { $maintenant += 30; return TF::totp($secret, intdiv($maintenant, 30)); };

// ═════════════════════════════════════════════════════════════════════
section('1. Disponibilité');
check(N::disponible() === false, 'sans SMTP configuré : alertes inactives');
$avant = count($audits('operator.notification.failed'));
$ops->changerMotDePasse($superId, $mdp, 'Nv9!kLm2#vXq7@Zt', 'Nv9!kLm2#vXq7@Zt');
$mdp = 'Nv9!kLm2#vXq7@Zt';
check($recus === [] && count($audits('operator.notification.failed')) === $avant, 'inactives : rien envoyé, aucun échec journalisé (pas de bruit)');
N::definirEnvoyeur($envoyeur);
check(N::disponible() === true, 'avec un envoyeur : actives');

// ═════════════════════════════════════════════════════════════════════
section('2. Mot de passe');
$recus = [];
$nouveau = 'Qw8!rTy3#uIo6@Pa';
$r = $ops->changerMotDePasse($superId, $mdp, $nouveau, $nouveau);
$mdp = $nouveau;
check($r === [] && count($recus) === 1 && $recus[0]['to'] === 'super@exemple.test' && str_contains($recus[0]['objet'], 'mot de passe'), 'changement : un e-mail à l\'opérateur concerné');
check(!str_contains($recus[0]['html'], $nouveau) && !str_contains($recus[0]['html'], 'Zq7!'), 'changement : le mot de passe n\'est pas dans le message');
$recus = [];
$r = $ops->changerMotDePasse($superId, 'mauvais', 'Aa1!bbbbbbbbbbbb', 'Aa1!bbbbbbbbbbbb');
check($r !== [] && $recus === [], 'changement refusé (ancien mot de passe faux) : aucun e-mail');
$r = $ops->changerMotDePasse($superId, $mdp, 'court', 'court');
check($r !== [] && $recus === [], 'changement refusé (mot de passe faible) : aucun e-mail');
$res = $ops->reinitialiserMotDePasse('autre@exemple.test', null, PlatformOperatorService::ACTEUR_CLI);
check(count($recus) === 1 && $recus[0]['to'] === 'autre@exemple.test' && str_contains($recus[0]['objet'], 'réinitialisé'), 'réinitialisation par un administrateur : e-mail à l\'opérateur concerné');
check($res['password'] !== null && !str_contains($recus[0]['html'], (string)$res['password']), 'réinitialisation : le mot de passe généré n\'est pas dans le message');
$mdpAutre = (string)$res['password'];

// ═════════════════════════════════════════════════════════════════════
section('3. Double authentification');
$tf = new TF($pdo, $horloge);
$recus = [];
$secret = TF::nouveauSecret();
$codes = $tf->activer($superId, $secret, $code($secret));
check(count($recus) === 1 && $recus[0]['to'] === 'super@exemple.test' && str_contains($recus[0]['objet'], 'activée'), 'activation : un e-mail');
$tout = $recus[0]['html'];
$fuite = str_contains($tout, $secret);
foreach ($codes as $c) { $fuite = $fuite || str_contains($tout, $c); }
check(!$fuite, 'activation : ni le secret ni les codes de secours dans le message');
$recus = [];
try { $tf->activer($adminId, TF::nouveauSecret(), '000000'); } catch (\Throwable) {}
check($recus === [], 'activation refusée (mauvais code) : aucun e-mail');

$recus = [];
$tf->regenererCodes($superId, $code($secret));
check(count($recus) === 1 && str_contains($recus[0]['objet'], 'codes de secours'), 'régénération des codes : un e-mail');
$recus = [];
try { $tf->regenererCodes($superId, '000000'); } catch (\Throwable) {}
check($recus === [], 'régénération refusée : aucun e-mail');

$recus = [];
try { $tf->desactiver($superId, 'mauvais', $code($secret)); } catch (\Throwable) {}
check($recus === [], 'désactivation refusée (mauvais mot de passe) : aucun e-mail');
$tf->desactiver($superId, $mdp, $code($secret));
check(count($recus) === 1 && str_contains($recus[0]['objet'], 'désactivée'), 'désactivation : un e-mail');

$secret2 = TF::nouveauSecret();
$tf->activer($autreId, $secret2, $code($secret2));
$recus = [];
$tf->reinitialiser($autreId, PlatformOperatorService::ACTEUR_CLI);
check(count($recus) === 1 && $recus[0]['to'] === 'autre@exemple.test' && str_contains($recus[0]['objet'], 'réinitialisée'), 'réinitialisation par un tiers : e-mail à l\'opérateur concerné');
$recus = [];
try { $tf->reinitialiser($autreId, PlatformOperatorService::ACTEUR_CLI); } catch (\Throwable) {}
check($recus === [], 'réinitialisation sans 2FA active : aucun e-mail');

// ═════════════════════════════════════════════════════════════════════
section('4. Robustesse');
foreach (['refus' => 'envoi refusé par le serveur', 'exception' => 'exception pendant l\'envoi'] as $m => $libelle) {
    $mode = $m; $recus = [];
    $avant = count($audits('operator.notification.failed'));
    $s = TF::nouveauSecret();
    $erreur = null;
    try { $tf->activer($adminId, $s, $code($s)); } catch (\Throwable $e) { $erreur = $e->getMessage(); }
    check($erreur === null && $tf->estActive($adminId), "$libelle : l'opération réussit quand même");
    $nouv = array_slice($audits('operator.notification.failed'), $avant);
    check(count($nouv) === 1 && $nouv[0]['resultat'] === 'failure' && (int)$nouv[0]['entity_id'] === $adminId && str_contains((string)$nouv[0]['nouvelle_valeur'], '2fa_activee'), "$libelle : échec inscrit au journal global (événement, opérateur)");
    check(!str_contains((string)$nouv[0]['nouvelle_valeur'], 'admin@exemple.test') && !str_contains((string)$nouv[0]['nouvelle_valeur'], 'serveur SMTP'), "$libelle : ni adresse e-mail ni détail technique dans le journal");
    $tf->reinitialiser($adminId, PlatformOperatorService::ACTEUR_CLI);
}
$mode = 'ok'; $recus = [];

$pdo->exec("UPDATE platform_operators SET email = NULL WHERE id = $adminId");
$s = TF::nouveauSecret(); $avant = count($audits('operator.notification.failed'));
$tf->activer($adminId, $s, $code($s));
check($recus === [] && count($audits('operator.notification.failed')) === $avant && $tf->estActive($adminId), 'opérateur sans adresse e-mail : pas d\'alerte, pas d\'erreur');
$pdo->exec("UPDATE platform_operators SET email = 'admin@exemple.test' WHERE id = $adminId");

$pdo->exec("UPDATE platform_operators SET actif = 0 WHERE id = $autreId");
N::notifier($autreId, N::MOT_DE_PASSE_MODIFIE);
check($recus === [], 'opérateur désactivé : pas d\'alerte');
$pdo->exec("UPDATE platform_operators SET actif = 1 WHERE id = $autreId");
N::notifier(999999, N::MOT_DE_PASSE_MODIFIE); N::notifier(0, N::MOT_DE_PASSE_MODIFIE); N::notifier($superId, 'evenement_inconnu');
check($recus === [], 'opérateur inconnu, identifiant nul ou événement inconnu : rien, sans erreur');

$pdo->beginTransaction();
$s = TF::nouveauSecret();
$tf->activer($autreId, $s, $code($s));
$pdo->rollBack();
check($recus === [] && !$tf->estActive($autreId), 'transaction englobante annulée : aucune alerte pour une opération qui n\'a pas eu lieu');

// ═════════════════════════════════════════════════════════════════════
section('5. Contenu');
$recus = [];
$_SERVER['REMOTE_ADDR'] = '203.0.113.7';
$_SERVER['HTTP_USER_AGENT'] = "Mozilla/5.0 (Test)\r\nBcc: pirate@exemple.test";
N::notifier($superId, N::CONNEXION, ['methode' => 'recovery']);
$m = $recus[0] ?? ['html' => '', 'objet' => ''];
$t = $texte($m);
check(str_contains($t, '203.0.113.7') && str_contains($t, 'Navigateur : Mozilla/5.0 (Test)'), 'adresse IP et navigateur présents');
check(!str_contains($t, "\r") && !str_contains($m['html'], "Bcc: pirate") === false || !preg_match('/\r\nBcc:/', $m['html']), 'saut de ligne du navigateur neutralisé (pas d\'en-tête injecté)');
check(str_contains($t, 'code de secours') && preg_match('/\d{2}\/\d{2}\/\d{4} à \d{2}:\d{2}:\d{2}/', $t) === 1, 'méthode du second facteur et date lisible');
check(!str_contains($m['html'], 'href=') && !str_contains($m['html'], 'http://') && !str_contains($m['html'], 'https://'), 'aucun lien dans le message');
check(str_contains($t, 'prévenez un autre super administrateur'), 'conduite à tenir indiquée');
unset($_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']);
$recus = [];
N::notifier($superId, N::CONNEXION);
check(str_contains($texte($recus[0]), 'ligne de commande'), 'en ligne de commande : l\'origine est « accès serveur »');

// ═════════════════════════════════════════════════════════════════════
section('6. Parcours HTTP');
$P = '/ecole_app/platform';
@unlink($sink);
$lire = static function () use ($sink): array {
    return is_file($sink) ? array_map(static fn($l) => json_decode($l, true), array_values(array_filter(explode("\n", (string)file_get_contents($sink))))) : [];
};
$ouvert = ['id' => $autreId, 'niveau' => 'super_admin'];
// Nouveau mot de passe connu pour 'autre' (il a été réinitialisé : changement obligatoire, mais la connexion fonctionne).
$r = http($nomCopie, 'guest', 'POST', "$P/login", ['email' => 'autre@exemple.test', 'password' => $mdpAutre], ['PLATFORM_REQ_IP' => ip()]);
$mails = $lire();
check($r['operator'] !== null && count($mails) === 1 && $mails[0]['to'] === 'autre@exemple.test' && str_contains($mails[0]['objet'], 'Nouvelle connexion'), 'connexion réussie : une alerte à l\'opérateur', json_encode($r));
@unlink($sink);
$r = http($nomCopie, 'guest', 'POST', "$P/login", ['email' => 'autre@exemple.test', 'password' => 'faux'], ['PLATFORM_REQ_IP' => ip()]);
check($r['operator'] === null && $lire() === [], 'connexion échouée : aucune alerte');
$r = http($nomCopie, 'guest', 'POST', "$P/login", ['email' => 'inconnu@exemple.test', 'password' => 'faux'], ['PLATFORM_REQ_IP' => ip()]);
check($r['operator'] === null && $lire() === [], 'adresse inconnue : aucune alerte');
$r = http($nomCopie, 'guest', 'POST', "$P/login", ['email' => 'autre@exemple.test', 'password' => $mdpAutre], ['PLATFORM_REQ_IP' => ip(), 'PLATFORM_NOTIFY_LOGIN' => '0']);
check($r['operator'] !== null && $lire() === [], 'réglage « alerte à chaque connexion » = 0 : connexion sans alerte');
// Le réglage en base prime sur l'environnement.
$pdo->exec("INSERT INTO platform_settings (cle, valeur) VALUES ('alertes_connexion', '0') ON DUPLICATE KEY UPDATE valeur = '0'");
$r = http($nomCopie, 'guest', 'POST', "$P/login", ['email' => 'autre@exemple.test', 'password' => $mdpAutre], ['PLATFORM_REQ_IP' => ip()]);
check($r['operator'] !== null && $lire() === [], 'réglage en base à 0 : connexion sans alerte');
$pdo->exec("DELETE FROM platform_settings WHERE cle = 'alertes_connexion'");
// Les alertes critiques ne dépendent pas de ce réglage.
$pdo->exec("INSERT INTO platform_settings (cle, valeur) VALUES ('alertes_connexion', '0') ON DUPLICATE KEY UPDATE valeur = '0'");
PlatformSettings::oublier();
$recus = [];
N::notifier($superId, N::MOT_DE_PASSE_MODIFIE);
check(count($recus) === 1, 'alerte de changement de mot de passe envoyée même si l\'alerte de connexion est désactivée');
$pdo->exec("DELETE FROM platform_settings WHERE cle = 'alertes_connexion'");
PlatformSettings::oublier();

// Connexion avec second facteur par code de secours : alerte de connexion + alerte « code de secours utilisé ».
$s3 = TF::nouveauSecret();
$codes3 = $tf->activer($superId, $s3, $code($s3));
$opS = ['id' => $superId, 'email' => 'super@exemple.test', 'nom' => 'Super Opérateur', 'niveau' => 'super_admin', 'must_change' => false];
@unlink($sink);
$session = json_encode(['_platform_mfa_pending' => ['op' => $opS, 'at' => time(), 'essais' => 0]]);
$r = http($nomCopie, 'guest', 'POST', "$P/login/2fa", ['code' => $codes3[0]], ['PLATFORM_REQ_SESSION' => $session, 'PLATFORM_REQ_IP' => ip()]);
$objets = array_column($lire(), 'objet');
check($r['operator'] !== null && count($objets) === 2 && (bool)array_filter($objets, fn($o) => str_contains($o, 'Nouvelle connexion')) && (bool)array_filter($objets, fn($o) => str_contains($o, 'code de secours')), 'connexion par code de secours : alerte de connexion ET alerte « code de secours utilisé »', json_encode($objets));
check(!str_contains((string)file_get_contents($sink), $codes3[0]) && !str_contains((string)file_get_contents($sink), $codes3[1]), 'aucun code de secours dans les messages');
@unlink($sink);
$r = http($nomCopie, 'guest', 'POST', "$P/login/2fa", ['code' => '000000'], ['PLATFORM_REQ_SESSION' => $session, 'PLATFORM_REQ_IP' => ip()]);
check($r['operator'] === null && $lire() === [], 'second facteur incorrect : aucune alerte');

// Page « Sécurité (2FA) » : le bandeau n'apparaît que sans envoi possible.
$r = http($nomCopie, json_encode($opS), 'GET', "$P/compte/2fa");
check(!str_contains((string)$r['text'], 'Alertes de sécurité par e-mail inactives'), 'envoi possible : pas de bandeau d\'avertissement');
$r = http($nomCopie, json_encode($opS), 'GET', "$P/compte/2fa", [], ['PLATFORM_REQ_MAIL_SINK' => '']);
check(str_contains((string)$r['text'], 'Alertes de sécurité par e-mail inactives'), 'SMTP non configuré : la page « Sécurité » le signale');

@unlink($sink);
echo "\n";
echo $failed === 0 ? "\033[32m$passed OK, 0 KO\033[0m\n" : "\033[31m$passed OK, $failed KO\033[0m\n";
exit($failed === 0 ? 0 : 1);
