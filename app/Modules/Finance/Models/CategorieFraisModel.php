<?php

namespace App\Modules\Finance\Models;

use Core\Model;
use Core\Tenant\ScopedWhenMigrated;
use Core\Tenant\TenantScope;

class CategorieFraisModel extends Model
{
    use ScopedWhenMigrated;

    protected string $table      = 'finance_categories_frais';
    protected string $primaryKey = 'id';

    public function findActives(): array
    {
        return $this->query(
            "SELECT * FROM `finance_categories_frais` WHERE actif = 1 AND " . TenantScope::sqlEtab('finance_categories_frais', 'etablissement_id') . " ORDER BY nom"
        );
    }

    public function findForSelect(): array
    {
        return $this->query(
            "SELECT id, nom, code, couleur, icone
             FROM `finance_categories_frais`
             WHERE actif = 1 AND " . TenantScope::sqlEtab('finance_categories_frais', 'etablissement_id') . "
             ORDER BY nom"
        );
    }

    public function codeExists(string $code, int $excludeId = 0): bool
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM `finance_categories_frais` WHERE code = ? AND id != ? AND " . TenantScope::sqlEtab('finance_categories_frais', 'etablissement_id')
        );
        $stmt->execute([$code, $excludeId]);
        return (int)$stmt->fetchColumn() > 0;
    }
}
