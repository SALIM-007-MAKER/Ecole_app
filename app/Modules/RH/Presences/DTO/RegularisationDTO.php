<?php

declare(strict_types=1);

namespace App\Modules\RH\Presences\DTO;

class RegularisationDTO
{
    public const TYPES = [
        'correction_heure',
        'correction_statut',
        'justification',
        'annulation',
    ];

    public function __construct(
        public readonly string  $typeRegularisation,
        public readonly ?string $heureArrivee,
        public readonly ?string $heureDepart,
        public readonly ?string $nouveauStatut,
        public readonly string  $motif,
    ) {}

    public static function fromRequest(array $post): self
    {
        return new self(
            typeRegularisation: trim($post['type_regularisation'] ?? ''),
            heureArrivee:       ($post['heure_arrivee'] ?? '') !== '' ? trim($post['heure_arrivee']) : null,
            heureDepart:        ($post['heure_depart']  ?? '') !== '' ? trim($post['heure_depart'])  : null,
            nouveauStatut:      ($post['nouveau_statut']?? '') !== '' ? trim($post['nouveau_statut']): null,
            motif:              trim($post['motif'] ?? ''),
        );
    }

    public function validate(): array
    {
        $errors = [];

        if (!in_array($this->typeRegularisation, self::TYPES, true)) {
            $errors[] = 'Type de régularisation invalide.';
        }

        if (trim($this->motif) === '') {
            $errors[] = 'Le motif de régularisation est obligatoire.';
        }

        if ($this->typeRegularisation === 'correction_heure') {
            if ($this->heureArrivee === null && $this->heureDepart === null) {
                $errors[] = 'Au moins une heure (arrivée ou départ) est requise pour une correction d\'horaire.';
            }
            if ($this->heureArrivee !== null && $this->heureDepart !== null
                && $this->heureDepart <= $this->heureArrivee) {
                $errors[] = 'L\'heure de départ doit être postérieure à l\'heure d\'arrivée.';
            }
        }

        if ($this->typeRegularisation === 'correction_statut' && $this->nouveauStatut === null) {
            $errors[] = 'Le nouveau statut est requis pour une correction de statut.';
        }

        if ($this->nouveauStatut !== null && !in_array($this->nouveauStatut, AttendanceDTO::STATUTS, true)) {
            $errors[] = 'Nouveau statut invalide.';
        }

        return $errors;
    }

    /** Retourne les champs qui seront mis à jour dans rh_presences */
    public function changesArray(): array
    {
        $changes = [];

        if ($this->typeRegularisation === 'correction_heure') {
            if ($this->heureArrivee !== null) $changes['heure_arrivee'] = $this->heureArrivee;
            if ($this->heureDepart  !== null) $changes['heure_depart']  = $this->heureDepart;
        }

        if ($this->typeRegularisation === 'correction_statut' && $this->nouveauStatut !== null) {
            $changes['statut'] = $this->nouveauStatut;
        }

        return $changes;
    }
}
