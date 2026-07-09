<?php

namespace App\Modules\Academique\ValueObjects;

final class NoteValue
{
    private ?float $value;

    public function __construct(?float $value, BaremeValue $bareme)
    {
        if ($value !== null) {
            if ($value < 0.0) {
                throw new \InvalidArgumentException("Une note ne peut pas être négative.");
            }
            if ($value > $bareme->getValue()) {
                throw new \InvalidArgumentException(
                    "La note {$value} dépasse le barème ({$bareme->getValue()})."
                );
            }
        }
        $this->value = $value;
    }

    public static function absent(BaremeValue $bareme): self
    {
        return new self(null, $bareme);
    }

    public function getValue(): ?float
    {
        return $this->value;
    }

    public function isAbsent(): bool
    {
        return $this->value === null;
    }

    public function toDb(): mixed
    {
        return $this->value;
    }

    public function rapporteSur(float $cible = 20.0): ?float
    {
        if ($this->value === null) return null;
        // safe: bareme already validated > 0 in BaremeValue
        return null; // computed by AcademicCalculationService in Phase 2.5
    }

    public function equals(self $other): bool
    {
        if ($this->value === null && $other->value === null) return true;
        if ($this->value === null || $other->value === null) return false;
        return abs($this->value - $other->value) < 0.001;
    }
}
