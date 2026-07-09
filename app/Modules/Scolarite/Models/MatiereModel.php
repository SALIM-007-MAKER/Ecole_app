<?php

namespace App\Modules\Scolarite\Models;

use Core\Model;

class MatiereModel extends Model
{
    protected string $table      = 'matieres';
    protected string $primaryKey = 'id';

    public const COULEURS_PRESET = [
        '#6366F1' => 'Violet',
        '#3B82F6' => 'Bleu',
        '#10B981' => 'Vert',
        '#F59E0B' => 'Ambre',
        '#EF4444' => 'Rouge',
        '#EC4899' => 'Rose',
        '#8B5CF6' => 'Pourpre',
        '#14B8A6' => 'Teal',
        '#F97316' => 'Orange',
        '#64748B' => 'Slate',
    ];

    /** Toutes les matières actives, triées par nom. */
    public function findActives(): array
    {
        return $this->query(
            "SELECT * FROM `matieres` WHERE actif = 1 ORDER BY nom"
        );
    }

    /** Pour les selects dans formulaires — retourne id + label. */
    public function findForSelect(): array
    {
        return $this->query(
            "SELECT id, CONCAT(nom, ' (coef. ', coefficient, ')') AS label
             FROM `matieres`
             WHERE actif = 1
             ORDER BY nom"
        );
    }

    /** Retourne les niveaux d'une matière sous forme de tableau. */
    public function getNiveauxArray(object $matiere): array
    {
        if (empty($matiere->niveaux)) return [];
        return array_filter(explode(',', (string)$matiere->niveaux));
    }
}
