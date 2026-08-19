<?php

namespace App\Modules\Academique\DTO;

class PeriodeConfigDTO
{
    public function __construct(
        public readonly int    $numero,
        public readonly string $nomDefaut,
        public readonly int    $moisDebut,
        public readonly int    $jourDebut,
        public readonly int    $moisFin,
        public readonly int    $jourFin,
        public readonly int    $ordre,
    ) {}

    public static function fromRequest(int $numero, array $data): self
    {
        return new self(
            numero:    $numero,
            nomDefaut: trim($data['nom_defaut'] ?? ''),
            moisDebut: (int)($data['mois_debut'] ?? 0),
            jourDebut: (int)($data['jour_debut'] ?? 0),
            moisFin:   (int)($data['mois_fin']   ?? 0),
            jourFin:   (int)($data['jour_fin']   ?? 0),
            ordre:     (int)($data['ordre']      ?? $numero),
        );
    }

    public function toArray(): array
    {
        return [
            'nom_defaut' => $this->nomDefaut,
            'mois_debut' => $this->moisDebut,
            'jour_debut' => $this->jourDebut,
            'mois_fin'   => $this->moisFin,
            'jour_fin'   => $this->jourFin,
            'ordre'      => $this->ordre,
        ];
    }

    public function validate(): array
    {
        $errors = [];

        if ($this->nomDefaut === '') {
            $errors['nom_defaut'][] = 'Le nom par défaut est obligatoire.';
        } elseif (mb_strlen($this->nomDefaut) > 80) {
            $errors['nom_defaut'][] = 'Le nom ne peut pas dépasser 80 caractères.';
        }

        if (!checkdate($this->moisDebut, $this->jourDebut, 2024)) {
            $errors['date_debut'][] = 'Date de début par défaut invalide (jour/mois).';
        }
        if (!checkdate($this->moisFin, $this->jourFin, 2024)) {
            $errors['date_fin'][] = 'Date de fin par défaut invalide (jour/mois).';
        }

        return $errors;
    }
}
