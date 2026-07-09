<?php

declare(strict_types=1);

namespace App\Modules\Documents\Events;

use Core\Event;

class DocumentShared extends Event
{
    public function __construct(
        public readonly int     $documentId,
        public readonly int     $partageId,
        public readonly string  $destinataireType,
        public readonly ?int    $destinataireId,
        public readonly string  $permission,
        public readonly ?string $token,
        public readonly int     $sharedById,
    ) { parent::__construct(); }

    public function toArray(): array
    {
        return [
            'document_id'        => $this->documentId,
            'partage_id'         => $this->partageId,
            'destinataire_type'  => $this->destinataireType,
            'destinataire_id'    => $this->destinataireId,
            'permission'         => $this->permission,
            'shared_by'          => $this->sharedById,
        ];
    }
}
