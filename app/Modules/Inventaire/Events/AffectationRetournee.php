<?php

declare(strict_types=1);

namespace App\Modules\Inventaire\Events;

use Core\Event;

class AffectationRetournee extends Event
{
    public function __construct(
        public readonly int    $affectationId,
        public readonly int    $articleId,
        public readonly int    $userId,
        public readonly float  $quantite,
        public readonly string $etat,
        public readonly int    $etablissementId,
    ) { parent::__construct(); }

    public function toArray(): array
    {
        return [
            'affectation_id'   => $this->affectationId,
            'article_id'       => $this->articleId,
            'user_id'          => $this->userId,
            'quantite'         => $this->quantite,
            'etat'             => $this->etat,
            'etablissement_id' => $this->etablissementId,
        ];
    }
}
