<?php

namespace App\Modules\Academique\Events;

use Core\Event;

class RankingGenerated extends Event
{
    public function __construct(
        public readonly string $type,         // 'classe' | 'niveau' | 'matiere' | 'general'
        public readonly int    $periodeId,
        public readonly ?int   $classeId,
        public readonly ?int   $matiereId,
        public readonly int    $nbEleves,
        public readonly float  $moyenneClasse,
        public readonly float  $tauxReussite,
        public readonly int    $generatedById,
    ) {
        parent::__construct();
    }

    public function getName(): string
    {
        return 'ranking.generated';
    }

    public function toArray(): array
    {
        return [
            'type'            => $this->type,
            'periode_id'      => $this->periodeId,
            'classe_id'       => $this->classeId,
            'matiere_id'      => $this->matiereId,
            'nb_eleves'       => $this->nbEleves,
            'moyenne_classe'  => $this->moyenneClasse,
            'taux_reussite'   => $this->tauxReussite,
            'generated_by_id' => $this->generatedById,
            'fired_at'        => $this->getFiredAt(),
        ];
    }
}
