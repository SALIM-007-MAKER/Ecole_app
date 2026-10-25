<?php

namespace App\Modules\Academique\Events;

use Core\Event;

/**
 * Régénération EXPLICITE d'un brouillon de bulletin en vue de sa publication (Conduite complète exigée).
 * Ne publie jamais. Le jeton complet n'est jamais journalisé : le gestionnaire d'audit n'en garde que l'empreinte.
 */
class BulletinRegenerated extends Event
{
    public function __construct(
        public readonly int    $eleveId,
        public readonly int    $classeId,
        public readonly int    $periodeId,
        public readonly string $verificationToken,
        public readonly int    $regeneratedById,
    ) {
        parent::__construct();
    }

    public function getName(): string
    {
        return 'bulletin.regenerated';
    }

    public function toArray(): array
    {
        return [
            'eleve_id'           => $this->eleveId,
            'classe_id'          => $this->classeId,
            'periode_id'         => $this->periodeId,
            'verification_token' => $this->verificationToken,
            'regenerated_by_id'  => $this->regeneratedById,
            'fired_at'           => $this->getFiredAt(),
        ];
    }
}
