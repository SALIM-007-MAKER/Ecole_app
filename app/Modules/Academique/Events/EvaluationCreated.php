<?php

namespace App\Modules\Academique\Events;

use Core\Event;

class EvaluationCreated extends Event
{
    public function __construct(
        public readonly int    $evaluationId,
        public readonly string $libelle,
        public readonly int    $periodeScolaireId,
        public readonly int    $classeId,
        public readonly int    $matiereId,
        public readonly int    $createdById,
    ) {
        parent::__construct();
    }

    public function getName(): string
    {
        return 'evaluation.created';
    }

    public function toArray(): array
    {
        return [
            'evaluation_id'       => $this->evaluationId,
            'libelle'             => $this->libelle,
            'periode_scolaire_id' => $this->periodeScolaireId,
            'classe_id'           => $this->classeId,
            'matiere_id'          => $this->matiereId,
            'created_by_id'       => $this->createdById,
            'fired_at'            => $this->getFiredAt(),
        ];
    }
}
