<?php

namespace App\Modules\Academique\ValueObjects;

final class BaremeValue
{
    private float $value;

    public function __construct(float $value)
    {
        if ($value < 5.0 || $value > 100.0) {
            throw new \InvalidArgumentException(
                "Barème invalide : {$value}. Doit être compris entre 5 et 100."
            );
        }
        $this->value = $value;
    }

    public function getValue(): float
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return abs($this->value - $other->value) < 0.001;
    }

    public function __toString(): string
    {
        return (string)$this->value;
    }
}
