<?php

namespace App\Modules\Academique\DTO;

final class NoteDTO
{
    public function __construct(
        public readonly int     $evaluationId,
        public readonly int     $eleveId,
        public readonly ?float  $valeur,
        public readonly bool    $estAbsent,
        public readonly ?string $commentaire,
        /** Une valeur a été fournie mais n'est pas un nombre : elle ne doit JAMAIS être convertie en 0. */
        public readonly bool    $valeurInvalide = false,
    ) {}

    /** Nombre décimal saisi à la française (« 12,5 ») ou à l'anglaise ; null si ce n'est pas un nombre fini. */
    public static function nombre(mixed $brut): ?float
    {
        if (!is_scalar($brut)) {
            return null;
        }
        $t = str_replace(',', '.', trim((string)$brut));
        return ($t !== '' && is_numeric($t) && is_finite((float)$t)) ? (float)$t : null;
    }

    public static function fromRequest(array $data): self
    {
        $estAbsent = !empty($data['est_absent']) && $data['est_absent'] !== '0';
        $valeur    = null;
        $invalide  = false;

        if (!$estAbsent && isset($data['valeur']) && trim((string)$data['valeur']) !== '') {
            $valeur   = self::nombre($data['valeur']);
            $invalide = $valeur === null;
        }

        $commentaire = isset($data['commentaire']) && trim($data['commentaire']) !== ''
            ? trim($data['commentaire'])
            : null;

        return new self(
            evaluationId: (int)($data['evaluation_id'] ?? 0),
            eleveId:      (int)($data['eleve_id']      ?? 0),
            valeur:       $valeur,
            estAbsent:    $estAbsent,
            commentaire:  $commentaire,
            valeurInvalide: $invalide,
        );
    }

    public function toArray(): array
    {
        return [
            'evaluation_id' => $this->evaluationId,
            'eleve_id'      => $this->eleveId,
            'valeur'        => $this->estAbsent ? null : $this->valeur,
            'est_absent'    => $this->estAbsent ? 1 : 0,
            'commentaire'   => $this->commentaire,
        ];
    }

    public function validate(float $noteMax): array
    {
        $errors = [];

        if ($this->evaluationId <= 0) {
            $errors['evaluation_id'][] = "Évaluation requise.";
        }
        if ($this->eleveId <= 0) {
            $errors['eleve_id'][] = "Élève requis.";
        }
        if (!$this->estAbsent && $this->valeur !== null) {
            if ($this->valeur < 0) {
                $errors['valeur'][] = "La note ne peut pas être négative.";
            } elseif ($this->valeur > $noteMax) {
                $errors['valeur'][] = "La note ({$this->valeur}) dépasse le barème ({$noteMax}).";
            }
        }
        if ($this->commentaire !== null && strlen($this->commentaire) > 500) {
            $errors['commentaire'][] = "Commentaire trop long (max 500 caractères).";
        }

        return $errors;
    }
}
