<?php

namespace App\Modules\Academique\Policies;

class NotePolicy
{
    /** Permission dédiée à la matière de Conduite (migration T049). */
    public const PERMISSION_CONDUITE = 'academique.conduite.manage';

    /**
     * Matière de Conduite : identifiée par le marqueur `matieres.est_conduite` (jamais par son libellé).
     * Saisie, modification, import, publication, verrouillage et suppression des notes de Conduite exigent
     * `academique.conduite.manage` (Admin et Directeur) — un enseignant ne peut que les consulter.
     * Les autres matières ne changent pas. Voir aussi EvaluationPolicy.
     *
     * @param int|bool|string|null $marqueur valeur de `matieres.est_conduite` (ou `matiere_est_conduite` d'une requête)
     */
    public function estConduite(int|bool|string|null $marqueur): bool
    {
        return (int)$marqueur === 1;
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
        if (!$this->has($user, 'academique.notes.manage')) {
            return false;
        }
        return !$this->estConduite($evaluation->matiere_est_conduite ?? null) || $this->has($user, self::PERMISSION_CONDUITE);
    }

    public function canModifier(array $user, object $note): bool
    {
        if ($note->statut === 'verrouillee') {
            return false;
        }
        if (!$this->has($user, 'academique.notes.manage')) {
            return false;
        }
        return !$this->estConduite($note->matiere_est_conduite ?? null) || $this->has($user, self::PERMISSION_CONDUITE);
    }

    /**
     * Publication des notes d'une évaluation. $evaluation (optionnel, pour l'affichage des boutons) permet de
     * réserver la Conduite à `academique.conduite.manage`.
     */
    public function canPublier(array $user, ?object $evaluation = null): bool
    {
        if (!$this->has($user, 'academique.notes.manage')) {
            return false;
        }
        return $evaluation === null
            || !$this->estConduite($evaluation->matiere_est_conduite ?? null)
            || $this->has($user, self::PERMISSION_CONDUITE);
    }

    /**
     * Suppression : Admin/Directeur (academique.notes.admin) pour une matière ordinaire, `academique.conduite.manage`
     * pour la Conduite — l'enseignant peut corriger une note (canModifier) mais pas la supprimer.
     * Même garde que canModifier : impossible si verrouillée.
     */
    public function canSupprimer(array $user, object $note): bool
    {
        $droit = $this->estConduite($note->matiere_est_conduite ?? null) ? self::PERMISSION_CONDUITE : 'academique.notes.admin';
        return $this->has($user, $droit) && $note->statut !== 'verrouillee';
    }

    /** Verrouillage des notes d'une évaluation (même logique que canSupprimer). */
    public function canVerrouiller(array $user, ?object $evaluation = null): bool
    {
        $droit = ($evaluation !== null && $this->estConduite($evaluation->matiere_est_conduite ?? null))
            ? self::PERMISSION_CONDUITE : 'academique.notes.admin';
        return $this->has($user, $droit);
    }

    public function canImporter(array $user, object $evaluation): bool
    {
        // Même règle que la saisie (l'import passe par le même service).
        return $this->canSaisir($user, $evaluation);
    }

    private function has(array $user, string $permission): bool
    {
        $perms = $user['permissions'] ?? [];
        return in_array($permission, $perms, true)
            || in_array('*', $perms, true);
    }
}
