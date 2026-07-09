<?php

namespace App\Modules\VieScolaire\Retards\Models;

use Core\Model;

class RetardModel extends Model
{
    protected string $table = 'vs_retards';

    public function softDelete(int $id): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE {$this->table} SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL"
        );
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }
}
