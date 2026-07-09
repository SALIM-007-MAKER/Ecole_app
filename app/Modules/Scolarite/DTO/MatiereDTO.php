<?php

namespace App\Modules\Scolarite\DTO;

class MatiereDTO
{
    public const FILIERES = [
        ''                         => 'Toutes filières',
        'Sciences Expérimentales'  => 'Sciences Expérimentales',
        'Mathématiques'            => 'Mathématiques',
        'Lettres et Philosophie'   => 'Lettres et Philosophie',
        'Langues Étrangères'       => 'Langues Étrangères',
        'Sciences Techniques'      => 'Sciences Techniques',
        'Gestion et Économie'      => 'Gestion et Économie',
        'Commun'                   => 'Toutes filières (Commun)',
    ];

    public function __construct(
        public readonly string  $nom,
        public readonly float   $coefficient,
        public readonly int     $volumeHoraire,
        public readonly string  $description   = '',
        public readonly string  $filiere       = '',
        public readonly array   $niveaux       = [],
        public readonly string  $couleur       = '',
        public readonly ?int    $responsableId = null,
    ) {}

    public static function fromRequest(array $data): self
    {
        $coef = (float)str_replace(',', '.', $data['coefficient'] ?? '1');
        $niveaux = array_filter(array_map('trim', (array)($data['niveaux'] ?? [])));

        return new self(
            nom:           ucwords(strtolower(trim($data['nom'] ?? ''))),
            coefficient:   $coef,
            volumeHoraire: (int)($data['volume_horaire'] ?? 1),
            description:   trim($data['description'] ?? ''),
            filiere:       trim($data['filiere'] ?? ''),
            niveaux:       array_values($niveaux),
            couleur:       trim($data['couleur'] ?? ''),
            responsableId: !empty($data['responsable_id']) ? (int)$data['responsable_id'] : null,
        );
    }

    public function toArray(): array
    {
        return [
            'nom'            => $this->nom,
            'coefficient'    => $this->coefficient,
            'volume_horaire' => $this->volumeHoraire,
            'description'    => $this->description ?: null,
            'filiere'        => $this->filiere ?: null,
            'niveaux'        => $this->niveaux ? implode(',', $this->niveaux) : null,
            'couleur'        => $this->couleur ?: null,
            'responsable_id' => $this->responsableId,
        ];
    }

    /** Retourne les niveaux comme tableau (depuis CSV ou tableau). */
    public function getNiveauxArray(): array
    {
        return $this->niveaux;
    }

    public function validate(): array
    {
        $errors = [];

        if ($this->nom === '') {
            $errors['nom'] = 'Le nom de la matière est obligatoire.';
        } elseif (mb_strlen($this->nom) > 100) {
            $errors['nom'] = 'Le nom ne peut pas dépasser 100 caractères.';
        }

        if ($this->coefficient < 0.5 || $this->coefficient > 20) {
            $errors['coefficient'] = 'Le coefficient doit être compris entre 0,5 et 20.';
        }

        if ($this->volumeHoraire < 1 || $this->volumeHoraire > 30) {
            $errors['volume_horaire'] = 'Le volume horaire doit être compris entre 1 et 30 h/semaine.';
        }

        if ($this->couleur !== '' && !preg_match('/^#[0-9A-Fa-f]{6}$/', $this->couleur)) {
            $errors['couleur'] = 'La couleur doit être un code hexadécimal valide (ex. #3B82F6).';
        }

        return $errors;
    }
}
