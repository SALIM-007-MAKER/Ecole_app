<?php
declare(strict_types=1);

namespace App\Modules\Api\Auth;

use App\Modules\Api\Repositories\ApiKeyRepository;

class ApiKeyService
{
    public function __construct(private readonly ApiKeyRepository $repo) {}

    /**
     * Crée une nouvelle API Key et retourne la clé complète (affichée UNE seule fois).
     */
    public function create(
        int    $etablissementId,
        string $name,
        array  $permissions,
        int    $createdBy,
        bool   $isTest = false,
        int    $rateLimit = 1000,
        ?array $allowedIps = null,
        ?string $expiresAt = null
    ): array {
        $prefix  = $isTest ? 'sk_test_' : 'sk_live_';
        $rawKey  = $prefix . bin2hex(random_bytes(24)); // sk_live_{48_hex}
        $hash    = hash('sha256', $rawKey);
        $hint    = substr($rawKey, -8);

        $id = $this->repo->create([
            'etablissement_id' => $etablissementId,
            'name'             => $name,
            'key_prefix'       => $prefix,
            'key_hash'         => $hash,
            'key_hint'         => $hint,
            'permissions'      => json_encode($permissions),
            'rate_limit'       => $rateLimit,
            'allowed_ips'      => $allowedIps !== null ? json_encode($allowedIps) : null,
            'expires_at'       => $expiresAt,
            'created_by'       => $createdBy,
        ]);

        return ['id' => $id, 'key' => $rawKey, 'hint' => $hint];
    }

    /**
     * Résout une API key depuis son raw value.
     * Retourne null si invalide, révoquée ou expirée.
     */
    public function resolve(string $rawKey, string $clientIp): ?array
    {
        $hash = hash('sha256', $rawKey);
        $row  = $this->repo->findValid($hash);
        if ($row === null) {
            return null;
        }

        // Vérification whitelist IP (exact match + CIDR)
        if (!empty($row['allowed_ips'])) {
            $ips = json_decode($row['allowed_ips'], true) ?? [];
            if (!empty($ips) && !$this->ipMatchesWhitelist($clientIp, $ips)) {
                return null;
            }
        }

        $this->repo->touchLastUsed((int)$row['id']);
        return $row;
    }

    private function ipMatchesWhitelist(string $clientIp, array $whitelist): bool
    {
        $clientLong = ip2long($clientIp);
        foreach ($whitelist as $entry) {
            if (str_contains($entry, '/')) {
                [$network, $bits] = explode('/', $entry, 2);
                $bits     = (int)$bits;
                $mask     = $bits > 0 ? ~((1 << (32 - $bits)) - 1) : 0;
                $netLong  = ip2long($network);
                if ($clientLong !== false && $netLong !== false
                    && ($clientLong & $mask) === ($netLong & $mask)) {
                    return true;
                }
            } elseif ($entry === $clientIp) {
                return true;
            }
        }
        return false;
    }

    public function revoke(int $id, int $etablissementId): void
    {
        $this->repo->revoke($id, $etablissementId);
    }

    public function listForEtab(int $etablissementId): array
    {
        return $this->repo->listForEtab($etablissementId);
    }
}
