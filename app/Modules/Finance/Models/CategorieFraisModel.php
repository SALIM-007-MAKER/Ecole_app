<?php

namespace App\Modules\Finance\Models;

use Core\Model;

class CategorieFraisModel extends Model
{
    protected string $table      = 'finance_categories_frais';
    protected string $primaryKey = 'id';

    public function findActives(): array
    {
        return $this->query(
            "SELECT * FROM `finance_categories_frais` WHERE actif = 1 ORDER BY nom"
        );
    }

    public function findForSelect(): array
    {
        return $this->query(
            "SELECT id, nom, code, couleur, icone
             FROM `finance_categories_frais`
             WHERE actif = 1
             ORDER BY nom"
        );
    }

    public function codeExists(string $code, int $excludeId = 0): bool
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM `finance_categories_frais` WHERE code = ? AND id != ?"
        );
        $stmt->execute([$code, $excludeId]);
        return (int)$stmt->fetchColumn() > 0;
    }
}
