<?php

namespace App\Modules\Finance\DTO;

class FraisTypeDTO
{
    public const PERIODICITES = [
        'unique'       => 'Paiement unique',
        'mensuel'      => 'Mensuel',
        'trimestriel'  => 'Trimestriel',
        'semestriel'   => 'Semestriel',
        'annuel'       => 'Annuel',
    ];

    public const DEVISES = [
        'XOF' => 'Franc CFA (XOF)',
        'EUR' => 'Euro (EUR)',
        'USD' => 'Dollar US (USD)',
        'MAD' => 'Dirham marocain (MAD)',
        'GNF' => 'Franc guinéen (GNF)',
    ];

    public function __construct(
        public readonly string  $code,
        public readonly string  $nom,
        public readonly float   $montantDefaut,
        public readonly string  $periodicite,
        public readonly bool    $estObligatoire,
        public readonly string  $description     = '',
        public readonly ?int    $categorieId     = null,
        public readonly string  $devise          = 'XOF',
        public readonly array   $niveauxCibles   = [],
        public readonly ?string $anneeScolaire   = null,
        public readonly ?string $dateLimite      = null,
        public readonly bool    $peutAvoirRemise = true,
    ) {}

    public static function fromRequest(array $data): self
    {
        $montant      = (float)str_replace(',', '.', $data['montant_defaut'] ?? '0');
        $niveaux      = array_filter(array_map('trim', (array)($data['niveaux_cibles'] ?? [])));
        $anneeScolaire = trim($data['annee_scolaire'] ?? '');

        return new self(
            code:           strtoupper(trim($data['code'] ?? '')),
            nom:            trim($data['nom'] ?? ''),
            montantDefaut:  $montant,
            periodicite:    trim($data['periodicite'] ?? 'annuel'),
            estObligatoire: (bool)($data['est_obligatoire'] ?? true),
            description:    trim($data['description'] ?? ''),
            categorieId:    !empty($data['categorie_id']) ? (int)$data['categorie_id'] : null,
            devise:         strtoupper(trim($data['devise'] ?? 'XOF')),
            niveauxCibles:  array_values($niveaux),
            anneeScolaire:  $anneeScolaire !== '' ? $anneeScolaire : null,
            dateLimite:     !empty($data['date_limite']) ? $data['date_limite'] : null,
            peutAvoirRemise:(bool)($data['peut_avoir_remise'] ?? true),
        );
    }

    public function toArray(): array
    {
        return [
            'code'             => $this->code,
            'nom'              => $this->nom,
            'montant_defaut'   => $this->montantDefaut,
            'periodicite'      => $this->periodicite,
            'est_obligatoire'  => (int)$this->estObligatoire,
            'description'      => $this->description ?: null,
            'categorie_id'     => $this->categorieId,
            'devise'           => $this->devise,
            'niveaux_cibles'   => !empty($this->niveauxCibles)
                                    ? json_encode(array_values($this->niveauxCibles), JSON_UNESCAPED_UNICODE)
                                    : null,
            'annee_scolaire'   => $this->anneeScolaire,
            'date_limite'      => $this->dateLimite,
            'peut_avoir_remise'=> (int)$this->peutAvoirRemise,
        ];
    }

    public function validate(): array
    {
        $errors = [];

        if ($this->code === '') {
            $errors['code'] = 'Le code est obligatoire.';
        } elseif (!preg_match('/^[A-Z0-9_]{2,30}$/', $this->code)) {
            $errors['code'] = 'Le code doit contenir uniquement des lettres majuscules, chiffres et underscores (2-30 caractères).';
        }

        if ($this->nom === '') {
            $errors['nom'] = 'Le nom du frais est obligatoire.';
        } elseif (mb_strlen($this->nom) > 150) {
            $errors['nom'] = 'Le nom ne peut pas dépasser 150 caractères.';
        }

        if ($this->montantDefaut < 0) {
            $errors['montant_defaut'] = 'Le montant doit être positif ou nul.';
        }

        if (!array_key_exists($this->periodicite, self::PERIODICITES)) {
            $errors['periodicite'] = 'Périodicité invalide.';
        }

        if (!array_key_exists($this->devise, self::DEVISES)) {
            $errors['devise'] = 'Devise non supportée.';
        }

        if ($this->anneeScolaire !== null && !preg_match('/^\d{4}-\d{4}$/', $this->anneeScolaire)) {
            $errors['annee_scolaire'] = 'Format année scolaire invalide (attendu : AAAA-AAAA).';
        }

        return $errors;
    }
}
