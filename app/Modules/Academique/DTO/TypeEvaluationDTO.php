<?php

namespace App\Modules\Academique\DTO;

class TypeEvaluationDTO
{
    public function __construct(
        public readonly string  $code,
        public readonly string  $nom,
        public readonly string  $description,
        public readonly float   $coefficientDefaut,
        public readonly float   $noteMaxDefaut,
        public readonly bool    $estEliminatoire,
        public readonly ?float  $seuilEliminatoire,
        public readonly string  $couleur,
        public readonly string  $icone,
        public readonly int     $ordre,
    ) {}

    public static function fromRequest(array $data): self
    {
        $estEliminatoire   = !empty($data['est_eliminatoire']);
        $seuilRaw          = trim($data['seuil_eliminatoire'] ?? '');
        $seuilEliminatoire = ($estEliminatoire && $seuilRaw !== '')
            ? (float)$seuilRaw
            : null;

        $couleur = trim($data['couleur'] ?? '');
        if ($couleur !== '' && !preg_match('/^#[0-9A-Fa-f]{6}$/', $couleur)) {
            $couleur = '';
        }

        return new self(
            code:               strtolower(trim($data['code'] ?? '')),
            nom:                trim($data['nom'] ?? ''),
            description:        trim($data['description'] ?? ''),
            coefficientDefaut:  (float)($data['coefficient_defaut'] ?? 1.00),
            noteMaxDefaut:      (float)($data['note_max_defaut']    ?? 20.00),
            estEliminatoire:    $estEliminatoire,
            seuilEliminatoire:  $seuilEliminatoire,
            couleur:            $couleur,
            icone:              trim($data['icone'] ?? ''),
            ordre:              (int)($data['ordre'] ?? 0),
        );
    }

    public function toArray(): array
    {
        return [
            'code'                => $this->code,
            'nom'                 => $this->nom,
            'description'         => $this->description ?: null,
            'coefficient_defaut'  => $this->coefficientDefaut,
            'note_max_defaut'     => $this->noteMaxDefaut,
            'est_eliminatoire'    => $this->estEliminatoire ? 1 : 0,
            'seuil_eliminatoire'  => $this->seuilEliminatoire,
            'couleur'             => $this->couleur ?: null,
            'icone'               => $this->icone   ?: null,
            'ordre'               => $this->ordre,
        ];
    }

    public function toUpdateArray(): array
    {
        $data = $this->toArray();
        unset($data['code']);
        return $data;
    }

    public function validate(bool $isCreate = true): array
    {
        $errors = [];

        if ($isCreate) {
            if ($this->code === '') {
                $errors['code'][] = 'Le code est obligatoire.';
            } elseif (!preg_match('/^[a-z0-9_-]{2,30}$/', $this->code)) {
                $errors['code'][] = 'Le code doit contenir 2 à 30 caractères alphanumériques, tirets ou underscores (minuscules).';
            }
        }

        if ($this->nom === '') {
            $errors['nom'][] = 'Le nom est obligatoire.';
        } elseif (mb_strlen($this->nom) > 80) {
            $errors['nom'][] = 'Le nom ne peut pas dépasser 80 caractères.';
        }

        if (mb_strlen($this->description) > 500) {
            $errors['description'][] = 'La description ne peut pas dépasser 500 caractères.';
        }

        if ($this->coefficientDefaut < 0.25 || $this->coefficientDefaut > 10.00) {
            $errors['coefficient_defaut'][] = 'Le coefficient doit être compris entre 0,25 et 10,00.';
        }

        if ($this->noteMaxDefaut < 5.00 || $this->noteMaxDefaut > 100.00) {
            $errors['note_max_defaut'][] = 'La note maximale doit être comprise entre 5 et 100.';
        }

        if ($this->estEliminatoire && $this->seuilEliminatoire === null) {
            $errors['seuil_eliminatoire'][] = 'Le seuil éliminatoire est requis quand le type est éliminatoire.';
        }
        if ($this->seuilEliminatoire !== null) {
            if ($this->seuilEliminatoire < 0 || $this->seuilEliminatoire > $this->noteMaxDefaut) {
                $errors['seuil_eliminatoire'][] = "Le seuil éliminatoire doit être compris entre 0 et {$this->noteMaxDefaut}.";
            }
        }

        if ($this->couleur !== '' && !preg_match('/^#[0-9A-Fa-f]{6}$/', $this->couleur)) {
            $errors['couleur'][] = 'La couleur doit être au format #RRGGBB.';
        }

        if (mb_strlen($this->icone) > 50) {
            $errors['icone'][] = "Le nom d'icône ne peut pas dépasser 50 caractères.";
        }

        if ($this->ordre < 0 || $this->ordre > 99) {
            $errors['ordre'][] = "L'ordre doit être compris entre 0 et 99.";
        }

        return $errors;
    }
}
