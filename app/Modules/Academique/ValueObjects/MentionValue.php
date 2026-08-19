<?php

namespace App\Modules\Academique\ValueObjects;

final class MentionValue
{
    const TB          = 'Très Bien';
    const BIEN        = 'Bien';
    const ASSEZ_BIEN  = 'Assez Bien';
    const PASSABLE    = 'Passable';
    const INSUFFISANT = 'Insuffisant';

    private string $label;
    private string $code;
    private string $cssColor;

    private static array $THRESHOLDS = [
        16 => ['label' => self::TB,          'code' => 'TB',  'css' => 'emerald'],
        14 => ['label' => self::BIEN,         'code' => 'B',   'css' => 'blue'],
        12 => ['label' => self::ASSEZ_BIEN,   'code' => 'AB',  'css' => 'cyan'],
        10 => ['label' => self::PASSABLE,     'code' => 'P',   'css' => 'amber'],
         0 => ['label' => self::INSUFFISANT,  'code' => 'INS', 'css' => 'red'],
    ];

    private function __construct(string $label, string $code, string $cssColor)
    {
        $this->label    = $label;
        $this->code     = $code;
        $this->cssColor = $cssColor;
    }

    /**
     * Table des seuils, du plus haut au plus bas — pour les écrans qui
     * affichent la légende des mentions (ex: bulletins/index.php) sans
     * dupliquer les seuils codés en dur ailleurs.
     *
     * @return array<int, array{label: string, code: string, css: string}>
     */
    public static function thresholds(): array
    {
        return self::$THRESHOLDS;
    }

    public static function fromAverage(float $average): self
    {
        foreach (self::$THRESHOLDS as $seuil => $data) {
            if ($average >= $seuil) {
                return new self($data['label'], $data['code'], $data['css']);
            }
        }
        $ins = self::$THRESHOLDS[0];
        return new self($ins['label'], $ins['code'], $ins['css']);
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getCssColor(): string
    {
        return $this->cssColor;
    }

    public function isAdmis(): bool
    {
        return $this->code !== 'INS';
    }

    public function getBadgeClasses(): string
    {
        return "bg-{$this->cssColor}-100 text-{$this->cssColor}-700";
    }

    public function __toString(): string
    {
        return $this->label;
    }
}
