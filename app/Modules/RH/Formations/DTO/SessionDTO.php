<?php

declare(strict_types=1);

namespace App\Modules\RH\Formations\DTO;

class SessionDTO
{
    public function __construct(
        public readonly int     $formationId,
        public readonly string  $codeSession,
        public readonly ?string $lieu,
        public readonly string  $dateDebut,
        public readonly string  $dateFin,
        public readonly string  $heureDebut,
        public readonly string  $heureFin,
        public readonly int     $maxParticipants,
        public readonly ?string $formateurNom,
        public readonly float   $coutTotal,
        public readonly string  $financeur,
        public readonly ?string $commentaire,
    ) {}

    public static function fromRequest(array $post): self
    {
        return new self(
            formationId:    (int)($post['formation_id']    ?? 0),
            codeSession:    trim($post['code_session']     ?? ''),
            lieu:           ($post['lieu'] ?? '') !== '' ? trim($post['lieu']) : null,
            dateDebut:      trim($post['date_debut']       ?? ''),
            dateFin:        trim($post['date_fin']         ?? ''),
            heureDebut:     trim($post['heure_debut']      ?? '08:00'),
            heureFin:       trim($post['heure_fin']        ?? '17:00'),
            maxParticipants: max(1, (int)($post['max_participants'] ?? 20)),
            formateurNom:   ($post['formateur_nom'] ?? '') !== '' ? trim($post['formateur_nom']) : null,
            coutTotal:      (float)($post['cout_total']   ?? 0),
            financeur:      trim($post['financeur']        ?? 'etablissement'),
            commentaire:    ($post['commentaire'] ?? '') !== '' ? trim($post['commentaire']) : null,
        );
    }

    public function validate(): array
    {
        $errors = [];
        if ($this->formationId  <= 0) $errors['formation_id']  = 'Formation requise.';
        if ($this->codeSession  === '') $errors['code_session'] = 'Code session requis.';
        if ($this->dateDebut    === '') $errors['date_debut']   = 'Date début requise.';
        if ($this->dateFin      === '') $errors['date_fin']     = 'Date fin requise.';
        if ($this->dateDebut && $this->dateFin && $this->dateFin < $this->dateDebut) {
            $errors['date_fin'] = 'Date fin antérieure à date début.';
        }
        return $errors;
    }
}
