<?php

namespace App\Modules\Academique\ValueObjects;

final class CoefficientValue
{
    private float $value;

    public function __construct(float $value)
    {
        if ($value < 0.25 || $value > 10.0) {
            throw new \InvalidArgumentException(
                "Coefficient invalide : {$value}. Doit être compris entre 0.25 et 10."
            );
        }
        $this->value = $value;
    }

    public function getValue(): float
    {
        return $this->value;
    }

    public function apply(float $note): float
    {
        return $note * $this->value;
    }

    public function __toString(): string
    {
        return (string)$this->value;
    }
}
