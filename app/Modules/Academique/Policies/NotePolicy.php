<?php

namespace App\Modules\Academique\Policies;

class NotePolicy
{
    public function canView(array $user): bool
    {
        return $this->has($user, 'academique.notes.view')
            || $this->has($user, 'academique.notes.manage');
    }

    public function canSaisir(array $user, object $evaluation): bool
    {
        return $this->has($user, 'academique.notes.manage')
            && (int)$evaluation->notes_saisie_ouverte === 1;
    }

    public function canModifier(array $user, object $note): bool
    {
        return $this->has($user, 'academique.notes.manage')
            && $note->statut !== 'verrouillee';
    }

    public function canPublier(array $user): bool
    {
        return $this->has($user, 'academique.notes.manage');
    }

    public function canVerrouiller(array $user): bool
    {
        return $this->has($user, 'academique.notes.admin');
    }

    public function canImporter(array $user, object $evaluation): bool
    {
        return $this->has($user, 'academique.notes.manage')
            && (int)$evaluation->notes_saisie_ouverte === 1;
    }

    private function has(array $user, string $permission): bool
    {
        $perms = $user['permissions'] ?? [];
        return in_array($permission, $perms, true)
            || in_array('*', $perms, true);
    }
}
