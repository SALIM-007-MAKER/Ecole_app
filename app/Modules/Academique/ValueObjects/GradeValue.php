<?php

namespace App\Modules\Academique\ValueObjects;

/**
 * Représente une note dans le contexte d'un calcul.
 * Distinct de NoteValue (validation d'entrée) — GradeValue sert aux calculs.
 */
final class GradeValue
{
    private float $rawValue;
    private float $noteMax;
    private float $coefficient;

    public function __construct(float $rawValue, float $noteMax, float $coefficient = 1.0)
    {
        if ($noteMax <= 0) {
            throw new \InvalidArgumentException("note_max doit être > 0 (reçu : {$noteMax}).");
        }
        if ($rawValue < 0 || $rawValue > $noteMax) {
            throw new \InvalidArgumentException(
                "Valeur {$rawValue} hors barème [0, {$noteMax}]."
            );
        }
        if ($coefficient <= 0) {
            throw new \InvalidArgumentException("Coefficient doit être > 0.");
        }

        $this->rawValue    = $rawValue;
        $this->noteMax     = $noteMax;
        $this->coefficient = $coefficient;
    }

    public function getRaw(): float
    {
        return $this->rawValue;
    }

    public function getNoteMax(): float
    {
        return $this->noteMax;
    }

    public function getCoefficient(): float
    {
        return $this->coefficient;
    }

    /** Ramène la note sur /20. */
    public function getValueSur20(): float
    {
        return ($this->rawValue / $this->noteMax) * 20.0;
    }

    /** Note pondérée = (valeur/noteMax × 20) × coefficient. */
    public function getWeighted(): float
    {
        return $this->getValueSur20() * $this->coefficient;
    }

    public function getPercentage(): float
    {
        return ($this->rawValue / $this->noteMax) * 100.0;
    }
}
