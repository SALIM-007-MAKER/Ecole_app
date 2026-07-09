<?php

namespace App\Events;

use Core\Event;

class DocumentGenere extends Event
{
    public const TYPES = [
        'bulletin'        => 'Bulletin de notes',
        'bulletins_classe' => 'Bulletins de classe (lot)',
        'recu_paiement'   => 'Reçu de paiement',
        'rapport'         => 'Rapport analytique',
        'export_csv'      => 'Export CSV',
        'export_excel'    => 'Export Excel',
        'backup'          => 'Sauvegarde base de données',
    ];

    public function __construct(
        public readonly string $type,
        public readonly string $format,
        public readonly string $filename,
        public readonly int    $genereParId,
        public readonly int    $entityId = 0,
        public readonly int    $lignes   = 0,
        public readonly int    $taille   = 0,
    ) {
        parent::__construct();
    }

    public function toArray(): array
    {
        return [
            'type'      => $this->type,
            'format'    => $this->format,
            'filename'  => $this->filename,
            'entity_id' => $this->entityId,
            'lignes'    => $this->lignes,
            'taille'    => $this->taille,
        ];
    }
}
