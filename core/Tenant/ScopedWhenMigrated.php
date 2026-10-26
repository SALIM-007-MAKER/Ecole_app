<?php

declare(strict_types=1);

namespace Core\Tenant;

/**
 * Modèle de CATALOGUE dont la table reçoit une colonne etablissement_id par migration (ex. T057) : tant que la colonne
 * n'existe pas (base non migrée) le modèle reste non filtré (comportement historique) ; dès qu'elle existe et qu'un contexte
 * d'établissement est positionné, lectures / écritures / insertions sont bornées à l'établissement courant (Core\Model::tenantId()).
 */
trait ScopedWhenMigrated
{
    protected function tenantId(): ?int
    {
        $t = TenantContext::current();
        return ($t !== null && TenantScope::colonneEtablissement($this->table)) ? $t : null;
    }
}