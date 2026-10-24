<?php

namespace App\Modules\Academique\Policies;

class EvaluationPolicy
{
    /**
     * Matière « Conduite » : circuit réservé au Directeur/Admin (académique.
     * evaluations.admin) — un enseignant ne peut ni créer ni modifier une
     * évaluation de Conduite, contrairement aux autres matières où
     * academique.evaluations.manage suffit. Voir aussi NotePolicy (même
     * restriction côté saisie des notes).
     */
    public function estConduite(?string $matiereNom): bool
    {
        return $matiereNom !== null && mb_strtolower(trim($matiereNom)) === 'conduite';
    }

    public function canView(array $user): bool
    {
        return $this->hasPermission($user, 'academique.evaluations.view')
            || $this->hasPermission($user, 'academique.evaluations.manage');
    }

    /**
     * @param string|null $matiereNom Nom de la matière ciblée, quand connu
     *                                (ex : soumission du formulaire de création).
     *                                Laisser null pour un contrôle d'accès générique
     *                                (ex : affichage du formulaire avant sélection).
     */
    public function canCreate(array $user, ?string $matiereNom = null): bool
    {
        if (!$this->hasPermission($user, 'academique.evaluations.manage')) {
            return false;
        }
        if ($this->estConduite($matiereNom)) {
            return $this->hasPermission($user, 'academique.evaluations.admin');
        }
        return true;
    }

    public function canUpdate(array $user, ?\stdClass $evaluation = null): bool
    {
        if (!$this->hasPermission($user, 'academique.evaluations.manage')) {
            return false;
        }
        if (!$evaluation) {
            return true;
        }
        if ($this->estConduite($evaluation->matiere_nom ?? null)) {
            return $this->hasPermission($user, 'academique.evaluations.admin');
        }
        // Archivée : aucune modification possible
        if ($evaluation->statut === 'archivee') {
            return false;
        }
        // Verrouillée : admin uniquement
        if ($evaluation->statut === 'verrouillee') {
            return $this->hasPermission($user, 'academique.evaluations.admin');
        }
        return true;
    }

    public function canPublish(array $user, ?\stdClass $evaluation = null): bool
    {
        if (!$this->hasPermission($user, 'academique.evaluations.manage')) {
            return false;
        }
        return $evaluation && $evaluation->statut === 'brouillon';
    }

    public function canVerrouiller(array $user, ?\stdClass $evaluation = null): bool
    {
        if (!$this->hasPermission($user, 'academique.evaluations.manage')) {
            return false;
        }
        return $evaluation && $evaluation->statut === 'publiee';
    }

    public function canDeverrouiller(array $user, ?\stdClass $evaluation = null): bool
    {
        if (!$this->hasPermission($user, 'academique.evaluations.admin')) {
            return false;
        }
        return $evaluation && $evaluation->statut === 'verrouillee';
    }

    public function canArchiver(array $user, ?\stdClass $evaluation = null): bool
    {
        if (!$this->hasPermission($user, 'academique.evaluations.admin')) {
            return false;
        }
        return $evaluation && $evaluation->statut !== 'archivee';
    }

    private function hasPermission(array $user, string $permission): bool
    {
        return in_array($permission, $user['permissions'] ?? [], true);
    }
}
