<?php
/**
 * Tests — BulletinTokenService (jetons de vérification des bulletins)
 *
 * Vérifie : validation stricte du secret (aucune valeur par défaut, aucun repli vers APP_KEY ni un autre secret),
 * représentation canonique du message signé, vecteur de test connu (détecte toute dérive de l'algorithme),
 * déterminisme, séparation par établissement / élève / période / secret, vérification stricte, et absence de
 * fuite de la clé (messages d'erreur, affichage, sérialisation).
 *
 * Aucune base de données, aucun fichier : tous les secrets de ce test sont des valeurs jetables.
 *
 * Lance : C:\wamp64\bin\php\php8.2.29\php.exe tests/Unit/BulletinTokenServiceTest.php
 */

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__, 2));
require_once ROOT_PATH . '/app/Modules/Academique/Services/BulletinTokenException.php';
require_once ROOT_PATH . '/app/Modules/Academique/Services/BulletinTokenService.php';

use App\Modules\Academique\Services\BulletinTokenException;
use App\Modules\Academique\Services\BulletinTokenService as T;

$passed = 0; $failed = 0;
function check(bool $ok, string $label): void
{
    global $passed, $failed;
    if ($ok) { $passed++; echo "\033[32m  ✓ $label\033[0m\n"; }
    else     { $failed++; echo "\033[31m  ✗ $label\033[0m\n"; }
}
function section(string $t): void { echo "\n\033[33m── $t\033[0m\n"; }
/** @return int|null code de l'exception levée, null si aucune */
function codeLevee(callable $f): ?int
{
    try { $f(); return null; } catch (BulletinTokenException $e) { return $e->getCode(); }
}

// Secrets JETABLES, propres à ce test (non périodiques, jamais utilisés ailleurs).
const SECRET  = '740b4f70153c46519f8c2989d4ea3df50dec9fbaec9536f2219b70645c59bebd';   // sha256('bulletin-token-test-secret-1')
const SECRET2 = 'c0641aeac51c3f3025892ee791c7022cfd2f43dc31052d21224980607aa3a1bb';   // sha256('bulletin-token-test-secret-2')

// ────────────────────────────────────────────────────────────────────────────
section('1. Secret : aucune valeur par défaut, validation stricte');

check(codeLevee(fn() => T::fromEnvironment([])) === BulletinTokenException::MISSING, 'variable absente : refus (MISSING)');
check(codeLevee(fn() => T::fromEnvironment(['BULLETIN_TOKEN_SECRET' => ''])) === BulletinTokenException::MISSING, 'variable vide : refus (MISSING)');
check(codeLevee(fn() => T::fromEnvironment(['BULLETIN_TOKEN_SECRET' => null])) === BulletinTokenException::MISSING, 'variable nulle : refus');
check(codeLevee(fn() => T::fromEnvironment(['BULLETIN_TOKEN_SECRET' => ['x']])) === BulletinTokenException::MISSING, 'variable non textuelle : refus');
check(!T::isConfigured([]), 'isConfigured() faux sans secret');
check(T::isConfigured(['BULLETIN_TOKEN_SECRET' => SECRET]), 'isConfigured() vrai avec un secret valide');

$apresRepli = ['APP_KEY' => SECRET, 'JWT_SECRET' => SECRET2, 'STORAGE_SIGNING_KEY' => SECRET];
check(codeLevee(fn() => T::fromEnvironment($apresRepli)) === BulletinTokenException::MISSING,
      'APP_KEY / JWT_SECRET valides mais BULLETIN_TOKEN_SECRET absent : refus (AUCUN repli vers un autre secret)');
check(codeLevee(fn() => T::fromEnvironment(['BULLETIN_TOKEN_SECRET' => 'ecole_app_v2'])) === BulletinTokenException::FORMAT, 'l\'ancien littéral « ecole_app_v2 » est refusé');

$invalides = [
    'trop court (62)'            => [str_repeat('ab', 31), BulletinTokenException::FORMAT],
    'longueur impaire (65)'      => [SECRET . 'a', BulletinTokenException::FORMAT],
    'non hexadécimal'            => [str_repeat('zy', 32), BulletinTokenException::FORMAT],
    'espace en tête'             => [' ' . SECRET, BulletinTokenException::FORMAT],
    'retour à la ligne final'    => [SECRET . "\n", BulletinTokenException::FORMAT],
    'répétition « 00… »'         => [str_repeat('0', 64), BulletinTokenException::LOW_ENTROPY],
    'motif « abab… »'            => [str_repeat('ab', 32), BulletinTokenException::LOW_ENTROPY],
    'motif « 01234567 » répété'  => [str_repeat('01234567', 8), BulletinTokenException::LOW_ENTROPY],
    'motif de 16 répété (4 fois)' => [str_repeat('0123456789abcdef', 4), BulletinTokenException::LOW_ENTROPY],
    'même moitié répétée'        => [SECRET . SECRET, BulletinTokenException::LOW_ENTROPY],
];
foreach ($invalides as $label => [$val, $code]) {
    check(codeLevee(fn() => T::fromEnvironment(['BULLETIN_TOKEN_SECRET' => $val])) === $code, "secret $label : refusé");
}
foreach (['APP_KEY', 'JWT_SECRET', 'STORAGE_SIGNING_KEY', 'VAPID_PRIVATE_KEY'] as $autre) {
    check(codeLevee(fn() => T::fromEnvironment(['BULLETIN_TOKEN_SECRET' => SECRET, $autre => strtoupper(SECRET)])) === BulletinTokenException::REUSED,
          "secret identique à $autre (même en majuscules) : refusé (REUSED)");
}
check(!codeLevee(fn() => T::fromEnvironment(['BULLETIN_TOKEN_SECRET' => SECRET, 'APP_KEY' => SECRET2])), 'secret distinct des autres secrets : accepté');
check(!codeLevee(fn() => T::fromEnvironment(['BULLETIN_TOKEN_SECRET' => strtoupper(SECRET)])), 'secret hexadécimal en majuscules : accepté');
check(!codeLevee(fn() => T::fromEnvironment(['BULLETIN_TOKEN_SECRET' => SECRET . SECRET2])), 'secret plus long (128 caractères, non périodique) : accepté');
check(!codeLevee(fn() => T::fromEnvironment(['BULLETIN_TOKEN_SECRET' => bin2hex(random_bytes(32))])), 'secret aléatoire de 256 bits : accepté');

// ────────────────────────────────────────────────────────────────────────────
section('2. Les messages d\'erreur ne contiennent jamais la valeur du secret');

$fuite = false;
foreach ([str_repeat('zy', 32), 'ecole_app_v2', str_repeat('0', 64), SECRET . 'a'] as $mauvais) {
    try { T::fromEnvironment(['BULLETIN_TOKEN_SECRET' => $mauvais]); }
    catch (BulletinTokenException $e) { if (str_contains($e->getMessage(), $mauvais)) { $fuite = true; } }
}
try { T::fromEnvironment(['BULLETIN_TOKEN_SECRET' => SECRET, 'APP_KEY' => SECRET]); }
catch (BulletinTokenException $e) { if (str_contains($e->getMessage(), SECRET)) { $fuite = true; } }
check(!$fuite, 'aucune exception ne reprend la valeur fournie (invalide, trop prévisible ou réutilisée)');
try { T::fromEnvironment([]); } catch (BulletinTokenException $e) {
    check(str_contains($e->getMessage(), 'random_bytes') && str_contains($e->getMessage(), 'Aucune valeur par défaut'), 'le message « absent » explique comment générer le secret');
}

// ────────────────────────────────────────────────────────────────────────────
section('3. Représentation canonique du message signé');

check(T::canonicalMessage(1, 2, 3) === 'bulletin|v2|1|2|3', 'message = « bulletin|v2|{établissement}|{élève}|{période} »');
check(T::canonicalMessage(10, 20, 30) === 'bulletin|v2|10|20|30', 'entiers en base 10, sans zéro initial ni séparateur');
check(T::canonicalMessage(1, 23, 4) !== T::canonicalMessage(12, 3, 4), 'pas d\'ambiguïté : (1,23,4) ≠ (12,3,4)');
check(T::canonicalMessage(1, 2, 34) !== T::canonicalMessage(1, 23, 4), 'pas d\'ambiguïté : (1,2,34) ≠ (1,23,4)');
foreach ([[0, 1, 1], [1, 0, 1], [1, 1, 0], [-1, 1, 1], [1, -5, 1], [1, 1, -9]] as [$a, $b, $c]) {
    check(codeLevee(fn() => T::canonicalMessage($a, $b, $c)) === BulletinTokenException::INPUT, "identifiants ($a,$b,$c) : refusés");
}

// ────────────────────────────────────────────────────────────────────────────
section('4. Vecteur de test connu (détecte toute dérive de l\'algorithme)');

$svc = T::fromEnvironment(['BULLETIN_TOKEN_SECRET' => SECRET]);
$attendu = 'e6b6b4ef87f862c27989c0279e328cd40eb676395d5ace4d0e061620902f2847';
check($svc->issue(1, 2, 3) === $attendu, 'issue(1,2,3) = vecteur fixe (HMAC-SHA256, clé = hex2bin(secret))');
check($svc->issue(1, 2, 3) === hash_hmac('sha256', 'bulletin|v2|1|2|3', hex2bin(SECRET)), 'issue() = hash_hmac calculé indépendamment');
check($svc->issue(2, 2, 3) === '4b81b34aab0497b01dbdd449f65fdad45605ea7a278b9dd29fdff70f3395152a', 'établissement différent : vecteur fixe différent');
check($svc->issue(1, 2, 3) !== hash('sha256', 'bulletin:2:3:' . SECRET), 'ce n\'est PAS l\'ancien schéma SHA-256 concaténé');
check($svc->issue(1, 2, 3) !== hash('sha256', 'bulletin:2:3:ecole_app_v2'), 'ce n\'est pas le calcul à partir du littéral public');

// ────────────────────────────────────────────────────────────────────────────
section('5. Propriétés : format, déterminisme, séparation');

$tok = $svc->issue(1, 2, 3);
check(T::isWellFormed($tok) && strlen($tok) === 64, 'jeton = 64 hexadécimaux minuscules');
check($svc->issue(1, 2, 3) === $tok && T::fromEnvironment(['BULLETIN_TOKEN_SECRET' => SECRET])->issue(1, 2, 3) === $tok,
      'déterministe : même secret, mêmes identifiants, même jeton (une régénération ne change pas le jeton)');
check($svc->issue(2, 2, 3) !== $tok, 'séparé par établissement');
check($svc->issue(1, 3, 3) !== $tok, 'séparé par élève');
check($svc->issue(1, 2, 4) !== $tok, 'séparé par période');
check(T::fromEnvironment(['BULLETIN_TOKEN_SECRET' => SECRET2])->issue(1, 2, 3) !== $tok, 'séparé par secret');
check($svc->issue(1, 2, 3) !== $svc->issue(1, 3, 2), 'l\'ordre élève / période compte');
$tous = []; for ($e = 1; $e <= 40; $e++) { for ($p = 1; $p <= 12; $p++) { $tous[$svc->issue(1, $e, $p)] = true; } }
check(count($tous) === 480, 'aucune collision sur 480 couples (élève, période)');

// ────────────────────────────────────────────────────────────────────────────
section('6. Vérification stricte');

check($svc->matches($tok, 1, 2, 3), 'jeton valide pour ses identifiants : accepté');
check(!$svc->matches($tok, 1, 2, 4) && !$svc->matches($tok, 1, 3, 3) && !$svc->matches($tok, 2, 2, 3), 'jeton présenté pour d\'autres identifiants : refusé');
foreach ([
    'majuscules' => strtoupper($tok), 'tronqué' => substr($tok, 0, 63), 'trop long' => $tok . '0', 'vide' => '',
    'espaces' => " $tok ", 'retour ligne' => $tok . "\n", 'dernier car. modifié' => substr($tok, 0, 63) . ($tok[63] === 'a' ? 'b' : 'a'),
    'non hexadécimal' => str_repeat('g', 64), 'ancien schéma' => hash('sha256', 'bulletin:2:3:ecole_app_v2'),
] as $label => $mauvais) {
    check(!$svc->matches($mauvais, 1, 2, 3), "jeton $label : refusé");
}
check(!T::isWellFormed(strtoupper($tok)) && !T::isWellFormed("$tok\n"), 'format : minuscules strictes, aucune fin de ligne tolérée');

// ────────────────────────────────────────────────────────────────────────────
section('7. Journaux : empreintes, jamais le jeton complet');

$fp = T::fingerprint($tok);
check(strlen($fp) === 12 && ctype_xdigit($fp), 'empreinte de journal = 12 hexadécimaux');
check($fp === T::fingerprint($tok) && $fp !== T::fingerprint($svc->issue(1, 2, 4)), 'empreinte stable, distincte d\'un bulletin à l\'autre');
check(!str_contains($tok, $fp) && $fp !== substr($tok, 0, 12), 'l\'empreinte n\'est pas un fragment du jeton');
check(T::retiredDigest($tok) === hash('sha256', $tok) && strlen(T::retiredDigest($tok)) === 64, 'jeton retiré : SHA-256 complet (le jeton lui-même n\'est jamais conservé)');

// ────────────────────────────────────────────────────────────────────────────
section('8. La clé ne fuit pas (affichage, sérialisation)');

ob_start(); var_dump($svc); print_r($svc); var_export($svc);
$affiche = ob_get_clean();
check(!str_contains($affiche, SECRET) && !str_contains($affiche, hex2bin(SECRET)) && !str_contains(bin2hex($affiche), bin2hex(hex2bin(SECRET))),
      'var_dump / print_r / var_export n\'exposent ni le secret ni la clé');
check(!str_contains((string)json_encode($svc), SECRET) && json_encode($svc) === '{}', 'json_encode n\'expose rien');
$leve = false; try { serialize($svc); } catch (\LogicException) { $leve = true; }
check($leve, 'serialize() est interdit');
$leve = false; $cls = \App\Modules\Academique\Services\BulletinTokenService::class;
try { @unserialize('O:' . strlen($cls) . ':"' . $cls . '":0:{}'); } catch (\Throwable) { $leve = true; }
check($leve, 'unserialize() est interdit');
check(!method_exists($svc, '__toString'), 'pas de conversion en chaîne');

echo "\n";
$total = $passed + $failed;
echo ($failed === 0 ? "\033[1;32m" : "\033[1;31m") . "Résultat : {$passed}/{$total} assertions passées\033[0m\n";
exit($failed === 0 ? 0 : 1);
