<?php

namespace App\Modules\Scolarite\Policies;

use Core\Database;

/**
 * Politique d'autorisation pour le domaine Familles.
 *
 * Règle clé : un utilisateur de rôle « parent » ne peut voir
 * que les familles liées à SES propres enfants (via eleves.parent_id).
 */
class FamillePolicy
{
    public function canView(array $user, ?object $famille = null): bool
    {
        if ($this->hasPermission($user, 'familles.view')) {
            return true;
        }

        // Un parent connecté peut voir la famille liée à ses enfants
        if (($user['role'] ?? '') === 'parent' && $famille !== null) {
            return $this->isFamilleOfOwnChild($user, (int)$famille->id);
        }

        return false;
    }

    public function canManage(array $user): bool
    {
        return $this->hasPermission($user, 'familles.manage');
    }

    public function canArchive(array $user): bool
    {
        return $this->hasPermission($user, 'familles.manage');
    }

    public function canLinkEleve(array $user): bool
    {
        return $this->hasPermission($user, 'familles.manage');
    }

    public function canUnlinkEleve(array $user): bool
    {
        return $this->hasPermission($user, 'familles.manage');
    }

    /** Un parent ne peut voir que les familles liées à SES enfants. */
    private function isFamilleOfOwnChild(array $user, int $familleId): bool
    {
        $pdo  = Database::getInstance()->getConnection();
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM `familles_eleves` fe
             JOIN `eleves` e ON e.id = fe.eleve_id
             WHERE fe.famille_id = ? AND e.parent_id = ?"
        );
        $stmt->execute([$familleId, $user['id']]);
        return (int)$stmt->fetchColumn() > 0;
    }

    private function hasPermission(array $user, string $permission): bool
    {
        $permissions = $user['permissions'] ?? [];
        return in_array($permission, (array)$permissions, true)
            || in_array('*', (array)$permissions, true);
    }
}
