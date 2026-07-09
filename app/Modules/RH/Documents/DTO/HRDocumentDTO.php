<?php

declare(strict_types=1);

namespace App\Modules\RH\Documents\DTO;

use App\Modules\RH\Documents\Models\HRDocumentModel;

class HRDocumentDTO
{
    public function __construct(
        public readonly string  $type,
        public readonly int     $employeId,
        public readonly string  $titre,
        public readonly string  $dateEmission,
        public readonly string  $confidentialite   = 'confidentiel',
        public readonly ?string $referenceExterne  = null,
        public readonly ?string $dateExpiration    = null,
        public readonly ?int    $alerteJours       = 30,
        public readonly ?string $emetteur          = null,
        public readonly ?string $notes             = null,
        public readonly ?int    $contratId         = null,
        public readonly ?int    $formationSessionId = null,
        public readonly ?int    $evaluationId      = null,
        public readonly ?string $notesVersion      = null,
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            type:               trim($data['type'] ?? ''),
            employeId:          (int)($data['employe_id'] ?? 0),
            titre:              trim($data['titre'] ?? ''),
            dateEmission:       trim($data['date_emission'] ?? date('Y-m-d')),
            confidentialite:    trim($data['confidentialite'] ?? 'confidentiel'),
            referenceExterne:   ($v = trim($data['reference_externe'] ?? '')) !== '' ? $v : null,
            dateExpiration:     ($v = trim($data['date_expiration'] ?? '')) !== '' ? $v : null,
            alerteJours:        ($v = ($data['alerte_jours'] ?? '')) !== '' ? (int)$v : 30,
            emetteur:           ($v = trim($data['emetteur'] ?? '')) !== '' ? $v : null,
            notes:              ($v = trim($data['notes'] ?? '')) !== '' ? $v : null,
            contratId:          ($v = ($data['contrat_id'] ?? '')) !== '' ? (int)$v : null,
            formationSessionId: ($v = ($data['formation_session_id'] ?? '')) !== '' ? (int)$v : null,
            evaluationId:       ($v = ($data['evaluation_id'] ?? '')) !== '' ? (int)$v : null,
            notesVersion:       ($v = trim($data['notes_version'] ?? '')) !== '' ? $v : null,
        );
    }

    public function validate(): array
    {
        $errors = [];
        if (empty($this->type)) {
            $errors['type'] = 'Type de document requis.';
        } elseif (!in_array($this->type, HRDocumentModel::TYPES, true)) {
            $errors['type'] = 'Type de document invalide.';
        }
        if ($this->employeId <= 0) {
            $errors['employe_id'] = 'Employé requis.';
        }
        if (empty($this->titre)) {
            $errors['titre'] = 'Titre requis.';
        }
        if (empty($this->dateEmission)) {
            $errors['date_emission'] = 'Date d\'émission requise.';
        }
        if (!in_array($this->confidentialite, HRDocumentModel::CONFIDENTIALITES, true)) {
            $errors['confidentialite'] = 'Niveau de confidentialité invalide.';
        }
        if ($this->dateExpiration !== null && $this->dateExpiration <= $this->dateEmission) {
            $errors['date_expiration'] = 'La date d\'expiration doit être postérieure à la date d\'émission.';
        }
        return $errors;
    }

    public function toArray(): array
    {
        return [
            'type'                => $this->type,
            'employe_id'          => $this->employeId,
            'titre'               => $this->titre,
            'date_emission'       => $this->dateEmission,
            'confidentialite'     => $this->confidentialite,
            'reference_externe'   => $this->referenceExterne,
            'date_expiration'     => $this->dateExpiration,
            'alerte_jours'        => $this->alerteJours,
            'emetteur'            => $this->emetteur,
            'notes'               => $this->notes,
            'contrat_id'          => $this->contratId,
            'formation_session_id'=> $this->formationSessionId,
            'evaluation_id'       => $this->evaluationId,
        ];
    }
}
