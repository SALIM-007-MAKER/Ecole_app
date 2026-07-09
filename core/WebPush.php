<?php

declare(strict_types=1);

namespace Core;

/**
 * WebPush — Implémentation VAPID pour les Push Notifications
 * PHP 8.0+ avec extension OpenSSL (disponible par défaut dans WampServer)
 *
 * Architecture : ping push (SW récupère le contenu depuis /api/notifications/latest)
 * Ce mode évite d'implémenter le chiffrement Web Push (AES-GCM + ECDH)
 * tout en restant pleinement fonctionnel.
 */
class WebPush
{
    // ─── Encodage base64url ───────────────────────────────────────────────────
    public static function b64url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    public static function b64urlDecode(string $data): string
    {
        $pad = 4 - (strlen($data) % 4);
        if ($pad < 4) {
            $data .= str_repeat('=', $pad);
        }
        return base64_decode(strtr($data, '-_', '+/'));
    }

    // ─── Génération de clés VAPID (EC prime256v1) ─────────────────────────────
    public static function generateKeys(): array
    {
        if (!extension_loaded('openssl')) {
            throw new \RuntimeException('Extension OpenSSL requise pour VAPID.');
        }

        $keypair = openssl_pkey_new([
            'curve_name'       => 'prime256v1',
            'private_key_type' => OPENSSL_KEYTYPE_EC,
        ]);

        if (!$keypair) {
            throw new \RuntimeException('Génération de clé EC échouée : ' . openssl_error_string());
        }

        $details = openssl_pkey_get_details($keypair);
        if (!isset($details['ec']['x'], $details['ec']['y'])) {
            throw new \RuntimeException('Impossible d\'extraire les coordonnées EC.');
        }

        // Clé publique non compressée : 0x04 | x | y (65 octets)
        $pubKeyRaw = "\x04" . $details['ec']['x'] . $details['ec']['y'];

        openssl_pkey_export($keypair, $privKeyPem);

        return [
            'public_key'      => self::b64url($pubKeyRaw),
            'private_key_pem' => $privKeyPem,
        ];
    }

    // ─── Création du JWT VAPID (ES256) ───────────────────────────────────────
    public static function createVapidJwt(
        string $endpoint,
        string $privateKeyPem,
        string $subject = 'mailto:admin@ecole-app.local'
    ): string {
        // audience = origin du push service
        $scheme   = (string)parse_url($endpoint, PHP_URL_SCHEME);
        $host     = (string)parse_url($endpoint, PHP_URL_HOST);
        $port     = parse_url($endpoint, PHP_URL_PORT);
        $audience = $scheme . '://' . $host . ($port ? ':' . $port : '');

        $header  = self::b64url(json_encode(
            ['typ' => 'JWT', 'alg' => 'ES256'],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        ));
        $payload = self::b64url(json_encode([
            'aud' => $audience,
            'sub' => $subject,
            'exp' => time() + 43200, // 12 h
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        $unsigned = "{$header}.{$payload}";
        $privKey  = openssl_pkey_get_private($privateKeyPem);

        if (!$privKey) {
            throw new \RuntimeException('Clé privée VAPID invalide.');
        }

        openssl_sign($unsigned, $derSig, $privKey, OPENSSL_ALGO_SHA256);

        return "{$unsigned}." . self::b64url(self::derSigToRaw($derSig));
    }

    // ─── Conversion DER → r||s (64 octets bruts) ─────────────────────────────
    private static function derSigToRaw(string $der): string
    {
        $pos = 0;

        if (!isset($der[$pos]) || ord($der[$pos++]) !== 0x30) {
            throw new \RuntimeException('Signature DER invalide (SEQUENCE manquant).');
        }

        // Longueur du SEQUENCE (forme courte ou longue)
        $seqLen = ord($der[$pos++]);
        if ($seqLen & 0x80) {
            $pos += $seqLen & 0x7F;
        }

        // INTEGER r
        if (ord($der[$pos++]) !== 0x02) {
            throw new \RuntimeException('Signature DER invalide (INTEGER r manquant).');
        }
        $rLen = ord($der[$pos++]);
        $r    = substr($der, $pos, $rLen);
        $pos += $rLen;

        // INTEGER s
        if (ord($der[$pos++]) !== 0x02) {
            throw new \RuntimeException('Signature DER invalide (INTEGER s manquant).');
        }
        $sLen = ord($der[$pos++]);
        $s    = substr($der, $pos, $sLen);

        // Normaliser à 32 octets chacun (retirer l'octet de signe 0x00 ajouté par DER)
        $r = ltrim($r, "\x00");
        $s = ltrim($s, "\x00");

        return str_pad($r, 32, "\x00", STR_PAD_LEFT)
             . str_pad($s, 32, "\x00", STR_PAD_LEFT);
    }

    // ─── Envoi d'un push (ping sans payload) ─────────────────────────────────
    /**
     * Envoie un push notification sans payload chiffré.
     * Le Service Worker reçoit l'événement push et récupère le contenu
     * depuis /api/notifications/latest.
     *
     * @throws \RuntimeException Si cURL n'est pas disponible.
     */
    public static function sendPing(
        string $endpoint,
        string $vapidPublicKey,
        string $vapidPrivateKeyPem,
        string $vapidSubject = 'mailto:admin@ecole-app.local',
        int    $ttl      = 86400,
        string $urgency  = 'normal'
    ): array {
        if (!extension_loaded('curl')) {
            throw new \RuntimeException('Extension cURL requise pour Web Push.');
        }

        $jwt     = self::createVapidJwt($endpoint, $vapidPrivateKeyPem, $vapidSubject);
        $headers = [
            "Authorization: vapid t={$jwt},k={$vapidPublicKey}",
            "TTL: {$ttl}",
            "Urgency: {$urgency}",
            'Content-Length: 0',
        ];

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => '',
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_FOLLOWLOCATION => false,
        ]);

        $body     = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        $success = ($httpCode >= 200 && $httpCode < 300) || $httpCode === 201;

        return [
            'success'   => $success,
            'http_code' => $httpCode,
            'body'      => $body ?: '',
            'error'     => $curlErr ?: null,
        ];
    }
}
