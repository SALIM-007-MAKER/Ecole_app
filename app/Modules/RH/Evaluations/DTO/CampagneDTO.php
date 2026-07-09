<?php

declare(strict_types=1);

namespace App\Modules\RH\Evaluations\DTO;

class CampagneDTO
{
    public function __construct(
        public readonly string  $code,
        public readonly string  $libelle,
        public readonly ?string $description,
        public readonly int     $annee,
        public readonly string  $periode,
        public readonly string  $dateDebut,
        public readonly string  $dateFin,
        public readonly ?string $dateLimiteAutoEval,
        public readonly ?string $dateLimiteEval,
        public readonly array   $critereIds,  // [['critere_id'=>x,'poids_override'=>y,'obligatoire'=>1], ...]
    ) {}

    public static function fromRequest(array $post): self
    {
        $critereIds = [];
        foreach ((array)($post['criteres'] ?? []) as $cid) {
            $cid = (int)$cid;
            if ($cid > 0) $critereIds[] = ['critere_id' => $cid, 'obligatoire' => 1];
        }

        return new self(
            code:               trim($post['code']       ?? ''),
            libelle:            trim($post['libelle']    ?? ''),
            description:        ($post['description'] ?? '') !== '' ? trim($post['description']) : null,
            annee:              (int)($post['annee']     ?? date('Y')),
            periode:            trim($post['periode']    ?? 'annuelle'),
            dateDebut:          trim($post['date_debut'] ?? ''),
            dateFin:            trim($post['date_fin']   ?? ''),
            dateLimiteAutoEval: ($post['date_limite_auto_eval'] ?? '') !== '' ? trim($post['date_limite_auto_eval']) : null,
            dateLimiteEval:     ($post['date_limite_eval']      ?? '') !== '' ? trim($post['date_limite_eval'])      : null,
            critereIds:         $critereIds,
        );
    }

    public function validate(): array
    {
        $errors = [];
        if ($this->code    === '') $errors['code']      = 'Code requis.';
        if ($this->libelle === '') $errors['libelle']   = 'Libellé requis.';
        if ($this->dateDebut === '') $errors['date_debut'] = 'Date début requise.';
        if ($this->dateFin   === '') $errors['date_fin']   = 'Date fin requise.';
        if ($this->dateDebut && $this->dateFin && $this->dateFin < $this->dateDebut) {
            $errors['date_fin'] = 'Date fin antérieure à date début.';
        }
        return $errors;
    }
}
