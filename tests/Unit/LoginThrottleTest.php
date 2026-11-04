<?php
/**
 * Tests — LoginThrottle (anti-force-brute de la connexion) : unitaire, dossier et horloge jetables, aucune base.
 *
 * Lance : C:\wamp64\bin\php\php8.2.29\php.exe tests/Unit/LoginThrottleTest.php
 */

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__, 2));
require_once ROOT_PATH . '/app/Shared/Http/LoginThrottle.php';

use App\Shared\Http\LoginThrottle;

$passed = 0; $failed = 0;
function check(bool $ok, string $label, string $detail = ''): void
{
    global $passed, $failed;
    if ($ok) { $passed++; echo "\033[32m  ✓ $label\033[0m\n"; }
    else     { $failed++; echo "\033[31m  ✗ $label" . ($detail !== '' ? " — $detail" : '') . "\033[0m\n"; }
}

$dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'login_throttle_' . bin2hex(random_bytes(4));
$now = 1_000_000;
$t = new LoginThrottle($dir, 900, function () use (&$now) { return $now; });

echo "1. Comptage des échecs\n";
check($t->retryAfter('a', 5) === 0, 'aucun échec : pas de blocage');
for ($i = 0; $i < 4; $i++) { $t->recordFailure('a'); }
check($t->retryAfter('a', 5) === 0, '4 échecs sur 5 : pas encore bloqué');
$t->recordFailure('a');
check($t->retryAfter('a', 5) === 900, '5e échec : bloqué pour toute la fenêtre', (string)$t->retryAfter('a', 5));
check($t->retryAfter('b', 5) === 0, 'une autre clé n\'est pas affectée');

echo "2. Expiration\n";
$now += 899;
check($t->retryAfter('a', 5) === 1, 'à 899 s : encore bloqué 1 s');
$now += 2;
check($t->retryAfter('a', 5) === 0, 'après la fenêtre : débloqué');

echo "3. Réussite = remise à zéro\n";
for ($i = 0; $i < 4; $i++) { $t->recordFailure('c'); }
$t->clear('c');
$t->recordFailure('c');
check($t->retryAfter('c', 5) === 0, 'clear() efface les échecs précédents');

echo "4. Seuils indépendants par limite\n";
for ($i = 0; $i < 20; $i++) { $t->recordFailure('d'); }
check($t->retryAfter('d', 30) === 0 && $t->retryAfter('d', 20) > 0, '20 échecs : sous 30, bloqué à 20');

echo "5. Confidentialité et disponibilité\n";
$noms = array_map('basename', glob($dir . '/login_*.json') ?: []);
check($noms !== [] && count(array_filter($noms, fn($n) => preg_match('/^login_[0-9a-f]{64}\.json$/', $n))) === count($noms), 'noms de fichiers hachés (ni e-mail ni IP en clair)');
$casse = new LoginThrottle(__FILE__ . '/impossible', 900);
$casse->recordFailure('x');
check($casse->retryAfter('x', 1) === 0, 'stockage inutilisable : connexion non bloquée (pas de panne)');

foreach (glob($dir . '/*') ?: [] as $f) { @unlink($f); }
@rmdir($dir);
echo "\n" . ($failed === 0 ? "\033[1;32m" : "\033[1;31m") . "Résultat : $passed/" . ($passed + $failed) . " assertions passées\033[0m\n";
exit($failed === 0 ? 0 : 1);
