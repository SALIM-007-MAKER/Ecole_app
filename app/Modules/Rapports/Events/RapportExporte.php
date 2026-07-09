<?php
declare(strict_types=1);

namespace App\Modules\Rapports\Events;

use Core\Event;

class RapportExporte extends Event
{
    public function __construct(
        public readonly int    $exportId,
        public readonly string $domaine,
        public readonly string $typeExport,
        public readonly string $fichierNom,
        public readonly int    $userId,
        public readonly int    $etablissementId,
    ) {
        parent::__construct();
    }

    public function toArray(): array
    {
        return [
            'export_id'        => $this->exportId,
            'domaine'          => $this->domaine,
            'type_export'      => $this->typeExport,
            'fichier_nom'      => $this->fichierNom,
            'user_id'          => $this->userId,
            'etablissement_id' => $this->etablissementId,
        ];
    }
}
