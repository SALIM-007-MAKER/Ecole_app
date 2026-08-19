<?php

namespace App\Models;

use Core\Model;

class SalleModel extends Model
{
    protected string $table = 'salles';

    const TYPES = [
        'salle_cours'  => ['label' => 'Salle de cours',     'icon' => 'door-open'],
        'laboratoire'  => ['label' => 'Laboratoire',         'icon' => 'flask'],
        'salle_info'   => ['label' => 'Salle informatique',  'icon' => 'pc-display'],
        'gymnase'      => ['label' => 'Gymnase',             'icon' => 'dribbble'],
        'amphitheatre' => ['label' => 'Amphithéâtre',        'icon' => 'camera-video'],
        'autre'        => ['label' => 'Autre',               'icon' => 'building'],
    ];

    public function findAllActives(): array
    {
        return $this->query('SELECT * FROM salles WHERE actif = 1 ORDER BY batiment ASC, nom ASC');
    }

    public function findWithStats(string $annee): array
    {
        return $this->query(
            'SELECT s.*,
                    COUNT(edt.id)           AS nb_seances,
                    COUNT(DISTINCT edt.classe_id) AS nb_classes
             FROM salles s
             LEFT JOIN emplois_du_temps edt ON edt.salle_id = s.id
                   AND edt.annee_scolaire = ? AND edt.actif = 1
             GROUP BY s.id
             ORDER BY s.batiment ASC, s.nom ASC',
            [$annee]
        );
    }

    public function isAvailable(int $salleId, int $creneauId, int $jour, string $annee, ?int $exceptId = null): bool
    {
        $sql    = 'SELECT id FROM emplois_du_temps WHERE salle_id = ? AND creneau_id = ? AND jour_semaine = ? AND annee_scolaire = ? AND actif = 1';
        $params = [$salleId, $creneauId, $jour, $annee];
        if ($exceptId) {
            $sql    .= ' AND id != ?';
            $params[] = $exceptId;
        }
        return $this->queryOne($sql, $params) === false;
    }
}
