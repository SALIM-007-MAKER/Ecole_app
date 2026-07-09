<?php
declare(strict_types=1);

namespace App\Modules\Portals\Framework;

class NavigationEngine
{
    private const PORTAL_PERMISSIONS = [
        'admin'        => 'portal.admin.access',
        'direction'    => 'portal.direction.access',
        'enseignant'   => 'portal.enseignant.access',
        'eleve'        => 'portal.eleve.access',
        'parent'       => 'portal.parent.access',
        'comptabilite' => 'portal.comptabilite.access',
        'rh'           => 'portal.rh.access',
    ];

    private const ROLE_TO_PORTAL = [
        'admin'      => 'admin',
        'directeur'  => 'direction',
        'enseignant' => 'enseignant',
        'eleve'      => 'eleve',
        'parent'     => 'parent',
        'comptable'  => 'comptabilite',
        'secretaire' => 'comptabilite',
    ];

    public function canAccessPortal(string $portal, array $perms): bool
    {
        $required = self::PORTAL_PERMISSIONS[$portal] ?? null;
        if ($required === null) {
            return false;
        }
        return in_array($required, $perms, true);
    }

    public function getPortalForRole(string $role): ?string
    {
        return self::ROLE_TO_PORTAL[$role] ?? null;
    }

    public function getDashboardUrl(string $portal): string
    {
        return BASE_URL . '/v2/portals/' . $portal;
    }

    /** URL du dashboard par défaut en fonction du rôle utilisateur */
    public function getDefaultPortalUrl(array $user): string
    {
        $role   = $user['role'] ?? '';
        $portal = $this->getPortalForRole($role);
        if ($portal === null) {
            return BASE_URL . '/dashboard';
        }
        return $this->getDashboardUrl($portal);
    }

    /** Liste des portails accessibles selon les permissions */
    public function getAvailablePortals(array $perms): array
    {
        $available = [];
        foreach (self::PORTAL_PERMISSIONS as $portal => $perm) {
            if (in_array($perm, $perms, true)) {
                $available[] = $portal;
            }
        }
        return $available;
    }

    public function isKnownPortal(string $portal): bool
    {
        return isset(self::PORTAL_PERMISSIONS[$portal]);
    }
}
