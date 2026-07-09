<?php

namespace App\Modules\VieScolaire\EmploisDuTemps\Models;

use Core\Model;

class CreneauModel extends Model
{
    protected string $table = 'vs_edt_creneaux';

    public function softDelete(int $id): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE vs_edt_creneaux SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL'
        );
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }
}
