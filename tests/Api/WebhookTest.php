<?php
declare(strict_types=1);

/**
 * Tests unitaires — WebhookSigner + WebhookDispatcher (Phase 13.2)
 */

$base = dirname(__DIR__, 2);
if (!defined('ROOT_PATH')) define('ROOT_PATH', $base);

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

use App\Modules\Api\Webhooks\WebhookSigner;

// ─── W01–W10 : WebhookSigner ─────────────────────────────────────────────────

$payload = '{"event":"eleve.created","data":{"id":1}}';
$secret  = 'my-webhook-secret-32chars-xxxxxxxx';

$sig = WebhookSigner::sign($payload, $secret);
t('W01 sig starts sha256=',     str_starts_with($sig, 'sha256='));
t('W02 sig length > 10',        strlen($sig) > 10);

t('W03 verify valid sig',       WebhookSigner::verify($payload, $secret, $sig));
t('W04 wrong payload fails',    !WebhookSigner::verify('{"tampered":true}', $secret, $sig));
t('W05 wrong secret fails',     !WebhookSigner::verify($payload, 'wrong-secret', $sig));
t('W06 wrong sig fails',        !WebhookSigner::verify($payload, $secret, 'sha256=wrongsig'));

// Deterministic
$sig2 = WebhookSigner::sign($payload, $secret);
t('W07 deterministic',          $sig === $sig2);

// Different payloads
$sig3 = WebhookSigner::sign('different payload', $secret);
t('W08 different payloads',     $sig !== $sig3);

// Empty payload
$sigEmpty = WebhookSigner::sign('', $secret);
t('W09 empty payload ok',       str_starts_with($sigEmpty, 'sha256='));

// Verify empty payload
t('W10 verify empty payload',   WebhookSigner::verify('', $secret, $sigEmpty));

// ─── API Key format tests ─────────────────────────────────────────────────────

$livKey  = 'sk_live_' . bin2hex(random_bytes(24));
$testKey = 'sk_test_' . bin2hex(random_bytes(24));

t('K01 live key format',        (bool)preg_match('/^sk_live_[0-9a-f]{48}$/', $livKey));
t('K02 test key format',        (bool)preg_match('/^sk_test_[0-9a-f]{48}$/', $testKey));

$hash1 = hash('sha256', $livKey);
t('K03 hash is 64 chars',       strlen($hash1) === 64);

$hash2 = hash('sha256', 'sk_live_' . bin2hex(random_bytes(24)));
t('K04 two keys different hash',$hash1 !== $hash2);

$hashSame = hash('sha256', $livKey);
t('K05 hash reproducible',      $hash1 === $hashSame);

// ─── Résumé ───────────────────────────────────────────────────────────────────
echo "\nWebhookTest+ApiKeyTest: $pass/" . ($pass + $fail) . " PASS\n";
if ($fail > 0) exit(1);
