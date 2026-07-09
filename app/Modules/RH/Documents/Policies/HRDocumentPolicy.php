<?php

declare(strict_types=1);

namespace App\Modules\RH\Documents\Policies;

class HRDocumentPolicy
{
    public function canView(array $user): bool
    {
        return in_array('hr_document.view', $user['permissions'] ?? [], true);
    }

    public function canCreate(array $user): bool
    {
        return in_array('hr_document.create', $user['permissions'] ?? [], true);
    }

    public function canUpdate(array $user): bool
    {
        return in_array('hr_document.update', $user['permissions'] ?? [], true);
    }

    public function canArchive(array $user): bool
    {
        return in_array('hr_document.archive', $user['permissions'] ?? [], true);
    }

    public function canExport(array $user): bool
    {
        return in_array('hr_document.export', $user['permissions'] ?? [], true);
    }

    /** L'employé peut voir ses propres documents publics/confidentiels (pas secret). */
    public function canViewOwn(array $user, array $document): bool
    {
        if (!$this->canView($user)) return false;
        $isOwner = (int)($user['employe_id'] ?? 0) === (int)$document['employe_id'];
        if (!$isOwner) return true; // RH/admin can see all
        return $document['confidentialite'] !== 'secret'; // own secret docs need hr_document.view full
    }

    /** Seul admin/directeur peut voir les documents secrets. */
    public function canViewSecret(array $user): bool
    {
        $roles = $user['roles'] ?? [];
        return in_array('admin', $roles, true) || in_array('directeur', $roles, true);
    }
}
