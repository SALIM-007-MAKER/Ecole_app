<?php
declare(strict_types=1);

/**
 * Tests unitaires — API Platform Auth (Phase 13.2)
 * Format: runner personnalisé (sans PHPUnit)
 */

$base = dirname(__DIR__, 2);
if (!defined('ROOT_PATH')) define('ROOT_PATH', $base);

spl_autoload_register(function (string $class) use ($base): void {
    $map = [
        'App\Modules\Api\\' => $base . '/app/Modules/Api/',
        'App\Shared\Api\\'  => $base . '/app/Shared/Api/',
        'Core\\'            => $base . '/core/',
    ];
    foreach ($map as $prefix => $dir) {
        if (str_starts_with($class, $prefix)) {
            $rel  = str_replace('\\', '/', substr($class, strlen($prefix)));
            $file = $dir . $rel . '.php';
            if (file_exists($file)) { require_once $file; return; }
        }
    }
});

$pass = 0; $fail = 0;
function t(string $id, bool $result, string $info = ''): void {
    global $pass, $fail;
    if ($result) { $pass++; echo "  PASS  $id $info\n"; }
    else         { $fail++; echo "  FAIL  $id $info\n"; }
}

// ─── Setup ────────────────────────────────────────────────────────────────────

$_ENV['JWT_SECRET'] = 'test-secret-key-at-least-32-chars-xxxxxxxxxxxxxxxxxxx';

use App\Modules\Api\Auth\JwtService;
use App\Modules\Api\Auth\AuthContext;
use App\Modules\Api\Exceptions\AuthException;

$jwt = new JwtService();

// ─── A01–A06 : JwtService ─────────────────────────────────────────────────────

$token = $jwt->encode(['sub' => 1, 'etab' => 1, 'role' => 'admin', 'perms' => []]);
t('A01 JWT has 3 parts',     substr_count($token, '.') === 2);

$payload = $jwt->decode($token);
t('A02 sub roundtrip',       $payload['sub'] === 1);
t('A03 role roundtrip',      $payload['role'] === 'admin');
t('A04 jti present',         isset($payload['jti']) && strlen($payload['jti']) > 10);
t('A05 exp in future',       $payload['exp'] > time());
t('A06 iat present',         isset($payload['iat']) && $payload['iat'] <= time());

// Expired token
$expired = $jwt->encode(['sub' => 1, 'etab' => 1, 'role' => 'admin'], -1);
$threw = false;
try { $jwt->decode($expired); } catch (AuthException) { $threw = true; }
t('A07 expired token throws', $threw);

// Tampered signature
$parts    = explode('.', $token);
$parts[2] = 'tampered';
$threw2   = false;
try { $jwt->decode(implode('.', $parts)); } catch (AuthException) { $threw2 = true; }
t('A08 tampered sig throws', $threw2);

// Malformed
$threw3 = false;
try { $jwt->decode('not.a.valid'); } catch (AuthException) { $threw3 = true; }
t('A09 malformed throws',    $threw3);

// Two tokens have different JTIs
$tok2 = $jwt->encode(['sub' => 1, 'etab' => 1, 'role' => 'admin']);
$p2   = $jwt->decode($tok2);
t('A10 unique JTIs',         $payload['jti'] !== $p2['jti']);

// ─── A11–A15 : AuthContext ────────────────────────────────────────────────────

$ctx = new AuthContext(
    userId:           42,
    etablissementId:  3,
    role:             'enseignant',
    permissions:      ['notes.view', 'notes.create', 'eleves.view'],
    authMethod:       'jwt',
    jti:              'abc-jti',
);

t('A11 hasPermission true',   $ctx->hasPermission('notes.view'));
t('A12 hasPermission false',  !$ctx->hasPermission('admin.super'));
t('A13 hasAnyPermission',     $ctx->hasAnyPermission('nonexistent', 'notes.view'));
t('A14 hasAnyPermission none',!$ctx->hasAnyPermission('x', 'y', 'z'));
t('A15 apiKeyId null',        $ctx->apiKeyId === null);

$ctxKey = new AuthContext(
    userId:           5,
    etablissementId:  1,
    role:             'api',
    permissions:      ['eleves.view'],
    authMethod:       'api_key',
    apiKeyId:         'key_abc',
);
t('A16 api_key authMethod',  $ctxKey->authMethod === 'api_key');
t('A17 apiKeyId set',        $ctxKey->apiKeyId === 'key_abc');
t('A18 jti null for api_key',$ctxKey->jti === null);

// ─── Résumé ───────────────────────────────────────────────────────────────────
echo "\nAuthApiTest: $pass/" . ($pass + $fail) . " PASS\n";
if ($fail > 0) exit(1);
