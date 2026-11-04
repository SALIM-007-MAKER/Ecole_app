<?php

declare(strict_types=1);

namespace Core\Tenant;

/**
 * Modèle d'un référentiel PROPRE à l'établissement (salles, plages horaires, motifs, catégories… — colonne etablissement_id).
 * Lecture / modification / suppression limitées à l'établissement courant ; l'insertion renseigne l'établissement courant.
 * Tant que la colonne est absente (base non migrée) ou sans contexte, le référentiel reste partagé (comportement historique).
 * À utiliser dans une sous-classe de Core\Model.
 */
trait ReferentielPropre
{
    protected function ownershipRestriction(): ?array
    {
        return TenantScope::restrictionReferentiel($this->table);
    }

    public function insert(array $data): int
    {
        if (!array_key_exists('etablissement_id', $data) && ($e = TenantScope::valeurInsertion($this->table)) !== null) {
            $data['etablissement_id'] = $e;
        }
        return parent::insert($data);
    }
}
