<?php

declare(strict_types=1);

namespace App\Modules\Communication\DTO;

class ThreadMessageDTO
{
    public function __construct(
        public readonly int    $threadId,
        public readonly string $corps,
        public readonly string $type        = 'texte',
        public readonly array  $documentIds = [],
    ) {}

    public static function fromRequest(array $data): self
    {
        $docIds = $data['document_ids'] ?? [];
        if (!is_array($docIds)) {
            $docIds = [];
        }
        return new self(
            threadId:    (int) ($data['thread_id'] ?? 0),
            corps:       trim($data['corps']       ?? ''),
            type:        $data['type']             ?? 'texte',
            documentIds: array_map('intval', $docIds),
        );
    }

    public function validate(): array
    {
        $errors = [];
        if ($this->threadId <= 0) {
            $errors[] = 'Thread invalide.';
        }
        if (empty($this->corps)) {
            $errors[] = 'Le message ne peut pas être vide.';
        }
        return $errors;
    }
}
