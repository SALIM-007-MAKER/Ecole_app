<?php

namespace App\Modules\Finance\Models;

class CompteModel
{
    public const TYPES = [
        'actif'   => 'Actif',
        'passif'  => 'Passif',
        'charge'  => 'Charge',
        'produit' => 'Produit',
    ];

    public const TYPE_COLORS = [
        'actif'   => 'blue',
        'passif'  => 'violet',
        'charge'  => 'rose',
        'produit' => 'emerald',
    ];

    public int     $id;
    public string  $code;
    public string  $libelle;
    public string  $type;
    public int     $classe;
    public ?int    $parent_id;
    public int     $niveau;
    public bool    $actif;
    public bool    $systeme;
    public ?string $note;

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function typeColor(): string
    {
        return self::TYPE_COLORS[$this->type] ?? 'slate';
    }

    public function isCharge(): bool
    {
        return $this->type === 'charge';
    }

    public function isProduit(): bool
    {
        return $this->type === 'produit';
    }

    public function isActif(): bool
    {
        return $this->type === 'actif';
    }

    public function isPassif(): bool
    {
        return $this->type === 'passif';
    }

    public function estSupprimable(): bool
    {
        return !$this->systeme && $this->actif;
    }
}
