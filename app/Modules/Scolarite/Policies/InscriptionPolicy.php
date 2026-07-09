<?php

namespace App\Modules\Scolarite\Policies;

class InscriptionPolicy
{
    /**
     * @param array       $user
     * @param string      $action   'view' | 'create' | 'update' | 'cancel' | 'valider' | 'rejeter'
     * @param object|null $resource Inscription concernée
     */
    public function authorize(array $user, string $action, ?object $resource = null): bool
    {
        $permissions = $user['permissions'] ?? [];

        return match ($action) {
            'view'    => in_array('inscriptions.view',   $permissions, true),
            'create'  => in_array('inscriptions.create', $permissions, true),
            'update'  => in_array('inscriptions.update', $permissions, true),
            'cancel'  => in_array('inscriptions.update', $permissions, true),
            'valider' => in_array('inscriptions.update', $permissions, true),
            'rejeter' => in_array('inscriptions.update', $permissions, true),
            default   => false,
        };
    }
}
