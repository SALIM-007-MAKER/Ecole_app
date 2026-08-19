<?php

namespace App\Modules\Academique\Events;

use Core\Event;

class BulletinAppreciationUpdated extends Event
{
    public function __construct(
        public readonly int    $eleveId,
        public readonly int    $periodeId,
        public readonly string $verificationToken,
        public readonly int    $updatedById,
    ) {
        parent::__construct();
    }

    public function getName(): string
    {
        return 'bulletin.appreciation_directeur_updated';
    }

    public function toArray(): array
    {
        return [
            'eleve_id'           => $this->eleveId,
            'periode_id'         => $this->periodeId,
            'verification_token' => $this->verificationToken,
            'updated_by_id'      => $this->updatedById,
            'fired_at'           => $this->getFiredAt(),
        ];
    }
}
