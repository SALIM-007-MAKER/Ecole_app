<?php

namespace App\Modules\VieScolaire\Presences\Models;

use Core\Model;

class AppelModel extends Model
{
    protected string $table = 'vs_appels';

    public function softDelete(int $id): bool
    {
        $stmt = $this->db()->prepare(
            "UPDATE {$this->table} SET deleted_at = NOW()
             WHERE id = :id AND deleted_at IS NULL AND statut = 'brouillon'"
        );
        return $stmt->execute([':id' => $id]);
    }
}
