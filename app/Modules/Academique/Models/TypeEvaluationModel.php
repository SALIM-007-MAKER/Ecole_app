<?php

namespace App\Modules\Academique\Models;

use Core\Model;

class TypeEvaluationModel extends Model
{
    protected string $table = 'types_evaluations';

    public const V1_CODES = ['controle', 'devoir', 'examen', 'tp', 'oral'];

    public const ICONES_SUGGERES = [
        'file-text', 'pencil', 'graduation-cap', 'flask-conical',
        'mic', 'presentation', 'book-open', 'calculator', 'clock',
        'star', 'trophy', 'award', 'clipboard-list', 'pen-tool',
    ];

    public function findActifs(): array
    {
        return $this->query(
            "SELECT id, code, nom, coefficient_defaut, note_max_defaut,
                    est_eliminatoire, seuil_eliminatoire, couleur, icone, ordre
             FROM `types_evaluations`
             WHERE actif = 1 AND est_archive = 0
             ORDER BY ordre ASC, nom ASC"
        );
    }

    public function findByCode(string $code): ?\stdClass
    {
        return $this->queryOne(
            "SELECT * FROM `types_evaluations` WHERE code = ? LIMIT 1",
            [$code]
        ) ?: null;
    }

    public function findForSelect(): array
    {
        return $this->query(
            "SELECT id, code, nom, coefficient_defaut, note_max_defaut, couleur, icone
             FROM `types_evaluations`
             WHERE actif = 1 AND est_archive = 0
             ORDER BY ordre ASC, nom ASC"
        );
    }
}
