<?php

namespace App\Modules\Academique\Events;

use Core\Event;

class EvaluationPublished extends Event
{
    public function __construct(
        public readonly int    $evaluationId,
        public readonly string $libelle,
        public readonly int    $classeId,
        public readonly int    $matiereId,
        public readonly int    $publishedById,
    ) {
        parent::__construct();
    }

    public function getName(): string
    {
        return 'evaluation.published';
    }

    public function toArray(): array
    {
        return [
            'evaluation_id'  => $this->evaluationId,
            'libelle'        => $this->libelle,
            'classe_id'      => $this->classeId,
            'matiere_id'     => $this->matiereId,
            'published_by_id'=> $this->publishedById,
            'fired_at'       => $this->getFiredAt(),
        ];
    }
}
