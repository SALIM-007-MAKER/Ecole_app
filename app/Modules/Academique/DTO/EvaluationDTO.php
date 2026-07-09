<?php

namespace App\Modules\Academique\DTO;

class EvaluationDTO
{
    public function __construct(
        public readonly int     $periodeScolaireId,
        public readonly int     $typeEvaluationId,
        public readonly int     $matiereId,
        public readonly int     $classeId,
        public readonly ?int    $enseignantId,
        public readonly string  $libelle,
        public readonly string  $description,
        public readonly ?string $dateEvaluation,
        public readonly float   $coefficient,
        public readonly float   $noteMax,
    ) {}

    public static function fromRequest(array $data): self
    {
        $enseignantId  = !empty($data['enseignant_id']) ? (int)$data['enseignant_id'] : null;
        $dateEvaluation = trim($data['date_evaluation'] ?? '') ?: null;

        return new self(
            periodeScolaireId: (int)($data['periode_scolaire_id'] ?? 0),
            typeEvaluationId:  (int)($data['type_evaluation_id']  ?? 0),
            matiereId:         (int)($data['matiere_id']           ?? 0),
            classeId:          (int)($data['classe_id']            ?? 0),
            enseignantId:      $enseignantId,
            libelle:           trim($data['libelle'] ?? ''),
            description:       trim($data['description'] ?? ''),
            dateEvaluation:    $dateEvaluation,
            coefficient:       (float)($data['coefficient'] ?? 1.00),
            noteMax:           (float)($data['note_max']    ?? 20.00),
        );
    }

    public function toArray(): array
    {
        return [
            'periode_scolaire_id' => $this->periodeScolaireId,
            'type_evaluation_id'  => $this->typeEvaluationId,
            'matiere_id'          => $this->matiereId,
            'classe_id'           => $this->classeId,
            'enseignant_id'       => $this->enseignantId,
            'libelle'             => $this->libelle,
            'description'         => $this->description ?: null,
            'date_evaluation'     => $this->dateEvaluation,
            'coefficient'         => $this->coefficient,
            'note_max'            => $this->noteMax,
        ];
    }

    public function validate(): array
    {
        $errors = [];

        if ($this->periodeScolaireId <= 0) {
            $errors['periode_scolaire_id'][] = 'La période scolaire est obligatoire.';
        }
        if ($this->typeEvaluationId <= 0) {
            $errors['type_evaluation_id'][] = "Le type d'évaluation est obligatoire.";
        }
        if ($this->matiereId <= 0) {
            $errors['matiere_id'][] = 'La matière est obligatoire.';
        }
        if ($this->classeId <= 0) {
            $errors['classe_id'][] = 'La classe est obligatoire.';
        }

        if ($this->libelle === '') {
            $errors['libelle'][] = "L'intitulé est obligatoire.";
        } elseif (mb_strlen($this->libelle) > 120) {
            $errors['libelle'][] = "L'intitulé ne peut pas dépasser 120 caractères.";
        }

        if (mb_strlen($this->description) > 1000) {
            $errors['description'][] = 'La description ne peut pas dépasser 1000 caractères.';
        }

        if ($this->dateEvaluation !== null && !$this->isValidDate($this->dateEvaluation)) {
            $errors['date_evaluation'][] = 'Date invalide (format attendu : YYYY-MM-DD).';
        }

        if ($this->coefficient < 0.25 || $this->coefficient > 10.00) {
            $errors['coefficient'][] = 'Le coefficient doit être compris entre 0,25 et 10,00.';
        }

        if ($this->noteMax < 5.00 || $this->noteMax > 100.00) {
            $errors['note_max'][] = 'La note maximale doit être comprise entre 5 et 100.';
        }

        return $errors;
    }

    private function isValidDate(string $date): bool
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) return false;
        [$y, $m, $d] = explode('-', $date);
        return checkdate((int)$m, (int)$d, (int)$y);
    }
}
