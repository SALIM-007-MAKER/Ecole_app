<?php

namespace App\Modules\VieScolaire\EmploisDuTemps\Models;

use Core\Model;

class EmploiDuTempsModel extends Model
{
    protected string $table = 'vs_emplois_du_temps';

    public function softDelete(int $id): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE vs_emplois_du_temps SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL'
        );
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }
}
