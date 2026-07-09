<?php

namespace App\Modules\VieScolaire\Absences\Models;

use Core\Model;

class MotifAbsenceModel extends Model
{
    protected string $table = 'vs_motifs_absence';

    public function findAllActifs(): array
    {
        return $this->db()->query(
            "SELECT * FROM {$this->table} WHERE actif = 1 ORDER BY libelle ASC"
        )->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }
}
