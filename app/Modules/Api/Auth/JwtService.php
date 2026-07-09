<?php
declare(strict_types=1);

namespace App\Modules\Api\Auth;

use App\Modules\Api\Exceptions\AuthException;

/**
 * Service JWT HS256 — encode/decode sans dépendance externe.
 */
class JwtService
{
    private string $secret;
    private int    $accessTtl;
    private string $issuer;
    private string $audience;

    public function __construct()
    {
        $cfg = require ROOT_PATH . '/config/api.php';
        $this->secret    = $cfg['jwt']['secret'];
        $this->accessTtl = $cfg['jwt']['access_ttl'];
        $this->issuer    = $cfg['jwt']['issuer'];
        $this->audience  = $cfg['jwt']['audience'];
    }

    public function encode(array $customPayload, ?int $ttl = null): string
    {
        $now = time();
        $ttl = $ttl ?? $this->accessTtl;

        $header = $this->base64UrlEncode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $payload = $this->base64UrlEncode(json_encode(array_merge([
            'iss' => $this->issuer,
            'aud' => $this->audience,
            'jti' => $this->generateJti(),
            'iat' => $now,
            'nbf' => $now,
            'exp' => $now + $ttl,
        ], $customPayload)));

        $signature = $this->base64UrlEncode(
            hash_hmac('sha256', "$header.$payload", $this->secret, true)
        );

        return "$header.$payload.$signature";
    }

    /**
     * @throws AuthException si le token est invalide, expiré ou mal formé
     */
    public function decode(string $token): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new AuthException('Token malformé.', 'auth_invalid');
        }

        [$headerB64, $payloadB64, $sigB64] = $parts;

        // Vérification de la signature
        $expected = $this->base64UrlEncode(
            hash_hmac('sha256', "$headerB64.$payloadB64", $this->secret, true)
        );

        if (!hash_equals($expected, $sigB64)) {
            throw new AuthException('Signature JWT invalide.', 'auth_invalid');
        }

        $payload = json_decode($this->base64UrlDecode($payloadB64), true);
        if (!is_array($payload)) {
            throw new AuthException('Payload JWT invalide.', 'auth_invalid');
        }

        // Vérification expiration
        if (isset($payload['exp']) && $payload['exp'] < time()) {
            throw new AuthException('Token JWT expiré.', 'auth_expired');
        }

        // Vérification issuer / audience
        if (($payload['iss'] ?? '') !== $this->issuer) {
            throw new AuthException('Issuer JWT invalide.', 'auth_invalid');
        }

        return $payload;
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', (4 - strlen($data) % 4) % 4));
    }

    private function generateJti(): string
    {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
}
