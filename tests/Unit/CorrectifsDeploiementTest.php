<?php
/**
 * Tests — correctifs de l'audit de déploiement : détection HTTPS derrière un proxy de confiance (cookie de session « Secure »)
 * et refus du chiffrement des sauvegardes sans APP_KEY valide. Aucune base de données.
 *
 * Lance : C:\wamp64\bin\php\php8.2.29\php.exe tests/Unit/CorrectifsDeploiementTest.php
 */

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__, 2));
require_once ROOT_PATH . '/app/Shared/Http/ClientIp.php';
require_once ROOT_PATH . '/core/Backup/BackupEncryption.php';

use App\Shared\Http\ClientIp;
use Core\Backup\BackupEncryption;

$passed = 0; $failed = 0;
function check(bool $ok, string $label, string $detail = ''): void
{
    global $passed, $failed;
    if ($ok) { $passed++; echo "\033[32m  ✓ $label\033[0m\n"; }
    else     { $failed++; echo "\033[31m  ✗ $label" . ($detail !== '' ? " — $detail" : '') . "\033[0m\n"; }
}
function section(string $t): void { echo "\n\033[33m── $t\033[0m\n"; }

section('ClientIp::isHttps — cookie de session Secure');
check(ClientIp::isHttps(['HTTPS' => 'on']), 'HTTPS direct');
check(!ClientIp::isHttps(['HTTPS' => 'off']), 'HTTPS=off : non');
check(!ClientIp::isHttps([]), 'HTTP simple : non');
check(!ClientIp::isHttps(['REMOTE_ADDR' => '203.0.113.9', 'HTTP_X_FORWARDED_PROTO' => 'https']), 'X-Forwarded-Proto ignoré sans proxy de confiance configuré');
check(!ClientIp::isHttps(['REMOTE_ADDR' => '203.0.113.9', 'HTTP_X_FORWARDED_PROTO' => 'https'], ['10.0.0.1']), 'X-Forwarded-Proto ignoré si l\'émetteur n\'est pas un proxy de confiance (usurpation)');
check(ClientIp::isHttps(['REMOTE_ADDR' => '10.0.0.1', 'HTTP_X_FORWARDED_PROTO' => 'https'], ['10.0.0.1']), 'proxy de confiance + X-Forwarded-Proto: https : oui');
check(ClientIp::isHttps(['REMOTE_ADDR' => '10.0.0.7', 'HTTP_X_FORWARDED_PROTO' => 'https, http'], ['10.0.0.0/24']), 'CIDR de confiance, premier élément de la liste');
check(!ClientIp::isHttps(['REMOTE_ADDR' => '10.0.0.1', 'HTTP_X_FORWARDED_PROTO' => 'http'], ['10.0.0.1']), 'proxy de confiance mais protocole http : non');
check(!ClientIp::isHttps(['REMOTE_ADDR' => '10.0.0.1', 'HTTP_X_FORWARDED_PROTO' => 'https'], ['0.0.0.0/0']), 'règle « tout le monde » refusée');

section('BackupEncryption::make — pas de clé par défaut');
$avant = $_ENV['APP_KEY'] ?? null;
$refus = function (?string $cle): bool {
    if ($cle === null) { unset($_ENV['APP_KEY']); putenv('APP_KEY'); } else { $_ENV['APP_KEY'] = $cle; }
    try { BackupEncryption::make(); return false; } catch (\RuntimeException $e) { return true; }
};
check($refus(null), 'APP_KEY absente : refus');
check($refus(''), 'APP_KEY vide : refus');
check($refus('trop-courte'), 'APP_KEY trop courte : refus');
check($refus('edunova-default-backup-key-change-in-production'), 'ancienne valeur par défaut publique : refus');
$_ENV['APP_KEY'] = str_repeat('k', 32);
$e = BackupEncryption::make();
$clair = 'données de sauvegarde';
check($e->decrypt($e->encrypt($clair)) === $clair, 'clé valide : chiffrement / déchiffrement aller-retour');
if ($avant === null) { unset($_ENV['APP_KEY']); } else { $_ENV['APP_KEY'] = $avant; }

echo "\n$passed OK, $failed KO\n";
exit($failed ? 1 : 0);
