<?php

namespace App\Modules\VieScolaire\Absences\Models;

use Core\Model;

class AbsenceModel extends Model
{
    protected string $table = 'vs_absences';

    public function softDelete(int $id): bool
    {
        $stmt = $this->db()->prepare(
            "UPDATE {$this->table} SET deleted_at = NOW() WHERE id = :id AND deleted_at IS NULL"
        );
        return $stmt->execute([':id' => $id]);
    }
}
