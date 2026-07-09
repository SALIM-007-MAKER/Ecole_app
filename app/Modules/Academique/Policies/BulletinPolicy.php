<?php

namespace App\Modules\Academique\Policies;

class BulletinPolicy
{
    /** Peut générer un bulletin (admin, directeur, prof principal). */
    public function canGenerate(array $user): bool
    {
        return $this->has($user, 'academique.bulletin.generate')
            || $this->has($user, 'academique.bulletin.admin');
    }

    /** Peut visualiser un bulletin (enseignant, parent avec accès). */
    public function canView(array $user): bool
    {
        return $this->has($user, 'academique.bulletin.view')
            || $this->has($user, 'academique.bulletin.generate')
            || $this->has($user, 'academique.bulletin.admin');
    }

    /** Peut publier un bulletin (le rendre visible aux parents/élèves). */
    public function canPublish(array $user): bool
    {
        return $this->has($user, 'academique.bulletin.publish')
            || $this->has($user, 'academique.bulletin.admin');
    }

    /** Peut archiver un bulletin. */
    public function canArchive(array $user): bool
    {
        return $this->has($user, 'academique.bulletin.archive')
            || $this->has($user, 'academique.bulletin.admin');
    }

    /** Peut ajouter ou modifier l'appréciation du directeur. */
    public function canAddDirecteurAppreciation(array $user): bool
    {
        return $this->has($user, 'academique.bulletin.admin');
    }

    /** Peut exporter en PDF. */
    public function canExport(array $user): bool
    {
        return $this->has($user, 'academique.bulletin.export')
            || $this->has($user, 'academique.bulletin.generate')
            || $this->has($user, 'academique.bulletin.admin');
    }

    /** Peut visualiser via token de vérification (public, sans auth). */
    public function canVerify(): bool
    {
        return true;
    }

    private function has(array $user, string $permission): bool
    {
        $perms = $user['permissions'] ?? [];
        return in_array($permission, $perms, true)
            || in_array('*', $perms, true);
    }
}
