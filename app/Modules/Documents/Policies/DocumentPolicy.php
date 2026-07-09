<?php

declare(strict_types=1);

namespace App\Modules\Documents\Policies;

class DocumentPolicy
{
    private function has(array $user, string $perm): bool
    {
        return in_array($perm, $user['permissions'] ?? [], true);
    }

    public function canView(array $user, array $document): bool
    {
        if (!$this->has($user, 'document.view')) return false;
        if ($document['confidentialite'] === 'secret') return $this->canViewSecret($user);
        return true;
    }

    public function canViewAll(array $user): bool
    {
        return $this->has($user, 'document.view_all');
    }

    public function canViewSecret(array $user): bool
    {
        return $this->has($user, 'document.view_secret')
            || in_array($user['role'] ?? '', ['admin', 'directeur'], true);
    }

    public function canCreate(array $user): bool
    {
        return $this->has($user, 'document.create');
    }

    public function canUpdate(array $user, array $document): bool
    {
        return $this->has($user, 'document.update');
    }

    public function canNewVersion(array $user, array $document): bool
    {
        return $this->has($user, 'document.version')
            && !in_array($document['statut'] ?? '', ['archive', 'corbeille'], true);
    }

    public function canArchive(array $user, array $document): bool
    {
        return $this->has($user, 'document.archive')
            && !in_array($document['statut'] ?? '', ['archive', 'corbeille'], true);
    }

    public function canTrash(array $user): bool
    {
        return $this->has($user, 'document.delete');
    }

    public function canRestore(array $user): bool
    {
        return $this->has($user, 'document.restore');
    }

    public function canPurge(array $user): bool
    {
        return $this->has($user, 'document.admin');
    }

    public function canShare(array $user, array $document): bool
    {
        return $this->has($user, 'document.share') && $this->canView($user, $document);
    }

    public function canExport(array $user): bool
    {
        return $this->has($user, 'document.export');
    }

    public function canDownload(array $user, array $document): bool
    {
        return $this->canView($user, $document);
    }

    public function canAdmin(array $user): bool
    {
        return $this->has($user, 'document.admin');
    }

    public function canSign(array $user): bool
    {
        return $this->has($user, 'document.sign');
    }

    public function canRequestSignature(array $user): bool
    {
        return $this->has($user, 'document.admin') || $this->has($user, 'document.share');
    }
}
