<?php
/**
 * Tests — briques HTTP de la vérification publique des bulletins (sans base de données)
 *
 *   - ClientIp : X-Forwarded-For n'est JAMAIS cru sans configuration explicite des proxys de confiance
 *   - PublicThrottle : 30 requêtes / minute / IP (fenêtre glissante), seaux indépendants, panne de stockage tolérée
 *   - BulletinVerificationUrl : URL de QR absolue, HTTPS et hôte non local en production
 *   - BulletinPublicationGate : interrupteur de publication fermé par défaut
 *
 * Lance : C:\wamp64\bin\php\php8.2.29\php.exe tests/Unit/HttpSupportTest.php
 */

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__, 2));
foreach (['app/Shared/Http/ClientIp.php', 'app/Shared/Http/PublicThrottle.php',
          'app/Modules/Academique/Services/BulletinTokenException.php', 'app/Modules/Academique/Services/BulletinTokenService.php',
          'app/Modules/Academique/Services/BulletinVerificationUrl.php', 'app/Modules/Academique/Services/BulletinPublicationGate.php'] as $f) {
    require_once ROOT_PATH . '/' . $f;
}

use App\Modules\Academique\Services\BulletinPublicationGate;
use App\Modules\Academique\Services\BulletinTokenService;
use App\Modules\Academique\Services\BulletinVerificationUrl;
use App\Shared\Http\ClientIp;
use App\Shared\Http\PublicThrottle;

$passed = 0; $failed = 0;
function check(bool $ok, string $label): void
{
    global $passed, $failed;
    if ($ok) { $passed++; echo "\033[32m  ✓ $label\033[0m\n"; }
    else     { $failed++; echo "\033[31m  ✗ $label\033[0m\n"; }
}
function section(string $t): void { echo "\n\033[33m── $t\033[0m\n"; }
function rmrf(string $d): void { foreach (glob($d . '/*') ?: [] as $f) { is_dir($f) ? rmrf($f) : @unlink($f); } @rmdir($d); }

// ════════════════════════════════════════════════════════════════════════════
section('1. ClientIp : jamais de confiance aveugle envers X-Forwarded-For');

$srv = fn(string $remote, ?string $xff = null) => ['REMOTE_ADDR' => $remote] + ($xff !== null ? ['HTTP_X_FORWARDED_FOR' => $xff] : []);

check(ClientIp::resolve($srv('203.0.113.9')) === '203.0.113.9', 'connexion directe : REMOTE_ADDR');
check(ClientIp::resolve($srv('203.0.113.9', '1.2.3.4')) === '203.0.113.9', 'SANS configuration : X-Forwarded-For forgé ignoré');
check(ClientIp::resolve($srv('203.0.113.9', '1.2.3.4'), []) === '203.0.113.9', 'liste de proxys vide : ignoré');
check(ClientIp::resolve($srv('203.0.113.9', '1.2.3.4'), ['10.0.0.1']) === '203.0.113.9', 'REMOTE_ADDR absent de la liste de confiance : X-Forwarded-For ignoré (le visiteur n\'est pas le proxy)');
check(ClientIp::resolve(['REMOTE_ADDR' => 'pas-une-ip']) === ClientIp::UNKNOWN, 'REMOTE_ADDR invalide : adresse inconnue (pas d\'exception)');
check(ClientIp::resolve([]) === ClientIp::UNKNOWN, 'REMOTE_ADDR absent : adresse inconnue');

$proxy = ['10.0.0.1'];
check(ClientIp::resolve($srv('10.0.0.1', '198.51.100.7'), $proxy) === '198.51.100.7', 'proxy de confiance : le client vient de X-Forwarded-For');
check(ClientIp::resolve($srv('10.0.0.1'), $proxy) === '10.0.0.1', 'proxy de confiance sans en-tête : REMOTE_ADDR');
check(ClientIp::resolve($srv('10.0.0.1', '6.6.6.6, 198.51.100.7'), $proxy) === '198.51.100.7',
      'entrée forgée à GAUCHE ignorée : la dernière adresse non-proxy (ajoutée par le proxy) est retenue');
check(ClientIp::resolve($srv('10.0.0.2', '6.6.6.6, 198.51.100.7, 10.0.0.1'), ['10.0.0.1', '10.0.0.2']) === '198.51.100.7', 'chaîne de deux proxys de confiance : on les saute');
check(ClientIp::resolve($srv('10.0.0.1', '10.0.0.2'), ['10.0.0.1', '10.0.0.2']) === '10.0.0.1', 'chaîne entièrement de proxys : repli sur REMOTE_ADDR');
check(ClientIp::resolve($srv('10.0.0.1', 'forgé, 198.51.100.7'), $proxy) === '198.51.100.7', 'entrée illisible à gauche, valide à droite : la valide est retenue');
check(ClientIp::resolve($srv('10.0.0.1', '198.51.100.7, n\'importe quoi'), $proxy) === '10.0.0.1', 'entrée illisible à droite : repli sur REMOTE_ADDR (on ne devine pas)');
check(ClientIp::resolve($srv('10.0.0.1', '198.51.100.7'), ['10.0.0.0/8']) === '198.51.100.7', 'CIDR IPv4 /8 reconnu');
check(ClientIp::resolve($srv('10.200.3.4', '198.51.100.7'), ['10.0.0.0/8']) === '198.51.100.7', 'CIDR : toute l\'adresse du bloc est un proxy');
check(ClientIp::resolve($srv('11.0.0.1', '198.51.100.7'), ['10.0.0.0/8']) === '11.0.0.1', 'CIDR : adresse hors du bloc n\'est pas un proxy');
check(ClientIp::resolve($srv('10.0.0.1', '198.51.100.7'), ['10.0.0.0/24']) === '198.51.100.7', 'CIDR /24');
check(ClientIp::resolve($srv('10.0.1.1', '198.51.100.7'), ['10.0.0.0/24']) === '10.0.1.1', 'CIDR /24 : adresse voisine hors bloc');
check(ClientIp::resolve($srv('10.0.0.1', '2001:db8::5'), $proxy) === '2001:db8::5', 'IPv6 dans X-Forwarded-For');
check(ClientIp::resolve($srv('2001:db8:0:0:0:0:0:1', '198.51.100.7'), ['2001:db8::1']) === '198.51.100.7', 'IPv6 : notations équivalentes reconnues');
check(ClientIp::resolve($srv('2001:db8:1::1', '198.51.100.7'), ['2001:db8:1::/48']) === '198.51.100.7', 'CIDR IPv6');
check(ClientIp::resolve($srv('2001:db8:2::1', '198.51.100.7'), ['2001:db8:1::/48']) === '2001:db8:2::1', 'CIDR IPv6 : hors bloc');
check(ClientIp::resolve($srv('10.0.0.1', '198.51.100.7'), ['::ffff:10.0.0.0/104']) === '10.0.0.1', 'IPv4 contre règle IPv6 : jamais équivalents');

foreach (['*', '0.0.0.0/0', '::/0', '10.0.0.0/33', '10.0.0.0/abc', '999.1.1.1', '', 'tous'] as $mauvais) {
    check(!ClientIp::isAcceptableRule($mauvais), "règle « $mauvais » refusée (joker ou invalide)");
}
check(ClientIp::resolve($srv('203.0.113.9', '1.2.3.4'), ['*']) === '203.0.113.9' && ClientIp::resolve($srv('203.0.113.9', '1.2.3.4'), ['0.0.0.0/0']) === '203.0.113.9',
      'joker « * » ou /0 : AUCUNE confiance accordée');
check(ClientIp::trustedFromEnvironment([]) === [] && ClientIp::trustedFromEnvironment(['TRUSTED_PROXIES' => '']) === [], 'TRUSTED_PROXIES absent ou vide : aucun proxy de confiance');
check(ClientIp::trustedFromEnvironment(['TRUSTED_PROXIES' => ' 10.0.0.1 , 192.168.0.0/16 ,*, n\'importe quoi ,2001:db8::/32']) === ['10.0.0.1', '192.168.0.0/16', '2001:db8::/32'],
      'TRUSTED_PROXIES : valeurs invalides et jokers écartés, espaces ignorés');
check(ClientIp::trustedFromEnvironment(['TRUSTED_PROXIES' => '# commentaire resté sur la ligne']) === [], 'commentaire résiduel dans la valeur : aucun proxy de confiance');

// ════════════════════════════════════════════════════════════════════════════
section('2. PublicThrottle : 30 requêtes par minute et par IP');

$dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'throttle_test_' . bin2hex(random_bytes(4));
$now = 1_800_000_000;
$clock = function () use (&$now) { return $now; };
$t = new PublicThrottle($dir, 30, 60, $clock);

$ok = true; for ($i = 1; $i <= 30; $i++) { $r = $t->hit('verif', '203.0.113.1'); if (!$r['allowed']) { $ok = false; } }
check($ok, '30 requêtes consécutives : toutes autorisées');
$r31 = $t->hit('verif', '203.0.113.1');
check(!$r31['allowed'] && $r31['remaining'] === 0 && $r31['retryAfter'] >= 1 && $r31['retryAfter'] <= 60, 'la 31e est refusée, avec un délai de reprise (Retry-After) entre 1 et 60 s');
check(!$t->hit('verif', '203.0.113.1')['allowed'], 'les suivantes restent refusées');
check($t->hit('verif', '203.0.113.2')['allowed'], 'une autre adresse IP n\'est pas affectée');
check($t->hit('autre-page', '203.0.113.1')['allowed'], 'un autre seau (autre page) n\'est pas affecté');
$now += 30;
check(!$t->hit('verif', '203.0.113.1')['allowed'], '30 s plus tard : toujours limité (fenêtre glissante, pas de remise à zéro brutale)');
$now += 31;
check($t->hit('verif', '203.0.113.1')['allowed'], 'plus de 60 s après le premier appel : de nouveau autorisé');
$restant = $t->hit('verif', '203.0.113.9')['remaining'];
check($restant === 29, 'compteur restant cohérent (29 après la 1re requête)');
check(array_filter(glob($dir . '/*'), fn($f) => str_contains($f, '203.0.113')) === [], 'aucune adresse IP en clair dans les noms de fichiers');
check(preg_match('/^[a-f0-9]{64}\.json$/', basename(glob($dir . '/*.json')[0])) === 1, 'noms de fichiers = SHA-256');

$limiteBasse = new PublicThrottle($dir . '_b', 3, 10, $clock);
$res = [];
for ($i = 0; $i < 5; $i++) { $res[] = $limiteBasse->hit('x', '198.51.100.1')['allowed']; }
check($res === [true, true, true, false, false], 'limite configurable (3 par 10 s)');

$fichier = tempnam(sys_get_temp_dir(), 'thr');                // un FICHIER à la place du dossier : stockage inutilisable
$casse = new PublicThrottle($fichier . DIRECTORY_SEPARATOR . 'sous', 1, 60, $clock);
$ra = @$casse->hit('x', '198.51.100.1'); $rb = @$casse->hit('x', '198.51.100.1');
check($ra['allowed'] && $rb['allowed'], 'stockage inutilisable : requêtes autorisées (la page publique reste disponible)');
@unlink($fichier);
rmrf($dir); rmrf($dir . '_b');

// ════════════════════════════════════════════════════════════════════════════
section('3. BulletinVerificationUrl : URL absolue du QR officiel');

$tok = str_repeat('a1', 32);
check(BulletinVerificationUrl::build($tok, 'https://ecole.example.org', 'production') === "https://ecole.example.org/v2/academique/bulletins/verify/$tok", 'URL absolue HTTPS');
check(BulletinVerificationUrl::build($tok, 'https://ecole.example.org/ecole_app/', 'production') === "https://ecole.example.org/ecole_app/v2/academique/bulletins/verify/$tok", 'chemin d\'application conservé, barre finale normalisée');
check(BulletinVerificationUrl::build($tok, 'http://localhost/ecole_app', 'development') === "http://localhost/ecole_app/v2/academique/bulletins/verify/$tok", 'développement : localhost autorisé');
$refus = function (callable $f): bool { try { $f(); return false; } catch (\InvalidArgumentException) { return true; } };
check($refus(fn() => BulletinVerificationUrl::build($tok, 'http://ecole.example.org', 'production')), 'production : HTTP refusé');
foreach (['https://localhost', 'https://127.0.0.1', 'https://192.168.1.10', 'https://10.0.0.5', 'https://[::1]', 'https://ecole.local', 'https://app.localhost'] as $local) {
    check($refus(fn() => BulletinVerificationUrl::build($tok, $local, 'production')), "production : hôte local « $local » refusé");
}
foreach (['', '/ecole_app', 'ecole.example.org', 'ftp://ecole.example.org', 'javascript:alert(1)', 'https://user:pw@ecole.example.org', 'https://ecole.example.org?x=1', 'https://ecole.example.org#f'] as $mauvais) {
    check($refus(fn() => BulletinVerificationUrl::build($tok, $mauvais, 'development')), "URL de base « $mauvais » refusée");
}
foreach (['', 'AB', str_repeat('A', 64), substr($tok, 0, 63), $tok . 'x'] as $mauvaisJeton) {
    check($refus(fn() => BulletinVerificationUrl::build($mauvaisJeton, 'https://ecole.example.org', 'production')), 'jeton mal formé « ' . substr($mauvaisJeton, 0, 10) . ' » refusé');
}
check(BulletinTokenService::isWellFormed($tok), 'le jeton de test est bien formé');

// ════════════════════════════════════════════════════════════════════════════
section('4. BulletinPublicationGate : fermé par défaut');

check(!BulletinPublicationGate::isOpen([]), 'variable absente : fermé');
foreach (['', 'false', '0', 'no', 'off', 'non', 'ouvert', 'enabled', 'trueish', 'null'] as $v) {
    check(!BulletinPublicationGate::isOpen(['BULLETIN_PUBLICATION_ENABLED' => $v]), "valeur « $v » : fermé");
}
foreach (['true', 'TRUE', '1', 'yes', 'on', ' true '] as $v) {
    check(BulletinPublicationGate::isOpen(['BULLETIN_PUBLICATION_ENABLED' => $v]), "valeur « $v » : ouvert");
}
check(!BulletinPublicationGate::isOpen(['BULLETIN_PUBLICATION_ENABLED' => true]), 'valeur non textuelle : fermé');

echo "\n";
$total = $passed + $failed;
echo ($failed === 0 ? "\033[1;32m" : "\033[1;31m") . "Résultat : {$passed}/{$total} assertions passées\033[0m\n";
exit($failed === 0 ? 0 : 1);
