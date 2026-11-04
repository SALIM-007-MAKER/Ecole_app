<?php

namespace App\Modules\Academique\Policies;

class EvaluationPolicy
{
    /** Permission dédiée à la matière de Conduite (migration T049). */
    public const PERMISSION_CONDUITE = 'academique.conduite.manage';

    /**
     * Matière de Conduite : identifiée par le marqueur `matieres.est_conduite` (jamais par son libellé).
     * Toutes les actions sur ses évaluations exigent `academique.conduite.manage` (Admin et Directeur),
     * en plus du droit de base du module. Les autres matières ne changent pas.
     * Voir aussi NotePolicy (même règle côté notes).
     *
     * @param int|bool|string|null $marqueur valeur de `matieres.est_conduite` (ou `matiere_est_conduite` d'une requête)
     */
    public function estConduite(int|bool|string|null $marqueur): bool
    {
        return (int)$marqueur === 1;
    }

    private function conduiteDe(?\stdClass $evaluation): bool
    {
        return $evaluation !== null && $this->estConduite($evaluation->matiere_est_conduite ?? null);
    }

    public function canView(array $user): bool
    {
        return $this->hasPermission($user, 'academique.evaluations.view')
            || $this->hasPermission($user, 'academique.evaluations.manage');
    }

    /**
     * @param bool $conduite true quand la matière ciblée est la Conduite (ex : soumission du formulaire de
     *                       création). Laisser false pour un contrôle d'accès générique (affichage du formulaire).
     */
    public function canCreate(array $user, bool $conduite = false): bool
    {
        if (!$this->hasPermission($user, 'academique.evaluations.manage')) {
            return false;
        }
        return !$conduite || $this->hasPermission($user, self::PERMISSION_CONDUITE);
    }

    public function canUpdate(array $user, ?\stdClass $evaluation = null): bool
    {
        if (!$this->hasPermission($user, 'academique.evaluations.manage')) {
            return false;
        }
        if (!$evaluation) {
            return true;
        }
        // Archivée : aucune modification possible (Conduite comprise)
        if ($evaluation->statut === 'archivee') {
            return false;
        }
        if ($this->conduiteDe($evaluation)) {
            return $this->hasPermission($user, self::PERMISSION_CONDUITE);
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
        if ($this->conduiteDe($evaluation) && !$this->hasPermission($user, self::PERMISSION_CONDUITE)) {
            return false;
        }
        return $evaluation && $evaluation->statut === 'brouillon';
    }

    public function canVerrouiller(array $user, ?\stdClass $evaluation = null): bool
    {
        if (!$this->hasPermission($user, 'academique.evaluations.manage')) {
            return false;
        }
        if ($this->conduiteDe($evaluation) && !$this->hasPermission($user, self::PERMISSION_CONDUITE)) {
            return false;
        }
        return $evaluation && $evaluation->statut === 'publiee';
    }

    public function canDeverrouiller(array $user, ?\stdClass $evaluation = null): bool
    {
        $droit = $this->conduiteDe($evaluation) ? self::PERMISSION_CONDUITE : 'academique.evaluations.admin';
        if (!$this->hasPermission($user, $droit)) {
            return false;
        }
        return $evaluation && $evaluation->statut === 'verrouillee';
    }

    public function canArchiver(array $user, ?\stdClass $evaluation = null): bool
    {
        $droit = $this->conduiteDe($evaluation) ? self::PERMISSION_CONDUITE : 'academique.evaluations.admin';
        if (!$this->hasPermission($user, $droit)) {
            return false;
        }
        return $evaluation && $evaluation->statut !== 'archivee';
    }

    private function hasPermission(array $user, string $permission): bool
    {
        return in_array($permission, $user['permissions'] ?? [], true);
    }
}
