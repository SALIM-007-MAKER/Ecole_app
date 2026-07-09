<?php
declare(strict_types=1);

namespace App\Modules\Rapports\DTO;

class PlanificationDTO
{
    public function __construct(
        public readonly string  $nom,
        public readonly string  $domaine,
        public readonly string  $typeExport,
        public readonly string  $frequence,
        public readonly string  $heureExecution,
        public readonly ?array  $filtres          = null,
        public readonly ?int    $jourExecution    = null,
        public readonly ?array  $destinataires    = null,
        public readonly bool    $actif            = true,
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            nom:            trim($data['nom']            ?? ''),
            domaine:        trim($data['domaine']        ?? ''),
            typeExport:     trim($data['type_export']    ?? 'pdf'),
            frequence:      trim($data['frequence']      ?? 'mensuel'),
            heureExecution: trim($data['heure_execution'] ?? '06:00:00'),
            filtres:        isset($data['filtres']) && is_array($data['filtres']) ? $data['filtres'] : null,
            jourExecution:  isset($data['jour_execution']) ? (int)$data['jour_execution'] : null,
            destinataires:  isset($data['destinataires']) && is_array($data['destinataires']) ? $data['destinataires'] : null,
            actif:          isset($data['actif']) ? (bool)$data['actif'] : true,
        );
    }
}
