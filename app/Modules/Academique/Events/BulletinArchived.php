<?php

namespace App\Modules\Academique\Events;

use Core\Event;

class BulletinArchived extends Event
{
    public function __construct(
        public readonly int    $eleveId,
        public readonly int    $classeId,
        public readonly int    $periodeId,
        public readonly string $verificationToken,
        public readonly string $archivedAt,
        public readonly int    $archivedById,
    ) {
        parent::__construct();
    }

    public function getName(): string
    {
        return 'bulletin.archived';
    }

    public function toArray(): array
    {
        return [
            'eleve_id'           => $this->eleveId,
            'classe_id'          => $this->classeId,
            'periode_id'         => $this->periodeId,
            'verification_token' => $this->verificationToken,
            'archived_at'        => $this->archivedAt,
            'archived_by_id'     => $this->archivedById,
            'fired_at'           => $this->getFiredAt(),
        ];
    }
}
