<?php

declare(strict_types=1);

namespace App\Modules\RH\Formations\DTO;

class TrainingDTO
{
    public function __construct(
        public readonly string  $code,
        public readonly string  $titre,
        public readonly ?string $description,
        public readonly string  $type,
        public readonly float   $dureeHeures,
        public readonly string  $niveau,
        public readonly string  $modalite,
        public readonly ?int    $organismeId,
        public readonly ?string $formateurPrincipal,
        public readonly float   $coutUnitaire,
        public readonly int     $maxParticipants,
        public readonly ?string $prerequis,
        public readonly ?string $objectifs,
        public readonly array   $competenceIds,
    ) {}

    public static function fromRequest(array $post): self
    {
        return new self(
            code:               trim($post['code']              ?? ''),
            titre:              trim($post['titre']             ?? ''),
            description:        ($post['description'] ?? '') !== '' ? trim($post['description']) : null,
            type:               trim($post['type']              ?? 'interne'),
            dureeHeures:        (float)($post['duree_heures']  ?? 0),
            niveau:             trim($post['niveau']            ?? 'debutant'),
            modalite:           trim($post['modalite']          ?? 'presentiel'),
            organismeId:        ($post['organisme_id'] ?? '') !== '' ? (int)$post['organisme_id'] : null,
            formateurPrincipal: ($post['formateur_principal'] ?? '') !== '' ? trim($post['formateur_principal']) : null,
            coutUnitaire:       (float)($post['cout_unitaire'] ?? 0),
            maxParticipants:    max(1, (int)($post['max_participants'] ?? 20)),
            prerequis:          ($post['prerequis']  ?? '') !== '' ? trim($post['prerequis'])  : null,
            objectifs:          ($post['objectifs']  ?? '') !== '' ? trim($post['objectifs'])  : null,
            competenceIds:      array_map('intval', (array)($post['competences'] ?? [])),
        );
    }

    public function validate(): array
    {
        $errors = [];
        if ($this->code   === '') $errors['code']  = 'Code requis.';
        if ($this->titre  === '') $errors['titre'] = 'Titre requis.';
        if ($this->dureeHeures <= 0) $errors['duree_heures'] = 'Durée (heures) requise.';
        return $errors;
    }
}
