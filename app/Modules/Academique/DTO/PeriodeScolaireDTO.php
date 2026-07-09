<?php

namespace App\Modules\Academique\DTO;

class PeriodeScolaireDTO
{
    public function __construct(
        public readonly string  $anneeScolaire,
        public readonly string  $typePeriode,
        public readonly int     $numero,
        public readonly string  $nom,
        public readonly ?string $dateDebut,
        public readonly ?string $dateFin,
        public readonly int     $ordre,
    ) {}

    public static function fromRequest(array $data): self
    {
        $anneeScolaire = trim($data['annee_scolaire'] ?? '');
        $typePeriode   = trim($data['type_periode']   ?? 'trimestre');
        $numero        = (int)($data['numero']         ?? 1);
        $nom           = trim($data['nom']             ?? '');
        $dateDebut     = trim($data['date_debut']      ?? '') ?: null;
        $dateFin       = trim($data['date_fin']        ?? '') ?: null;
        $ordre         = (int)($data['ordre']           ?? 0);

        if ($nom === '' && $anneeScolaire !== '' && $typePeriode !== '') {
            $labels = self::typeLabels();
            $typeLabel = $labels[$typePeriode] ?? ucfirst($typePeriode);
            $nom = $typeLabel . ' ' . $numero . ' — ' . $anneeScolaire;
        }

        return new self($anneeScolaire, $typePeriode, $numero, $nom, $dateDebut, $dateFin, $ordre);
    }

    public function toArray(): array
    {
        return [
            'annee_scolaire'       => $this->anneeScolaire,
            'type_periode'         => $this->typePeriode,
            'numero'               => $this->numero,
            'nom'                  => $this->nom,
            'date_debut'           => $this->dateDebut,
            'date_fin'             => $this->dateFin,
            'ordre'                => $this->ordre,
        ];
    }

    public function validate(): array
    {
        $errors = [];

        if (!preg_match('/^\d{4}-\d{4}$/', $this->anneeScolaire)) {
            $errors['annee_scolaire'][] = 'Format invalide. Attendu : YYYY-YYYY (ex: 2025-2026).';
        } else {
            [$y1, $y2] = explode('-', $this->anneeScolaire);
            if ((int)$y2 !== (int)$y1 + 1) {
                $errors['annee_scolaire'][] = "L'année de fin doit être l'année de début + 1.";
            }
        }

        if (!in_array($this->typePeriode, array_keys(self::typeLabels()), true)) {
            $errors['type_periode'][] = 'Type de période invalide.';
        } else {
            $maxNumero = $this->typePeriode === 'semestre' ? 2 : 3;
            if ($this->numero < 1 || $this->numero > $maxNumero) {
                $errors['numero'][] = "Le numéro doit être entre 1 et {$maxNumero} pour un(e) {$this->typePeriode}.";
            }
        }

        if ($this->nom === '') {
            $errors['nom'][] = 'Le nom est obligatoire.';
        } elseif (mb_strlen($this->nom) > 80) {
            $errors['nom'][] = 'Le nom ne peut pas dépasser 80 caractères.';
        }

        if ($this->dateDebut !== null && !$this->isValidDate($this->dateDebut)) {
            $errors['date_debut'][] = 'Date de début invalide (format attendu : YYYY-MM-DD).';
        }
        if ($this->dateFin !== null && !$this->isValidDate($this->dateFin)) {
            $errors['date_fin'][] = 'Date de fin invalide (format attendu : YYYY-MM-DD).';
        }
        if ($this->dateDebut !== null && $this->dateFin !== null && $this->dateFin <= $this->dateDebut) {
            $errors['date_fin'][] = 'La date de fin doit être postérieure à la date de début.';
        }

        if ($this->ordre < 0 || $this->ordre > 99) {
            $errors['ordre'][] = "L'ordre doit être compris entre 0 et 99.";
        }

        return $errors;
    }

    public static function typeLabels(): array
    {
        return [
            'trimestre' => 'Trimestre',
            'semestre'  => 'Semestre',
            'custom'    => 'Période',
        ];
    }

    public static function statutLabels(): array
    {
        return [
            'ouverte'      => 'Ouverte',
            'fermee'       => 'Fermée',
            'verrouillee'  => 'Verrouillée',
            'archivee'     => 'Archivée',
        ];
    }

    public static function statutColors(): array
    {
        return [
            'ouverte'      => 'emerald',
            'fermee'       => 'amber',
            'verrouillee'  => 'red',
            'archivee'     => 'slate',
        ];
    }

    private function isValidDate(string $date): bool
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) return false;
        [$y, $m, $d] = explode('-', $date);
        return checkdate((int)$m, (int)$d, (int)$y);
    }
}
