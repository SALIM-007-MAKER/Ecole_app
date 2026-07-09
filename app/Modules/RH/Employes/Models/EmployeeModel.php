<?php

namespace App\Modules\RH\Employes\Models;

use Core\Model;

class EmployeeModel extends Model
{
    protected string $table = 'rh_employes';

    public const TYPES = [
        'enseignant'    => 'Enseignant',
        'administratif' => 'Administratif',
        'support'       => 'Support',
        'direction'     => 'Direction',
        'technique'     => 'Technique',
    ];

    public const STATUTS = [
        'actif'         => 'Actif',
        'inactif'       => 'Inactif',
        'suspendu'      => 'Suspendu',
        'conge'         => 'En congé',
        'retraite'      => 'Retraité',
        'demissionnaire'=> 'Démissionnaire',
    ];

    public const STATUT_COLORS = [
        'actif'         => 'green',
        'inactif'       => 'slate',
        'suspendu'      => 'orange',
        'conge'         => 'blue',
        'retraite'      => 'purple',
        'demissionnaire'=> 'red',
    ];

    public function findForSelect(): array
    {
        return $this->query(
            "SELECT id, CONCAT(prenom, ' ', nom, ' [', matricule, ']') AS label
             FROM rh_employes
             WHERE deleted_at IS NULL AND statut = 'actif'
             ORDER BY nom, prenom"
        );
    }
}
