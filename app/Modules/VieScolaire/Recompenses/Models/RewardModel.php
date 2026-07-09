<?php

namespace App\Modules\VieScolaire\Recompenses\Models;

use Core\Model;

class RewardModel extends Model
{
    protected string $table = 'vs_recompenses';

    public function softDelete(int $id): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE vs_recompenses SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL'
        );
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }
}
