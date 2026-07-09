<?php

namespace App\Modules\RH\Enseignants\Models;

use Core\Model;

class TeacherModel extends Model
{
    protected string $table = 'rh_enseignants';

    public const STATUTS = [
        'titulaire'   => 'Titulaire',
        'vacataire'   => 'Vacataire',
        'remplacant'  => 'Remplaçant',
        'stagiaire'   => 'Stagiaire',
        'contractuel' => 'Contractuel',
    ];

    public const STATUT_COLORS = [
        'titulaire'   => 'emerald',
        'vacataire'   => 'blue',
        'remplacant'  => 'amber',
        'stagiaire'   => 'purple',
        'contractuel' => 'slate',
    ];

    public function findForSelect(): array
    {
        return $this->query(
            "SELECT t.id,
                    CONCAT(e.prenom, ' ', e.nom, ' [', e.matricule, ']') AS label,
                    e.matricule
             FROM rh_enseignants t
             INNER JOIN rh_employes e ON e.id = t.employe_id
             WHERE t.deleted_at IS NULL AND e.deleted_at IS NULL AND e.statut = 'actif'
             ORDER BY e.nom, e.prenom"
        );
    }
}
