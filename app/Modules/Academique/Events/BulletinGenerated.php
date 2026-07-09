<?php

namespace App\Modules\Academique\Events;

use Core\Event;

class BulletinGenerated extends Event
{
    public function __construct(
        public readonly int    $eleveId,
        public readonly int    $classeId,
        public readonly int    $periodeId,
        public readonly string $verificationToken,
        public readonly float  $moyenne,
        public readonly string $mentionCode,
        public readonly int    $rang,
        public readonly int    $nbEleves,
        public readonly string $decision,
        public readonly string $statut,
        public readonly int    $generatedById,
    ) {
        parent::__construct();
    }

    public function getName(): string
    {
        return 'bulletin.generated';
    }

    public function toArray(): array
    {
        return [
            'eleve_id'           => $this->eleveId,
            'classe_id'          => $this->classeId,
            'periode_id'         => $this->periodeId,
            'verification_token' => $this->verificationToken,
            'moyenne'            => $this->moyenne,
            'mention_code'       => $this->mentionCode,
            'rang'               => $this->rang,
            'nb_eleves'          => $this->nbEleves,
            'decision'           => $this->decision,
            'statut'             => $this->statut,
            'generated_by_id'    => $this->generatedById,
            'fired_at'           => $this->getFiredAt(),
        ];
    }
}
