<?php
declare(strict_types=1);

namespace App\Modules\Api\Auth;

/**
 * Contexte d'authentification résolu à chaque requête API.
 */
final class AuthContext
{
    public function __construct(
        public readonly int    $userId,
        public readonly int    $etablissementId,
        public readonly string $role,
        public readonly array  $permissions,
        public readonly string $authMethod,   // 'jwt' | 'api_key' | 'session'
        public readonly ?string $apiKeyId  = null,
        public readonly ?string $jti       = null, // JWT ID
    ) {}

    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->permissions, true);
    }

    public function hasAnyPermission(string ...$permissions): bool
    {
        foreach ($permissions as $p) {
            if ($this->hasPermission($p)) {
                return true;
            }
        }
        return false;
    }

    public function toArray(): array
    {
        return [
            'user_id'          => $this->userId,
            'etablissement_id' => $this->etablissementId,
            'role'             => $this->role,
            'auth_method'      => $this->authMethod,
        ];
    }
}
