<?php

namespace App\Modules\Scolarite\Policies;

use Core\Database;

class ClassePolicy
{
    /**
     * @param array       $user
     * @param string      $action   'view' | 'create' | 'update' | 'delete' | 'view.own'
     * @param object|null $resource Classe concernée
     */
    public function authorize(array $user, string $action, ?object $resource = null): bool
    {
        $permissions = $user['permissions'] ?? [];

        return match ($action) {
            'view'   => in_array('classes.view', $permissions, true),
            'create' => in_array('classes.create', $permissions, true),
            'update' => in_array('classes.update', $permissions, true),
            'delete' => in_array('classes.delete', $permissions, true),

            'view.own' => in_array('classes.view.own', $permissions, true)
                       && $resource !== null
                       && $this->isEnseignantDe($user, $resource),

            default => false,
        };
    }

    private function isEnseignantDe(array $user, object $classe): bool
    {
        $professeurId = $user['professeur_id'] ?? null;
        if (!$professeurId) {
            return false;
        }

        $pdo  = Database::getInstance()->getConnection();
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM `enseignements`
             WHERE professeur_id = ? AND classe_id = ?"
        );
        $stmt->execute([$professeurId, $classe->id]);
        return (int)$stmt->fetchColumn() > 0;
    }
}
