<?php

namespace App\Modules\Academique\Models;

use Core\Model;

class NoteModel extends Model
{
    protected string $table = 'notes_v2';

    const STATUTS = ['saisie', 'publiee', 'verrouillee'];

    const STATUT_LABELS = [
        'saisie'      => 'Saisie',
        'publiee'     => 'Publiée',
        'verrouillee' => 'Verrouillée',
    ];

    const STATUT_COLORS = [
        'saisie'      => 'blue',
        'publiee'     => 'emerald',
        'verrouillee' => 'red',
    ];

    const STATUT_ICONS = [
        'saisie'      => 'edit-3',
        'publiee'     => 'check-circle',
        'verrouillee' => 'lock',
    ];

    public function findByEvaluationEtEleve(int $evaluationId, int $eleveId): ?object
    {
        return $this->queryOne(
            "SELECT * FROM {$this->table} WHERE evaluation_id = ? AND eleve_id = ? LIMIT 1",
            [$evaluationId, $eleveId]
        );
    }

    public function findByEvaluation(int $evaluationId): array
    {
        return $this->query(
            "SELECT * FROM {$this->table} WHERE evaluation_id = ? ORDER BY eleve_id",
            [$evaluationId]
        );
    }
}
