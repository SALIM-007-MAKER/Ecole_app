<?php

namespace App\Models;

use Core\Model;

class FraisTypeModel extends Model
{
    protected string $table = 'frais_types';

    public const PERIODICITES = [
        'unique'      => 'Paiement unique',
        'mensuel'     => 'Mensuel',
        'trimestriel' => 'Trimestriel',
        'annuel'      => 'Annuel',
    ];

    public function findAllActifs(): array
    {
        return $this->query("SELECT * FROM `frais_types` WHERE actif = 1 ORDER BY nom");
    }

    public function findWithStats(): array
    {
        return $this->query(
            "SELECT ft.*,
                COUNT(DISTINCT fe.id)                   AS nb_affectations,
                COALESCE(SUM(fe.montant),0)             AS total_attendu,
                COALESCE(SUM(p_sum.paye),0)             AS total_encaisse
             FROM `frais_types` ft
             LEFT JOIN `frais_eleves` fe ON fe.frais_type_id = ft.id
             LEFT JOIN (
                SELECT frais_eleve_id, SUM(montant) AS paye
                FROM paiements GROUP BY frais_eleve_id
             ) p_sum ON p_sum.frais_eleve_id = fe.id
             GROUP BY ft.id
             ORDER BY ft.actif DESC, ft.nom"
        );
    }
}
