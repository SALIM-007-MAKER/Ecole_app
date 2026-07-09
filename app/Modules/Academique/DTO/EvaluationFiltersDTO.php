<?php

namespace App\Modules\Academique\DTO;

class EvaluationFiltersDTO
{
    public function __construct(
        public readonly string $search,
        public readonly string $statut,
        public readonly int    $periodeScolaireId,
        public readonly int    $classeId,
        public readonly int    $matiereId,
        public readonly int    $typeEvaluationId,
        public readonly int    $page,
        public readonly int    $perPage,
    ) {}

    public static function fromRequest(array $data): self
    {
        $perPage = (int)($data['per_page'] ?? 20);
        if (!in_array($perPage, [10, 20, 50], true)) {
            $perPage = 20;
        }

        return new self(
            search:            trim($data['search']              ?? ''),
            statut:            trim($data['statut']              ?? ''),
            periodeScolaireId: (int)($data['periode_scolaire_id'] ?? 0),
            classeId:          (int)($data['classe_id']            ?? 0),
            matiereId:         (int)($data['matiere_id']           ?? 0),
            typeEvaluationId:  (int)($data['type_evaluation_id']   ?? 0),
            page:              max(1, (int)($data['page'] ?? 1)),
            perPage:           $perPage,
        );
    }

    public function toArray(): array
    {
        return [
            'search'              => $this->search,
            'statut'              => $this->statut,
            'periode_scolaire_id' => $this->periodeScolaireId,
            'classe_id'           => $this->classeId,
            'matiere_id'          => $this->matiereId,
            'type_evaluation_id'  => $this->typeEvaluationId,
        ];
    }
}
