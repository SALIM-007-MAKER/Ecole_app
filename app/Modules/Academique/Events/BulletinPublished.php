<?php

namespace App\Modules\Academique\Events;

use Core\Event;

class BulletinPublished extends Event
{
    public function __construct(
        public readonly int    $eleveId,
        public readonly int    $classeId,
        public readonly int    $periodeId,
        public readonly string $verificationToken,
        public readonly string $publishedAt,
        public readonly int    $publishedById,
    ) {
        parent::__construct();
    }

    public function getName(): string
    {
        return 'bulletin.published';
    }

    public function toArray(): array
    {
        return [
            'eleve_id'           => $this->eleveId,
            'classe_id'          => $this->classeId,
            'periode_id'         => $this->periodeId,
            'verification_token' => $this->verificationToken,
            'published_at'       => $this->publishedAt,
            'published_by_id'    => $this->publishedById,
            'fired_at'           => $this->getFiredAt(),
        ];
    }
}
