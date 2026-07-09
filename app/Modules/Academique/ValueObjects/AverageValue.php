<?php

namespace App\Modules\Academique\ValueObjects;

final class AverageValue
{
    private float $value;
    private int   $precision;
    private bool  $isEmpty;

    public function __construct(float $value, int $precision = 2, bool $isEmpty = false)
    {
        $this->value     = round($value, $precision);
        $this->precision = $precision;
        $this->isEmpty   = $isEmpty;
    }

    public static function empty(): self
    {
        return new self(0.0, 2, true);
    }

    public function getValue(): float
    {
        return $this->value;
    }

    public function isEmpty(): bool
    {
        return $this->isEmpty;
    }

    public function isPassant(float $seuil = 10.0): bool
    {
        return !$this->isEmpty && $this->value >= $seuil;
    }

    public function compareTo(self $other): int
    {
        return $this->value <=> $other->value;
    }

    public function isGreaterThan(self $other): bool
    {
        return $this->value > $other->value;
    }

    public function format(int $decimals = 2): string
    {
        return number_format($this->value, $decimals, '.', '');
    }

    public function __toString(): string
    {
        return $this->format($this->precision);
    }
}
