<?php

namespace App\Modules\Scolarite\DTO;

class InscriptionDTO
{
    public function __construct(
        public readonly int    $eleveId,
        public readonly string $anneeScolaire,
        public readonly ?int   $classeId  = null,
        public readonly string $notes     = '',
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            eleveId:       (int)($data['eleve_id']       ?? 0),
            anneeScolaire: trim($data['annee_scolaire']  ?? ''),
            classeId:      !empty($data['classe_id']) ? (int)$data['classe_id'] : null,
            notes:         trim($data['notes']            ?? ''),
        );
    }

    public function toArray(): array
    {
        return [
            'eleve_id'       => $this->eleveId,
            'annee_scolaire' => $this->anneeScolaire,
            'classe_id'      => $this->classeId,
            'notes'          => $this->notes,
        ];
    }

    public function validate(): array
    {
        $errors = [];

        if ($this->eleveId <= 0) {
            $errors['eleve_id'][] = "L'élève est obligatoire.";
        }

        if ($this->anneeScolaire === '') {
            $errors['annee_scolaire'][] = "L'année scolaire est obligatoire.";
        } elseif (!preg_match('/^\d{4}-\d{4}$/', $this->anneeScolaire)) {
            $errors['annee_scolaire'][] = "Format attendu : 2025-2026.";
        } else {
            [$from, $to] = explode('-', $this->anneeScolaire);
            if ((int)$to !== (int)$from + 1) {
                $errors['annee_scolaire'][] = "L'année de fin doit être l'année de début + 1.";
            }
        }

        return $errors;
    }
}
