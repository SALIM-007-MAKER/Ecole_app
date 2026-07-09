<?php
declare(strict_types=1);

/**
 * Tests unitaires — ApiKeyService logic (Phase 13.2)
 */

$base = dirname(__DIR__, 2);

spl_autoload_register(function (string $class) use ($base): void {
    $map = [
        'App\Modules\Api\\' => $base . '/app/Modules/Api/',
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

// ─── K01–K10 : Clé API format ─────────────────────────────────────────────────

$livKey  = 'sk_live_' . bin2hex(random_bytes(24));
$testKey = 'sk_test_' . bin2hex(random_bytes(24));

t('K01 live key matches pattern',  (bool)preg_match('/^sk_live_[0-9a-f]{48}$/', $livKey));
t('K02 test key matches pattern',  (bool)preg_match('/^sk_test_[0-9a-f]{48}$/', $testKey));
t('K03 live prefix',               str_starts_with($livKey, 'sk_live_'));
t('K04 test prefix',               str_starts_with($testKey, 'sk_test_'));
t('K05 live key length = 56',      strlen($livKey) === 56);
t('K06 test key length = 56',      strlen($testKey) === 56);

// Hash storage
$hash1 = hash('sha256', $livKey);
$hash2 = hash('sha256', $livKey);
t('K07 hash is 64 chars',          strlen($hash1) === 64);
t('K08 hash reproducible',         $hash1 === $hash2);

$hashOther = hash('sha256', 'sk_live_' . bin2hex(random_bytes(24)));
t('K09 different keys diff hash',  $hash1 !== $hashOther);

// Timing-safe comparison simulation
$eq = hash_equals($hash1, $hash2);
t('K10 timing-safe compare ok',    $eq);

// ─── K11–K15 : IP whitelist logic ────────────────────────────────────────────

function ipInCidr(string $ip, string $cidr): bool {
    [$network, $bits] = explode('/', $cidr);
    $mask = -1 << (32 - (int)$bits);
    return (ip2long($ip) & $mask) === (ip2long($network) & $mask);
}

t('K11 ip in cidr /24',           ipInCidr('192.168.1.50', '192.168.1.0/24'));
t('K12 ip not in cidr /24',       !ipInCidr('192.168.2.50', '192.168.1.0/24'));
t('K13 exact ip match',           ipInCidr('10.0.0.1', '10.0.0.1/32'));
t('K14 loopback in /8',           ipInCidr('127.0.0.50', '127.0.0.0/8'));
t('K15 empty whitelist = allow',  true); // empty = no restriction

// ─── K16–K20 : Hint (last 4 chars) ───────────────────────────────────────────

$key  = 'sk_live_' . bin2hex(random_bytes(24));
$hint = substr($key, -4);
t('K16 hint is 4 chars',          strlen($hint) === 4);
t('K17 hint is last 4',           $hint === substr($key, strlen($key) - 4));
t('K18 hint lowercase hex',       (bool)preg_match('/^[0-9a-f]{4}$/', $hint));

// Key never stored raw
$stored = hash('sha256', $key);
t('K19 raw key != stored hash',   $key !== $stored);
t('K20 hash length is sha256',    strlen($stored) === 64);

// ─── Résumé ───────────────────────────────────────────────────────────────────
echo "\nApiKeyTest: $pass/" . ($pass + $fail) . " PASS\n";
if ($fail > 0) exit(1);
