<?php

declare(strict_types=1);

namespace App\Modules\RH\Presences\DTO;

class AttendanceDTO
{
    public const STATUTS = [
        'present',
        'absent',
        'retard',
        'sortie_anticipee',
        'mi_temps',
        'mission',
        'heure_sup',
    ];

    public const MODES = [
        'manuel',
        'badge',
        'qr_code',
        'biometrie',
        'api_externe',
    ];

    public function __construct(
        public readonly int     $employeId,
        public readonly ?int    $affectationId,
        public readonly string  $datePresence,
        public readonly ?string $heureArrivee,
        public readonly ?string $heureDepart,
        public readonly string  $statut,
        public readonly string  $modePointage,
        public readonly string  $heureReferenceArrivee,
        public readonly int     $dureeReferenceMinutes,
        public readonly ?string $motif,
        public readonly ?string $notes,
        public readonly ?string $sourceId,
    ) {}

    public static function fromRequest(array $post): self
    {
        return new self(
            employeId:              (int)($post['employe_id'] ?? 0),
            affectationId:          ($post['affectation_id'] ?? '') !== '' ? (int)$post['affectation_id'] : null,
            datePresence:           trim($post['date_presence'] ?? ''),
            heureArrivee:           ($post['heure_arrivee'] ?? '') !== '' ? trim($post['heure_arrivee']) : null,
            heureDepart:            ($post['heure_depart']  ?? '') !== '' ? trim($post['heure_depart'])  : null,
            statut:                 trim($post['statut'] ?? 'present'),
            modePointage:           trim($post['mode_pointage'] ?? 'manuel'),
            heureReferenceArrivee:  ($post['heure_reference_arrivee'] ?? '') !== '' ? trim($post['heure_reference_arrivee']) : '08:00',
            dureeReferenceMinutes:  (int)($post['duree_reference_minutes'] ?? 480),
            motif:                  ($post['motif'] ?? '') !== '' ? trim($post['motif']) : null,
            notes:                  ($post['notes'] ?? '') !== '' ? trim($post['notes']) : null,
            sourceId:               ($post['source_id'] ?? '') !== '' ? trim($post['source_id']) : null,
        );
    }

    public function validate(): array
    {
        $errors = [];

        if ($this->employeId <= 0) {
            $errors[] = 'L\'employé est obligatoire.';
        }

        if ($this->datePresence === '') {
            $errors[] = 'La date de présence est obligatoire.';
        } elseif (!\DateTime::createFromFormat('Y-m-d', $this->datePresence)) {
            $errors[] = 'La date de présence est invalide.';
        }

        if (!in_array($this->statut, self::STATUTS, true)) {
            $errors[] = 'Statut invalide.';
        }

        if (!in_array($this->modePointage, self::MODES, true)) {
            $errors[] = 'Mode de pointage invalide.';
        }

        // Pour un statut impliquant une présence physique, l'heure d'arrivée est requise
        $statutsAvecArrivee = ['present', 'retard', 'sortie_anticipee', 'mi_temps', 'heure_sup'];
        if (in_array($this->statut, $statutsAvecArrivee, true) && $this->heureArrivee === null) {
            $errors[] = 'L\'heure d\'arrivée est obligatoire pour ce statut.';
        }

        // Heure de départ doit être postérieure à l'arrivée
        if ($this->heureArrivee !== null && $this->heureDepart !== null) {
            if ($this->heureDepart <= $this->heureArrivee) {
                $errors[] = 'L\'heure de départ doit être postérieure à l\'heure d\'arrivée.';
            }
        }

        if ($this->dureeReferenceMinutes < 60 || $this->dureeReferenceMinutes > 720) {
            $errors[] = 'La durée de référence doit être entre 60 et 720 minutes (1h–12h).';
        }

        return $errors;
    }

    public function toArray(): array
    {
        return [
            'employe_id'               => $this->employeId,
            'affectation_id'           => $this->affectationId,
            'date_presence'            => $this->datePresence,
            'heure_arrivee'            => $this->heureArrivee,
            'heure_depart'             => $this->heureDepart,
            'statut'                   => $this->statut,
            'mode_pointage'            => $this->modePointage,
            'heure_reference_arrivee'  => $this->heureReferenceArrivee,
            'duree_reference_minutes'  => $this->dureeReferenceMinutes,
            'motif'                    => $this->motif,
            'notes'                    => $this->notes,
            'source_id'                => $this->sourceId,
        ];
    }
}
