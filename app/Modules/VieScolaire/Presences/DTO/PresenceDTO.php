<?php

namespace App\Modules\VieScolaire\Presences\DTO;

class PresenceDTO
{
    public const STATUTS = ['present', 'absent', 'retard', 'dispense', 'sortie_anticipee'];

    public function __construct(
        public readonly int     $eleveId,
        public readonly string  $statut        = 'present',
        public readonly ?string $heureArrivee  = null,
        public readonly ?int    $retardMinutes = null,
        public readonly ?string $observation   = null,
        public readonly ?string $motifCorrection = null, // renseigné en cas de modification
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            eleveId:         (int)($data['eleve_id']           ?? 0),
            statut:          trim($data['statut']               ?? 'present'),
            heureArrivee:    $data['heure_arrivee'] !== '' ? ($data['heure_arrivee'] ?? null) : null,
            retardMinutes:   isset($data['retard_minutes']) && $data['retard_minutes'] !== ''
                                 ? (int)$data['retard_minutes'] : null,
            observation:     $data['observation']     !== '' ? ($data['observation']     ?? null) : null,
            motifCorrection: $data['motif_correction'] ?? null,
        );
    }

    /** Build a PresenceDTO for a single student from the bulk form data. */
    public static function fromBulkRequest(int $eleveId, array $studentData): self
    {
        return new self(
            eleveId:         $eleveId,
            statut:          trim($studentData['statut']         ?? 'present'),
            heureArrivee:    $studentData['heure_arrivee'] !== '' ? ($studentData['heure_arrivee'] ?? null) : null,
            retardMinutes:   isset($studentData['retard_minutes']) && $studentData['retard_minutes'] !== ''
                                 ? (int)$studentData['retard_minutes'] : null,
            observation:     $studentData['observation']     !== '' ? ($studentData['observation']     ?? null) : null,
            motifCorrection: $studentData['motif_correction'] ?? null,
        );
    }

    public function validate(): array
    {
        $errors = [];

        if ($this->eleveId <= 0) {
            $errors[] = "L'élève est obligatoire.";
        }
        if (!in_array($this->statut, self::STATUTS, true)) {
            $errors[] = "Statut de présence invalide : {$this->statut}.";
        }
        if ($this->statut === 'retard' && $this->retardMinutes !== null && $this->retardMinutes < 0) {
            $errors[] = "Le nombre de minutes de retard ne peut pas être négatif.";
        }

        return $errors;
    }
}
