<?php

declare(strict_types=1);

namespace App\Modules\RH\Evaluations\DTO;

class EvaluationDTO
{
    public function __construct(
        public readonly int   $campagneId,
        public readonly int   $employeId,
        public readonly ?int  $affectationId,
        public readonly ?int  $evaluateurId,
    ) {}

    public static function fromRequest(array $post): self
    {
        return new self(
            campagneId:    (int)($post['campagne_id']    ?? 0),
            employeId:     (int)($post['employe_id']     ?? 0),
            affectationId: ($post['affectation_id'] ?? '') !== '' ? (int)$post['affectation_id'] : null,
            evaluateurId:  ($post['evaluateur_id']  ?? '') !== '' ? (int)$post['evaluateur_id']  : null,
        );
    }

    public function validate(): array
    {
        $errors = [];
        if ($this->campagneId <= 0)  $errors['campagne_id']  = 'Campagne requise.';
        if ($this->employeId  <= 0)  $errors['employe_id']   = 'Employé requis.';
        return $errors;
    }

    public function toArray(): array
    {
        return [
            'campagne_id'    => $this->campagneId,
            'employe_id'     => $this->employeId,
            'affectation_id' => $this->affectationId,
            'evaluateur_id'  => $this->evaluateurId,
        ];
    }
}
