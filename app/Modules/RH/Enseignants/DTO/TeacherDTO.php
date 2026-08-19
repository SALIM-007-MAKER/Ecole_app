<?php

namespace App\Modules\RH\Enseignants\DTO;

class TeacherDTO
{
    public const STATUTS_PEDAGOGIQUES = [
        'titulaire'   => 'Titulaire',
        'vacataire'   => 'Vacataire',
        'remplacant'  => 'Remplaçant',
        'stagiaire'   => 'Stagiaire',
        'contractuel' => 'Contractuel',
    ];

    // Libellés alignés sur la nomenclature nigérienne des classes (App\Models\ClasseModel::NIVEAUX) —
    // clés de stockage inchangées ('moyen'/'secondaire'), seuls les libellés affichés sont mis à jour.
    public const NIVEAUX_DISPONIBLES = [
        'prescolaire' => 'Préscolaire',
        'primaire'    => 'Primaire',
        'moyen'       => 'Collège',
        'secondaire'  => 'Lycée',
        'superieur'   => 'Supérieur',
    ];

    public function __construct(
        public readonly int    $employeId,
        public readonly string $statutPedagogique,
        public readonly ?string $specialitePrincipale,
        public readonly int    $chargeHoraireMax,
        public readonly ?string $dateDebutEnseignement,
        public readonly ?string $notesPedagogiques,
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            employeId:             (int)($data['employe_id']              ?? 0),
            statutPedagogique:     trim($data['statut_pedagogique']        ?? 'titulaire'),
            specialitePrincipale:  trim($data['specialite_principale']     ?? '') ?: null,
            chargeHoraireMax:      max(1, min(40, (int)($data['charge_horaire_max'] ?? 18))),
            dateDebutEnseignement: trim($data['date_debut_enseignement']   ?? '') ?: null,
            notesPedagogiques:     trim($data['notes_pedagogiques']        ?? '') ?: null,
        );
    }

    public function validate(): array
    {
        $errors = [];

        if ($this->employeId <= 0) {
            $errors['employe_id'][] = "Un employé doit être sélectionné.";
        }

        if (!array_key_exists($this->statutPedagogique, self::STATUTS_PEDAGOGIQUES)) {
            $errors['statut_pedagogique'][] = "Statut pédagogique invalide.";
        }

        if ($this->chargeHoraireMax < 1 || $this->chargeHoraireMax > 40) {
            $errors['charge_horaire_max'][] = "La charge horaire maximale doit être entre 1 et 40 heures.";
        }

        if ($this->dateDebutEnseignement !== null
            && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->dateDebutEnseignement)) {
            $errors['date_debut_enseignement'][] = "Format de date invalide.";
        }

        return $errors;
    }

    public function toArray(): array
    {
        return [
            'employe_id'              => $this->employeId,
            'statut_pedagogique'      => $this->statutPedagogique,
            'specialite_principale'   => $this->specialitePrincipale,
            'charge_horaire_max'      => $this->chargeHoraireMax,
            'date_debut_enseignement' => $this->dateDebutEnseignement,
            'notes_pedagogiques'      => $this->notesPedagogiques,
        ];
    }
}
