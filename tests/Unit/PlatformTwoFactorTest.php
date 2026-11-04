<?php
/**
 * Tests — double authentification (TOTP) des opérateurs de la plateforme (migration T072).
 *
 * 1. Algorithme : vecteurs officiels de la RFC 6238 (SHA-1), base32, URI otpauth
 * 2. Migration T072 : additive, idempotente, personne n'a la 2FA après application
 * 3. Activation : code de confirmation, secret chiffré, codes de secours hachés, audit sans secret
 * 4. Vérification : tolérance ±1 pas, anti-rejeu, codes de secours à usage unique, comptes inactifs, APP_KEY
 * 5. Codes de secours (régénération), désactivation (mot de passe + code), réinitialisation par un tiers
 * 6. Audit strict : échec d'audit = rien de modifié, transaction englobante
 * 7. Paramètre « obligatoire » (booléen, .env)
 * 8. Parcours HTTP : connexion en deux temps, limitation, expiration, activation obligatoire, garde du paramètre
 * 9. Script d'exploitation : database/create-operator.php --reinitialiser-2fa
 *
 * Travaille uniquement sur une copie jetable ecole_app_copy_* : la base vivante n'est jamais touchée.
 *
 * Lance : C:\wamp64\bin\php\php8.2.29\php.exe tests/Unit/PlatformTwoFactorTest.php
 */

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__, 2));
foreach (file(ROOT_PATH . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $l) {
    if (str_starts_with(trim($l), '#') || !str_contains($l, '=')) { continue; }
    [$k, $v] = explode('=', $l, 2);
    $_ENV[trim($k)] = trim($v, " \t\n\r\0\x0B\"'");
}
unset($_ENV['PLATFORM_TIMEZONE'], $_ENV['PLATFORM_DEFAULT_CURRENCY'], $_ENV['PLATFORM_REQUIRE_2FA']);
putenv('PLATFORM_REQUIRE_2FA');
if (strlen((string)($_ENV['APP_KEY'] ?? '')) < 16) { $_ENV['APP_KEY'] = bin2hex(random_bytes(16)); }
$APP_KEY = $_ENV['APP_KEY'];
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
use Core\Platform\PlatformSettings;
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
        ['REQUEST_AS_DB' => $nomCopie, 'PLATFORM_REQ_AUDIT_DIR' => $dossierSecours, 'PLATFORM_THROTTLE_DIR' => $dossierThrottle] + $env);
    return preg_match('/^RESULT:(.*)$/m', $sortie, $m) ? (json_decode($m[1], true) ?: ['status' => 0]) : ['status' => 0, 'brut' => $sortie, 'code' => $code];
}
function operateur(int $id, string $niveau, array $extra = []): string { return json_encode(['id' => $id, 'niveau' => $niveau, 'email' => "op$id@exemple.test", 'nom' => "Op $id"] + $extra); }
function ip(): string { return '10.' . random_int(1, 250) . '.' . random_int(1, 250) . '.' . random_int(1, 250); }
function refus(callable $f): ?string
{
    try { $f(); return null; } catch (TwoFactorException $e) { return $e->getMessage(); }
}

// ═════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════
section('1. Algorithme (RFC 6238, SHA-1)');
$rfc = TF::base32Encode('12345678901234567890');
check($rfc === 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', 'base32 du secret de la RFC', $rfc);
foreach ([59 => '287082', 1111111109 => '081804', 1111111111 => '050471', 1234567890 => '005924', 2000000000 => '279037', 20000000000 => '353130'] as $t => $attendu) {
    check(TF::totp($rfc, intdiv($t, 30)) === $attendu, "vecteur RFC 6238 à t=$t : $attendu");
}
$b = random_bytes(20);
check(TF::base32Decode(TF::base32Encode($b)) === $b, 'base32 : aller-retour exact sur 20 octets aléatoires');
$ex = false; try { TF::base32Decode('AB1'); } catch (\InvalidArgumentException) { $ex = true; }
check($ex, 'base32 : caractère invalide refusé');
check(preg_match('/^[A-Z2-7]{32}$/', TF::nouveauSecret()) === 1 && TF::nouveauSecret() !== TF::nouveauSecret(), 'secret généré : 160 bits en base32, différent à chaque appel');
$uri = TF::uri('a@b.test', 'JBSWY3DPEHPK3PXP');
check(str_starts_with($uri, 'otpauth://totp/EduNova%3Aa%40b.test?secret=JBSWY3DPEHPK3PXP') && str_contains($uri, 'issuer=EduNova') && str_contains($uri, 'digits=6') && str_contains($uri, 'period=30'), 'URI otpauth conforme', $uri);

$_SESSION = [];
$copie = \Tests\Support\DbCopy::redirigerVersCopie(ROOT_PATH) ?? exit("ECHEC : copie jetable impossible (mysql/mysqldump ou droit CREATE DATABASE)\n");
$nomCopie = $copie->connection()['db'];
$pdo = Database::getInstance()->getConnection();
$dossierSecours = sys_get_temp_dir() . '/ptf_' . bin2hex(random_bytes(4));
$dossierThrottle = sys_get_temp_dir() . '/ptf_thr_' . bin2hex(random_bytes(4));
PlatformAuditService::definirDossierSecours($dossierSecours);
$ops = new PlatformOperatorService($pdo);
$mdp = 'Zq7!pLm2#vXk9@Rt';
$superId = $ops->creer('super@exemple.test', 'Super Opérateur', 'super_admin', $mdp, PlatformOperatorService::ACTEUR_CLI)['id'];
$admin2 = $ops->creer('admin2@exemple.test', 'Admin Deux', 'super_admin', $mdp, PlatformOperatorService::ACTEUR_CLI)['id'];
$sansTf = $ops->creer('sans@exemple.test', 'Sans Deux', 'admin', $mdp, PlatformOperatorService::ACTEUR_CLI)['id'];
$maintenant = 1_800_000_000;   // horloge de test
$horloge = static function () use (&$maintenant): int { return $maintenant; };
$tf = new TF($pdo, $horloge);
$pas = function (int $d = 0) use (&$maintenant): int { return intdiv($maintenant, 30) + $d; };
$ligne = static fn(int $id) => $pdo->query("SELECT * FROM platform_operators WHERE id = $id")->fetch(PDO::FETCH_ASSOC);
$audits = static fn(string $like) => $pdo->query('SELECT * FROM platform_audit_logs WHERE action LIKE ' . $pdo->quote($like) . ' ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
$nbCodes = static fn(int $id, bool $restants = false) => (int)$pdo->query("SELECT COUNT(*) FROM platform_operator_recovery_codes WHERE operator_id = $id" . ($restants ? ' AND used_at IS NULL' : ''))->fetchColumn();

// ═════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════
section('2. Migration T072');
$migration = require ROOT_PATH . '/database/migrations/T072_double_authentification_operateurs.php';
$migration['rollback']($pdo);
check(!TF::disponible($pdo), 'sans la migration : indisponible (le portail garde son comportement d\'avant)');
$migration['run']($pdo);
$migration['run']($pdo);
check(TF::disponible($pdo), 'T072 appliquée deux fois sans erreur (idempotente)');
check((int)$pdo->query('SELECT COUNT(*) FROM platform_operators WHERE totp_enabled_at IS NOT NULL')->fetchColumn() === 0 && !$tf->estActive($superId), 'personne n\'a la 2FA après la migration');

// ═════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════
section('3. Activation');
$secret = TF::nouveauSecret();
check(refus(fn() => $tf->activer($superId, $secret, '000000')) !== null && !$tf->estActive($superId), 'mauvais code : refusé, rien d\'activé');
check(refus(fn() => $tf->activer($superId, $secret, 'abcdef')) !== null && refus(fn() => $tf->activer($superId, $secret, '')) !== null, 'code non numérique ou vide : refusé');
check(refus(fn() => $tf->activer($superId, $secret, TF::totp($secret, $pas(-2)))) !== null, 'code hors tolérance (2 pas en arrière) : refusé');
check(refus(fn() => $tf->activer($superId, 'AB1!', '123456')) !== null, 'secret invalide : refusé proprement');
$codes = $tf->activer($superId, $secret, TF::totp($secret, $pas()));
$l = $ligne($superId);
check($tf->estActive($superId) && $l['totp_enabled_at'] !== null && (int)$l['totp_last_step'] === $pas(), '2FA activée, dernier pas mémorisé (anti-rejeu dès l\'activation)');
check(count($codes) === 10 && count(array_unique($codes)) === 10 && preg_match('/^[A-HJ-NP-Z2-9]{5}-[A-HJ-NP-Z2-9]{5}$/', $codes[0]) === 1, '10 codes de secours distincts au format XXXXX-XXXXX, sans caractères ambigus');
check($l['totp_secret'] !== $secret && !str_contains((string)$l['totp_secret'], $secret) && base64_decode((string)$l['totp_secret'], true) !== false, 'secret stocké chiffré (jamais en clair)');
$hashes = $pdo->query("SELECT code_hash FROM platform_operator_recovery_codes WHERE operator_id = $superId")->fetchAll(PDO::FETCH_COLUMN);
check(count($hashes) === 10 && preg_match('/^[0-9a-f]{64}$/', $hashes[0]) === 1 && !in_array(str_replace('-', '', $codes[0]), $hashes, true), 'codes de secours stockés hachés (HMAC-SHA256)');
check($tf->statut($superId)['codes_restants'] === 10 && $tf->statut($sansTf)['active'] === false, 'statut : 10 codes restants ; les autres comptes sont inchangés');
check(refus(fn() => $tf->activer($superId, TF::nouveauSecret(), '123456')) !== null, 'déjà activée : nouvelle activation refusée');
$a = $audits('operator.2fa_enabled');
$tout = json_encode($pdo->query('SELECT * FROM platform_audit_logs')->fetchAll(PDO::FETCH_ASSOC));
check(count($a) === 1 && (int)$a[0]['entity_id'] === $superId && !str_contains($tout, $secret) && !str_contains($tout, str_replace('-', '', $codes[0])) && !str_contains($tout, (string)$l['totp_secret']), 'audit : activation tracée, ni secret ni code de secours dans le journal');

// ═════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════
section('4. Vérification');
check($tf->verifier($superId, TF::totp($secret, $pas())) === null, 'code déjà utilisé à l\'activation : rejeu refusé');
$maintenant += 30;
$c1 = TF::totp($secret, $pas());
check($tf->verifier($superId, $c1) === 'totp', 'code du pas suivant : accepté');
check($tf->verifier($superId, $c1) === null, 'même code rejoué : refusé');
check($tf->verifier($superId, ' ' . substr($c1, 0, 3) . ' ' . substr($c1, 3)) === null, 'code déjà consommé, même saisi avec des espaces : refusé');
$maintenant += 30;
$futur = TF::totp($secret, $pas(1));
check($tf->verifier($superId, $futur) === 'totp', 'tolérance : code du pas suivant (+1) accepté');
check($tf->verifier($superId, TF::totp($secret, $pas())) === null, 'après un pas plus récent, un code plus ancien est refusé');
$maintenant += 30;
check($tf->verifier($superId, TF::totp($secret, $pas(2))) === null && $tf->verifier($superId, TF::totp($secret, $pas(-2))) === null, 'au-delà de ±1 pas : refusé');
$maintenant += 60;   // le code du pas courant a déjà servi comme « code du futur » ci-dessus
$bon = TF::totp($secret, $pas());
check($tf->verifier($superId, ' ' . substr($bon, 0, 3) . ' ' . substr($bon, 3) . ' ') === 'totp', 'espaces autour / dans la saisie tolérés');
check($tf->verifier($superId, '') === null && $tf->verifier($superId, 'abcdef') === null && $tf->verifier($superId, '12345') === null && $tf->verifier($superId, '1234567') === null, 'saisies vides, non numériques ou de mauvaise longueur : refusées');
check($tf->verifier($sansTf, TF::totp($secret, $pas())) === null && $tf->verifier(999999, '123456') === null, 'compte sans 2FA ou inconnu : jamais accepté');

$rc = $codes[0];
check($tf->verifier($superId, $rc) === 'recovery' && $tf->statut($superId)['codes_restants'] === 9, 'code de secours accepté, un code de moins');
check($tf->verifier($superId, $rc) === null, 'code de secours : usage unique');
check($tf->verifier($superId, strtolower(str_replace('-', '', $codes[1]))) === 'recovery', 'code de secours saisi en minuscules et sans tiret : accepté');
check($tf->verifier($superId, 'AAAAA-AAAAA') === null && $tf->verifier($superId, 'ILO01-ILO01') === null, 'code de secours inventé : refusé');
$secret2 = TF::nouveauSecret();
$codes2 = $tf->activer($admin2, $secret2, TF::totp($secret2, $pas()));
check($tf->verifier($superId, $codes2[0]) === null && $tf->statut($admin2)['codes_restants'] === 10, 'le code de secours d\'un autre opérateur n\'est pas accepté, et n\'est pas consommé');

$pdo->exec("UPDATE platform_operators SET actif = 0 WHERE id = $admin2");
$maintenant += 30;
check($tf->verifier($admin2, TF::totp($secret2, $pas())) === null && $tf->verifier($admin2, $codes2[1]) === null, 'opérateur désactivé : second facteur jamais accepté');
$pdo->exec("UPDATE platform_operators SET actif = 1 WHERE id = $admin2");

$_ENV['APP_KEY'] = 'une-autre-cle-une-autre-cle';
$maintenant += 30;
check($tf->verifier($superId, TF::totp($secret, $pas())) === null, 'APP_KEY modifiée : secret illisible, refus sans exception');
$_ENV['APP_KEY'] = '';
check(str_contains((string)refus(fn() => $tf->activer($sansTf, TF::nouveauSecret(), '123456') ), 'Code incorrect') || refus(fn() => (new TF($pdo, $horloge))->activer($sansTf, $secret, TF::totp($secret, $pas()))) !== null, 'APP_KEY absente : activation refusée');
$_ENV['APP_KEY'] = $APP_KEY;
check(!$tf->estActive($sansTf), 'aucune activation partielle sans APP_KEY');
check($tf->verifier($superId, TF::totp($secret, $pas())) === 'totp', 'APP_KEY rétablie : la 2FA refonctionne');

// ═════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════
section('5. Régénération, désactivation, réinitialisation');
$maintenant += 30;
check(refus(fn() => $tf->regenererCodes($superId, $codes[2])) !== null && $tf->statut($superId)['codes_restants'] === 8, 'régénération : un code de secours ne suffit pas');
check(refus(fn() => $tf->regenererCodes($superId, '000000')) !== null, 'régénération : mauvais code refusé');
$nouveaux = $tf->regenererCodes($superId, TF::totp($secret, $pas()));
check(count($nouveaux) === 10 && $tf->statut($superId)['codes_restants'] === 10 && count($audits('operator.2fa_recovery_regenerated')) === 1, 'régénération : 10 nouveaux codes, audit');
check($tf->verifier($superId, $codes[3]) === null && $tf->verifier($superId, $nouveaux[0]) === 'recovery', 'anciens codes invalidés, nouveaux valables');
$maintenant += 30;
check(refus(fn() => $tf->desactiver($superId, 'mauvais', TF::totp($secret, $pas()))) !== null && $tf->estActive($superId), 'désactivation : mauvais mot de passe refusé');
check(refus(fn() => $tf->desactiver($superId, $mdp, '000000')) !== null && $tf->estActive($superId), 'désactivation : mauvais code refusé');
(new PlatformSettings($pdo))->enregistrer([PlatformSettings::DEUX_FACTEURS => '1'], $superId);
$maintenant += 30;
$m = refus(fn() => $tf->desactiver($superId, $mdp, TF::totp($secret, $pas())));
check($m !== null && str_contains($m, 'obligatoire') && $tf->estActive($superId), '2FA obligatoire : désactivation refusée même avec mot de passe et code');
(new PlatformSettings($pdo))->enregistrer([PlatformSettings::DEUX_FACTEURS => ''], $superId);
$maintenant += 30;
$tf->desactiver($superId, $mdp, TF::totp($secret, $pas()));
$l = $ligne($superId);
check(!$tf->estActive($superId) && $l['totp_secret'] === null && $l['totp_last_step'] === null && $nbCodes($superId) === 0 && count($audits('operator.2fa_disabled')) === 1, 'désactivation : secret et codes effacés, audit');
$secret3 = TF::nouveauSecret();
$tf->activer($superId, $secret3, TF::totp($secret3, $pas()));
check($tf->estActive($superId) && $tf->verifier($superId, TF::totp($secret, $pas(1))) === null, 'réactivation avec un nouveau secret : l\'ancien ne fonctionne plus');

check(refus(fn() => $tf->reinitialiser($sansTf, PlatformOperatorService::ACTEUR_CLI)) !== null, 'réinitialisation d\'un compte sans 2FA : refusée');
$tf->reinitialiser($admin2, PlatformOperatorService::ACTEUR_CLI);
$r = $audits('operator.2fa_reset');
check(!$tf->estActive($admin2) && $nbCodes($admin2) === 0 && count($r) === 1 && $r[0]['actor_email'] === 'cli', 'réinitialisation par un tiers : 2FA retirée, acteur « cli » dans l\'audit');
$tf->activer($admin2, $secret2, TF::totp($secret2, $pas()));
$tf->reinitialiser($admin2);
check(!$tf->estActive($admin2), 'réinitialisation sans acteur explicite (opérateur connecté) acceptée');

// ═════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════
section('6. Audit strict');
$pdo->exec("CREATE TRIGGER t_tf_audit_ko BEFORE INSERT ON platform_audit_logs FOR EACH ROW BEGIN
    IF NEW.action LIKE 'operator.2fa_%' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'echec audit simule'; END IF; END");
try {
    $s4 = TF::nouveauSecret();
    $maintenant += 30;
    $m = refus(fn() => $tf->activer($sansTf, $s4, TF::totp($s4, $pas())));
    check($m !== null && str_contains($m, 'trace d\'audit') && !$tf->estActive($sansTf) && $nbCodes($sansTf) === 0, 'audit impossible : activation refusée, ni secret ni code écrits');
    $avantCodes = $nbCodes($superId);
    $m = refus(fn() => $tf->regenererCodes($superId, TF::totp($secret3, $pas())));
    check($m !== null && $nbCodes($superId) === $avantCodes && $tf->verifier($superId, $nouveaux[1]) === null, 'audit impossible : régénération annulée, codes inchangés');
    $maintenant += 30;
    $m = refus(fn() => $tf->desactiver($superId, $mdp, TF::totp($secret3, $pas())));
    check($m !== null && $tf->estActive($superId), 'audit impossible : désactivation annulée, 2FA toujours active');
    $m = refus(fn() => $tf->reinitialiser($superId, PlatformOperatorService::ACTEUR_CLI));
    check($m !== null && $tf->estActive($superId), 'audit impossible : réinitialisation annulée');
    $pdo->beginTransaction();
    $m = refus(fn() => $tf->activer($sansTf, $s4, TF::totp($s4, $pas())));
    check($m !== null && $pdo->inTransaction() && !$tf->estActive($sansTf), 'transaction englobante : seule l\'opération est annulée, celle de l\'appelant reste ouverte');
    $pdo->rollBack();
} finally {
    $pdo->exec('DROP TRIGGER IF EXISTS t_tf_audit_ko');
}
$maintenant += 30;
$tf->activer($sansTf, $s4, TF::totp($s4, $pas()));
check($tf->estActive($sansTf), 'audit rétabli : l\'activation fonctionne');
$tf->reinitialiser($sansTf, PlatformOperatorService::ACTEUR_CLI);

// ═════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════
section('7. Paramètre « obligatoire »');
PlatformSettings::oublier();
check(!TF::obligatoire(), 'par défaut : facultative');
$_ENV['PLATFORM_REQUIRE_2FA'] = 'true';
check(TF::obligatoire(), '.env « true » : obligatoire');
$_ENV['PLATFORM_REQUIRE_2FA'] = 'peut-etre';
check(!TF::obligatoire(), '.env invalide : ignoré');
unset($_ENV['PLATFORM_REQUIRE_2FA']);
$e = null; try { (new PlatformSettings($pdo))->enregistrer([PlatformSettings::DEUX_FACTEURS => '2'], $superId); } catch (\Core\Platform\PlanValidationException $x) { $e = $x->getErrors(); }
check($e !== null && !TF::obligatoire(), 'valeur autre que 0 / 1 refusée');
(new PlatformSettings($pdo))->enregistrer([PlatformSettings::DEUX_FACTEURS => 'oui'], $superId);
check(TF::obligatoire() && $pdo->query("SELECT valeur FROM platform_settings WHERE cle = 'deux_facteurs_obligatoire'")->fetchColumn() === '1', '« oui » enregistré en 1');
(new PlatformSettings($pdo))->enregistrer([PlatformSettings::DEUX_FACTEURS => ''], $superId);

// ═════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════
section('8. Parcours HTTP');
$P = '/ecole_app/platform';
$reel = static fn(int $d = 0): int => intdiv(time(), 30) + $d;
$activerReel = static function (int $id) use ($pdo): array {
    $s = TF::nouveauSecret();
    $svc = new TF($pdo);
    $codes = $svc->activer($id, $s, TF::totp($s, intdiv(time(), 30)));
    return [$s, $codes];
};
[$secH, $codesH] = $activerReel($admin2);                                   // admin2 : 2FA active
$opH = ['id' => $admin2, 'email' => 'admin2@exemple.test', 'nom' => 'Admin Deux', 'niveau' => 'super_admin', 'must_change' => false];
$attente = static fn(int $essais = 0, int $age = 0): string => json_encode(['_platform_mfa_pending' => ['op' => $opH, 'at' => time() - $age, 'essais' => $essais]]);

$r = http($nomCopie, 'guest', 'POST', "$P/login", ['email' => 'admin2@exemple.test', 'password' => $mdp], ['PLATFORM_REQ_IP' => ip()]);
check($r['operator'] === null && $r['mfa_pending'] === true && str_contains((string)$r['location'], '/platform/login/2fa'), 'bon mot de passe + 2FA active : PAS connecté, second facteur demandé', json_encode($r));
check(count($audits('operator.login.2fa_required')) >= 1, 'étape mot de passe inscrite au journal');
$r = http($nomCopie, 'guest', 'POST', "$P/login", ['email' => 'admin2@exemple.test', 'password' => 'faux'], ['PLATFORM_REQ_IP' => ip()]);
check($r['mfa_pending'] === false && $r['operator'] === null, 'mauvais mot de passe : aucun état de second facteur');
$r = http($nomCopie, 'guest', 'POST', "$P/login", ['email' => 'sans@exemple.test', 'password' => $mdp], ['PLATFORM_REQ_IP' => ip()]);
check($r['operator'] !== null && $r['mfa_pending'] === false && $r['must_2fa'] === false && str_contains((string)$r['location'], '/platform/dashboard'), 'opérateur sans 2FA et 2FA facultative : connexion normale');

$r = http($nomCopie, 'guest', 'GET', "$P/login/2fa");
check($r['status'] === 302 && str_contains((string)$r['location'], '/platform/login') && !str_contains((string)$r['location'], '2fa'), 'page du second facteur sans mot de passe validé : renvoi à la connexion');
$r = http($nomCopie, 'guest', 'POST', "$P/login/2fa", ['code' => TF::totp($secH, $reel(1))], ['PLATFORM_REQ_IP' => ip()]);
check($r['operator'] === null, 'envoi d\'un code sans mot de passe validé : aucune connexion');
$r = http($nomCopie, 'guest', 'GET', "$P/login/2fa", [], ['PLATFORM_REQ_SESSION' => $attente()]);
check($r['status'] === 200 && str_contains((string)$r['text'], 'Vérification en deux étapes'), 'avec mot de passe validé : page du second facteur affichée');
$r = http($nomCopie, 'guest', 'GET', "$P/dashboard", [], ['PLATFORM_REQ_SESSION' => $attente()]);
check($r['status'] === 302 && str_contains((string)$r['location'], '/platform/login') && $r['operator'] === null, 'mot de passe validé SEUL : le tableau de bord reste inaccessible');
$r = http($nomCopie, 'guest', 'POST', "$P/login/2fa", ['code' => TF::totp($secH, $reel(1))], ['PLATFORM_REQ_SESSION' => $attente(0, 400), 'PLATFORM_REQ_IP' => ip()]);
check($r['operator'] === null && str_contains((string)$r['location'], '/platform/login') && !str_contains((string)$r['location'], '2fa'), 'état expiré (plus de 5 minutes) : connexion refusée');
$r = http($nomCopie, 'guest', 'POST', "$P/login/2fa", ['code' => TF::totp($secH, $reel(1))], ['PLATFORM_REQ_SESSION' => $attente(), 'PLATFORM_REQ_IP' => ip(), 'REQUEST_AS_NO_CSRF' => '1']);
check($r['operator'] === null, 'sans jeton CSRF valide : refusé');
$avantEchecs = count($audits('operator.2fa.failed'));
$r = http($nomCopie, 'guest', 'POST', "$P/login/2fa", ['code' => '000000'], ['PLATFORM_REQ_SESSION' => $attente(), 'PLATFORM_REQ_IP' => ip()]);
check($r['operator'] === null && $r['mfa_pending'] === true && str_contains((string)$r['location'], '/platform/login/2fa') && count($audits('operator.2fa.failed')) === $avantEchecs + 1, 'mauvais code : refusé, on reste à l\'étape, échec inscrit au journal');
$r = http($nomCopie, 'guest', 'POST', "$P/login/2fa", ['code' => '000000'], ['PLATFORM_REQ_SESSION' => $attente(4), 'PLATFORM_REQ_IP' => ip()]);
check($r['operator'] === null && $r['mfa_pending'] === false && str_contains((string)$r['location'], '/platform/login') && !str_contains((string)$r['location'], '2fa'), '5e échec : l\'état est supprimé, le mot de passe est à ressaisir');
$r = http($nomCopie, 'guest', 'POST', "$P/login/2fa", ['code' => TF::totp($secH, $reel(1))], ['PLATFORM_REQ_SESSION' => $attente(), 'PLATFORM_REQ_IP' => ip()]);
check($r['operator'] !== null && (int)$r['operator']['id'] === $admin2 && $r['mfa_method'] === 'totp' && $r['mfa_pending'] === false && str_contains((string)$r['location'], '/platform/dashboard'), 'bon code : connecté (méthode totp), état de second facteur supprimé', json_encode($r));
check(count($audits('operator.login.success')) >= 1 && str_contains(json_encode($audits('operator.login.success')), 'totp'), 'connexion réussie inscrite avec le second facteur utilisé');
$r = http($nomCopie, 'guest', 'POST', "$P/login/2fa", ['code' => TF::totp($secH, $reel(1))], ['PLATFORM_REQ_SESSION' => $attente(), 'PLATFORM_REQ_IP' => ip()]);
check($r['operator'] === null, 'le même code ne sert pas deux fois, même dans une autre session');
$r = http($nomCopie, 'guest', 'POST', "$P/login/2fa", ['code' => $codesH[0]], ['PLATFORM_REQ_SESSION' => $attente(), 'PLATFORM_REQ_IP' => ip()]);
check($r['operator'] !== null && $r['mfa_method'] === 'recovery' && count($audits('operator.2fa.recovery_used')) === 1, 'code de secours : connecté, utilisation inscrite au journal');
$r = http($nomCopie, 'guest', 'POST', "$P/login/2fa", ['code' => $codesH[0]], ['PLATFORM_REQ_SESSION' => $attente(), 'PLATFORM_REQ_IP' => ip()]);
check($r['operator'] === null, 'code de secours déjà utilisé : refusé');

$ipBloc = ip();
for ($i = 0; $i < 5; $i++) {
    http($nomCopie, 'guest', 'POST', "$P/login/2fa", ['code' => '000000'], ['PLATFORM_REQ_SESSION' => $attente(), 'PLATFORM_REQ_IP' => $ipBloc]);
}
$avantBloc = count($audits('operator.2fa.throttled'));
$r = http($nomCopie, 'guest', 'POST', "$P/login/2fa", ['code' => $codesH[2]], ['PLATFORM_REQ_SESSION' => $attente(), 'PLATFORM_REQ_IP' => $ipBloc]);
check($r['operator'] === null && count($audits('operator.2fa.throttled')) === $avantBloc + 1, 'après 5 échecs : bloqué même avec le BON code, blocage inscrit au journal');
$r = http($nomCopie, 'guest', 'POST', "$P/login/2fa", ['code' => $codesH[1]], ['PLATFORM_REQ_SESSION' => $attente(), 'PLATFORM_REQ_IP' => ip()]);
check($r['operator'] !== null, 'le blocage est propre au couple opérateur + IP : une autre adresse n\'est pas bloquée');

// Page du compte
$S = operateur($superId, 'super_admin');
$r = http($nomCopie, operateur($sansTf, 'super_admin'), 'GET', "$P/compte/2fa");
check($r['status'] === 200 && str_contains((string)$r['text'], 'Enregistrer l\'application') && !str_contains((string)$r['text'], 'Activée'), 'compte sans 2FA : page d\'activation (QR code)');
$tf->reinitialiser($superId, PlatformOperatorService::ACTEUR_CLI);
$sec = TF::nouveauSecret();
$graine = json_encode(['_platform_2fa_setup' => ['secret' => $sec, 'at' => time()]]);
$r = http($nomCopie, $S, 'POST', "$P/compte/2fa/activer", ['code' => '000000'], ['PLATFORM_REQ_SESSION' => $graine, 'PLATFORM_REQ_IP' => ip()]);
check(!$tf->estActive($superId), 'activation avec un mauvais code : refusée');
// Ré-authentification : sans mot de passe, ou avec un mauvais, l'activation est refusée même avec le bon code.
$r = http($nomCopie, $S, 'POST', "$P/compte/2fa/activer", ['code' => TF::totp($sec, $reel())], ['PLATFORM_REQ_SESSION' => $graine, 'PLATFORM_REQ_IP' => ip()]);
check(!$tf->estActive($superId), 'activation sans mot de passe : refusée (session ouverte laissée sans surveillance)');
$r = http($nomCopie, $S, 'POST', "$P/compte/2fa/activer", ['code' => TF::totp($sec, $reel()), 'current_password' => 'faux'], ['PLATFORM_REQ_SESSION' => $graine, 'PLATFORM_REQ_IP' => ip()]);
check(!$tf->estActive($superId), 'activation avec un mauvais mot de passe : refusée');
$vieux = json_encode(['_platform_2fa_setup' => ['secret' => $sec, 'at' => time() - 1000]]);
$r = http($nomCopie, $S, 'POST', "$P/compte/2fa/activer", ['code' => TF::totp($sec, $reel()), 'current_password' => $mdp], ['PLATFORM_REQ_SESSION' => $vieux, 'PLATFORM_REQ_IP' => ip()]);
check(!$tf->estActive($superId), 'secret d\'enregistrement vieux de plus de 15 minutes : refusé même avec le bon mot de passe et le bon code');
$r = http($nomCopie, $S, 'POST', "$P/compte/2fa/activer", ['code' => TF::totp($sec, $reel()), 'current_password' => $mdp], ['PLATFORM_REQ_SESSION' => $graine, 'PLATFORM_REQ_IP' => ip()]);
check($r['status'] === 302 && $tf->estActive($superId), 'activation avec mot de passe et bon code : 2FA active', json_encode($r));
$r = http($nomCopie, 'guest', 'POST', "$P/compte/2fa/activer", ['code' => TF::totp($sec, $reel())], ['PLATFORM_REQ_SESSION' => $graine]);
check($r['status'] !== 200 && $tf->statut($sansTf)['active'] === false, 'activation sans être connecté : refusée');
$r = http($nomCopie, $S, 'GET', "$P/compte/2fa");
check(str_contains((string)$r['text'], 'Activée') && str_contains((string)$r['text'], 'code(s) de secours restant(s)'), 'compte avec 2FA : statut et codes restants');
$r = http($nomCopie, $S, 'POST', "$P/compte/2fa/desactiver", ['current_password' => 'faux', 'code' => TF::totp($sec, $reel(1))], ['PLATFORM_REQ_IP' => ip()]);
check($tf->estActive($superId), 'désactivation avec un mauvais mot de passe : refusée');

// Garde du paramètre « obligatoire »
$sansTfHttp = operateur($sansTf, 'super_admin');
$r = http($nomCopie, $sansTfHttp, 'POST', "$P/parametres", [PlatformSettings::DEUX_FACTEURS => '1']);
check($pdo->query("SELECT COUNT(*) FROM platform_settings WHERE cle = 'deux_facteurs_obligatoire'")->fetchColumn() == 0, 'rendre la 2FA obligatoire sans avoir la sienne : refusé (pas d\'auto-blocage)');
$r = http($nomCopie, $S, 'POST', "$P/parametres", [PlatformSettings::DEUX_FACTEURS => '1']);
check($pdo->query("SELECT valeur FROM platform_settings WHERE cle = 'deux_facteurs_obligatoire'")->fetchColumn() === '1', 'avec sa propre 2FA : paramètre enregistré');
PlatformSettings::oublier();

// 2FA obligatoire : activation imposée
$r = http($nomCopie, 'guest', 'POST', "$P/login", ['email' => 'sans@exemple.test', 'password' => $mdp], ['PLATFORM_REQ_IP' => ip()]);
check($r['operator'] !== null && $r['must_2fa'] === true && str_contains((string)$r['location'], '/platform/compte/2fa'), '2FA obligatoire : connexion sans 2FA conduite à l\'activation');
$M = operateur($sansTf, 'super_admin', ['must_2fa' => true]);
$r = http($nomCopie, $M, 'GET', "$P/dashboard");
check($r['status'] === 302 && str_contains((string)$r['location'], '/platform/compte/2fa'), 'tableau de bord fermé tant que la 2FA n\'est pas activée');
$r = http($nomCopie, $M, 'GET', "$P/revenus");
check($r['status'] === 302 && str_contains((string)$r['location'], '/platform/compte/2fa'), 'les autres pages (revenus) sont fermées aussi');
$r = http($nomCopie, $M, 'GET', "$P/compte/2fa");
check($r['status'] === 200 && str_contains((string)$r['text'], 'obligatoire'), 'la page d\'activation reste ouverte et explique l\'obligation');
$r = http($nomCopie, $M, 'GET', "$P/compte/mot-de-passe");
check($r['status'] === 200, 'le changement de mot de passe reste ouvert');
$r = http($nomCopie, $M, 'POST', "$P/logout");
check($r['operator'] === null, 'la déconnexion reste possible');
$sec5 = TF::nouveauSecret();
$r = http($nomCopie, $M, 'POST', "$P/compte/2fa/activer", ['code' => TF::totp($sec5, $reel()), 'current_password' => $mdp], ['PLATFORM_REQ_SESSION' => json_encode(['_platform_2fa_setup' => ['secret' => $sec5, 'at' => time()]]), 'PLATFORM_REQ_IP' => ip()]);
check($tf->estActive($sansTf), 'activation depuis le mode obligatoire : 2FA active');
$r = http($nomCopie, $S, 'POST', "$P/compte/2fa/desactiver", ['current_password' => $mdp, 'code' => TF::totp($sec, $reel(2))], ['PLATFORM_REQ_IP' => ip()]);
check($tf->estActive($superId), 'désactivation refusée tant que la 2FA est obligatoire');

// ═════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════
section('9. Script d\'exploitation');
$script = static fn(array $args) => lancer([PHP_BINARY, ROOT_PATH . '/database/create-operator.php', ...$args], ['OPERATOR_DB' => $nomCopie]);
[$code, $sortie] = $script(['--reinitialiser-2fa', '--email=inconnu@exemple.test']);
check($code === 3, 'adresse inconnue : échec explicite (code 3)', $sortie);
[$code, $sortie] = $script(['--reinitialiser-2fa', '--email=admin2@exemple.test']);
check($code === 0 && !$tf->estActive($admin2) && str_contains($sortie, 'réinitialisée'), 'téléphone perdu : 2FA retirée par l\'accès serveur', $sortie);
[$code] = $script(['--reinitialiser-2fa', '--email=admin2@exemple.test']);
check($code === 3, 'compte sans 2FA : refus explicite');
check(count($audits('operator.2fa_reset')) >= 1, 'réinitialisations inscrites au journal global');

echo "\n$passed OK, $failed KO\n";
exit($failed ? 1 : 0);
