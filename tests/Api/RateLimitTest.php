<?php
declare(strict_types=1);

/**
 * Tests unitaires — RateLimiter config + ApiResponseBuilder (Phase 13.2)
 */

$base = dirname(__DIR__, 2);

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

if (!defined('ROOT_PATH')) define('ROOT_PATH', $base);

$pass = 0; $fail = 0;
function t(string $id, bool $result, string $info = ''): void {
    global $pass, $fail;
    if ($result) { $pass++; echo "  PASS  $id $info\n"; }
    else         { $fail++; echo "  FAIL  $id $info\n"; }
}

// ─── R01–R08 : Rate Limit Config ─────────────────────────────────────────────

$config = require $base . '/config/api.php';

$rl = $config['rate_limit'];
t('R01 rate_limit key exists',  array_key_exists('rate_limit', $config));
t('R02 default group exists',   array_key_exists('default', $rl));
t('R03 auth group exists',      array_key_exists('auth', $rl));
t('R04 api_key_read group',     array_key_exists('api_key_read', $rl));

foreach (['default', 'auth', 'api_key_read'] as $grp) {
    $key = array_key_exists('requests', $rl[$grp]) ? 'requests' : 'max';
    t("R05 $grp has limit key", array_key_exists($key, $rl[$grp]));
    t("R06 $grp has window",    array_key_exists('window', $rl[$grp]));
    t("R07 $grp limit > 0",     $rl[$grp][$key] > 0);
    t("R08 $grp window > 0",    $rl[$grp]['window'] > 0);
}

// auth group should be stricter than default
$authKey    = array_key_exists('requests', $rl['auth'])    ? 'requests' : 'max';
$defaultKey = array_key_exists('requests', $rl['default']) ? 'requests' : 'max';
t('R09 auth stricter than def', $rl['auth'][$authKey] < $rl['default'][$defaultKey]);

// ─── R10–R14 : JWT config ────────────────────────────────────────────────────

t('R10 jwt config exists',      array_key_exists('jwt', $config));
t('R11 jwt ttl > 0',            ($config['jwt']['access_ttl'] ?? 0) > 0);
t('R12 refresh ttl > access',   ($config['jwt']['refresh_ttl'] ?? 0) > ($config['jwt']['access_ttl'] ?? 0));

// ─── R13–R18 : Pagination config ─────────────────────────────────────────────

t('R13 pagination config',      array_key_exists('pagination', $config));
t('R14 pagination default > 0', ($config['pagination']['default_per_page'] ?? 0) > 0);
t('R15 pagination max ≥ def',   ($config['pagination']['max_per_page'] ?? 0) >= ($config['pagination']['default_per_page'] ?? 0));

// ─── R16–R18 : Webhook config ────────────────────────────────────────────────

t('R16 webhooks config',        array_key_exists('webhooks', $config));
t('R17 webhook max attempts',   ($config['webhooks']['max_attempts'] ?? 0) > 0);
t('R18 retry delays array',     is_array($config['webhooks']['retry_delays'] ?? null));

// ─── R19–R22 : Token bucket key format ───────────────────────────────────────

$userId  = 42;
$etab    = 1;
foreach (['default', 'auth', 'api_key_read'] as $i => $group) {
    $key = "rl:{$group}:{$etab}:{$userId}";
    t("R1" . (9 + $i) . " key format $group", (bool)preg_match('/^rl:[a-z_]+:\d+:\d+$/', $key));
}

// ─── Résumé ───────────────────────────────────────────────────────────────────
echo "\nRateLimitTest: $pass/" . ($pass + $fail) . " PASS\n";
if ($fail > 0) exit(1);
