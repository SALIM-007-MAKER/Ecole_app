<?php
declare(strict_types=1);

namespace App\Modules\Rapports\Events;

use Core\Event;

class RapportGenere extends Event
{
    public function __construct(
        public readonly string $domaine,
        public readonly string $typeExport,
        public readonly int    $userId,
        public readonly int    $nbLignes,
        public readonly int    $etablissementId,
    ) {
        parent::__construct();
    }

    public function toArray(): array
    {
        return [
            'domaine'          => $this->domaine,
            'type_export'      => $this->typeExport,
            'user_id'          => $this->userId,
            'nb_lignes'        => $this->nbLignes,
            'etablissement_id' => $this->etablissementId,
        ];
    }
}
