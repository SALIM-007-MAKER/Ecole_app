<?php

declare(strict_types=1);

namespace App\Modules\RH\Affectations\DTO;

readonly class AssignmentDTO
{
    public const TYPES = ['principale', 'secondaire', 'temporaire'];

    public function __construct(
        public int     $employeId,
        public ?int    $contratId,
        public ?int    $posteId,
        public ?int    $departementId,
        public ?int    $serviceId,
        public ?int    $responsableId,
        public string  $type,
        public string  $dateDebut,
        public ?string $dateFin,
        public ?string $notes,
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            employeId:     (int)($data['employe_id'] ?? 0),
            contratId:     ($data['contrat_id']     ?? '') !== '' ? (int)$data['contrat_id']     : null,
            posteId:       ($data['poste_id']       ?? '') !== '' ? (int)$data['poste_id']       : null,
            departementId: ($data['departement_id'] ?? '') !== '' ? (int)$data['departement_id'] : null,
            serviceId:     ($data['service_id']     ?? '') !== '' ? (int)$data['service_id']     : null,
            responsableId: ($data['responsable_id'] ?? '') !== '' ? (int)$data['responsable_id'] : null,
            type:          in_array($data['type'] ?? '', self::TYPES, true) ? $data['type'] : 'principale',
            dateDebut:     trim($data['date_debut'] ?? ''),
            dateFin:       ($data['date_fin'] ?? '') !== '' ? trim($data['date_fin']) : null,
            notes:         ($data['notes'] ?? '') !== '' ? trim($data['notes']) : null,
        );
    }

    public function validate(): array
    {
        $errors = [];

        if ($this->employeId <= 0) {
            $errors['employe_id'] = 'L\'employé est obligatoire.';
        }
        if (!in_array($this->type, self::TYPES, true)) {
            $errors['type'] = 'Type d\'affectation invalide.';
        }
        if ($this->dateDebut === '' || !$this->isValidDate($this->dateDebut)) {
            $errors['date_debut'] = 'La date de début est obligatoire et doit être valide (AAAA-MM-JJ).';
        }
        if ($this->dateFin !== null) {
            if (!$this->isValidDate($this->dateFin)) {
                $errors['date_fin'] = 'La date de fin doit être valide (AAAA-MM-JJ).';
            } elseif ($this->dateFin <= $this->dateDebut) {
                $errors['date_fin'] = 'La date de fin doit être postérieure à la date de début.';
            }
        }

        return $errors;
    }

    public function toArray(): array
    {
        return [
            'employe_id'     => $this->employeId,
            'contrat_id'     => $this->contratId,
            'poste_id'       => $this->posteId,
            'departement_id' => $this->departementId,
            'service_id'     => $this->serviceId,
            'responsable_id' => $this->responsableId,
            'type'           => $this->type,
            'date_debut'     => $this->dateDebut,
            'date_fin'       => $this->dateFin,
            'notes'          => $this->notes,
        ];
    }

    private function isValidDate(string $date): bool
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) return false;
        [$y, $m, $d] = explode('-', $date);
        return checkdate((int)$m, (int)$d, (int)$y);
    }
}
