<?php

declare(strict_types=1);

namespace App\Modules\Inventaire\Events;

use Core\Event;

class StockAjustement extends Event
{
    public function __construct(
        public readonly int    $articleId,
        public readonly float  $quantiteAvant,
        public readonly float  $quantiteApres,
        public readonly string $motif,
        public readonly int    $userId,
        public readonly int    $etablissementId,
    ) { parent::__construct(); }

    public function toArray(): array
    {
        return [
            'article_id'       => $this->articleId,
            'quantite_avant'   => $this->quantiteAvant,
            'quantite_apres'   => $this->quantiteApres,
            'motif'            => $this->motif,
            'user_id'          => $this->userId,
            'etablissement_id' => $this->etablissementId,
        ];
    }
}
