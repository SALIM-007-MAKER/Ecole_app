<?php

declare(strict_types=1);

namespace App\Modules\Documents\DTO;

use App\Modules\Documents\Models\DocumentModel;

class DocumentDTO
{
    public function __construct(
        public readonly string  $titre,
        public readonly string  $moduleSource,
        public readonly string  $confidentialite  = 'interne',
        public readonly ?int    $folderId         = null,
        public readonly ?int    $categorieId      = null,
        public readonly ?string $entiteType       = null,
        public readonly ?int    $entiteId         = null,
        public readonly ?string $description      = null,
        public readonly ?string $dateEmission     = null,
        public readonly ?string $dateExpiration   = null,
        public readonly int     $alerteJours      = 30,
        public readonly ?string $referenceExterne = null,
        public readonly ?string $emetteur         = null,
        public readonly ?string $notes            = null,
        public readonly ?array  $metadata         = null,
        public readonly ?string $notesVersion     = null,
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            titre:           trim($data['titre'] ?? ''),
            moduleSource:    trim($data['module_source'] ?? ''),
            confidentialite: trim($data['confidentialite'] ?? 'interne'),
            folderId:        ($v = ($data['folder_id'] ?? '')) !== '' ? (int)$v : null,
            categorieId:     ($v = ($data['categorie_id'] ?? '')) !== '' ? (int)$v : null,
            entiteType:      ($v = trim($data['entite_type'] ?? '')) !== '' ? $v : null,
            entiteId:        ($v = ($data['entite_id'] ?? '')) !== '' ? (int)$v : null,
            description:     ($v = trim($data['description'] ?? '')) !== '' ? $v : null,
            dateEmission:    ($v = trim($data['date_emission'] ?? '')) !== '' ? $v : date('Y-m-d'),
            dateExpiration:  ($v = trim($data['date_expiration'] ?? '')) !== '' ? $v : null,
            alerteJours:     (int)($data['alerte_jours'] ?? 30),
            referenceExterne:($v = trim($data['reference_externe'] ?? '')) !== '' ? $v : null,
            emetteur:        ($v = trim($data['emetteur'] ?? '')) !== '' ? $v : null,
            notes:           ($v = trim($data['notes'] ?? '')) !== '' ? $v : null,
            metadata:        isset($data['metadata']) && is_array($data['metadata']) ? $data['metadata'] : null,
            notesVersion:    ($v = trim($data['notes_version'] ?? '')) !== '' ? $v : null,
        );
    }

    public function validate(): array
    {
        $errors = [];

        if (empty($this->titre)) {
            $errors['titre'] = 'Le titre est requis.';
        } elseif (mb_strlen($this->titre) > 255) {
            $errors['titre'] = 'Le titre ne doit pas dépasser 255 caractères.';
        }

        if (empty($this->moduleSource)) {
            $errors['module_source'] = 'Le module source est requis.';
        } elseif (!in_array($this->moduleSource, DocumentModel::MODULES_SOURCE, true)) {
            $errors['module_source'] = 'Module source invalide.';
        }

        if (!in_array($this->confidentialite, DocumentModel::CONFIDENTIALITES, true)) {
            $errors['confidentialite'] = 'Niveau de confidentialité invalide.';
        }

        if ($this->dateExpiration !== null && $this->dateEmission !== null) {
            if ($this->dateExpiration <= $this->dateEmission) {
                $errors['date_expiration'] = 'La date d\'expiration doit être postérieure à la date d\'émission.';
            }
        }

        if ($this->alerteJours < 0 || $this->alerteJours > 365) {
            $errors['alerte_jours'] = 'Les jours d\'alerte doivent être entre 0 et 365.';
        }

        return $errors;
    }

    public function toArray(): array
    {
        return [
            'titre'            => $this->titre,
            'module_source'    => $this->moduleSource,
            'confidentialite'  => $this->confidentialite,
            'folder_id'        => $this->folderId,
            'categorie_id'     => $this->categorieId,
            'entite_type'      => $this->entiteType,
            'entite_id'        => $this->entiteId,
            'description'      => $this->description,
            'date_emission'    => $this->dateEmission,
            'date_expiration'  => $this->dateExpiration,
            'alerte_jours'     => $this->alerteJours,
            'reference_externe'=> $this->referenceExterne,
            'emetteur'         => $this->emetteur,
            'notes'            => $this->notes,
            'metadata'         => $this->metadata,
        ];
    }
}
