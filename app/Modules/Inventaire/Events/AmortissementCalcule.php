<?php

declare(strict_types=1);

namespace App\Modules\Inventaire\Events;

use Core\Event;

class AmortissementCalcule extends Event
{
    public function __construct(
        public readonly int   $articleId,
        public readonly float $valeurNette,
        public readonly float $montantDotation,
        public readonly int   $etablissementId,
    ) { parent::__construct(); }

    public function toArray(): array
    {
        return [
            'article_id'       => $this->articleId,
            'valeur_nette'     => $this->valeurNette,
            'montant_dotation' => $this->montantDotation,
            'etablissement_id' => $this->etablissementId,
        ];
    }
}
