<?php

namespace App\Modules\Academique\DTO;

final class NoteFiltersDTO
{
    public function __construct(
        public readonly ?int    $evaluationId,
        public readonly ?int    $eleveId,
        public readonly string  $statut,
        public readonly string  $presence,  // 'all' | 'present' | 'absent'
        public readonly int     $page,
        public readonly int     $perPage,
    ) {}

    public static function fromRequest(array $get): self
    {
        return new self(
            evaluationId: isset($get['evaluation_id']) && $get['evaluation_id'] !== ''
                ? (int)$get['evaluation_id'] : null,
            eleveId: isset($get['eleve_id']) && $get['eleve_id'] !== ''
                ? (int)$get['eleve_id'] : null,
            statut:   in_array($get['statut'] ?? '', ['saisie','publiee','verrouillee'], true)
                ? $get['statut'] : '',
            presence: in_array($get['presence'] ?? '', ['present','absent'], true)
                ? $get['presence'] : 'all',
            page:    max(1, (int)($get['page']     ?? 1)),
            perPage: min(100, max(10, (int)($get['per_page'] ?? 50))),
        );
    }

    public function toArray(): array
    {
        return [
            'evaluation_id' => $this->evaluationId,
            'eleve_id'      => $this->eleveId,
            'statut'        => $this->statut,
            'presence'      => $this->presence,
        ];
    }
}
