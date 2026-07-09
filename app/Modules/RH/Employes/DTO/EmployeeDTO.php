<?php

namespace App\Modules\RH\Employes\DTO;

class EmployeeDTO
{
    public const TYPES = ['enseignant', 'administratif', 'support', 'direction', 'technique'];
    public const STATUTS = ['actif', 'inactif', 'suspendu', 'conge', 'retraite', 'demissionnaire'];
    public const GENRES  = ['M', 'F', 'autre'];

    public function __construct(
        public readonly string  $typePersonnel,
        public readonly string  $nom,
        public readonly string  $prenom,
        public readonly string  $statut         = 'actif',
        public readonly ?int    $userId          = null,
        public readonly ?int    $professeurId    = null,
        public readonly ?string $dateNaissance   = null,
        public readonly ?string $lieuNaissance   = null,
        public readonly ?string $genre           = null,
        public readonly string  $nationalite     = 'Algérienne',
        public readonly ?string $cniNumero       = null,
        public readonly ?string $cniExpiration   = null,
        public readonly ?string $adresse         = null,
        public readonly ?string $telephone       = null,
        public readonly ?string $emailPro        = null,
        public readonly ?string $emailPerso      = null,
        public readonly ?int    $departementId   = null,
        public readonly ?int    $posteId         = null,
        public readonly ?string $dateEntree      = null,
        public readonly ?string $dateSortie      = null,
        public readonly ?string $motifSortie     = null,
        public readonly ?string $diplome         = null,
        public readonly ?string $specialite      = null,
        public readonly ?string $notes           = null,
        public readonly ?string $photo           = null,
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            typePersonnel:  trim($data['type_personnel'] ?? ''),
            nom:            trim($data['nom']            ?? ''),
            prenom:         trim($data['prenom']         ?? ''),
            statut:         trim($data['statut']         ?? 'actif'),
            userId:         !empty($data['user_id'])       ? (int)$data['user_id']       : null,
            professeurId:   !empty($data['professeur_id']) ? (int)$data['professeur_id'] : null,
            dateNaissance:  !empty($data['date_naissance']) ? trim($data['date_naissance']) : null,
            lieuNaissance:  !empty($data['lieu_naissance']) ? trim($data['lieu_naissance']) : null,
            genre:          !empty($data['genre'])          ? trim($data['genre'])          : null,
            nationalite:    trim($data['nationalite'] ?? 'Algérienne'),
            cniNumero:      !empty($data['cni_numero'])     ? trim($data['cni_numero'])     : null,
            cniExpiration:  !empty($data['cni_expiration']) ? trim($data['cni_expiration']) : null,
            adresse:        !empty($data['adresse'])        ? trim($data['adresse'])        : null,
            telephone:      !empty($data['telephone'])      ? trim($data['telephone'])      : null,
            emailPro:       !empty($data['email_pro'])      ? trim($data['email_pro'])      : null,
            emailPerso:     !empty($data['email_perso'])    ? trim($data['email_perso'])    : null,
            departementId:  !empty($data['departement_id']) ? (int)$data['departement_id'] : null,
            posteId:        !empty($data['poste_id'])       ? (int)$data['poste_id']       : null,
            dateEntree:     !empty($data['date_entree'])    ? trim($data['date_entree'])    : null,
            dateSortie:     !empty($data['date_sortie'])    ? trim($data['date_sortie'])    : null,
            motifSortie:    !empty($data['motif_sortie'])   ? trim($data['motif_sortie'])   : null,
            diplome:        !empty($data['diplome'])        ? trim($data['diplome'])        : null,
            specialite:     !empty($data['specialite'])     ? trim($data['specialite'])     : null,
            notes:          !empty($data['notes'])          ? trim($data['notes'])          : null,
        );
    }

    public function validate(): array
    {
        $errors = [];

        if (empty($this->nom)) {
            $errors['nom'][] = 'Le nom est obligatoire.';
        } elseif (mb_strlen($this->nom) > 100) {
            $errors['nom'][] = 'Le nom ne peut pas dépasser 100 caractères.';
        }

        if (empty($this->prenom)) {
            $errors['prenom'][] = 'Le prénom est obligatoire.';
        } elseif (mb_strlen($this->prenom) > 100) {
            $errors['prenom'][] = 'Le prénom ne peut pas dépasser 100 caractères.';
        }

        if (!in_array($this->typePersonnel, self::TYPES, true)) {
            $errors['type_personnel'][] = 'Type de personnel invalide.';
        }

        if (!in_array($this->statut, self::STATUTS, true)) {
            $errors['statut'][] = 'Statut invalide.';
        }

        if ($this->genre !== null && !in_array($this->genre, self::GENRES, true)) {
            $errors['genre'][] = 'Genre invalide.';
        }

        if ($this->emailPro !== null && !filter_var($this->emailPro, FILTER_VALIDATE_EMAIL)) {
            $errors['email_pro'][] = 'Adresse e-mail professionnelle invalide.';
        }

        if ($this->emailPerso !== null && !filter_var($this->emailPerso, FILTER_VALIDATE_EMAIL)) {
            $errors['email_perso'][] = 'Adresse e-mail personnelle invalide.';
        }

        if ($this->dateNaissance !== null && !\DateTime::createFromFormat('Y-m-d', $this->dateNaissance)) {
            $errors['date_naissance'][] = 'Format de date de naissance invalide (YYYY-MM-DD).';
        }

        if ($this->dateEntree !== null && !\DateTime::createFromFormat('Y-m-d', $this->dateEntree)) {
            $errors['date_entree'][] = 'Format de date d\'entrée invalide (YYYY-MM-DD).';
        }

        if ($this->dateSortie !== null) {
            if (!\DateTime::createFromFormat('Y-m-d', $this->dateSortie)) {
                $errors['date_sortie'][] = 'Format de date de sortie invalide (YYYY-MM-DD).';
            }
            if ($this->dateEntree !== null && $this->dateSortie < $this->dateEntree) {
                $errors['date_sortie'][] = 'La date de sortie doit être postérieure à la date d\'entrée.';
            }
        }

        if (in_array($this->statut, ['inactif', 'retraite', 'demissionnaire'], true) && empty($this->motifSortie)) {
            $errors['motif_sortie'][] = 'Le motif de sortie est obligatoire pour ce statut.';
        }

        return $errors;
    }

    public function toArray(): array
    {
        return [
            'type_personnel' => $this->typePersonnel,
            'nom'            => $this->nom,
            'prenom'         => $this->prenom,
            'statut'         => $this->statut,
            'user_id'        => $this->userId,
            'professeur_id'  => $this->professeurId,
            'date_naissance' => $this->dateNaissance,
            'lieu_naissance' => $this->lieuNaissance,
            'genre'          => $this->genre,
            'nationalite'    => $this->nationalite,
            'cni_numero'     => $this->cniNumero,
            'cni_expiration' => $this->cniExpiration,
            'adresse'        => $this->adresse,
            'telephone'      => $this->telephone,
            'email_pro'      => $this->emailPro,
            'email_perso'    => $this->emailPerso,
            'departement_id' => $this->departementId,
            'poste_id'       => $this->posteId,
            'date_entree'    => $this->dateEntree,
            'date_sortie'    => $this->dateSortie,
            'motif_sortie'   => $this->motifSortie,
            'diplome'        => $this->diplome,
            'specialite'     => $this->specialite,
            'notes'          => $this->notes,
        ];
    }
}
