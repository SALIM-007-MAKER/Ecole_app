<?php

namespace App\Modules\VieScolaire\Absences\Models;

use Core\Model;
use Core\Tenant\ReferentielPropre;

class MotifAbsenceModel extends Model
{
    use ReferentielPropre;   // référentiel propre à l'établissement (Lot 3)

    protected string $table = 'vs_motifs_absence';

    public function findAllActifs(): array
    {
        [$ts, $tp] = \Core\Tenant\TenantScope::referentiel($this->table, 'etablissement_id');
        $stmt = $this->db()->prepare("SELECT * FROM {$this->table} WHERE actif = 1" . $ts . " ORDER BY libelle ASC");
        $stmt->execute($tp);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }
}
