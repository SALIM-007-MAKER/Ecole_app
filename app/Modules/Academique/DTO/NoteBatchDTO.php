<?php

namespace App\Modules\Academique\DTO;

final class NoteBatchDTO
{
    /** @var NoteDTO[] */
    public readonly array $notes;

    public function __construct(
        public readonly int $evaluationId,
        array $notes,
    ) {
        $this->notes = $notes;
    }

    public static function fromRequest(int $evaluationId, array $post): self
    {
        $notes = [];
        foreach ($post['notes'] ?? [] as $eleveId => $data) {
            $notes[] = NoteDTO::fromRequest(array_merge($data, [
                'evaluation_id' => $evaluationId,
                'eleve_id'      => (int)$eleveId,
            ]));
        }
        return new self($evaluationId, $notes);
    }

    public static function fromCsvRows(int $evaluationId, array $rows): self
    {
        $notes = [];
        foreach ($rows as $row) {
            if (empty($row[0]) || !is_numeric($row[0])) continue;
            $notes[] = new NoteDTO(
                evaluationId: $evaluationId,
                eleveId:      (int)$row[0],
                valeur:       isset($row[1]) && $row[1] !== '' ? (float)$row[1] : null,
                estAbsent:    !empty($row[2]) && $row[2] !== '0',
                commentaire:  !empty($row[3]) ? trim($row[3]) : null,
            );
        }
        return new self($evaluationId, $notes);
    }
}
