<?php

namespace App\Modules\Scolarite\Policies;

/**
 * Policy V2 — Autorisations sur les élèves.
 *
 * Centralise toutes les règles d'autorisation liées aux élèves.
 * Chaque action peut dépendre à la fois du rôle (permission globale)
 * ET du contexte (ex : un parent ne peut voir que ses propres enfants).
 *
 * Usage dans un contrôleur :
 *   $this->policy->authorize($this->currentUser(), 'view', $eleve)
 *       || $this->abort(403);
 */
class ElevePolicy
{
    /**
     * Vérifier si l'utilisateur peut effectuer une action sur un élève.
     *
     * @param array       $user     Utilisateur connecté (depuis Session)
     * @param string      $action   'view' | 'create' | 'update' | 'delete' | 'view.own'
     * @param object|null $resource Objet élève concerné (null pour les actions de création)
     */
    public function authorize(array $user, string $action, ?object $resource = null): bool
    {
        $permissions = $user['permissions'] ?? [];

        return match ($action) {
            'view'   => in_array('eleves.view', $permissions, true),
            'create' => in_array('eleves.create', $permissions, true),
            'update' => in_array('eleves.update', $permissions, true),
            'delete' => in_array('eleves.delete', $permissions, true),

            // Un parent peut voir uniquement ses propres enfants
            'view.own' => in_array('eleves.view.own', $permissions, true)
                       && $resource !== null
                       && $this->isParentOf($user, $resource),

            default => false,
        };
    }

    /**
     * Vérifie si l'utilisateur est parent/tuteur de cet élève.
     * TODO: implémenter via table familles_eleves en Phase 1.2
     */
    private function isParentOf(array $user, object $eleve): bool
    {
        // Fallback V1 : vérification via parent_id sur l'élève
        return isset($eleve->parent_id) && (int)$eleve->parent_id === (int)$user['id'];
    }
}
