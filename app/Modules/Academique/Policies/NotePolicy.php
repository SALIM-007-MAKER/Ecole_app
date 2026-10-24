<?php

namespace App\Modules\Academique\Policies;

class NotePolicy
{
    /**
     * Matière « Conduite » : saisie/modification réservée au Directeur/Admin
     * (academique.notes.admin) — un enseignant ne peut pas noter la Conduite,
     * contrairement aux autres matières où academique.notes.manage suffit.
     * Voir aussi EvaluationPolicy (même restriction côté création/édition
     * de l'évaluation elle-même).
     */
    public function estConduite(?string $matiereNom): bool
    {
        return $matiereNom !== null && mb_strtolower(trim($matiereNom)) === 'conduite';
    }

    public function canView(array $user): bool
    {
        return $this->has($user, 'academique.notes.view')
            || $this->has($user, 'academique.notes.manage');
    }

    public function canSaisir(array $user, object $evaluation): bool
    {
        if ((int)$evaluation->notes_saisie_ouverte !== 1) {
            return false;
        }
        if ($this->estConduite($evaluation->matiere_nom ?? null)) {
            return $this->has($user, 'academique.notes.admin');
        }
        return $this->has($user, 'academique.notes.manage');
    }

    public function canModifier(array $user, object $note): bool
    {
        if ($note->statut === 'verrouillee') {
            return false;
        }
        if ($this->estConduite($note->matiere_nom ?? null)) {
            return $this->has($user, 'academique.notes.admin');
        }
        return $this->has($user, 'academique.notes.manage');
    }

    public function canPublier(array $user): bool
    {
        return $this->has($user, 'academique.notes.manage');
    }

    /**
     * Suppression réservée à Admin/Directeur (academique.notes.admin) —
     * l'enseignant peut corriger une note (canModifier) mais pas la
     * supprimer. Même garde que canModifier : impossible si verrouillée.
     */
    public function canSupprimer(array $user, object $note): bool
    {
        return $this->has($user, 'academique.notes.admin')
            && $note->statut !== 'verrouillee';
    }

    public function canVerrouiller(array $user): bool
    {
        return $this->has($user, 'academique.notes.admin');
    }

    public function canImporter(array $user, object $evaluation): bool
    {
        if ((int)$evaluation->notes_saisie_ouverte !== 1) {
            return false;
        }
        if ($this->estConduite($evaluation->matiere_nom ?? null)) {
            return $this->has($user, 'academique.notes.admin');
        }
        return $this->has($user, 'academique.notes.manage');
    }

    private function has(array $user, string $permission): bool
    {
        $perms = $user['permissions'] ?? [];
        return in_array($permission, $perms, true)
            || in_array('*', $perms, true);
    }
}
