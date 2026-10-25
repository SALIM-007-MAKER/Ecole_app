<?php

namespace App\Modules\Academique\Models;

use Core\Model;
use Core\Tenant\TenantScope;

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

    /** Une note appartient à l'établissement de la classe de son évaluation (Lot 3). */
    protected function ownershipRestriction(): ?array
    {
        return TenantScope::restrictionEvaluation('evaluation_id');
    }

    public function findByEvaluationEtEleve(int $evaluationId, int $eleveId): ?object
    {
        return $this->queryOne(
            "SELECT * FROM {$this->table} WHERE evaluation_id = ? AND eleve_id = ?" . TenantScope::evaluation('evaluation_id')[0] . " LIMIT 1",
            [$evaluationId, $eleveId, ...TenantScope::evaluation('evaluation_id')[1]]
        ) ?: null;
    }

    public function findByEvaluation(int $evaluationId): array
    {
        return $this->query(
            "SELECT * FROM {$this->table} WHERE evaluation_id = ?" . TenantScope::evaluation('evaluation_id')[0] . " ORDER BY eleve_id",
            [$evaluationId, ...TenantScope::evaluation('evaluation_id')[1]]
        );
    }
}
