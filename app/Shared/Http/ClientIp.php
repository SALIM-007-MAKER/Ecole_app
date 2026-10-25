<?php

declare(strict_types=1);

namespace App\Shared\Http;

/**
 * Adresse IP du client, avec gestion explicite des proxys de confiance.
 *
 * RÈGLE : l'en-tête X-Forwarded-For n'est JAMAIS cru par défaut. Il n'est lu que si l'adresse qui se connecte
 * réellement (REMOTE_ADDR) appartient à une liste de proxys configurée explicitement (TRUSTED_PROXIES : adresses
 * ou CIDR séparés par des virgules). Sans configuration, un visiteur ne peut pas choisir son adresse en
 * envoyant un en-tête forgé (ce qui lui permettrait de contourner une limitation de débit).
 *
 * Avec des proxys de confiance, la chaîne est parcourue de DROITE À GAUCHE : on ignore les adresses des proxys
 * connus et on retient la première adresse qui n'en est pas un. Les entrées placées à gauche par le client
 * (forgées) ne sont donc jamais retenues. Joker interdit : « * », « 0.0.0.0/0 » et « ::/0 » sont ignorés.
 */
final class ClientIp
{
    public const UNKNOWN = '0.0.0.0';

    /**
     * @param array<string,mixed> $server   typiquement $_SERVER
     * @param string[]            $trusted  adresses ou CIDR des proxys de confiance (liste vide = aucun)
     */
    public static function resolve(array $server, array $trusted = []): string
    {
        $remote = self::normalize((string)($server['REMOTE_ADDR'] ?? ''));
        if ($remote === null) {
            return self::UNKNOWN;
        }

        $trusted = array_values(array_filter($trusted, [self::class, 'isAcceptableRule']));
        if ($trusted === [] || !self::inList($remote, $trusted)) {
            return $remote;                       // connexion directe, ou proxy inconnu : on ignore X-Forwarded-For
        }

        $header = (string)($server['HTTP_X_FORWARDED_FOR'] ?? '');
        if ($header === '') {
            return $remote;
        }
        $chain = array_map('trim', explode(',', $header));
        for ($i = count($chain) - 1; $i >= 0; $i--) {
            $ip = self::normalize($chain[$i]);
            if ($ip === null) {
                return $remote;                   // entrée illisible : on ne devine pas
            }
            if (!self::inList($ip, $trusted)) {
                return $ip;                       // premier non-proxy en partant de la droite = le vrai client
            }
        }
        return $remote;                           // toute la chaîne est constituée de proxys connus
    }

    /** Liste des proxys de confiance depuis TRUSTED_PROXIES ($_ENV puis getenv()). */
    public static function trustedFromEnvironment(?array $env = null): array
    {
        $raw = $env !== null ? ($env['TRUSTED_PROXIES'] ?? '') : ($_ENV['TRUSTED_PROXIES'] ?? getenv('TRUSTED_PROXIES'));
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }
        return array_values(array_filter(array_map('trim', explode(',', $raw)), [self::class, 'isAcceptableRule']));
    }

    /** Une règle est une adresse ou un CIDR valide, et n'est pas un joker « tout le monde ». */
    public static function isAcceptableRule(string $rule): bool
    {
        $rule = trim($rule);
        if ($rule === '' || $rule === '*') {
            return false;
        }
        if (!str_contains($rule, '/')) {
            return self::normalize($rule) !== null;
        }
        [$addr, $bits] = explode('/', $rule, 2);
        $ip = self::normalize($addr);
        if ($ip === null || !ctype_digit($bits)) {
            return false;
        }
        $max = str_contains($ip, ':') ? 128 : 32;
        return (int)$bits >= 1 && (int)$bits <= $max;       // /0 (tout le monde) refusé
    }

    private static function inList(string $ip, array $rules): bool
    {
        foreach ($rules as $rule) {
            if (self::matches($ip, $rule)) {
                return true;
            }
        }
        return false;
    }

    private static function matches(string $ip, string $rule): bool
    {
        if (!str_contains($rule, '/')) {
            return self::normalize($rule) === $ip;
        }
        [$addr, $bits] = explode('/', $rule, 2);
        $a = inet_pton($ip);
        $b = inet_pton((string)self::normalize($addr));
        if ($a === false || $b === false || strlen($a) !== strlen($b)) {
            return false;                          // IPv4 contre IPv6 : jamais équivalents
        }
        $bits = (int)$bits;
        $bytes = intdiv($bits, 8);
        if ($bytes > 0 && substr($a, 0, $bytes) !== substr($b, 0, $bytes)) {
            return false;
        }
        $rest = $bits % 8;
        if ($rest === 0) {
            return true;
        }
        $mask = (0xFF << (8 - $rest)) & 0xFF;
        return (ord($a[$bytes]) & $mask) === (ord($b[$bytes]) & $mask);
    }

    /** Forme canonique d'une adresse IPv4/IPv6 valide, ou null. */
    private static function normalize(string $ip): ?string
    {
        $ip = trim($ip);
        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return null;
        }
        $bin = inet_pton($ip);
        return $bin === false ? null : (string)inet_ntop($bin);
    }
}
