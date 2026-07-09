<?php

declare(strict_types=1);

namespace App\Modules\Inventaire\Events;

use Core\Event;

class AffectationCreee extends Event
{
    public function __construct(
        public readonly int   $affectationId,
        public readonly int   $articleId,
        public readonly int   $userId,
        public readonly float $quantite,
        public readonly int   $etablissementId,
    ) { parent::__construct(); }

    public function toArray(): array
    {
        return [
            'affectation_id'   => $this->affectationId,
            'article_id'       => $this->articleId,
            'user_id'          => $this->userId,
            'quantite'         => $this->quantite,
            'etablissement_id' => $this->etablissementId,
        ];
    }
}
