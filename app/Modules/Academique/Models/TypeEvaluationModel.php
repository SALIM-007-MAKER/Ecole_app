<?php

namespace App\Modules\Academique\Models;

use Core\Model;
use Core\Tenant\TenantScope;

class TypeEvaluationModel extends Model
{
    protected string $table = 'types_evaluations';

    public const V1_CODES = ['controle', 'devoir', 'examen', 'tp', 'oral'];

    public const ICONES_SUGGERES = [
        'file-text', 'pencil', 'graduation-cap', 'flask-conical',
        'mic', 'presentation', 'book-open', 'calculator', 'clock',
        'star', 'trophy', 'award', 'clipboard-list', 'pen-tool',
    ];

    /** Propre à l'établissement (colonne etablissement_id, T052) ; partagé tant que la migration n'est pas appliquée. */
    protected function ownershipRestriction(): ?array
    {
        return TenantScope::restrictionReferentiel('types_evaluations');
    }

    public function insert(array $data): int
    {
        if (!array_key_exists('etablissement_id', $data) && ($e = TenantScope::valeurInsertion('types_evaluations')) !== null) {
            $data['etablissement_id'] = $e;
        }
        return parent::insert($data);
    }

    public function findActifs(): array
    {
        return $this->query(
            "SELECT id, code, nom, coefficient_defaut, note_max_defaut,
                    est_eliminatoire, seuil_eliminatoire, couleur, icone, ordre
             FROM `types_evaluations`
             WHERE actif = 1 AND est_archive = 0" . TenantScope::referentiel('types_evaluations', 'etablissement_id')[0] . "
             ORDER BY ordre ASC, nom ASC",
            TenantScope::referentiel('types_evaluations', 'etablissement_id')[1]
        );
    }

    public function findByCode(string $code): ?\stdClass
    {
        return $this->queryOne(
            "SELECT * FROM `types_evaluations` WHERE code = ?" . TenantScope::referentiel('types_evaluations', 'etablissement_id')[0] . " LIMIT 1",
            [$code, ...TenantScope::referentiel('types_evaluations', 'etablissement_id')[1]]
        ) ?: null;
    }

    public function findForSelect(): array
    {
        return $this->query(
            "SELECT id, code, nom, coefficient_defaut, note_max_defaut, couleur, icone
             FROM `types_evaluations`
             WHERE actif = 1 AND est_archive = 0" . TenantScope::referentiel('types_evaluations', 'etablissement_id')[0] . "
             ORDER BY ordre ASC, nom ASC",
            TenantScope::referentiel('types_evaluations', 'etablissement_id')[1]
        );
    }
}
