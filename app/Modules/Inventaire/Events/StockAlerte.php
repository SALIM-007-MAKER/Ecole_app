<?php

declare(strict_types=1);

namespace App\Modules\Inventaire\Events;

use Core\Event;

class StockAlerte extends Event
{
    public function __construct(
        public readonly int    $articleId,
        public readonly string $designation,
        public readonly string $type,
        public readonly float  $valeurActuelle,
        public readonly float  $seuil,
        public readonly int    $etablissementId,
    ) { parent::__construct(); }

    public function toArray(): array
    {
        return [
            'article_id'       => $this->articleId,
            'designation'      => $this->designation,
            'type'             => $this->type,
            'valeur_actuelle'  => $this->valeurActuelle,
            'seuil'            => $this->seuil,
            'etablissement_id' => $this->etablissementId,
        ];
    }
}
