<?php

declare(strict_types=1);

namespace App\Modules\Inventaire\Events;

use Core\Event;

class StockTransfert extends Event
{
    public function __construct(
        public readonly int   $articleId,
        public readonly float $quantite,
        public readonly int   $sourceId,
        public readonly int   $destinationId,
        public readonly int   $userId,
        public readonly int   $etablissementId,
    ) { parent::__construct(); }

    public function toArray(): array
    {
        return [
            'article_id'       => $this->articleId,
            'quantite'         => $this->quantite,
            'source_id'        => $this->sourceId,
            'destination_id'   => $this->destinationId,
            'user_id'          => $this->userId,
            'etablissement_id' => $this->etablissementId,
        ];
    }
}
