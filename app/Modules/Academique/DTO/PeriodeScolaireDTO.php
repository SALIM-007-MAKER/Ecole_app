<?php

namespace App\Modules\Academique\DTO;

use App\Modules\Academique\Models\PeriodeScolaireModel;

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
        /** Édition directe et libre du statut par un administrateur.
         *  NULL = non fourni (création normale : statut géré par le service). */
        public readonly ?string $statut = null,
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
        $statut        = array_key_exists('statut', $data) && trim((string)$data['statut']) !== ''
            ? trim((string)$data['statut'])
            : null;

        if ($nom === '' && $anneeScolaire !== '' && $typePeriode !== '') {
            $labels = self::typeLabels();
            $typeLabel = $labels[$typePeriode] ?? ucfirst($typePeriode);
            $nom = $typeLabel . ' ' . $numero . ' — ' . $anneeScolaire;
        }

        return new self($anneeScolaire, $typePeriode, $numero, $nom, $dateDebut, $dateFin, $ordre, $statut);
    }

    public function toArray(): array
    {
        $data = [
            'annee_scolaire'       => $this->anneeScolaire,
            'type_periode'         => $this->typePeriode,
            'numero'               => $this->numero,
            'nom'                  => $this->nom,
            'date_debut'           => $this->dateDebut,
            'date_fin'             => $this->dateFin,
            'ordre'                => $this->ordre,
        ];
        if ($this->statut !== null) {
            $data['statut'] = $this->statut;
        }
        return $data;
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

        if ($this->statut !== null && !in_array($this->statut, PeriodeScolaireModel::STATUTS, true)) {
            $errors['statut'][] = 'Statut invalide.';
        }

        return $errors;
    }

    /**
     * Types de période autorisés à la création/modification. Le système
     * officiel du Niger (trimestre) reste le défaut, 'custom' couvre les
     * besoins ponctuels, et 'semestre' est proposé pour les établissements
     * fonctionnant en 2 semestres + moyenne annuelle (ex : bulletin CSP
     * La Persévérance) — voir PeriodeScolaireModel::TYPES.
     */
    public static function typeLabels(): array
    {
        return [
            'trimestre' => 'Trimestre',
            'semestre'  => 'Semestre',
            'custom'    => 'Période',
        ];
    }

    /** @deprecated Utiliser PeriodeScolaireModel::STATUT_LABELS (source unique). */
    public static function statutLabels(): array
    {
        return PeriodeScolaireModel::STATUT_LABELS;
    }

    /** @deprecated Utiliser PeriodeScolaireModel::STATUT_COLORS (source unique). */
    public static function statutColors(): array
    {
        return PeriodeScolaireModel::STATUT_COLORS;
    }

    private function isValidDate(string $date): bool
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) return false;
        [$y, $m, $d] = explode('-', $date);
        return checkdate((int)$m, (int)$d, (int)$y);
    }
}
