<?php

namespace App\Modules\Academique\DTO;

final class AppreciationBatchDTO
{
    /** @var AppreciationDTO[] */
    public readonly array $appreciations;

    public function __construct(
        public readonly int $classeId,
        public readonly int $matiereId,
        public readonly int $periodeId,
        array $appreciations,
    ) {
        $this->appreciations = $appreciations;
    }

    public static function fromRequest(int $classeId, int $matiereId, int $periodeId, array $post): self
    {
        $appreciations = [];
        foreach ($post['appreciations'] ?? [] as $eleveId => $data) {
            $appreciations[] = AppreciationDTO::fromRequest(array_merge($data, [
                'eleve_id' => (int)$eleveId,
            ]));
        }
        return new self($classeId, $matiereId, $periodeId, $appreciations);
    }
}
