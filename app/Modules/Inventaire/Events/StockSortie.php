<?php

declare(strict_types=1);

namespace App\Modules\Inventaire\Events;

use Core\Event;

class StockSortie extends Event
{
    public function __construct(
        public readonly int    $articleId,
        public readonly float  $quantite,
        public readonly int    $emplacementId,
        public readonly string $referenceType,
        public readonly int    $referenceId,
        public readonly float  $quantiteAvant,
        public readonly float  $quantiteApres,
        public readonly int    $userId,
        public readonly int    $etablissementId,
    ) { parent::__construct(); }

    public function toArray(): array
    {
        return [
            'article_id'       => $this->articleId,
            'quantite'         => $this->quantite,
            'emplacement_id'   => $this->emplacementId,
            'reference_type'   => $this->referenceType,
            'reference_id'     => $this->referenceId,
            'quantite_avant'   => $this->quantiteAvant,
            'quantite_apres'   => $this->quantiteApres,
            'user_id'          => $this->userId,
            'etablissement_id' => $this->etablissementId,
        ];
    }
}
