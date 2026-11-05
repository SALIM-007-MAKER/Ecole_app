<?php
/**
 * Tests — passkeys (WebAuthn) des opérateurs de la plateforme (migration T073).
 *
 * 1. CBOR : vecteurs de la RFC 8949 (annexe A) et refus des données malformées
 * 2. Vérification WebAuthn avec un FAUSSE appareil (vraies clés ES256 / RS256, vraies signatures) : cas valides et chaque refus
 * 3. Migration T073 : additive, idempotente, réversible
 * 4. Service : prérequis 2FA, ajout, doublon, plafond, renommage, suppression, audit sans clé, alertes e-mail
 * 5. Connexion : défi à usage unique / lié à l'opérateur / expirant, compteur de signatures, passkey d'un autre compte
 * 6. Audit strict : audit impossible = rien de modifié ; la désactivation de la 2FA retire les passkeys
 * 7. Parcours HTTP : connexion par passkey, limitation, ré-authentification par mot de passe, CSRF, affichage
 *
 * Travaille uniquement sur une copie jetable ecole_app_copy_* : la base vivante n'est jamais touchée.
 * Aucun vrai navigateur ni appareil n'est utilisé : l'appareil est simulé (voir la classe Appareil).
 *
 * Lance : C:\wamp64\bin\php\php8.2.29\php.exe tests/Unit/PlatformPasskeyTest.php
 */

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__, 2));
foreach (file(ROOT_PATH . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $l) {
    if (str_starts_with(trim($l), '#') || !str_contains($l, '=')) { continue; }
    [$k, $v] = explode('=', $l, 2);
    $_ENV[trim($k)] = trim($v, " \t\n\r\0\x0B\"'");
}
unset($_ENV['PLATFORM_TIMEZONE'], $_ENV['PLATFORM_DEFAULT_CURRENCY'], $_ENV['PLATFORM_REQUIRE_2FA'], $_ENV['PLATFORM_WEBAUTHN_ORIGIN'], $_ENV['PLATFORM_WEBAUTHN_RP_ID']);
putenv('PLATFORM_REQUIRE_2FA');
// Windows : sans fichier de configuration, openssl_pkey_new() échoue (il ne sert qu'à fabriquer les clés de l'appareil simulé).
if (!getenv('OPENSSL_CONF') && is_file($cnf = dirname(PHP_BINARY) . '/extras/ssl/openssl.cnf')) { putenv('OPENSSL_CONF=' . $cnf); }
$OPENSSL_CNF = is_file($cnf = dirname(PHP_BINARY) . '/extras/ssl/openssl.cnf') ? $cnf : null;
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
use Core\Platform\PlatformPasskeyService as P;
use Core\Platform\PlatformSecurityNotifier as N;
use Core\Platform\PlatformTwoFactorService as TF;
use Core\Platform\TwoFactorException;
use Core\Platform\WebAuthn\Cbor;
use Core\Platform\WebAuthn\WebAuthnException;
use Core\Platform\WebAuthn\WebAuthnVerifier as W;

$passed = 0; $failed = 0;
function check(bool $ok, string $label, string $detail = ''): void
{
    global $passed, $failed;
    if ($ok) { $passed++; echo "\033[32m  ✓ $label\033[0m\n"; }
    else     { $failed++; echo "\033[31m  ✗ $label" . ($detail !== '' ? " — $detail" : '') . "\033[0m\n"; }
}
function section(string $t): void { echo "\n\033[33m── $t\033[0m\n"; }
function lancer(array $cmd, array $env = []): array
{
    $p = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, ROOT_PATH, array_merge(getenv(), $env));
    $out = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
    return [proc_close($p), $out];
}
function http(string $nomCopie, string $operateur, string $methode, string $uri, array $post = [], array $env = []): array
{
    global $dossierSecours, $dossierThrottle, $dossierCorps;
    $corps = $dossierCorps . '_' . bin2hex(random_bytes(3));
    [$code, $sortie] = lancer([PHP_BINARY, ROOT_PATH . '/tests/Support/platform_request.php', $operateur, $methode, $uri, json_encode($post)],
        ['REQUEST_AS_DB' => $nomCopie, 'PLATFORM_REQ_AUDIT_DIR' => $dossierSecours, 'PLATFORM_THROTTLE_DIR' => $dossierThrottle, 'REQUEST_AS_BODY_OUT' => $corps] + $env);
    $r = preg_match('/^RESULT:(.*)$/m', $sortie, $m) ? (json_decode($m[1], true) ?: ['status' => 0]) : ['status' => 0, 'brut' => $sortie, 'code' => $code];
    $r['json'] = is_file($corps) ? json_decode((string)file_get_contents($corps), true) : null;
    return $r;
}
function operateur(int $id, string $niveau): string { return json_encode(['id' => $id, 'niveau' => $niveau, 'email' => "op$id@exemple.test", 'nom' => "Op $id", 'must_change' => false]); }
function ip(): string { return '10.' . random_int(1, 250) . '.' . random_int(1, 250) . '.' . random_int(1, 250); }

// ───────────────────────────────────────────── encodeur CBOR et faux appareil (tests uniquement) ─────────────────────────────────────────────
final class Bytes { public function __construct(public string $v) {} }
final class CMap  { public function __construct(public array $v) {} }
function tete(int $major, int $n): string
{
    return $n < 24 ? chr(($major << 5) | $n) : ($n < 256 ? chr(($major << 5) | 24) . chr($n) : ($n < 65536 ? chr(($major << 5) | 25) . pack('n', $n) : chr(($major << 5) | 26) . pack('N', $n)));
}
function cbor(mixed $v): string
{
    if ($v === true) { return "\xF5"; }
    if ($v === false) { return "\xF4"; }
    if ($v === null) { return "\xF6"; }
    if (is_int($v)) { return $v >= 0 ? tete(0, $v) : tete(1, -1 - $v); }
    if ($v instanceof Bytes) { return tete(2, strlen($v->v)) . $v->v; }
    if (is_string($v)) { return tete(3, strlen($v)) . $v; }
    if ($v instanceof CMap) { $v = $v->v; $liste = false; } else { $liste = array_is_list($v); }
    $s = tete($liste ? 4 : 5, count($v));
    foreach ($v as $k => $x) { $s .= ($liste ? '' : cbor($k)) . cbor($x); }
    return $s;
}
/** Appareil simulé : détient une vraie clé privée et signe comme un authentificateur WebAuthn. */
final class Appareil
{
    public $cle;
    public string $credId;
    public int $compteur = 0;
    public function __construct(public int $alg = -7, public string $rpId = 'localhost', public string $origine = 'http://localhost')
    {
        $this->credId = random_bytes(32);
        $this->cle = $alg === -7
            ? openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC] + self::config())
            : openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA] + self::config());
        if ($this->cle === false) { throw new RuntimeException('openssl_pkey_new a échoué : ' . openssl_error_string()); }
    }
    private static function config(): array
    {
        global $OPENSSL_CNF;
        return $OPENSSL_CNF !== null ? ['config' => $OPENSSL_CNF] : [];
    }
    public function cose(): array
    {
        $d = openssl_pkey_get_details($this->cle);
        if ($this->alg === -7) {
            return [1 => 2, 3 => -7, -1 => 1, -2 => new Bytes(str_pad($d['ec']['x'], 32, "\0", STR_PAD_LEFT)), -3 => new Bytes(str_pad($d['ec']['y'], 32, "\0", STR_PAD_LEFT))];
        }
        return [1 => 3, 3 => -257, -1 => new Bytes($d['rsa']['n']), -2 => new Bytes($d['rsa']['e'])];
    }
    /** @return array{0:string,1:string} [clientDataJSON, attestationObject] en base64url */
    public function attestation(string $defi, array $o = []): array
    {
        $client = json_encode(['type' => $o['type'] ?? 'webauthn.create', 'challenge' => W::b64uEncode($o['defi_dans_client'] ?? $defi), 'origin' => $o['origin'] ?? $this->origine, 'crossOrigin' => false]);
        $cose = $o['cose'] ?? $this->cose();
        $auth = hash('sha256', $o['rpId'] ?? $this->rpId, true) . chr($o['flags'] ?? 0x41) . pack('N', $this->compteur)
              . str_repeat("\0", 16) . pack('n', strlen($this->credId)) . $this->credId . cbor(new CMap($cose));
        if (isset($o['auth_tronque'])) { $auth = substr($auth, 0, $o['auth_tronque']); }
        $obj = cbor(new CMap(['fmt' => 'none', 'attStmt' => new CMap([]), 'authData' => new Bytes($auth)]));
        return [W::b64uEncode($client), W::b64uEncode($obj)];
    }
    /** @return array{0:string,1:string,2:string,3:string} [credentialId, clientDataJSON, authenticatorData, signature] en base64url */
    public function assertion(string $defi, array $o = []): array
    {
        $this->compteur = $o['compteur'] ?? $this->compteur + 1;
        $client = json_encode(['type' => $o['type'] ?? 'webauthn.get', 'challenge' => W::b64uEncode($o['defi_dans_client'] ?? $defi), 'origin' => $o['origin'] ?? $this->origine, 'crossOrigin' => false]);
        $auth = hash('sha256', $o['rpId'] ?? $this->rpId, true) . chr($o['flags'] ?? 0x01) . pack('N', $this->compteur);
        openssl_sign($auth . hash('sha256', $client, true), $sig, $o['cle_signature'] ?? $this->cle, OPENSSL_ALGO_SHA256);
        if (isset($o['signature_alteree'])) { $sig[strlen($sig) - 1] = chr(ord($sig[strlen($sig) - 1]) ^ 1); }
        if (isset($o['auth_altere'])) { $auth[34] = chr(ord($auth[34]) ^ 0xFF); }
        return [W::b64uEncode($this->credId), W::b64uEncode($client), W::b64uEncode($auth), W::b64uEncode($sig)];
    }
}
function leve(callable $f): ?string
{
    try { $f(); return null; } catch (WebAuthnException | TwoFactorException $e) { return $e->getMessage(); }
}

// ═════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════
section('1. CBOR (RFC 8949, annexe A)');
foreach ([['00', 0], ['17', 23], ['1818', 24], ['1864', 100], ['1903e8', 1000], ['20', -1], ['3863', -100], ['f4', false], ['f5', true], ['f6', null],
          ['6161', 'a'], ['4401020304', "\x01\x02\x03\x04"], ['83010203', [1, 2, 3]], ['a201020304', [1 => 2, 3 => 4]], ['a26161016162820203', ['a' => 1, 'b' => [2, 3]]]] as [$hex, $attendu]) {
    check(Cbor::decodeComplet(hex2bin($hex)) === $attendu, "décode $hex");
}
check(bin2hex(cbor([1 => 2, 3 => 4])) === 'a201020304' && bin2hex(cbor(new Bytes("\x01\x02"))) === '420102', 'l\'encodeur de test est cohérent avec la RFC');
foreach ([['tronqué', '1903'], ['octets en trop', '0000'], ['flottant', 'f90000'], ['longueur indéfinie', '9fff'], ['étiquette', 'c000'], ['chaîne plus longue que les données', '4a0102'],
          ['tableau annonçant trop d\'éléments', '9a000f4240'], ['clé en double', 'a201020102'], ['clé non entière/texte', 'a1f40a'], ['vide', ''], ['simple 255', 'f8ff']] as [$nom, $hex]) {
    check(leve(fn() => Cbor::decodeComplet(hex2bin($hex))) !== null, "refus : $nom");
}
check(leve(fn() => Cbor::decodeComplet(str_repeat("\x81", 20) . "\x00")) !== null, 'refus : imbrication excessive');

// ═════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════
section('2. Vérification WebAuthn avec un appareil simulé');
$ctx = P::contexte();
check($ctx['rp_id'] === 'localhost' && $ctx['origine'] === 'http://localhost', 'contexte : RP ID et origine issus d\'APP_URL', json_encode($ctx));
$defi = random_bytes(32);
foreach ([-7 => 'ES256', -257 => 'RS256'] as $alg => $nomAlg) {
    $a = new Appareil($alg);
    [$cd, $ao] = $a->attestation($defi);
    $cle = W::verifierEnregistrement($cd, $ao, $defi, 'localhost', 'http://localhost');
    check($cle['credential_id'] === $a->credId && $cle['alg'] === $alg && str_contains($cle['public_key_pem'], 'BEGIN PUBLIC KEY'), "$nomAlg : enregistrement accepté, clé publique extraite");
    [$id, $cd2, $ad, $sg] = $a->assertion($defi);
    check(W::verifierAssertion($cd2, $ad, $sg, $defi, 'localhost', 'http://localhost', $cle['public_key_pem']) === 1, "$nomAlg : assertion valide, compteur lu");
}
$a = new Appareil(-7);
[$cd, $ao] = $a->attestation($defi);
$cle = W::verifierEnregistrement($cd, $ao, $defi, 'localhost', 'http://localhost');
$pem = $cle['public_key_pem'];
$enr = static fn(array $o, ?string $defiAttendu = null, string $rp = 'localhost', string $origine = 'http://localhost') => leve(function () use ($a, $defi, $o, $defiAttendu, $rp, $origine): void {
    [$c, $b] = $a->attestation($defi, $o);
    W::verifierEnregistrement($c, $b, $defiAttendu ?? $defi, $rp, $origine);
});
check($enr(['defi_dans_client' => random_bytes(32)]) !== null, 'enregistrement : défi différent refusé');
check($enr([], random_bytes(32)) !== null, 'enregistrement : défi attendu différent refusé');
check($enr(['origin' => 'http://evil.example']) !== null, 'enregistrement : origine étrangère refusée');
check($enr(['origin' => 'http://localhost.evil.example']) !== null && $enr(['origin' => 'http://localhost:8080']) !== null, 'enregistrement : origine voisine (sous-domaine, autre port) refusée');
check($enr(['rpId' => 'evil.example']) !== null, 'enregistrement : RP ID étranger refusé');
check($enr(['type' => 'webauthn.get']) !== null, 'enregistrement : mauvais type d\'opération refusé');
check($enr(['flags' => 0x40]) !== null, 'enregistrement : sans présence de l\'utilisateur refusé');
check($enr(['flags' => 0x01]) !== null, 'enregistrement : sans données de credential refusé');
check($enr(['auth_tronque' => 20]) !== null && $enr(['auth_tronque' => 60]) !== null, 'enregistrement : données d\'authentificateur tronquées refusées');
check($enr(['cose' => [1 => 1, 3 => -8, -1 => 6, -2 => new Bytes(str_repeat('a', 32))]]) !== null, 'enregistrement : algorithme non pris en charge (EdDSA) refusé');
$coseCourte = $a->cose(); $coseCourte[-2] = new Bytes('court');
check($enr(['cose' => $coseCourte]) !== null, 'enregistrement : coordonnée de courbe de mauvaise taille refusée');
check(leve(fn() => W::verifierEnregistrement('%%%', $ao, $defi, 'localhost', 'http://localhost')) !== null, 'enregistrement : base64url invalide refusé');
check(leve(fn() => W::verifierEnregistrement($cd, W::b64uEncode(cbor('pas une table')), $defi, 'localhost', 'http://localhost')) !== null, 'enregistrement : objet d\'attestation invalide refusé');
$ass = static fn(array $o = [], ?string $defiAttendu = null, ?string $clePem = null, string $origine = 'http://localhost') => leve(function () use ($a, $defi, $o, $defiAttendu, $clePem, $pem, $origine): void {
    [, $c, $ad, $sg] = $a->assertion($defi, $o);
    W::verifierAssertion($c, $ad, $sg, $defiAttendu ?? $defi, 'localhost', $origine, $clePem ?? $pem);
});
check($ass() === null, 'assertion : valide');
check($ass(['defi_dans_client' => random_bytes(32)]) !== null && $ass([], random_bytes(32)) !== null, 'assertion : défi incorrect refusé');
check($ass(['origin' => 'http://evil.example']) !== null, 'assertion : origine étrangère refusée');
check($ass(['rpId' => 'evil.example']) !== null, 'assertion : RP ID étranger refusé');
check($ass(['type' => 'webauthn.create']) !== null, 'assertion : mauvais type refusé');
check($ass(['flags' => 0x00]) !== null, 'assertion : sans présence de l\'utilisateur refusée');
check($ass(['signature_alteree' => 1]) !== null, 'assertion : signature altérée refusée');
check($ass(['auth_altere' => 1]) !== null, 'assertion : données d\'authentificateur altérées refusées');
$autre = new Appareil(-7);
check($ass(['cle_signature' => $autre->cle]) !== null, 'assertion : signature d\'une autre clé refusée');
[$c2, $b2] = (new Appareil(-7))->attestation($defi);
$pemAutre = W::verifierEnregistrement($c2, $b2, $defi, 'localhost', 'http://localhost')['public_key_pem'];
check($ass([], null, $pemAutre) !== null, 'assertion : vérifiée avec la clé publique d\'un autre appareil, refusée');
check(leve(fn() => W::verifierAssertion('%%%', 'AA', 'AA', $defi, 'localhost', 'http://localhost', $pem)) !== null, 'assertion : base64url invalide refusé');

// ═════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════
section('3. Migration T073');
$_SESSION = [];
$copie = \Tests\Support\DbCopy::redirigerVersCopie(ROOT_PATH) ?? exit("ECHEC : copie jetable impossible (mysql/mysqldump ou droit CREATE DATABASE)\n");
$nomCopie = $copie->connection()['db'];
$pdo = Database::getInstance()->getConnection();
$dossierSecours = sys_get_temp_dir() . '/ppk_' . bin2hex(random_bytes(4));
$dossierThrottle = sys_get_temp_dir() . '/ppk_thr_' . bin2hex(random_bytes(4));
$dossierCorps = sys_get_temp_dir() . '/ppk_corps_' . bin2hex(random_bytes(4));
PlatformAuditService::definirDossierSecours($dossierSecours);
$migration = require ROOT_PATH . '/database/migrations/T073_passkeys_operateurs.php';
$migration['rollback']($pdo);
check(!P::disponible($pdo), 'sans la migration : indisponible (le portail garde son comportement d\'avant)');
$migration['run']($pdo);
$migration['run']($pdo);
check(P::disponible($pdo), 'T073 appliquée deux fois sans erreur (idempotente)');
check((int)$pdo->query('SELECT COUNT(*) FROM platform_operator_passkeys')->fetchColumn() === 0, 'personne n\'a de passkey après la migration');
$migration['rollback']($pdo); $migration['run']($pdo);
check(P::disponible($pdo), 'retour arrière puis nouvelle application : table présente');

// ═════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════
section('4. Service : prérequis, ajout, doublon, plafond, renommage, suppression');
$ops = new PlatformOperatorService($pdo);
$mdp = 'Zq7!pLm2#vXk9@Rt';
$superId = $ops->creer('super@exemple.test', 'Super Opérateur', 'super_admin', $mdp, PlatformOperatorService::ACTEUR_CLI)['id'];
$autreId = $ops->creer('autre@exemple.test', 'Autre Opérateur', 'super_admin', $mdp, PlatformOperatorService::ACTEUR_CLI)['id'];
$sansTf = $ops->creer('sans@exemple.test', 'Sans Deux', 'admin', $mdp, PlatformOperatorService::ACTEUR_CLI)['id'];
$maintenant = 1_800_000_000;
$tf = new TF($pdo, static function () use (&$maintenant): int { return $maintenant; });
$secrets = [];
$activer2fa = function (int $id) use ($tf, $maintenant, &$secrets): void {
    $secrets[$id] = TF::nouveauSecret();
    $tf->activer($id, $secrets[$id], TF::totp($secrets[$id], intdiv($maintenant, 30)));
};
$mails = [];
N::definirEnvoyeur(static function (string $to, string $objet, string $html) use (&$mails): bool { $mails[] = compact('to', 'objet', 'html'); return true; });
$svc = new P($pdo);
$audits = static fn(string $like) => $pdo->query('SELECT * FROM platform_audit_logs WHERE action LIKE ' . $pdo->quote($like) . ' ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);

check(leve(fn() => $svc->optionsEnregistrement($sansTf, 'sans@exemple.test')) !== null, 'sans 2FA par application : options d\'enregistrement refusées');
$activer2fa($superId); $activer2fa($autreId);
$opt = $svc->optionsEnregistrement($superId, 'super@exemple.test');
check(strlen(W::b64uDecode($opt['challenge'])) === 32 && $opt['rp']['id'] === 'localhost' && $opt['attestation'] === 'none' && $opt['excludeCredentials'] === [], 'options : défi de 32 octets, RP ID, attestation « none », rien à exclure');
check(!str_contains(json_encode($opt), 'super@exemple.test') || $opt['user']['name'] === 'super@exemple.test', 'options : l\'e-mail n\'apparaît que comme nom d\'utilisateur');
check($opt['user']['id'] !== W::b64uEncode((string)$superId) && strlen(W::b64uDecode($opt['user']['id'])) === 32, 'options : identifiant utilisateur opaque (pas l\'identifiant numérique)');

$tel = new Appareil(-7);
[$cd, $ao] = $tel->attestation(W::b64uDecode($_SESSION['_platform_passkey_challenge']['c']));
$svc->enregistrer($superId, "  Mon\tTéléphone  ", $cd, $ao);
$liste = $svc->liste($superId);
check(count($liste) === 1 && $liste[0]['nom'] === 'Mon Téléphone' && $liste[0]['last_used_at'] === null, 'ajout : passkey listée, nom nettoyé, jamais utilisée');
$brut = $pdo->query('SELECT * FROM platform_operator_passkeys')->fetch(PDO::FETCH_ASSOC);
check($brut['credential_id'] === W::b64uEncode($tel->credId) && str_contains($brut['public_key'], 'PUBLIC KEY') && !str_contains($brut['public_key'], 'PRIVATE'), 'stockage : identifiant en base64url et clé PUBLIQUE uniquement');
$a1 = $audits('operator.passkey_added');
check(count($a1) === 1 && !str_contains(json_encode($a1[0]), 'PUBLIC KEY') && !str_contains(json_encode($a1[0]), W::b64uEncode($tel->credId)), 'audit : ajout journalisé, sans clé ni identifiant de credential');
$dernier = $mails[count($mails) - 1] ?? ['to' => '', 'objet' => '', 'html' => ''];
check($dernier['to'] === 'super@exemple.test' && str_contains($dernier['objet'], 'passkey') && str_contains($dernier['objet'], 'ajoutée') && str_contains($dernier['html'], 'Mon Téléphone'), 'alerte e-mail : passkey ajoutée, avec son nom', json_encode(array_column($mails, 'objet'), JSON_UNESCAPED_UNICODE) . ' échecs=' . json_encode(array_map(fn($x) => $x['entity_type'] . ':' . ($x['apres'] ?? $x['nouvelle_valeur'] ?? ''), $audits('operator.notification.failed')), JSON_UNESCAPED_UNICODE) . ' dispo=' . var_export(N::disponible(), true));
check(!isset($_SESSION['_platform_passkey_challenge']), 'le défi est détruit après usage');

$opt2 = $svc->optionsEnregistrement($superId, 'super@exemple.test');
check(count($opt2['excludeCredentials']) === 1 && $opt2['excludeCredentials'][0]['id'] === W::b64uEncode($tel->credId), 'options : la passkey existante est exclue (pas de doublon sur le même appareil)');
[$cd, $ao] = $tel->attestation(W::b64uDecode($_SESSION['_platform_passkey_challenge']['c']));
check(leve(fn() => $svc->enregistrer($superId, 'Doublon', $cd, $ao)) !== null && $svc->nombre($superId) === 1, 'doublon : même credential refusé');
check(leve(fn() => $svc->enregistrer($superId, 'Rejeu', $cd, $ao)) !== null, 'rejeu : le défi déjà consommé ne sert plus');

$svc->optionsEnregistrement($autreId, 'autre@exemple.test');
[$cd, $ao] = (new Appareil(-7))->attestation(W::b64uDecode($_SESSION['_platform_passkey_challenge']['c']));
check(leve(fn() => $svc->enregistrer($superId, 'Mauvais compte', $cd, $ao)) !== null, 'un défi émis pour un autre opérateur ne peut pas servir');
$svc->optionsEnregistrement($superId, 'super@exemple.test');
$_SESSION['_platform_passkey_challenge']['at'] = time() - P::DUREE_DEFI - 1;
[$cd, $ao] = (new Appareil(-7))->attestation(W::b64uDecode($_SESSION['_platform_passkey_challenge']['c']));
check(leve(fn() => $svc->enregistrer($superId, 'Expiré', $cd, $ao)) !== null, 'un défi expiré (plus de 2 minutes) est refusé');
$svc->optionsEnregistrement($superId, 'super@exemple.test');
$defiGet = $svc->optionsConnexion($superId);
check(leve(function () use ($svc, $superId) {
    // défi « get » utilisé comme défi « create » : refusé
    [$c, $b] = (new Appareil(-7))->attestation(W::b64uDecode($_SESSION['_platform_passkey_challenge']['c']));
    $svc->enregistrer($superId, 'Mauvais usage', $c, $b);
}) !== null, 'un défi de connexion ne peut pas servir à un enregistrement');

// Plafond
$autresAppareils = [];
for ($i = 2; $i <= P::MAX_PAR_OPERATEUR; $i++) {
    $svc->optionsEnregistrement($superId, 'super@exemple.test');
    $ap = new Appareil($i % 2 ? -7 : -257);
    [$cd, $ao] = $ap->attestation(W::b64uDecode($_SESSION['_platform_passkey_challenge']['c']));
    $svc->enregistrer($superId, "Clé $i", $cd, $ao);
    $autresAppareils[] = $ap;
}
check($svc->nombre($superId) === P::MAX_PAR_OPERATEUR, 'plafond : ' . P::MAX_PAR_OPERATEUR . ' passkeys enregistrées (ES256 et RS256 mélangées)');
check(leve(fn() => $svc->optionsEnregistrement($superId, 'super@exemple.test')) !== null, 'plafond atteint : nouvelles options refusées');

// Renommage / suppression
$premier = $svc->liste($superId)[0]['id'];
$svc->renommer($superId, $premier, str_repeat('é', 120));
check(mb_strlen($svc->liste($superId)[0]['nom']) === 80, 'renommage : nom tronqué à 80 caractères');
$svc->renommer($superId, $premier, "  \t ");
check($svc->liste($superId)[0]['nom'] === 'Passkey', 'renommage : nom vide remplacé par « Passkey »');
check(leve(fn() => $svc->renommer($autreId, $premier, 'Volée')) !== null && $svc->liste($superId)[0]['nom'] === 'Passkey', 'renommage : impossible sur la passkey d\'un autre opérateur');
check(leve(fn() => $svc->supprimer($autreId, $premier)) !== null && $svc->nombre($superId) === P::MAX_PAR_OPERATEUR, 'suppression : impossible sur la passkey d\'un autre opérateur');
$avantMails = count($mails);
$svc->supprimer($superId, $premier);
check($svc->nombre($superId) === P::MAX_PAR_OPERATEUR - 1 && count($audits('operator.passkey_removed')) === 1 && count($audits('operator.passkey_renamed')) === 2, 'suppression et renommages journalisés');
check(count($mails) === $avantMails + 1 && str_contains($mails[count($mails) - 1]['objet'], 'retirée'), 'alerte e-mail : passkey retirée', $avantMails . ' -> ' . count($mails) . ' ' . json_encode(array_column($mails, 'objet'), JSON_UNESCAPED_UNICODE));
check(leve(fn() => $svc->supprimer($superId, 999999)) !== null, 'suppression : passkey inconnue refusée');

// ═════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════
section('5. Connexion : défi, compteur, passkey d\'un autre compte');
$pk = $autresAppareils[0];   // enregistrée pour $superId
$connexion = function (Appareil $ap, int $opId, array $o = [], bool $emettre = true) use ($svc): bool {
    if ($emettre) { $svc->optionsConnexion($opId); }
    $defiC = W::b64uDecode($_SESSION['_platform_passkey_challenge']['c'] ?? W::b64uEncode(random_bytes(32)));
    [$id, $c, $ad, $sg] = $ap->assertion($defiC, $o);
    return $svc->verifierConnexion($opId, $id, $c, $ad, $sg);
};
check($svc->optionsConnexion($sansTf) === null, 'options de connexion : null pour un opérateur sans passkey');
$optC = $svc->optionsConnexion($superId);
check(count($optC['allowCredentials']) === P::MAX_PAR_OPERATEUR - 1 && $optC['rpId'] === 'localhost' && strlen(W::b64uDecode($optC['challenge'])) === 32, 'options de connexion : défi, RP ID, liste des credentials de l\'opérateur');
check($connexion($pk, $superId) === true, 'connexion par passkey : acceptée');
$ligne = $pdo->query('SELECT sign_count, last_used_at FROM platform_operator_passkeys WHERE credential_id = ' . $pdo->quote(W::b64uEncode($pk->credId)))->fetch(PDO::FETCH_ASSOC);
check((int)$ligne['sign_count'] === 1 && $ligne['last_used_at'] !== null, 'compteur de signatures et date d\'usage mis à jour');
check(!isset($_SESSION['_platform_passkey_challenge']), 'le défi de connexion est détruit après usage');
$svc->optionsConnexion($superId);
$defiC = W::b64uDecode($_SESSION['_platform_passkey_challenge']['c']);
[$id, $c, $ad, $sg] = $pk->assertion($defiC);
check($svc->verifierConnexion($superId, $id, $c, $ad, $sg) === true && $svc->verifierConnexion($superId, $id, $c, $ad, $sg) === false, 'rejeu de la même réponse : refusé (défi à usage unique)');
check($connexion($pk, $superId, ['compteur' => 2]) === false, 'compteur qui ne progresse pas (clone possible) : refusé');
check($connexion($pk, $superId, ['compteur' => 1]) === false, 'compteur en arrière : refusé');
check($connexion($pk, $superId, ['compteur' => 50]) === true, 'compteur qui saute en avant : accepté');
check($connexion($pk, $superId, ['signature_alteree' => 1]) === false, 'signature altérée : refusée');
check($connexion($pk, $superId, ['origin' => 'http://evil.example']) === false, 'origine étrangère : refusée');
check($connexion($pk, $autreId) === false, 'la passkey d\'un opérateur ne connecte pas un autre opérateur');
$svc->optionsConnexion($superId);
check($connexion($pk, $autreId, [], false) === false, 'défi émis pour un opérateur, utilisé pour un autre : refusé');
check($connexion(new Appareil(-7), $superId) === false, 'passkey inconnue : refusée');
check($connexion($pk, $superId, [], false) === false, 'sans défi en cours : refusé');
$svc->optionsConnexion($superId);
$_SESSION['_platform_passkey_challenge']['at'] = time() - P::DUREE_DEFI - 1;
check($connexion($pk, $superId, ['compteur' => 99], false) === false, 'défi de connexion expiré : refusé');
// Appareil dont le compteur reste à 0 (cas fréquent des passkeys synchronisées)
$zero = new Appareil(-7);
$svc2 = $svc;
$svc2->optionsEnregistrement($autreId, 'autre@exemple.test');
[$cd, $ao] = $zero->attestation(W::b64uDecode($_SESSION['_platform_passkey_challenge']['c']));
$svc2->enregistrer($autreId, 'Synchronisée', $cd, $ao);
check($connexion($zero, $autreId, ['compteur' => 0]) === true && $connexion($zero, $autreId, ['compteur' => 0]) === true, 'appareil au compteur toujours à 0 : accepté');
check($connexion($zero, $autreId, ['compteur' => 5]) === true && $connexion($zero, $autreId, ['compteur' => 0]) === false, 'compteur qui repasse à 0 après avoir avancé : refusé');

// ═════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════
section('6. Audit strict et lien avec la 2FA');
$avant = $svc->nombre($superId);
$pdo->exec('RENAME TABLE platform_audit_logs TO platform_audit_logs_hs');
$premierId = $svc->liste($superId)[0]['id'];
$r = leve(fn() => $svc->supprimer($superId, $premierId));
$pdo->exec('RENAME TABLE platform_audit_logs_hs TO platform_audit_logs');
check($r !== null && $svc->nombre($superId) === $avant, 'audit impossible : la suppression est annulée, rien n\'est modifié');
$mailsAvant = count($mails);
$pdo->exec('RENAME TABLE platform_audit_logs TO platform_audit_logs_hs');
$cleAudit = new Appareil(-7);
$pdo->exec('DELETE FROM platform_operator_passkeys WHERE operator_id = ' . $autreId);
$svc->optionsEnregistrement($autreId, 'autre@exemple.test');
[$cd, $ao] = $cleAudit->attestation(W::b64uDecode($_SESSION['_platform_passkey_challenge']['c']));
$r = leve(fn() => $svc->enregistrer($autreId, 'Sans audit', $cd, $ao));
$pdo->exec('RENAME TABLE platform_audit_logs_hs TO platform_audit_logs');
check($r !== null && $svc->nombre($autreId) === 0 && count($mails) === $mailsAvant, 'audit impossible : l\'ajout est annulé et aucune alerte n\'est envoyée');

$svc->optionsEnregistrement($autreId, 'autre@exemple.test');
[$cd, $ao] = $cleAudit->attestation(W::b64uDecode($_SESSION['_platform_passkey_challenge']['c']));
$svc->enregistrer($autreId, 'À retirer', $cd, $ao);
check($svc->nombre($autreId) === 1, 'préparation : une passkey sur le compte « autre »');
$tf->reinitialiser($autreId);
check($svc->nombre($autreId) === 0, 'réinitialisation de la 2FA : les passkeys sont retirées');
$activer2fa($autreId);
$svc->optionsEnregistrement($autreId, 'autre@exemple.test');
[$cd, $ao] = (new Appareil(-7))->attestation(W::b64uDecode($_SESSION['_platform_passkey_challenge']['c']));
$svc->enregistrer($autreId, 'Autre', $cd, $ao);
check($svc->nombre($autreId) === 1, 'préparation : une passkey après réactivation de la 2FA');
$tf->desactiver($autreId, $mdp, TF::totp($secrets[$autreId], intdiv($maintenant, 30) + 1));
check($svc->nombre($autreId) === 0 && !$tf->estActive($autreId), 'désactivation de la 2FA : les passkeys sont retirées aussi');

// ═════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════
section('7. Parcours HTTP');
$P = '/ecole_app/platform';
$opH = ['id' => $superId, 'email' => 'super@exemple.test', 'nom' => 'Super Opérateur', 'niveau' => 'super_admin', 'must_change' => false];
$graine = static fn(string $usage, string $defiBin, ?array $op = null, int $age = 0): string => json_encode([
    '_platform_mfa_pending'       => ['op' => $op ?? $opH, 'at' => time() - $age, 'essais' => 0],
    '_platform_passkey_challenge' => ['c' => W::b64uEncode($defiBin), 'at' => time(), 'usage' => $usage, 'op' => (int)($op ?? $opH)['id']],
]);
$champs = static fn(array $a): array => ['credential_id' => $a[0], 'client_data' => $a[1], 'authenticator_data' => $a[2], 'signature' => $a[3]];
$pk = $autresAppareils[0];

$r = http($nomCopie, 'guest', 'GET', "$P/login/2fa", [], ['PLATFORM_REQ_SESSION' => $graine('get', random_bytes(32))]);
check($r['status'] === 200 && str_contains($r['text'], 'Utiliser ma passkey'), 'page de second facteur : bouton « Utiliser ma passkey » pour un opérateur qui en a');
$opSans = ['id' => $autreId, 'email' => 'autre@exemple.test', 'nom' => 'Autre', 'niveau' => 'super_admin', 'must_change' => false];
$r = http($nomCopie, 'guest', 'GET', "$P/login/2fa", [], ['PLATFORM_REQ_SESSION' => $graine('get', random_bytes(32), $opSans)]);
check($r['status'] === 200 && !str_contains($r['text'], 'Utiliser ma passkey') && str_contains($r['text'], 'Code de vérification'), 'page de second facteur : pas de bouton sans passkey, le code reste proposé');

$r = http($nomCopie, 'guest', 'POST', "$P/login/2fa/passkey/options", [], ['PLATFORM_REQ_SESSION' => $graine('get', random_bytes(32)), 'PLATFORM_REQ_IP' => ip()]);
check($r['status'] === 200 && ($r['json']['ok'] ?? false) === true && count($r['json']['options']['allowCredentials']) === $svc->nombre($superId), 'options de connexion (HTTP) : défi et credentials de l\'opérateur');
$r = http($nomCopie, 'guest', 'POST', "$P/login/2fa/passkey/options", [], ['PLATFORM_REQ_SESSION' => $graine('get', random_bytes(32), $opSans), 'PLATFORM_REQ_IP' => ip()]);
check($r['status'] === 404 && ($r['json']['ok'] ?? true) === false, 'options de connexion (HTTP) : 404 pour un opérateur sans passkey');
$r = http($nomCopie, 'guest', 'POST', "$P/login/2fa/passkey/options", [], ['PLATFORM_REQ_IP' => ip()]);
check($r['status'] === 401 && !empty($r['json']['redirect']), 'options de connexion (HTTP) : sans mot de passe validé → 401 et retour à la connexion');

$d = random_bytes(32);
$puits = $dossierCorps . '_mails.jsonl';
$r = http($nomCopie, 'guest', 'POST', "$P/login/2fa/passkey", $champs($pk->assertion($d)), ['PLATFORM_REQ_SESSION' => $graine('get', $d), 'PLATFORM_REQ_IP' => ip(), 'PLATFORM_REQ_MAIL_SINK' => $puits]);
check($r['status'] === 200 && ($r['json']['ok'] ?? false) === true && (int)($r['operator']['id'] ?? 0) === $superId && $r['mfa_method'] === 'passkey', 'connexion par passkey (HTTP) : session ouverte, méthode « passkey »', json_encode($r['json']));
$a = $pdo->query("SELECT * FROM platform_audit_logs WHERE action = 'operator.login.success' ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
check($a && str_contains(json_encode($a), 'passkey'), 'audit : la connexion indique le second facteur « passkey »');
$recus = is_file($puits) ? (string)file_get_contents($puits) : '';
check(str_contains($recus, 'Nouvelle connexion') && str_contains($recus, 'Second facteur : passkey'), 'alerte e-mail de connexion : précise le second facteur utilisé', $recus);

$d = random_bytes(32);
$r = http($nomCopie, 'guest', 'POST', "$P/login/2fa/passkey", $champs($pk->assertion($d, ['signature_alteree' => 1])), ['PLATFORM_REQ_SESSION' => $graine('get', $d), 'PLATFORM_REQ_IP' => ip()]);
check($r['status'] === 401 && $r['operator'] === null && ($r['json']['ok'] ?? true) === false, 'signature altérée (HTTP) : 401, pas de session');
$d = random_bytes(32);
$r = http($nomCopie, 'guest', 'POST', "$P/login/2fa/passkey", $champs($pk->assertion($d)), ['PLATFORM_REQ_SESSION' => $graine('get', $d, null, 400), 'PLATFORM_REQ_IP' => ip()]);
check($r['status'] === 401 && $r['operator'] === null && !empty($r['json']['redirect']), 'mot de passe validé il y a plus de 5 minutes (HTTP) : refusé, retour à la connexion');
$d = random_bytes(32);
$r = http($nomCopie, 'guest', 'POST', "$P/login/2fa/passkey", $champs($pk->assertion($d)), ['PLATFORM_REQ_SESSION' => $graine('get', $d), 'PLATFORM_REQ_IP' => ip(), 'REQUEST_AS_NO_CSRF' => '1']);
check($r['operator'] === null && ($r['json']['ok'] ?? false) !== true, 'sans jeton CSRF (HTTP) : refusé');
$d = random_bytes(32);
$r = http($nomCopie, 'guest', 'POST', "$P/login/2fa/passkey", $champs($pk->assertion($d)), ['PLATFORM_REQ_SESSION' => $graine('create', $d), 'PLATFORM_REQ_IP' => ip()]);
check($r['operator'] === null, 'défi émis pour un enregistrement, présenté à la connexion (HTTP) : refusé');

$ipT = ip();
for ($i = 0; $i < 5; $i++) {
    $d = random_bytes(32);
    http($nomCopie, 'guest', 'POST', "$P/login/2fa/passkey", $champs($pk->assertion($d, ['signature_alteree' => 1])), ['PLATFORM_REQ_SESSION' => $graine('get', $d), 'PLATFORM_REQ_IP' => $ipT]);
}
$d = random_bytes(32);
$r = http($nomCopie, 'guest', 'POST', "$P/login/2fa/passkey", $champs($pk->assertion($d)), ['PLATFORM_REQ_SESSION' => $graine('get', $d), 'PLATFORM_REQ_IP' => $ipT]);
check($r['status'] === 429 && $r['operator'] === null, '5 échecs depuis la même IP : la passkey valide est bloquée (limite partagée avec le code)');

// Côté compte
$S = operateur($superId, 'super_admin');
$ipA = ip();
$r = http($nomCopie, $S, 'POST', "$P/compte/2fa/passkeys/options", [], ['PLATFORM_REQ_IP' => $ipA]);
check($r['status'] === 403 && ($r['json']['ok'] ?? true) === false, 'ajout (HTTP) : sans mot de passe, refusé');
$r = http($nomCopie, $S, 'POST', "$P/compte/2fa/passkeys/options", ['current_password' => 'faux'], ['PLATFORM_REQ_IP' => $ipA, 'REQUEST_AS_NO_CSRF' => '1']);
check(($r['json']['ok'] ?? false) !== true, 'ajout (HTTP) : sans jeton CSRF, refusé');
$r = http($nomCopie, $S, 'POST', "$P/compte/2fa/passkeys/options", ['current_password' => $mdp], ['PLATFORM_REQ_IP' => ip()]);
check($r['status'] === 200 && ($r['json']['ok'] ?? false) === true && count($r['json']['options']['excludeCredentials']) === $svc->nombre($superId), 'ajout (HTTP) : mot de passe correct → options du défi');

$ipB = ip();
for ($i = 0; $i < 5; $i++) { http($nomCopie, $S, 'POST', "$P/compte/2fa/passkeys/options", ['current_password' => 'faux'], ['PLATFORM_REQ_IP' => $ipB]); }
$r = http($nomCopie, $S, 'POST', "$P/compte/2fa/passkeys/options", ['current_password' => $mdp], ['PLATFORM_REQ_IP' => $ipB]);
check($r['status'] === 429, 'ajout (HTTP) : 5 mots de passe faux → la page se verrouille');

$nouveau = new Appareil(-7);
$d = random_bytes(32);
$avant = $svc->nombre($superId);
[$cd, $ao] = $nouveau->attestation($d);
$r = http($nomCopie, $S, 'POST', "$P/compte/2fa/passkeys", ['nom' => 'Ordinateur', 'client_data' => $cd, 'attestation' => $ao], ['PLATFORM_REQ_SESSION' => $graine('create', $d), 'PLATFORM_REQ_IP' => ip()]);
check($r['status'] === 200 && ($r['json']['ok'] ?? false) === true && $svc->nombre($superId) === $avant + 1, 'enregistrement (HTTP) : passkey ajoutée', json_encode($r['json']));
[$cd, $ao] = (new Appareil(-7))->attestation($d, ['origin' => 'http://evil.example']);
$r = http($nomCopie, $S, 'POST', "$P/compte/2fa/passkeys", ['nom' => 'Pirate', 'client_data' => $cd, 'attestation' => $ao], ['PLATFORM_REQ_SESSION' => $graine('create', $d), 'PLATFORM_REQ_IP' => ip()]);
check($r['status'] === 422 && $svc->nombre($superId) === $avant + 1, 'enregistrement (HTTP) : origine étrangère refusée');
$r = http($nomCopie, $S, 'POST', "$P/compte/2fa/passkeys/options", ['current_password' => $mdp], ['PLATFORM_REQ_IP' => ip()]);
check($r['status'] === 422, 'ajout (HTTP) : plafond atteint → refusé');

$idCible = $svc->liste($superId)[0]['id'];
$r = http($nomCopie, $S, 'POST', "$P/compte/2fa/passkeys/$idCible/renommer", ['nom' => '<b>x</b>"\''], ['PLATFORM_REQ_IP' => ip()]);
check($r['status'] === 302 || $r['status'] === 200, 'renommage (HTTP) : accepté');
check($svc->liste($superId)[0]['nom'] === '<b>x</b>"\'', 'renommage (HTTP) : nom enregistré tel quel (échappé à l\'affichage)');
$r = http($nomCopie, $S, 'GET', "$P/compte/2fa", [], ['PLATFORM_REQ_IP' => ip()]);
check($r['status'] === 200 && str_contains($r['text'], 'Passkeys') && str_contains($r['text'], '&lt;b&gt;x&lt;/b&gt;') && !str_contains($r['text'], '<b>x</b>'), 'page Sécurité (HTTP) : passkeys listées, noms échappés');
$nb = $svc->nombre($superId);
$r = http($nomCopie, $S, 'POST', "$P/compte/2fa/passkeys/$idCible/supprimer", [], ['PLATFORM_REQ_IP' => ip()]);
check($svc->nombre($superId) === $nb, 'suppression (HTTP) : sans mot de passe, rien n\'est supprimé');
$r = http($nomCopie, $S, 'POST', "$P/compte/2fa/passkeys/$idCible/supprimer", ['current_password' => $mdp], ['PLATFORM_REQ_IP' => ip()]);
check($svc->nombre($superId) === $nb - 1, 'suppression (HTTP) : avec le mot de passe, supprimée');
$r = http($nomCopie, operateur($sansTf, 'admin'), 'POST', "$P/compte/2fa/passkeys/options", ['current_password' => $mdp], ['PLATFORM_REQ_IP' => ip()]);
check($r['status'] === 422, 'ajout (HTTP) : sans 2FA par application, refusé');
$r = http($nomCopie, 'guest', 'POST', "$P/compte/2fa/passkeys/options", [], ['PLATFORM_REQ_IP' => ip()]);
check($r['operator'] === null && $r['status'] !== 200, 'ajout (HTTP) : visiteur non connecté refusé');

// Nettoyage
N::definirEnvoyeur(null);
$copie->drop();
echo "\n";
echo $failed === 0 ? "\033[32m$passed OK, 0 KO\033[0m\n" : "\033[31m$passed OK, $failed KO\033[0m\n";
exit($failed === 0 ? 0 : 1);
